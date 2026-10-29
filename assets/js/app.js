/* XEVION FaceMatch — progressive enhancement. Every feature here has a no-JS fallback. */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function uuid() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    var b = new Uint8Array(16); crypto.getRandomValues(b);
    b[6] = (b[6] & 0x0f) | 0x40; b[8] = (b[8] & 0x3f) | 0x80;
    var h = Array.prototype.map.call(b, function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
    return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' + h.slice(16, 20) + '-' + h.slice(20);
  }

  /* ---------- scroll reveal ---------- */
  var revealEls = $$('[data-reveal]');
  if (revealEls.length) {
    document.documentElement.classList.add('js');
    if (reduceMotion || !('IntersectionObserver' in window)) {
      revealEls.forEach(function (el) { el.classList.add('in'); });
    } else {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
        });
      }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
      revealEls.forEach(function (el) { io.observe(el); });
    }
  }

  /* ---------- header shadow once the page scrolls ---------- */
  var header = $('.site-header');
  if (header) {
    var onScroll = function () { header.classList.toggle('scrolled', window.scrollY > 8); };
    window.addEventListener('scroll', onScroll, { passive: true }); onScroll();
  }

  /* ---------- hero: rotating category question ---------- */
  var ticker = $('[data-ticker]');
  if (ticker && !reduceMotion) {
    var qs = ticker.dataset.ticker.split('|'), qi = 0;
    setInterval(function () {
      if (document.hidden) return;
      ticker.classList.add('out');
      setTimeout(function () {
        qi = (qi + 1) % qs.length;
        ticker.textContent = qs[qi];
        ticker.classList.remove('out'); ticker.classList.add('pre');
        void ticker.offsetWidth; // restart the transition from below
        ticker.classList.remove('pre');
      }, 350);
    }, 2600);
  }

  /* ---------- nav ---------- */
  var toggle = $('[data-nav-toggle]');
  if (toggle) {
    toggle.addEventListener('click', function () {
      var links = $('#nav-links');
      var open = links.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  /* ---------- local times ---------- */
  $$('time[data-local]').forEach(function (t) {
    var d = new Date(t.getAttribute('datetime'));
    if (isNaN(d)) return;
    var opts = t.dataset.local === 'date'
      ? { year: 'numeric', month: 'short', day: 'numeric' }
      : { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' };
    t.textContent = d.toLocaleString(undefined, opts);
    t.title = d.toString();
  });

  /* ---------- copy / share ---------- */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-copy]');
    if (!btn) return;
    var box = btn.parentElement;
    var src = box && box.querySelector('[data-copy-source]');
    if (!src) return;
    var text = src.value !== undefined && src.tagName === 'INPUT' ? src.value : src.textContent.trim();
    var done = function () { var old = btn.textContent; btn.textContent = 'Copied'; setTimeout(function () { btn.textContent = old; }, 1600); };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(src); done(); });
    } else { fallbackCopy(src); done(); }
  });
  function fallbackCopy(src) {
    if (src.tagName === 'INPUT') { src.select(); try { document.execCommand('copy'); } catch (x) {} }
    else { var r = document.createRange(); r.selectNodeContents(src); var s = getSelection(); s.removeAllRanges(); s.addRange(r); try { document.execCommand('copy'); } catch (x) {} }
  }
  $$('[data-share]').forEach(function (b) {
    if (!navigator.share) return;
    b.hidden = false;
    b.addEventListener('click', function () {
      navigator.share({ title: b.dataset.shareTitle, url: b.dataset.shareUrl }).catch(function () {});
    });
  });
  $$('input[readonly][data-copy-source]').forEach(function (i) { i.addEventListener('focus', function () { i.select(); }); });

  /* ---------- confirmations ---------- */
  document.addEventListener('submit', function (e) {
    var f = e.target.closest('form[data-confirm]');
    if (f && !window.confirm(f.dataset.confirm)) e.preventDefault();
  });
  var focusEl = $('[data-focus-on-load]');
  if (focusEl) { focusEl.focus(); }

  /* ---------- browser-side photo resizing ----------
     Photos are resized and re-encoded to JPEG before upload. This keeps requests under
     serverless body limits (Vercel: 4.5 MB), speeds up uploads, applies the camera's
     orientation and drops EXIF/GPS metadata. The server still validates every file. */
  var canShrink = !!(window.DataTransfer && window.HTMLCanvasElement && HTMLCanvasElement.prototype.toBlob);

  function decodeImage(file) {
    if (window.createImageBitmap) {
      return createImageBitmap(file, { imageOrientation: 'from-image' }).catch(function () { return loadImg(file); });
    }
    return loadImg(file);
  }
  function loadImg(file) {
    return new Promise(function (resolve, reject) {
      var img = new Image(), u = URL.createObjectURL(file);
      img.onload = function () { URL.revokeObjectURL(u); resolve(img); };
      img.onerror = function () { URL.revokeObjectURL(u); reject(new Error('decode')); };
      img.src = u;
    });
  }
  /** Resolves to a JPEG File no larger than maxW × maxH, or the original file if anything fails. */
  function shrinkFile(file, maxW, maxH, quality) {
    if (!canShrink || !/^image\/(jpeg|png|webp)$/.test(file.type)) return Promise.resolve(file);
    return decodeImage(file).then(function (src) {
      var w = src.width, h = src.height, s = Math.min(1, maxW / w, maxH / h);
      var c = document.createElement('canvas');
      c.width = Math.max(1, Math.round(w * s)); c.height = Math.max(1, Math.round(h * s));
      var ctx = c.getContext('2d');
      ctx.fillStyle = '#0C0727'; ctx.fillRect(0, 0, c.width, c.height); // brand navy behind transparency
      ctx.drawImage(src, 0, 0, c.width, c.height);
      if (src.close) src.close();
      return new Promise(function (resolve) {
        c.toBlob(function (b) {
          resolve(b ? new File([b], file.name.replace(/\.[^.]*$/, '') + '.jpg', { type: 'image/jpeg' }) : file);
        }, 'image/jpeg', quality);
      });
    }).catch(function () { return file; });
  }
  /** Replace each input's file with a resized copy; retries smaller until all fit in budgetBytes. */
  function shrinkInputs(inputs, budgetBytes) {
    var originals = inputs.map(function (i) { return i.files[0]; });
    var passes = [[1, 0.85], [0.84, 0.75], [0.67, 0.65], [0.5, 0.6]];
    function run(p) {
      return Promise.all(inputs.map(function (inp, k) {
        var dims = (inp.dataset.shrink || '1200x1500').split('x');
        return shrinkFile(originals[k], +dims[0] * passes[p][0], +dims[1] * passes[p][0], passes[p][1]);
      })).then(function (files) {
        var total = files.reduce(function (n, f) { return n + f.size; }, 0);
        if (total > budgetBytes && p + 1 < passes.length) return run(p + 1);
        files.forEach(function (f, k) {
          var dt = new DataTransfer(); dt.items.add(f); inputs[k].files = dt.files;
        });
      });
    }
    return run(0);
  }
  function fileInputsWithFiles(form) {
    return $$('input[type=file]', form).filter(function (i) { return i.files && i.files.length; });
  }
  /* Generic forms (admin ad banners): shrink once, then resubmit with the same submit button. */
  document.addEventListener('submit', function (e) {
    var f = e.target.closest('form[data-shrink-images]');
    if (!f || f._shrunk || e.defaultPrevented || !canShrink) return;
    var inputs = fileInputsWithFiles(f);
    if (!inputs.length) return;
    e.preventDefault();
    var submitter = e.submitter;
    shrinkInputs(inputs, 4 * 1048576).then(function () {
      f._shrunk = true;
      if (f.requestSubmit) f.requestSubmit(submitter || undefined); else f.submit();
    });
  });

  /* ---------- create form ---------- */
  var form = $('[data-create-form]');
  if (form) initCreate(form);

  function initCreate(form) {
    var min = +form.dataset.min, max = +form.dataset.max, maxMb = +form.dataset.maxMb;
    var people = $('[data-people]', form), addBtn = $('[data-add]', form), tpl = $('[data-row-template]', form);
    var tz = $('[data-tz]', form); if (tz) tz.value = String(new Date().getTimezoneOffset());
    var cat = $('[data-category]', form);
    var sync = function () {
      form.classList.toggle('show-custom', cat.value === 'custom');
      var priv = $('[data-visibility]:checked', form);
      form.classList.toggle('show-private', priv && priv.value === 'private');
      var dur = $('[data-duration]', form), cc = $('[data-custom-close]', form);
      if (dur && cc) cc.hidden = dur.value !== 'custom';
    };
    form.addEventListener('change', sync); sync();

    var rows = function () { return $$('[data-row]', people); };
    var refresh = function () {
      var n = rows().length;
      rows().forEach(function (r, i) {
        var rm = $('[data-remove]', r); rm.hidden = n <= min;
        $('input[type=file]', r).setAttribute('aria-label', 'Photo for contender ' + (i + 1));
        $('input[name="names[]"]', r).setAttribute('aria-label', 'Name of contender ' + (i + 1));
      });
      addBtn.hidden = n >= max;
      addBtn.textContent = '+ Add contender (' + n + ' of ' + max + ')';
    };
    var bindRow = function (r) {
      var file = $('input[type=file]', r), label = $('[data-drop-label]', r), drop = $('.p-drop', r);
      file.addEventListener('change', function () {
        var old = $('img', drop); if (old) { URL.revokeObjectURL(old.src); old.remove(); }
        $$('.p-err', r).forEach(function (x) { x.remove(); }); r.classList.remove('has-error');
        var f = file.files && file.files[0];
        if (!f) { label.textContent = '+ Add photo'; return; }
        var problem = checkFile(f);
        if (problem) { file.value = ''; label.textContent = '+ Add photo'; rowError(r, problem); return; }
        sniffImage(f).then(function (ok) {
          if (!ok) {
            file.value = ''; label.textContent = '+ Add photo';
            isHeic(f).then(function (heic) {
              rowError(r, heic
                ? 'This is an iPhone HEIC photo, which browsers can’t read. Save it as JPG (or set Camera → Formats → Most Compatible) and try again.'
                : 'This file is not a real JPG, PNG or WebP image.');
            });
            return;
          }
          var img = document.createElement('img'); img.alt = ''; img.src = URL.createObjectURL(f);
          drop.insertBefore(img, file); label.textContent = 'Change photo';
        });
      });
      $('[data-remove]', r).addEventListener('click', function () {
        if (rows().length <= min) return;
        var img = $('.p-drop img', r); if (img) URL.revokeObjectURL(img.src);
        r.remove(); refresh();
        var first = $('[data-row] input[name="names[]"]', people); if (first) first.focus();
      });
    };
    function checkFile(f) {
      // Some phones and apps give no file type at all; the file's own bytes decide (sniffImage).
      var t = (f.type || '').toLowerCase();
      if (t && ['image/jpeg', 'image/jpg', 'image/pjpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'].indexOf(t) < 0) return 'Use a JPG, PNG or WebP image.';
      // Large originals are fine when the browser can resize them before upload.
      var limitMb = canShrink ? Math.max(maxMb, 30) : maxMb;
      if (f.size > limitMb * 1048576) return 'This photo is larger than ' + limitMb + ' MB.';
      return null;
    }
    function rowError(r, msg) {
      var s = document.createElement('span'); s.className = 'p-err'; s.textContent = msg;
      r.appendChild(s); r.classList.add('has-error');
    }
    rows().forEach(bindRow);
    addBtn.addEventListener('click', function () {
      if (rows().length >= max) return;
      var r = tpl.content.firstElementChild.cloneNode(true);
      people.insertBefore(r, addBtn); bindRow(r); refresh();
      $('input[type=file]', r).focus();
    });
    refresh();

    form.addEventListener('submit', function (e) {
      var err = $('[data-client-error]', form), problems = [];
      $$('.p-err', people).forEach(function (x) { x.remove(); });
      if (form.title.value.trim().length < 3) problems.push('Add a title (at least 3 characters).');
      if (!cat.value) problems.push('Choose a category.');
      if (cat.value === 'custom' && form.custom_category.value.trim().length < 3) problems.push('Name your custom category.');
      var names = {};
      rows().forEach(function (r, i) {
        var f = $('input[type=file]', r), n = $('input[name="names[]"]', r).value.trim();
        if (!f.files || !f.files.length) { rowError(r, 'Photo is missing.'); problems.push('Contender ' + (i + 1) + ' needs a photo.'); }
        if (!n) { rowError(r, 'Name is missing.'); problems.push('Contender ' + (i + 1) + ' needs a name.'); }
        else if (names[n.toLowerCase()]) { rowError(r, 'Each contender needs a different name.'); problems.push('Names must be different.'); }
        names[n.toLowerCase()] = true;
      });
      ['confirm_rights', 'confirm_adults', 'accept_terms'].forEach(function (k) { if (!form[k].checked) problems.push('Tick all three confirmation boxes.'); });
      if (problems.length) {
        e.preventDefault();
        err.textContent = problems.filter(function (p, i, a) { return a.indexOf(p) === i; }).join(' ');
        err.hidden = false; err.focus && err.setAttribute('tabindex', '-1'); err.focus();
        return;
      }
      err.hidden = true;
      var btn = $('[data-submit]', form); btn.classList.add('is-busy');
      if (form.hasAttribute('data-local')) {
        // No database: build the match in this browser and open it.
        e.preventDefault();
        btn.disabled = true; btn.textContent = 'Creating your match…';
        localCreateFromForm(form, rows()).then(function (id) {
          location.href = siteUrl('/play') + '#id=' + encodeURIComponent(id);
        }).catch(function (ex) {
          btn.disabled = false; btn.classList.remove('is-busy'); btn.textContent = 'Create match';
          err.textContent = 'The match could not be saved in this browser (' + (ex && ex.message ? ex.message : 'storage error') + '). Private browsing can block saving; try a normal window.';
          err.hidden = false;
        });
        return;
      }
      $('[data-submit-note]', form).textContent = 'This can take a moment on slow connections.';
      if (!canShrink || form._shrunk) { btn.textContent = 'Uploading photos…'; return; }
      // Resize every photo first; the whole request must stay under ~4 MB.
      e.preventDefault();
      btn.disabled = true; btn.textContent = 'Preparing photos…';
      shrinkInputs(fileInputsWithFiles(form), 3.8 * 1048576).then(function () {
        form._shrunk = true; btn.disabled = false; btn.textContent = 'Uploading photos…';
        form.submit();
      });
    });
  }

  /* ---------- arena ---------- */
  var arena = $('[data-arena]');
  if (arena) initArena(arena);

  function initArena(root) {
    var slug = root.dataset.slug, csrf = root.dataset.csrf, body = $('[data-arena-body]', root);
    var form = $('[data-vote-form]', root);
    if (!form) return;
    var state = { token: form.token.value, key: null, busy: false };
    var statusEl = $('[data-arena-status]', root);

    function setStatus(msg, kind) { statusEl.textContent = msg || ''; statusEl.className = 'arena-status' + (kind ? ' ' + kind : ''); }
    function grid() { return $('[data-arena-grid]', root); }

    form.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-choice]');
      if (!btn) return;
      e.preventDefault();
      vote(btn);
    });
    document.addEventListener('keydown', function (e) {
      if (e.target.matches('input, textarea, select') || e.metaKey || e.ctrlKey || e.altKey) return;
      var btns = $$('[data-choice]', root);
      if (btns.length !== 2) return;
      if (e.key === '1' || e.key === 'ArrowLeft') { e.preventDefault(); vote(btns[0]); }
      if (e.key === '2' || e.key === 'ArrowRight') { e.preventDefault(); vote(btns[1]); }
    });

    function vote(btn, attempt) {
      if (state.busy && !attempt) return;
      state.busy = true;
      attempt = attempt || 0;
      if (!state.key) state.key = uuid();
      $$('[data-choice]', root).forEach(function (b) {
        b.classList.toggle('is-picked', b === btn);
        b.classList.toggle('is-other', b !== btn);
        b.setAttribute('aria-disabled', 'true');
      });
      setStatus('Counting your vote…');
      fetch(root.dataset.voteUrl, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify({ m: slug, token: state.token, choice: btn.dataset.choice, key: state.key })
      }).then(function (r) { return r.json().catch(function () { return { ok: false, code: 'bad_response' }; }); })
        .then(function (res) {
          if (res.ok) {
            state.key = null;
            if (res.split) showSplit(btn, res.split);
            setStatus(res.split ? 'Vote counted. ' + res.split.chosen + '% of voters agree with you on this pair.' : 'Vote counted.', 'ok');
            setTimeout(loadNext, res.split ? 1300 : 650);
          } else if (res.code === 'closed') {
            state.key = null; showState('Voting has closed', res.message || 'Voting closed while you were deciding.', root.dataset.resultsUrl || null);
          } else if (res.code === 'stale' || res.code === 'already_voted') {
            state.key = null; setStatus(res.message || 'Loading a new matchup…'); setTimeout(loadNext, 700);
          } else if (res.code === 'csrf' || res.error === 'csrf') {
            setStatus('Your session expired. Reloading…', 'err'); setTimeout(function () { location.reload(); }, 1200);
          } else {
            state.key = null; resetPick(); setStatus(res.message || 'Your vote could not be counted. Try again.', 'err');
          }
        })
        .catch(function () {
          // Network failure: retry with the SAME idempotency key so the vote can never count twice.
          if (attempt < 2) { setStatus('Connection problem — retrying…', 'err'); setTimeout(function () { vote(btn, attempt + 1); }, 1200 * (attempt + 1)); }
          else { resetPick(); setStatus('Couldn’t reach the server. Check your connection and tap again.', 'err'); }
        });
    }

    function resetPick() {
      state.busy = false;
      $$('[data-choice]', root).forEach(function (b) { b.classList.remove('is-picked', 'is-other'); b.removeAttribute('aria-disabled'); });
    }
    function showSplit(btn, split) {
      $$('[data-choice]', root).forEach(function (b) {
        var s = $('[data-split]', b); s.textContent = (b === btn ? split.chosen : split.other) + '%';
      });
      grid().classList.add('has-split');
    }

    function loadNext() {
      fetch(root.dataset.nextUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res.progress) setProgress(res.progress);
          if (res.status === 'ok') return swap(res);
          if (res.status === 'complete') return showState('You’ve voted on every matchup', 'Thanks! That’s all ' + res.progress.total + ' pairs in this match.', res.results_url);
          if (res.status === 'closed') return showState('Voting has closed', '', res.results_url);
          showState('Not enough contenders right now', 'Some photos are being reviewed. Check back soon.', null);
        })
        .catch(function () { setStatus('Couldn’t load the next matchup. Reload the page to continue.', 'err'); });
    }

    function setProgress(p) {
      var pct = p.total ? Math.round(p.done * 100 / p.total) : 0;
      var bar = $('[data-progress]', root); bar.setAttribute('aria-valuenow', pct); bar.firstElementChild.style.width = pct + '%';
    }

    function swap(res) {
      var g = grid(), btns = $$('[data-choice]', root);
      var preload = res.pair.map(function (p) { return new Promise(function (ok) { var i = new Image(); i.onload = i.onerror = ok; i.src = p.image; }); });
      g.classList.add('is-swapping');
      Promise.all(preload.concat(new Promise(function (ok) { setTimeout(ok, reduceMotion ? 0 : 320); }))).then(function () {
        res.pair.forEach(function (p, i) {
          var b = btns[i];
          b.dataset.choice = p.id; b.value = p.id;
          b.setAttribute('aria-label', 'Vote for ' + p.name);
          $('img', b).src = p.image;
          $('[data-name]', b).textContent = p.name;
          $('[data-split]', b).textContent = '';
        });
        g.classList.remove('has-split');
        state.token = res.token; form.token.value = res.token;
        resetPick(); setStatus('');
        requestAnimationFrame(function () { g.classList.remove('is-swapping'); });
      });
    }

    function showState(title, text, resultsUrl) {
      var box = document.createElement('div'); box.className = 'arena-state card';
      var h = document.createElement('h2'); h.textContent = title; box.appendChild(h);
      if (text) { var p = document.createElement('p'); p.className = 'muted'; p.textContent = text; box.appendChild(p); }
      if (resultsUrl) {
        var a = document.createElement('a'); a.className = 'btn btn-primary'; a.href = resultsUrl; a.textContent = 'See results'; box.appendChild(a);
      }
      body.innerHTML = ''; body.appendChild(box);
      h.setAttribute('tabindex', '-1'); h.focus();
    }
  }

  /* =====================================================================
     Device mode (the site runs without a database).
     Matches are kept in this browser's IndexedDB. A share link carries the
     whole match (with small photos) in the URL fragment, which is never sent
     to the server. Every visitor's votes are counted on their own device.
     All user text is inserted with textContent; images must be data:image/.
     ===================================================================== */
  function siteUrl(path) {
    var home = $('.brand');
    return (home ? home.getAttribute('href') : '/').replace(/\/$/, '') + path;
  }
  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }
  function safeImg(src) { return typeof src === 'string' && /^data:image\/(jpeg|png|webp);base64,[A-Za-z0-9+\/=]+$/.test(src) ? src : ''; }
  function clip(s, n) { return String(s == null ? '' : s).replace(/[\u0000-\u001f]/g, ' ').trim().slice(0, n); }

  /* ---------- storage ---------- */
  var LDB = 'xevion-facematch', LSTORE = 'matches';
  function ldbOpen() {
    return new Promise(function (resolve, reject) {
      if (!window.indexedDB) { reject(new Error('this browser cannot store matches')); return; }
      var r = indexedDB.open(LDB, 1);
      r.onupgradeneeded = function () { r.result.createObjectStore(LSTORE, { keyPath: 'id' }); };
      r.onsuccess = function () { resolve(r.result); };
      r.onerror = function () { reject(r.error || new Error('storage unavailable')); };
    });
  }
  function ldb(mode, fn) {
    return ldbOpen().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(LSTORE, mode), req = fn(tx.objectStore(LSTORE)), out;
        if (req) req.onsuccess = function () { out = req.result; };
        tx.oncomplete = function () { db.close(); resolve(out); };
        tx.onerror = tx.onabort = function () { db.close(); reject(tx.error || new Error('storage error')); };
      });
    });
  }
  function lget(id) { return ldb('readonly', function (st) { return st.get(id); }); }
  function lput(m) { return ldb('readwrite', function (st) { return st.put(m); }); }
  function ldel(id) { return ldb('readwrite', function (st) { return st.delete(id); }); }
  function lall() { return ldb('readonly', function (st) { return st.getAll(); }).then(function (a) { return a || []; }); }
  function newId() {
    var b = new Uint8Array(9); crypto.getRandomValues(b);
    return Array.prototype.map.call(b, function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
  }

  /* ---------- images ---------- */
  function drawJpeg(src, maxW, maxH, quality) {
    var w = src.naturalWidth || src.width, h = src.naturalHeight || src.height, s = Math.min(1, maxW / w, maxH / h);
    var c = document.createElement('canvas');
    c.width = Math.max(1, Math.round(w * s)); c.height = Math.max(1, Math.round(h * s));
    var ctx = c.getContext('2d');
    ctx.fillStyle = '#0C0727'; ctx.fillRect(0, 0, c.width, c.height);
    ctx.drawImage(src, 0, 0, c.width, c.height);
    if (src.close) src.close();
    return c.toDataURL('image/jpeg', quality);
  }
  /** Check the file's first bytes: JPEG, PNG or WebP only, whatever its name or reported type says. */
  function sniffImage(file) {
    return file.slice(0, 12).arrayBuffer().then(function (buf) {
      var b = new Uint8Array(buf), at = function (i, str) { for (var k = 0; k < str.length; k++) if (b[i + k] !== str.charCodeAt(k)) return false; return true; };
      return (b[0] === 0xFF && b[1] === 0xD8 && b[2] === 0xFF) || at(0, '\x89PNG') || (at(0, 'RIFF') && at(8, 'WEBP'));
    }).catch(function () { return false; });
  }
  function isHeic(file) {
    return file.slice(4, 12).arrayBuffer().then(function (buf) {
      return /^ftyp(heic|heix|hevc|heim|heis|mif1|msf1)$/.test(String.fromCharCode.apply(null, new Uint8Array(buf)));
    }).catch(function () { return false; });
  }
  function fileToJpeg(file, maxW, maxH, quality) {
    return sniffImage(file).then(function (ok) {
      if (!ok) throw new Error('one of the files is not a real JPG, PNG or WebP image');
      return decodeImage(file);
    }).then(function (src) {
      var w = src.naturalWidth || src.width, h = src.naturalHeight || src.height;
      if (w < 160 || h < 160) throw new Error('a photo is too small (minimum 160 × 160 pixels)');
      if (w > 12000 || h > 12000 || w * h > 60000000) throw new Error('a photo has dimensions that are too large');
      return drawJpeg(src, maxW, maxH, quality);
    });
  }
  /** Random access code from an unambiguous alphabet: 10 characters, about 50 bits. */
  function newCode() {
    var abc = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789', b = new Uint8Array(10), out = '';
    crypto.getRandomValues(b);
    for (var i = 0; i < b.length; i++) out += abc[b[i] % abc.length];
    return out.slice(0, 5) + '-' + out.slice(5);
  }
  function dataUrlToJpeg(dataUrl, maxW, maxH, quality) {
    return new Promise(function (resolve, reject) {
      var img = new Image();
      img.onload = function () { resolve(drawJpeg(img, maxW, maxH, quality)); };
      img.onerror = function () { reject(new Error('image')); };
      img.src = dataUrl;
    });
  }

  /* ---------- create ---------- */
  function localCreateFromForm(form, rowEls) {
    var people = rowEls.map(function (r) {
      return { name: clip($('input[name="names[]"]', r).value, 40), file: $('input[type=file]', r).files[0] };
    });
    return Promise.all(people.map(function (p) { return fileToJpeg(p.file, 900, 1125, 0.82); })).then(function (imgs) {
      var m = {
        id: newId(), v: 1, created: Date.now(),
        title: clip(form.title.value, 120), category: form.category.value,
        custom: form.category.value === 'custom' ? clip(form.custom_category.value, 40) : '',
        description: clip(form.description.value, 600), instructions: clip(form.instructions.value, 300),
        people: people.map(function (p, i) { return { id: 'p' + i, name: p.name, img: imgs[i] }; }),
        wins: {}, losses: {}, pairs: {}, round: 1, seen: {}, mine: true
      };
      if (form.local_visibility && form.local_visibility.value === 'code') { m.private = true; m.code = newCode(); }
      return lput(m).then(function () { return m.id; });
    });
  }

  /* ---------- share links ---------- */
  function b64urlFromBytes(bytes) {
    var s = '', chunk = 0x8000;
    for (var i = 0; i < bytes.length; i += chunk) s += String.fromCharCode.apply(null, bytes.subarray(i, i + chunk));
    return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }
  function bytesFromB64url(s) {
    s = s.replace(/-/g, '+').replace(/_/g, '/'); while (s.length % 4) s += '=';
    var bin = atob(s), out = new Uint8Array(bin.length);
    for (var i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
    return out;
  }
  function packText(text) {
    var bytes = new TextEncoder().encode(text);
    if (!window.CompressionStream) return Promise.resolve('u' + b64urlFromBytes(bytes));
    return new Response(new Blob([bytes]).stream().pipeThrough(new CompressionStream('deflate-raw'))).arrayBuffer()
      .then(function (buf) { return 'z' + b64urlFromBytes(new Uint8Array(buf)); });
  }
  function unpackText(s) {
    var bytes = bytesFromB64url(s.slice(1));
    if (s.charAt(0) === 'u') return Promise.resolve(new TextDecoder().decode(bytes));
    if (s.charAt(0) !== 'z' || !window.DecompressionStream) return Promise.reject(new Error('unsupported link'));
    return new Response(new Blob([bytes]).stream().pipeThrough(new DecompressionStream('deflate-raw'))).text();
  }
  function codeKey(code, salt) {
    var norm = String(code).toUpperCase().replace(/[^A-Z0-9]/g, '');
    return crypto.subtle.importKey('raw', new TextEncoder().encode(norm), 'PBKDF2', false, ['deriveKey']).then(function (base) {
      return crypto.subtle.deriveKey({ name: 'PBKDF2', salt: salt, iterations: 210000, hash: 'SHA-256' }, base,
        { name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']);
    });
  }
  function encryptText(text, code) {
    var salt = crypto.getRandomValues(new Uint8Array(16)), iv = crypto.getRandomValues(new Uint8Array(12));
    return codeKey(code, salt).then(function (key) {
      return crypto.subtle.encrypt({ name: 'AES-GCM', iv: iv }, key, new TextEncoder().encode(text));
    }).then(function (ct) {
      var out = new Uint8Array(28 + ct.byteLength);
      out.set(salt, 0); out.set(iv, 16); out.set(new Uint8Array(ct), 28);
      return 'e' + b64urlFromBytes(out);
    });
  }
  function decryptText(packed, code) {
    var all = bytesFromB64url(packed.slice(1));
    if (all.length < 29) return Promise.reject(new Error('invalid'));
    return codeKey(code, all.slice(0, 16)).then(function (key) {
      return crypto.subtle.decrypt({ name: 'AES-GCM', iv: all.slice(16, 28) }, key, all.slice(28));
    }).then(function (pt) { return new TextDecoder().decode(pt); });
  }
  function shareUrlFor(m) {
    return Promise.all(m.people.map(function (p) { return dataUrlToJpeg(p.img, 240, 300, 0.6); })).then(function (imgs) {
      var payload = { v: 1, t: m.title, c: m.category, cc: m.custom || '', d: m.description || '', i: m.instructions || '',
        p: m.people.map(function (p, i) { return { n: p.name, g: imgs[i] }; }) };
      return packText(JSON.stringify(payload));
    }).then(function (packed) {
      return m.private ? encryptText(packed, m.code) : packed;
    }).then(function (packed) { return location.origin + siteUrl('/play') + '#s=' + packed; });
  }
  function digestId(text) {
    if (!(window.crypto && crypto.subtle)) return Promise.resolve('sh' + newId());
    return crypto.subtle.digest('SHA-256', new TextEncoder().encode(text)).then(function (buf) {
      return 'sh' + Array.prototype.map.call(new Uint8Array(buf).slice(0, 9), function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
    });
  }
  /** Validate a shared match and keep it in this browser (once per link). */
  function checkImage(src) {
    return new Promise(function (resolve, reject) {
      var img = new Image();
      img.onload = function () { (img.naturalWidth > 0 && img.naturalWidth <= 2000 && img.naturalHeight <= 2000) ? resolve() : reject(new Error('invalid')); };
      img.onerror = function () { reject(new Error('invalid')); };
      img.src = src;
    });
  }
  function blockedIds(node) {
    try { return JSON.parse((node && node.dataset.blocked) || '[]'); } catch (e) { return []; }
  }
  /** Turn a validated payload object into a stored-match shape. */
  function matchFromData(d, id, isPrivate, code) {
    return { id: id, v: 1, created: Date.now(), title: clip(d.t, 120) || 'Shared match', category: clip(d.c, 32),
      custom: clip(d.cc, 40), description: clip(d.d, 600), instructions: clip(d.i, 300),
      people: d.p.map(function (p, i) { return { id: 'p' + i, name: clip(p.n, 40) || 'Contender ' + (i + 1), img: safeImg(p.g) }; }),
      wins: {}, losses: {}, pairs: {}, round: 1, seen: {}, mine: false,
      private: isPrivate, code: isPrivate ? String(code) : undefined };
  }
  /** Decrypt if needed, size-cap, parse and check every photo. Saves nothing. */
  function decodeShared(packed, code) {
    var text, isPrivate = packed.charAt(0) === 'e';
    if (packed.length > 6000000) return Promise.reject(new Error('invalid'));
    return (isPrivate ? decryptText(packed, code || '') : Promise.resolve(packed)).then(unpackText).then(function (t) {
      text = t;
      var d = JSON.parse(t);
      if (!d || d.v !== 1 || !Array.isArray(d.p) || d.p.length < 2 || d.p.length > 20) throw new Error('invalid');
      if (d.p.some(function (p) { return !safeImg(p && p.g); })) throw new Error('invalid');
      return Promise.all(d.p.map(function (p) { return checkImage(p.g); })).then(function () { return digestId(text); });
    }).then(function (id) { return { id: id, match: matchFromData(JSON.parse(text), id, isPrivate, code), isPrivate: isPrivate }; });
  }
  function importShared(packed, code, blocked) {
    return decodeShared(packed, code).then(function (r) {
      if ((blocked || []).indexOf(r.id) >= 0) throw new Error('blocked');
      return lget(r.id).then(function (existing) { return existing ? r.id : lput(r.match).then(function () { return r.id; }); });
    });
  }
  /* ---------- play page ---------- */
  var lp = $('[data-local-play]');
  if (lp) initLocalPlay(lp);

  function initLocalPlay(root) {
    var cats = {}; try { cats = JSON.parse(root.dataset.cats || '{}'); } catch (e) {}
    var blocked = blockedIds(root);
    var q = function (s) { return $(s, root); }, m = null, current = null, busy = false, last = '';
    var hash = new URLSearchParams(location.hash.slice(1));

    var shared = hash.get('s') || '';
    var openShared = function (code) {
      return importShared(shared, code, blocked).then(function (id) {
        history.replaceState(null, '', location.pathname + '#id=' + encodeURIComponent(id));
        return lget(id);
      });
    };
    if (shared.charAt(0) === 'e') {
      // Private link: nothing can be read until the right access code is entered.
      q('[data-lp-loading]').hidden = true;
      var lock = q('[data-lp-locked]'), lockErr = q('[data-lp-locked-error]');
      lock.hidden = false;
      lock.addEventListener('submit', function (e) {
        e.preventDefault();
        lockErr.textContent = 'Checking…';
        openShared(lock.code.value).then(function (match) {
          lock.hidden = true; m = match; q('[data-lp-main]').hidden = false; renderHeader(); showArena();
        }).catch(function (ex) {
          if (ex && ex.message === 'blocked') { lock.hidden = true; q('[data-lp-blocked]').hidden = false; return; }
          lockErr.textContent = 'That code doesn’t open this match. Check it and try again.';
        });
      });
      lock.code.focus();
      return wireControls();
    }
    var load = shared ? openShared('') : (hash.get('id') ? lget(hash.get('id')) : Promise.resolve(null));

    load.then(function (match) {
      q('[data-lp-loading]').hidden = true;
      if (!match) { q('[data-lp-missing]').hidden = false; return; }
      if (blocked.indexOf(match.id) >= 0) { q('[data-lp-blocked]').hidden = false; return; }
      m = match; q('[data-lp-main]').hidden = false; renderHeader(); showArena();
    }).catch(function (ex) {
      q('[data-lp-loading]').hidden = true;
      q(ex && ex.message === 'blocked' ? '[data-lp-blocked]' : '[data-lp-missing]').hidden = false;
    });

    function catInfo() {
      var c = cats[m.category] || { l: 'Custom', p: 'Pick a winner' };
      return { label: m.category === 'custom' && m.custom ? m.custom : c.l, prompt: m.instructions || c.p };
    }
    function pairKey(a, b) { return a < b ? a + '|' + b : b + '|' + a; }
    function allPairs() {
      var out = [];
      for (var i = 0; i < m.people.length; i++) for (var j = i + 1; j < m.people.length; j++) out.push([m.people[i], m.people[j]]);
      return out;
    }
    function renderHeader() {
      var ci = catInfo();
      document.title = m.title + ' | XEVION FaceMatch';
      q('[data-lp-title]').textContent = m.title;
      q('[data-lp-id]').textContent = m.id;
      q('[data-lp-cat]').textContent = ci.label;
      q('[data-lp-prompt]').textContent = ci.prompt;
    }
    function progress() {
      var total = allPairs().length, done = Object.keys(m.seen).length;
      q('[data-lp-progress]').style.width = (total ? Math.round(done * 100 / total) : 0) + '%';
      q('[data-lp-round]').textContent = 'Voter ' + m.round + ' · ' + done + ' of ' + total + ' matchups';
    }
    function showArena() {
      q('[data-lp-results]').hidden = true;
      var left = allPairs().filter(function (p) { return !m.seen[pairKey(p[0].id, p[1].id)]; });
      progress();
      if (!left.length) { q('[data-lp-arena]').hidden = true; q('[data-lp-done]').hidden = false; return; }
      q('[data-lp-done]').hidden = true; q('[data-lp-arena]').hidden = false;
      // Prefer a pair that shares nobody with the previous one, so faces don't repeat back to back.
      var fresh = left.filter(function (p) { return last.indexOf(p[0].id + ',') < 0 && last.indexOf(p[1].id + ',') < 0; });
      var pool = fresh.length ? fresh : left, pick = pool[Math.floor(Math.random() * pool.length)];
      current = Math.random() < 0.5 ? pick : [pick[1], pick[0]];
      last = current[0].id + ',' + current[1].id + ',';
      $$('.contender', root).forEach(function (btn, i) {
        var p = current[i];
        btn.classList.remove('is-picked', 'is-other');
        btn.removeAttribute('aria-disabled');
        $('img', btn).src = safeImg(p.img);
        $('[data-lp-name]', btn).textContent = p.name;
        btn.setAttribute('aria-label', 'Vote for ' + p.name);
      });
      q('[data-lp-status]').textContent = '';
      busy = false;
    }
    function vote(side) {
      if (busy || !current) return;
      busy = true;
      var win = current[side], lose = current[1 - side], k = pairKey(win.id, lose.id);
      m.wins[win.id] = (m.wins[win.id] || 0) + 1;
      m.losses[lose.id] = (m.losses[lose.id] || 0) + 1;
      var pr = m.pairs[k] || {}; pr[win.id] = (pr[win.id] || 0) + 1; m.pairs[k] = pr;
      m.seen[k] = true;
      $$('.contender', root).forEach(function (btn, i) {
        btn.classList.toggle('is-picked', i === side); btn.classList.toggle('is-other', i !== side);
        btn.setAttribute('aria-disabled', 'true');
      });
      var both = (pr[win.id] || 0) + (pr[lose.id] || 0);
      q('[data-lp-status]').textContent = both > 1
        ? 'Vote counted. ' + Math.round((pr[win.id] || 0) * 100 / both) + '% of votes on this device agree.'
        : 'Vote counted.';
      q('[data-lp-status]').className = 'arena-status ok';
      lput(m).then(function () {
        var grid = q('[data-lp-grid]');
        setTimeout(function () {
          if (!reduceMotion) grid.classList.add('is-swapping');
          setTimeout(function () { showArena(); grid.classList.remove('is-swapping'); }, reduceMotion ? 0 : 320);
        }, 650);
      }).catch(function () { q('[data-lp-status]').textContent = 'Could not save the vote in this browser.'; busy = false; });
    }
    function renderResults() {
      var rows = m.people.map(function (p) {
        var w = m.wins[p.id] || 0, l = m.losses[p.id] || 0;
        return { p: p, w: w, l: l, rate: w + l ? w / (w + l) : -1 };
      }).sort(function (a, b) { return b.rate - a.rate || b.w - a.w; });
      var tbody = q('[data-lp-rows]'), total = 0;
      tbody.textContent = '';
      rows.forEach(function (r, i) {
        total += r.w;
        var prev = rows[i - 1], same = prev && prev.rate === r.rate && prev.w === r.w;
        r.rank = same ? prev.rank : i + 1;
        var tied = same || (rows[i + 1] && rows[i + 1].rate === r.rate && rows[i + 1].w === r.w);
        var tr = el('tr');
        tr.appendChild(el('td', 'rank', String(r.rank)));
        var who = el('td'), wrap = el('div', 'who'), img = el('img');
        img.src = safeImg(r.p.img); img.alt = ''; img.width = 44; img.height = 55;
        wrap.appendChild(img); wrap.appendChild(el('span', null, r.p.name));
        if (tied && r.w + r.l > 0) wrap.appendChild(el('span', 'tie', 'Tied'));
        who.appendChild(wrap); tr.appendChild(who);
        var rate = el('td', 'num', r.rate < 0 ? '—' : Math.round(r.rate * 100) + '%');
        var bar = el('div', 'bar'), fill = el('i'); fill.style.width = (r.rate < 0 ? 0 : Math.round(r.rate * 100)) + '%';
        bar.appendChild(fill); rate.appendChild(bar); tr.appendChild(rate);
        tr.appendChild(el('td', 'num', r.w + '–' + r.l));
        tbody.appendChild(tr);
      });
      q('[data-lp-total]').textContent = total + (total === 1 ? ' vote' : ' votes') + ' on this device';
    }
    function showResults() {
      renderResults();
      q('[data-lp-results]').hidden = false;
      q('[data-lp-results]').scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
    }

    wireControls();

    function wireControls() {
    $$('.contender', root).forEach(function (btn) {
      btn.addEventListener('click', function () { vote(+btn.dataset.side); });
    });
    document.addEventListener('keydown', function (e) {
      if (!m || q('[data-lp-arena]').hidden || /input|textarea|select/i.test((e.target || {}).tagName || '')) return;
      if (e.key === '1' || e.key === 'ArrowLeft') { e.preventDefault(); vote(0); }
      if (e.key === '2' || e.key === 'ArrowRight') { e.preventDefault(); vote(1); }
    });
    $$('[data-lp-tab]', root).forEach(function (b) {
      b.addEventListener('click', function () { if (b.dataset.lpTab === 'results') showResults(); else showArena(); });
    });
    q('[data-lp-next-round]').addEventListener('click', function () {
      m.round += 1; m.seen = {}; lput(m).then(showArena);
    });
    q('[data-lp-reset]').addEventListener('click', function () {
      if (!window.confirm('Clear every vote on this device for this match?')) return;
      m.wins = {}; m.losses = {}; m.pairs = {}; m.seen = {}; m.round = 1; lput(m).then(showArena);
    });
    q('[data-lp-delete]').addEventListener('click', function () {
      if (!window.confirm('Delete this match from this browser? This cannot be undone.')) return;
      ldel(m.id).then(function () { location.href = siteUrl('/explore'); });
    });
    q('[data-lp-share]').addEventListener('click', function () {
      var panel = q('[data-lp-share-panel]'), input = q('[data-lp-share-url]'), note = q('[data-lp-share-note]');
      panel.hidden = false; note.textContent = 'Preparing the link…'; input.value = '';
      q('[data-lp-code-row]').hidden = !m.private;
      if (m.private) {
        q('[data-lp-code]').textContent = m.code || '';
        q('[data-lp-share-intro]').textContent = 'This link is encrypted. It only opens with the access code, so send the code separately (for example by text message).';
      }
      shareUrlFor(m).then(function (url) {
        input.value = url;
        note.textContent = url.length > 60000 ? 'This link is long because it carries the photos. If an app cuts it off, send it by email or copy it into a note.' : '';
        if (navigator.share) navigator.share({ title: m.title, url: url }).catch(function () {});
      }).catch(function () { note.textContent = 'The link could not be created in this browser.'; });
    });
    q('[data-lp-copy]').addEventListener('click', function () {
      var input = q('[data-lp-share-url]'), note = q('[data-lp-share-note]');
      if (!input.value) return;
      (navigator.clipboard ? navigator.clipboard.writeText(input.value) : Promise.reject()).then(function () {
        note.textContent = 'Link copied.';
      }).catch(function () {
        input.focus(); input.select(); input.setSelectionRange(0, input.value.length);
        var ok = false; try { ok = document.execCommand('copy'); } catch (e) {}
        note.textContent = ok ? 'Link copied.' : 'Press Ctrl+C (or long-press) to copy.';
      });
    });
    }
  }

  /* ---------- "Your matches" list ---------- */
  var listRoot = $('[data-local-list]');
  if (listRoot) {
    var labels = {}; try { labels = JSON.parse(listRoot.dataset.labels || '{}'); } catch (e) {}
    lall().then(function (items) {
      var cat = listRoot.dataset.category;
      var blockedList = blockedIds(listRoot);
      items = items.filter(function (m) { return (!cat || m.category === cat) && blockedList.indexOf(m.id) < 0; }).sort(function (a, b) { return b.created - a.created; });
      if (!items.length) { $('[data-local-empty]').hidden = false; return; }
      items.forEach(function (m) {
        var a = el('a', 'match-card'); a.href = siteUrl('/play') + '#id=' + encodeURIComponent(m.id);
        var duo = el('div', 'duo'); duo.setAttribute('aria-hidden', 'true');
        [0, 1].forEach(function (i) {
          var p = m.people[i];
          if (p && safeImg(p.img)) { var img = el('img'); img.src = p.img; img.alt = ''; img.loading = 'lazy'; duo.appendChild(img); }
          else duo.appendChild(el('span', 'duo-empty', ''));
        });
        duo.appendChild(el('span', 'duo-vs', 'VS'));
        var body = el('div', 'match-body');
        body.appendChild(el('h3', null, m.title));
        var meta = el('div', 'match-meta');
        meta.appendChild(el('span', 'pill pill-cat', m.category === 'custom' && m.custom ? m.custom : (labels[m.category] || 'Custom')));
        meta.appendChild(el('span', 'pill', m.mine ? 'Created here' : 'Shared with you'));
        var votes = Object.keys(m.wins || {}).reduce(function (s, k) { return s + m.wins[k]; }, 0);
        var meta2 = el('div', 'match-meta');
        meta2.appendChild(el('span', null, m.people.length + ' contenders'));
        meta2.appendChild(el('span', null, votes + (votes === 1 ? ' vote' : ' votes')));
        body.appendChild(meta); body.appendChild(meta2);
        a.appendChild(duo); a.appendChild(body);
        listRoot.appendChild(a);
      });
    }).catch(function () { $('[data-local-empty]').hidden = false; });
  }

  /* ---------- admin dashboard without a database ---------- */
  var adminLite = $('[data-admin-lite]');
  if (adminLite) initAdminLite(adminLite);

  function initAdminLite(root) {
    var note = $('[data-lite-note]', root), rowsEl = $('[data-lite-rows]', root);
    function renderRows() {
      lall().then(function (items) {
        rowsEl.textContent = '';
        $('[data-lite-empty]', root).hidden = items.length > 0;
        items.sort(function (a, b) { return b.created - a.created; }).forEach(function (m) {
          var tr = el('tr'), votes = Object.keys(m.wins || {}).reduce(function (n, k) { return n + m.wins[k]; }, 0);
          var td = el('td'), a = el('a', null, m.title); a.href = siteUrl('/play') + '#id=' + encodeURIComponent(m.id);
          td.appendChild(a); if (m.private) td.appendChild(el('span', 'pill pill-private', 'Private')); tr.appendChild(td);
          tr.appendChild(el('td', null, String(m.people.length)));
          tr.appendChild(el('td', null, String(votes)));
          var idTd = el('td'); idTd.appendChild(el('code', null, m.id)); tr.appendChild(idTd);
          var act = el('td'), del = el('button', 'btn btn-danger btn-sm', 'Delete'); del.type = 'button';
          del.addEventListener('click', function () {
            if (!window.confirm('Delete “' + m.title + '” from this browser?')) return;
            ldel(m.id).then(renderRows);
          });
          act.appendChild(del); tr.appendChild(act);
          rowsEl.appendChild(tr);
        });
      }).catch(function () { note.textContent = 'This browser cannot open its match storage.'; });
    }
    renderRows();

    // Backup: a JSON file with the matches as stored (photos included).
    $('[data-lite-export]', root).addEventListener('click', function () {
      lall().then(function (items) {
        var blob = new Blob([JSON.stringify({ app: 'xevion-facematch', v: 1, exported: new Date().toISOString(), matches: items })], { type: 'application/json' });
        var a = el('a'); a.href = URL.createObjectURL(blob); a.download = 'facematch-backup-' + new Date().toISOString().slice(0, 10) + '.json';
        document.body.appendChild(a); a.click(); a.remove(); setTimeout(function () { URL.revokeObjectURL(a.href); }, 2000);
        note.textContent = items.length + (items.length === 1 ? ' match' : ' matches') + ' saved to a file.';
      });
    });
    // Restore: every match is re-validated like a share link before it is stored.
    $('[data-lite-import]', root).addEventListener('change', function (e) {
      var file = e.target.files && e.target.files[0];
      if (!file) return;
      if (file.size > 60 * 1048576) { note.textContent = 'That file is too large.'; return; }
      note.textContent = 'Checking the backup…';
      file.text().then(function (txt) {
        var d = JSON.parse(txt);
        if (!d || d.app !== 'xevion-facematch' || !Array.isArray(d.matches)) throw new Error('not a FaceMatch backup');
        return Promise.all(d.matches.slice(0, 500).map(function (m) {
          if (!m || !/^[a-z0-9]{6,40}$/.test(String(m.id)) || !Array.isArray(m.people) || m.people.length < 2 || m.people.length > 20) return false;
          var people = m.people.map(function (p, i) { return { id: 'p' + i, name: clip(p && p.name, 40) || 'Contender ' + (i + 1), img: safeImg(p && p.img) }; });
          if (people.some(function (p) { return !p.img; })) return false;
          return Promise.all(people.map(function (p) { return checkImage(p.img); })).then(function () {
            var clean = { id: String(m.id), v: 1, created: +m.created || Date.now(), title: clip(m.title, 120) || 'Match', category: clip(m.category, 32),
              custom: clip(m.custom, 40), description: clip(m.description, 600), instructions: clip(m.instructions, 300), people: people,
              wins: {}, losses: {}, pairs: {}, round: 1, seen: {}, mine: !!m.mine, private: !!m.private, code: m.private ? clip(m.code, 24) : undefined };
            return lput(clean).then(function () { return true; });
          }).catch(function () { return false; });
        }));
      }).then(function (res) {
        var ok = res.filter(Boolean).length;
        note.textContent = ok + ' restored' + (res.length - ok ? ', ' + (res.length - ok) + ' skipped because they failed checks' : '') + '. Votes are not restored.';
        renderRows();
      }).catch(function (ex) { note.textContent = 'Could not restore: ' + (ex && ex.message ? ex.message : 'invalid file') + '.'; });
      e.target.value = '';
    });

    // Moderation: decode a reported link here, without saving it.
    var form = $('[data-lite-inspect]', root), out = $('[data-lite-result]', root), blocked = blockedIds(root);
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      out.hidden = false; out.textContent = 'Opening the link…';
      var link = form.link.value, at = link.indexOf('#s=');
      if (at < 0) { out.textContent = 'That is not a FaceMatch share link (it should contain “#s=”).'; return; }
      decodeShared(decodeURIComponent(link.slice(at + 3)), form.code.value).then(function (r) {
        var m = r.match;
        out.textContent = '';
        out.appendChild(el('h3', null, m.title));
        var meta = el('p', 'muted small');
        meta.appendChild(document.createTextNode('Match ID '));
        meta.appendChild(el('code', null, r.id));
        meta.appendChild(document.createTextNode(' · ' + (r.isPrivate ? 'private' : 'public link') + ' · ' + m.people.length + ' contenders' + (blocked.indexOf(r.id) >= 0 ? ' · already blocked' : '')));
        out.appendChild(meta);
        if (m.description) out.appendChild(el('p', null, m.description));
        var grid = el('div', 'lite-faces');
        m.people.forEach(function (p) {
          var fig = el('figure'), img = el('img'); img.src = p.img; img.alt = ''; fig.appendChild(img);
          fig.appendChild(el('figcaption', null, p.name)); grid.appendChild(fig);
        });
        out.appendChild(grid);
        out.appendChild(el('p', 'small muted', 'To block it, add ' + r.id + ' to BLOCKED_MATCHES in Vercel and redeploy.'));
      }).catch(function () {
        out.textContent = form.link.value.indexOf('#s=e') >= 0 ? 'This private link needs the right access code.' : 'This link is damaged or not a valid FaceMatch match.';
      });
    });
  }
})();