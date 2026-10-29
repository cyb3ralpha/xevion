/**
 * Server storage for matches when there is no database (Vercel Blob, no MySQL).
 *
 *   POST /api/share  {"payload": "<packed match>"}         -> {"id": "aB3dE5fG7h", "key": "<delete key>"}
 *   POST /api/share  {"action": "delete", "id", "key"}       -> {"ok": true}
 *   GET  /api/share?id=aB3dE5fG7h                           -> the packed match (text)
 *   GET  /api/share?list=1[&category=smile][&page=2]        -> {"items": [...], "more": bool}
 *
 * Blob layout:
 *   s/{id}.txt                      the packed match (public: deflated JSON; private: AES-GCM ciphertext)
 *   k/{id}.json                     {"h": sha256(delete key), "i": index pathname or ""}
 *   i/{newest-first}-{id}-{cat}.json  public matches only: title, category, count and two cover photos
 *
 * Public matches are listed on the site. Private ones are encrypted in the browser with the
 * access code, are never listed, and can only be opened through their link plus the code.
 * The Blob token (BLOB_READ_WRITE_TOKEN) stays on the server.
 */
'use strict';

const crypto = require('crypto');
const zlib = require('zlib');
const { put, get, list, del } = require('@vercel/blob');

const MAX_PAYLOAD = 3 * 1024 * 1024;          // characters; Vercel caps request bodies at 4.5 MB
const ID_RE = /^[A-Za-z0-9]{10}$/;
const KEY_RE = /^[A-Za-z0-9_-]{32}$/;
const CAT_RE = /^[a-z]{2,20}$/;
const PAYLOAD_RE = /^[zue][A-Za-z0-9_-]+$/;
const IMG_RE = /^data:image\/(jpeg|png|webp);base64,[A-Za-z0-9+/=]+$/;
const PAGE_SIZE = 24;

// Best-effort per-instance limit; use Vercel Firewall rate limiting for a hard limit.
const recent = new Map();
function tooMany(ip, max) {
  const now = Date.now(), times = (recent.get(ip) || []).filter((t) => now - t < 60000);
  times.push(now);
  recent.set(ip, times);
  if (recent.size > 5000) recent.clear();
  return times.length > max;
}

function newId() {
  const abc = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
  let id = '';
  for (const b of crypto.randomBytes(10)) id += abc[b % abc.length];
  return id;
}

const sha256 = (s) => crypto.createHash('sha256').update(s).digest('hex');

function blocked() {
  return (process.env.BLOCKED_MATCHES || '').toLowerCase().split(',').map((s) => s.trim()).filter(Boolean);
}

/** Public matches must decode to the structure the site itself creates. Returns the data or null. */
function decodePublic(payload) {
  try {
    const raw = Buffer.from(payload.slice(1).replace(/-/g, '+').replace(/_/g, '/'), 'base64');
    const text = payload[0] === 'z' ? zlib.inflateRawSync(raw, { maxOutputLength: 8 * 1024 * 1024 }).toString('utf8') : raw.toString('utf8');
    const d = JSON.parse(text);
    const ok = d && d.v === 1 && typeof d.t === 'string' && d.t.length <= 200 && Array.isArray(d.p) && d.p.length >= 2 && d.p.length <= 20
      && d.p.every((p) => p && typeof p.n === 'string' && p.n.length <= 80 && typeof p.g === 'string' && IMG_RE.test(p.g));
    return ok ? d : null;
  } catch (e) {
    return null;
  }
}

function sameOrigin(req) {
  const origin = req.headers.origin;
  if (!origin) return false;
  try { return new URL(origin).host.toLowerCase() === String(req.headers.host || '').toLowerCase(); } catch (e) { return false; }
}

async function readBlob(pathname) {
  for (const access of ['private', 'public']) {
    try {
      const r = await get(pathname, { access });
      if (r && r.statusCode === 200 && r.stream) return await new Response(r.stream).text();
    } catch (e) { /* try the other access type */ }
  }
  return null;
}

async function writeBlob(pathname, body, contentType) {
  let lastError = null;
  for (const access of ['private', 'public']) {            // private stores first; older public stores still work
    try {
      return await put(pathname, body, { access, addRandomSuffix: false, allowOverwrite: true, contentType });
    } catch (e) { lastError = e; }
  }
  throw lastError || new Error('storage_failed');
}

/** Pathnames of every public index entry, newest first (Blob lists in name order). */
async function indexPaths() {
  const out = [];
  let cursor;
  do {
    const r = await list({ prefix: 'i/', limit: 1000, cursor });
    for (const b of r.blobs) out.push(b.pathname);
    cursor = r.hasMore ? r.cursor : undefined;
  } while (cursor && out.length < 5000);
  return out.sort();
}

async function handleList(req, res) {
  const cat = String((req.query && req.query.category) || '');
  const page = Math.max(1, Math.min(200, parseInt((req.query && req.query.page) || '1', 10) || 1));
  const block = blocked();
  const paths = (await indexPaths()).filter((p) => {
    const m = /^i\/\d{13}-([A-Za-z0-9]{10})-([a-z]{2,20})\.json$/.exec(p);
    return m && !block.includes(m[1].toLowerCase()) && (!CAT_RE.test(cat) || m[2] === cat);
  });
  const slice = paths.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);
  const items = (await Promise.all(slice.map(async (p) => {
    try { return JSON.parse(await readBlob(p)); } catch (e) { return null; }
  }))).filter(Boolean);
  res.setHeader('Cache-Control', 'public, max-age=0, s-maxage=20, stale-while-revalidate=60');
  return res.status(200).json({ ok: true, items, more: paths.length > page * PAGE_SIZE, total: paths.length });
}

