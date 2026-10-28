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
        var img = document.createElement('img'); img.alt = ''; img.src = URL.createObjectURL(f);
        drop.insertBefore(img, file); label.textContent = 'Change photo';
      });
      $('[data-remove]', r).addEventListener('click', function () {
        if (rows().length <= min) return;
        var img = $('.p-drop img', r); if (img) URL.revokeObjectURL(img.src);
        r.remove(); refresh();
        var first = $('[data-row] input[name="names[]"]', people); if (first) first.focus();
      });
    };
    function checkFile(f) {
      if (['image/jpeg', 'image/png', 'image/webp'].indexOf(f.type) < 0) return 'Use a JPG, PNG or WebP image.';
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
})();
