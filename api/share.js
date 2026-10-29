/**
 * Server storage for matches when there is no database (Vercel Blob, no MySQL).
 *
 *   POST /api/share  {"payload": "<packed match>"}         -> {"id": "aB3dE5fG7h", "key": "<delete key>"}
 *   POST /api/share  {"action": "delete", "id", "key"}       -> {"ok": true}
 *   GET  /api/share?id=aB3dE5fG7h                           -> the packed match (text)
 *   GET  /api/share?list=1[&category=smile][&page=2]        -> {"items": [...], "more": bool}
 *   POST /api/share  {"action": "vote", "id", "voter", "votes": [[winner, loser], ...], "vt"?}
 *   GET  /api/share?tally=aB3dE5fG7h                        -> {"n", "w": [...], "g": [...], "v", "voters"}
 *   POST /api/share  {"action": "admin", "op": "list"|"delete", ...}  signed by the PHP admin (X-Admin-Sig)
 *
 * Blob layout:
 *   s/{id}.txt                      the packed match (public: deflated JSON; private: AES-GCM ciphertext)
 *   k/{id}.json                     {"h": sha256(delete key), "i": index pathname or ""}
 *   i/{newest-first}-{id}-{cat}.json  public matches only: title, category, count and two cover photos
 *   t/{id}.json                     shared vote tally: wins and matchups per contender (by position),
 *                                   hashed voter/pair keys so one browser counts once per pair, salted
 *                                   network hashes (at most 3 voting browsers per connection), and for
 *                                   private matches the hash of the vote token derived from the access code
 *
 * Public matches are listed on the site. Private ones are encrypted in the browser with the
 * access code, are never listed, and can only be opened through their link plus the code.
 * Votes are counted for everyone: anyone with a public link can vote; on a private match a vote is
 * accepted only with a token that only holders of the access code can compute. Tallies are updated
 * with conditional writes (ETag), so simultaneous votes are not lost.
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
const VOTER_RE = /^[A-Za-z0-9_-]{16,64}$/;
const VT_RE = /^[a-f0-9]{64}$/;
const MAX_SEEN = 100000;                        // voter/pair keys kept per match (about 1.1 MB)
const NET_LIMIT = 3;                            // browsers per internet connection that may vote on one match

function clientIp(req) {
  return String(req.headers['x-real-ip'] || String(req.headers['x-forwarded-for'] || '').split(',')[0]).trim() || 'unknown';
}
/** The voter's network: the IPv4 address, or the /64 prefix for IPv6 (one household usually shares it). */
function network(req) {
  const ip = clientIp(req);
  return ip.includes(':') ? ip.split(':').slice(0, 4).join(':') : ip;
}
function netSalt() { return envValue('APP_SECRET') || envValue('BLOB_READ_WRITE_TOKEN'); }
/** Environment value with surrounding quotes removed, like env_value() in app/helpers.php. */
function envValue(k) {
  let v = String(process.env[k] || '').trim();
  if (v.length >= 2 && (v[0] === '"' || v[0] === "'") && v[v.length - 1] === v[0]) v = v.slice(1, -1).trim();
  return v;
}

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