async function handleCreate(req, res, payload) {
  const d = payload[0] === 'e' ? null : decodePublic(payload);
  if (payload[0] !== 'e' && !d) return res.status(400).json({ ok: false, error: 'bad_payload' });

  const id = newId(), key = crypto.randomBytes(24).toString('base64url');
  let indexPath = '';
  try {
    await writeBlob(`s/${id}.txt`, payload, 'text/plain; charset=utf-8');
    if (d) {
      const cat = CAT_RE.test(String(d.c || '')) ? d.c : 'custom', now = Date.now();
      indexPath = `i/${String(9999999999999 - now).padStart(13, '0')}-${id}-${cat}.json`;
      const entry = {
        id, t: d.t.slice(0, 120), c: cat, cc: typeof d.cc === 'string' ? d.cc.slice(0, 40) : '',
        n: d.p.length, at: now, g: d.p.slice(0, 2).map((p) => p.g),
      };
      await writeBlob(indexPath, JSON.stringify(entry), 'application/json');
    }
    await writeBlob(`k/${id}.json`, JSON.stringify({ h: sha256(key), i: indexPath }), 'application/json');
  } catch (e) {
    console.error('share upload failed:', e && e.message);
    return res.status(502).json({ ok: false, error: 'storage_failed' });
  }
  return res.status(201).json({ ok: true, id, key, listed: !!d });
}

async function handleDelete(req, res) {
  const id = String(req.body.id || ''), key = String(req.body.key || '');
  if (!ID_RE.test(id) || !KEY_RE.test(key)) return res.status(400).json({ ok: false, error: 'bad_request' });
  let meta = null;
  try { meta = JSON.parse(await readBlob(`k/${id}.json`)); } catch (e) { meta = null; }
  if (!meta || typeof meta.h !== 'string') return res.status(404).json({ ok: false, error: 'not_found' });
  const a = Buffer.from(meta.h), b = Buffer.from(sha256(key));
  if (a.length !== b.length || !crypto.timingSafeEqual(a, b)) return res.status(403).json({ ok: false, error: 'wrong_key' });
  const paths = [`s/${id}.txt`, `k/${id}.json`];
  if (typeof meta.i === 'string' && /^i\/[\w-]+\.json$/.test(meta.i)) paths.push(meta.i);
  try {
    const found = [];
    for (const p of paths) {
      const r = await list({ prefix: p, limit: 1 });
      for (const blob of r.blobs) if (blob.pathname === p) found.push(blob.url);
    }
    if (found.length) await del(found);
  } catch (e) {
    console.error('share delete failed:', e && e.message);
    return res.status(502).json({ ok: false, error: 'storage_failed' });
  }
  return res.status(200).json({ ok: true });
}

module.exports = async (req, res) => {
  res.setHeader('X-Content-Type-Options', 'nosniff');
  res.setHeader('Referrer-Policy', 'no-referrer');
  if (!process.env.BLOB_READ_WRITE_TOKEN) {
    return res.status(503).json({ ok: false, error: 'storage_not_configured' });
  }

  try {
    if (req.method === 'GET') {
      if (req.query && req.query.list) return await handleList(req, res);
      const id = String((req.query && req.query.id) || '');
      if (!ID_RE.test(id)) return res.status(400).json({ ok: false, error: 'bad_id' });
      if (blocked().includes(id.toLowerCase())) return res.status(410).json({ ok: false, error: 'blocked' });
      const text = await readBlob(`s/${id}.txt`);
      if (text === null) return res.status(404).json({ ok: false, error: 'not_found' });
      res.setHeader('Content-Type', 'text/plain; charset=utf-8');
      res.setHeader('Cache-Control', 'public, max-age=60, s-maxage=300');   // short cache so deleting and blocking take effect
      res.setHeader('X-Robots-Tag', 'noindex');
      return res.status(200).send(text);
    }

    if (req.method === 'POST') {
      if (!sameOrigin(req)) return res.status(403).json({ ok: false, error: 'origin' });
      const ip = String(req.headers['x-forwarded-for'] || '').split(',')[0].trim() || 'unknown';
      if (tooMany(ip, 20)) return res.status(429).json({ ok: false, error: 'rate_limited' });
      if (!req.body || typeof req.body !== 'object') return res.status(400).json({ ok: false, error: 'bad_request' });
      if (req.body.action === 'delete') return await handleDelete(req, res);
      const payload = typeof req.body.payload === 'string' ? req.body.payload : '';
      if (!payload || payload.length > MAX_PAYLOAD || !PAYLOAD_RE.test(payload)) {
        return res.status(400).json({ ok: false, error: 'bad_payload' });
      }
      return await handleCreate(req, res, payload);
    }
  } catch (e) {
    console.error('share error:', e && e.message);
    return res.status(500).json({ ok: false, error: 'server_error' });
  }

  res.setHeader('Allow', 'GET, POST');
  return res.status(405).json({ ok: false, error: 'method' });
};