/** Read a blob uncached, with its ETag, for read-modify-write. Returns {text, etag, access} or null. */
async function readFresh(pathname) {
  for (const access of ['private', 'public']) {
    try {
      const r = await get(pathname, { access, useCache: false });
      if (r && r.statusCode === 200 && r.stream) return { text: await new Response(r.stream).text(), etag: r.blob && r.blob.etag, access };
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

const newTally = (n, vh) => ({ n, w: new Array(n).fill(0), g: new Array(n).fill(0), v: 0, vs: '', s: '', vh: vh || '' });
const publicTally = (t) => ({ ok: true, n: t.n, w: t.w, g: t.g, v: t.v, voters: t.vs.length / 8 });

/** The current tally, or a fresh one for a public match stored before voting existed. */
async function loadTally(id) {
  const cur = await readFresh(`t/${id}.json`);
  if (cur) {
    try { return { t: JSON.parse(cur.text), etag: cur.etag, access: cur.access }; } catch (e) { return null; }
  }
  const text = await readBlob(`s/${id}.txt`);
  if (text === null || text[0] === 'e') return null;          // private matches get their tally at creation
  const d = decodePublic(text.trim());
  return d ? { t: newTally(d.p.length), etag: null } : null;
}

async function handleTally(req, res, id) {
  const cur = await loadTally(id);
  if (!cur) return res.status(404).json({ ok: false, error: 'not_found' });
  res.setHeader('Cache-Control', 'public, max-age=0, s-maxage=3, stale-while-revalidate=10');
  return res.status(200).json(publicTally(cur.t));
}

function hasVoter(vs, h) {
  for (let i = 0; i < vs.length; i += 8) if (vs.substr(i, 8) === h) return true;
  return false;
}

/** Create a blob only if it doesn't exist yet; throws if it does. */
async function writeNew(pathname, body, contentType) {
  let lastError = null;
  for (const access of ['private', 'public']) {
    try { return await put(pathname, body, { access, addRandomSuffix: false, allowOverwrite: false, contentType }); } catch (e) {
      lastError = e;
      if (/exist/i.test(String(e && e.message))) throw e;      // created meanwhile: the caller re-reads
    }
  }
  throw lastError || new Error('storage_failed');
}

async function handleVote(req, res) {
  const b = req.body, id = String(b.id || ''), voter = String(b.voter || '');
  if (!ID_RE.test(id) || !VOTER_RE.test(voter) || !Array.isArray(b.votes) || !b.votes.length || b.votes.length > 190) {
    return res.status(400).json({ ok: false, error: 'bad_request' });
  }
  if (blocked().includes(id.toLowerCase())) return res.status(410).json({ ok: false, error: 'blocked' });
  const voterHash = sha256(`${id}|${voter}`).slice(0, 8);
  const netHash = sha256(`${netSalt()}|${id}|${network(req)}`).slice(0, 8);   // the IP itself is never stored
  for (let attempt = 0; attempt < 8; attempt++) {
    const cur = await loadTally(id);
    if (!cur) return res.status(404).json({ ok: false, error: 'not_found' });
    const t = cur.t, n = t.n;
    if (t.vh) {
      const vt = String(b.vt || ''), a = Buffer.from(t.vh), c = Buffer.from(VT_RE.test(vt) ? sha256(vt) : '');
      if (a.length !== c.length || !crypto.timingSafeEqual(a, c)) return res.status(403).json({ ok: false, error: 'code_required' });
    }
    // A new browser may join only while its internet connection has fewer than NET_LIMIT voters on this match.
    t.ip = t.ip && typeof t.ip === 'object' ? t.ip : {};
    const isNew = !hasVoter(t.vs, voterHash);
    if (isNew && (t.ip[netHash] || 0) >= NET_LIMIT) return res.status(429).json({ ok: false, error: 'network_limit' });
    let added = 0;
    for (const pair of b.votes) {
      if (!Array.isArray(pair) || pair.length !== 2) continue;
      const w = pair[0], l = pair[1];
      if (!Number.isInteger(w) || !Number.isInteger(l) || w === l || w < 0 || l < 0 || w >= n || l >= n) continue;
      const key = sha256(`${voterHash}|${Math.min(w, l)}|${Math.max(w, l)}`).slice(0, 10) + ',';
      if (t.s.indexOf(key) >= 0 || t.s.length / 11 >= MAX_SEEN) continue;   // this browser already voted on this pair
      t.s += key; t.w[w]++; t.g[w]++; t.g[l]++; t.v++; added++;
    }
    if (!added) return res.status(200).json(Object.assign(publicTally(t), { added: 0 }));
    if (isNew) { t.vs += voterHash; t.ip[netHash] = (t.ip[netHash] || 0) + 1; }
    try {
      const body = JSON.stringify(t);
      if (cur.etag) {
        await put(`t/${id}.json`, body, { access: cur.access, addRandomSuffix: false, contentType: 'application/json', ifMatch: cur.etag });
      } else {
        await writeNew(`t/${id}.json`, body, 'application/json');
      }
      return res.status(200).json(Object.assign(publicTally(t), { added }));
    } catch (e) {
      // Another vote landed first (the ETag changed, or the tally was just created): re-read and retry.
      await new Promise((r) => setTimeout(r, 40 + Math.random() * 120 * (attempt + 1)));
    }
  }
  return res.status(503).json({ ok: false, error: 'busy' });
}

/* ---------- admin (called server-to-server by the PHP admin dashboard) ---------- */

/** Same key as lite_key() in app/admin_lite.php; null when no admin sign-in is configured. */
function adminKey() {
  const email = envValue('ADMIN_EMAIL'), hash = envValue('ADMIN_PASSWORD_HASH'), pw = envValue('ADMIN_PASSWORD'), secret = envValue('APP_SECRET');
  if (!/^[^@\s]+@[^@\s]+$/.test(email) || (!hash && pw.length < 12)) return null;
  return crypto.createHash('sha256').update(secret.length >= 32 ? `lite-admin|${secret}` : `lite-admin|${email}|${hash}|${pw}`).digest();
}

/** Requests carry X-Admin-Ts and X-Admin-Sig = HMAC-SHA256(key, "share-admin|" + ts), valid for 5 minutes. */
function adminOk(req) {
  const key = adminKey(), ts = String(req.headers['x-admin-ts'] || ''), sig = String(req.headers['x-admin-sig'] || '');
  if (!key || !/^\d{10}$/.test(ts) || Math.abs(Date.now() / 1000 - Number(ts)) > 300 || !/^[a-f0-9]{64}$/.test(sig)) return false;
  const want = Buffer.from(crypto.createHmac('sha256', key).update(`share-admin|${ts}`).digest('hex'));
  return crypto.timingSafeEqual(want, Buffer.from(sig));
}

async function listAll(prefix) {
  const out = [];
  let cursor;
  do {
    const r = await list({ prefix, limit: 1000, cursor });
    out.push(...r.blobs);
    cursor = r.hasMore ? r.cursor : undefined;
  } while (cursor && out.length < 20000);
  return out;
}

/** Every file that belongs to a match, as blob URLs. */
async function matchFiles(id) {
  const urls = [];
  for (const p of [`s/${id}.txt`, `k/${id}.json`, `t/${id}.json`]) {
    const r = await list({ prefix: p, limit: 1 });
    for (const blob of r.blobs) if (blob.pathname === p) urls.push(blob.url);
  }
  for (const blob of await listAll('i/')) if (blob.pathname.includes(`-${id}-`)) urls.push(blob.url);
  return urls;
}

async function handleAdmin(req, res) {
  if (!adminOk(req)) return res.status(403).json({ ok: false, error: 'admin_only' });
  res.setHeader('Cache-Control', 'no-store');
  const op = String(req.body.op || '');
  if (op === 'list') {
    const page = Math.max(1, Math.min(500, parseInt(req.body.page, 10) || 1)), per = 30;
    const [stored, index] = await Promise.all([listAll('s/'), listAll('i/')]);
    const listed = {};
    for (const b of index) { const m = /^i\/\d{13}-([A-Za-z0-9]{10})-/.exec(b.pathname); if (m) listed[m[1]] = b.pathname; }
    const all = stored.map((b) => ({ id: b.pathname.slice(2, -4), at: new Date(b.uploadedAt).getTime(), size: b.size }))
      .filter((x) => ID_RE.test(x.id)).sort((a, b) => b.at - a.at);
    const block = blocked();
    const items = await Promise.all(all.slice((page - 1) * per, page * per).map(async (x) => {
      let entry = null, tally = null;
      if (listed[x.id]) { try { entry = JSON.parse(await readBlob(listed[x.id])); } catch (e) { entry = null; } }
      try { const t = JSON.parse(await readBlob(`t/${x.id}.json`)); tally = { v: t.v, voters: t.vs.length / 8 }; } catch (e) { tally = null; }
      return { id: x.id, at: x.at, size: x.size, public: !!listed[x.id], blocked: block.includes(x.id.toLowerCase()),
        t: entry ? entry.t : '', n: entry ? entry.n : null, g: entry ? (entry.g || []).slice(0, 2) : [],
        v: tally ? tally.v : 0, voters: tally ? tally.voters : 0 };
    }));
    return res.status(200).json({ ok: true, total: all.length, publicCount: Object.keys(listed).length, page, pages: Math.max(1, Math.ceil(all.length / per)), items });
  }
  if (op === 'delete') {
    const id = String(req.body.id || '');
    if (!ID_RE.test(id)) return res.status(400).json({ ok: false, error: 'bad_id' });
    const urls = await matchFiles(id);
    if (!urls.length) return res.status(404).json({ ok: false, error: 'not_found' });
    await del(urls);
    return res.status(200).json({ ok: true, removed: urls.length });
  }
  return res.status(400).json({ ok: false, error: 'bad_op' });
}

async function handleCreate(req, res, payload) {
  const d = payload[0] === 'e' ? null : decodePublic(payload);
  if (payload[0] !== 'e' && !d) return res.status(400).json({ ok: false, error: 'bad_payload' });
  // Private matches: the browser sends the contender count and a token derived from the access code.
  // Without them (a page from before shared voting) the match is stored but votes stay on each device.
  const n = d ? d.p.length : Number(req.body.n), vt = String(req.body.vt || '');
  const countable = !!d || (Number.isInteger(n) && n >= 2 && n <= 20 && VT_RE.test(vt));

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
    if (countable) await writeBlob(`t/${id}.json`, JSON.stringify(newTally(n, d ? '' : sha256(vt))), 'application/json');
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
  const paths = [`s/${id}.txt`, `k/${id}.json`, `t/${id}.json`];
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
      if (req.query && req.query.tally) {
        const tid = String(req.query.tally);
        if (!ID_RE.test(tid)) return res.status(400).json({ ok: false, error: 'bad_id' });
        if (blocked().includes(tid.toLowerCase())) return res.status(410).json({ ok: false, error: 'blocked' });
        return await handleTally(req, res, tid);
      }
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
      if (req.body && typeof req.body === 'object' && req.body.action === 'admin') return await handleAdmin(req, res);
      if (!sameOrigin(req)) return res.status(403).json({ ok: false, error: 'origin' });
      const ip = String(req.headers['x-forwarded-for'] || '').split(',')[0].trim() || 'unknown';
      if (!req.body || typeof req.body !== 'object') return res.status(400).json({ ok: false, error: 'bad_request' });
      if (req.body.action === 'vote') {
        if (tooMany('v:' + ip, 60)) return res.status(429).json({ ok: false, error: 'rate_limited' });
        return await handleVote(req, res);
      }
      if (tooMany(ip, 20)) return res.status(429).json({ ok: false, error: 'rate_limited' });
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
