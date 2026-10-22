/* StudentHub front-end: theme, toasts, dialogs, command palette, validation,
   session countdown, charts and small progressive enhancements. No dependencies. */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var body = document.body;
  var BASE = body.dataset.base || '';
  var CSRF = body.dataset.csrf || '';
  var ICONS = {
    success: 'M5 12l5 5 9-11', danger: 'M12 4l9 16H3zM12 10v4M12 17v.5', error: 'M12 4l9 16H3zM12 10v4M12 17v.5',
    warning: 'M12 4l9 16H3zM12 10v4M12 17v.5', info: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18M12 11v5M12 8v.5', x: 'M6 6l12 12M18 6L6 18'
  };
  function svg(name) {
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' + ICONS[name] + '"/></svg>';
  }
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function debounce(fn, ms) { var t; return function () { var a = arguments, c = this; clearTimeout(t); t = setTimeout(function () { fn.apply(c, a); }, ms); }; }

  /* ---------- toasts ---------- */
  function toast(type, message, ms) {
    var box = $('#toasts'); if (!box) return;
    var el = document.createElement('div');
    el.className = 'toast ' + type;
    el.setAttribute('role', type === 'danger' || type === 'error' ? 'alert' : 'status');
    el.innerHTML = svg(type in ICONS ? type : 'info') + '<p>' + esc(message) + '</p><button type="button" aria-label="Dismiss">' + svg('x') + '</button>';
    box.appendChild(el);
    var close = function () { el.classList.add('out'); setTimeout(function () { el.remove(); }, 220); };
    $('button', el).addEventListener('click', close);
    setTimeout(close, ms || Math.max(type === 'danger' || type === 'error' ? 7000 : 4500, message.length * 80));
  }

  /* ---------- API helper (JSON + CSRF) ---------- */
  function api(path, opts) {
    opts = opts || {};
    var headers = { 'Accept': 'application/json', 'X-CSRF-Token': CSRF };
    var init = { method: opts.method || 'GET', headers: headers, credentials: 'same-origin', signal: opts.signal };
    if (opts.body !== undefined) { headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(opts.body); }
    return fetch(BASE + '/' + path.replace(/^\//, ''), init).then(function (res) {
      return res.json().catch(function () { return null; }).then(function (data) {
        return { ok: res.ok, status: res.status, data: data };
      });
    });
  }

  /* ---------- confirm dialog ---------- */
  function confirmBox(title, text, okLabel, tone) {
    var d = $('#confirm-dialog');
    return new Promise(function (resolve) {
      $('#confirm-title').textContent = title || 'Are you sure?';
      $('#confirm-text').textContent = text || '';
      var ok = $('#confirm-ok'); ok.textContent = okLabel || 'Confirm';
      ok.className = 'btn ' + (tone === 'primary' ? 'btn-primary' : 'btn-danger');
      d.addEventListener('close', function once() { d.removeEventListener('close', once); resolve(d.returnValue === 'ok'); });
      d.returnValue = ''; d.showModal();
    });
  }
  document.addEventListener('submit', function (e) {
    var f = e.target, trigger = e.submitter;
    var msg = (trigger && trigger.dataset.confirm) || f.dataset.confirm;
    if (!msg || f.dataset.confirmed) return;
    e.preventDefault();
    confirmBox((trigger && trigger.dataset.confirmTitle) || f.dataset.confirmTitle || 'Are you sure?', msg, (trigger && trigger.dataset.confirmOk) || f.dataset.confirmOk || 'Confirm')
      .then(function (yes) { if (yes) { f.dataset.confirmed = '1'; if (trigger && trigger.name) { var h = document.createElement('input'); h.type = 'hidden'; h.name = trigger.name; h.value = trigger.value; f.appendChild(h); } f.submit(); } });
  }, true);

  /* ---------- theme ---------- */
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-theme-toggle]'); if (!t) return;
    var root = document.documentElement;
    var dark = root.getAttribute('data-theme') ? root.getAttribute('data-theme') === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches;
    var next = dark ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    try { localStorage.setItem('sh-theme', next); } catch (err) {}
  });

  /* ---------- sidebar (mobile) ---------- */
  var sidebar = $('#sidebar'), scrim = $('.scrim');
  function sidebarSet(open) { if (!sidebar) return; sidebar.classList.toggle('open', open); if (scrim) scrim.hidden = !open; }
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-sidebar-open]')) sidebarSet(true);
    else if (e.target.closest('[data-sidebar-close]')) sidebarSet(false);
  });

  /* ---------- flash -> toasts ---------- */
  try { JSON.parse($('#flash-data').textContent).forEach(function (f) { toast(f.type, f.message); }); } catch (e) {}

  /* ---------- form UX: show/hide password, strength meter, validation ---------- */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-toggle-password]'); if (!b) return;
    var inp = document.getElementById(b.dataset.togglePassword);
    var show = inp.type === 'password'; inp.type = show ? 'text' : 'password';
    b.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  });
  function strength(v) {
    var s = 0; if (v.length >= 8) s++; if (/[a-z]/.test(v) && /[A-Z]/.test(v)) s++; if (/\d/.test(v)) s++; if (/[^A-Za-z0-9]/.test(v) || v.length >= 12) s++;
    return s;
  }
  $$('[data-meter-for]').forEach(function (m) {
    var inp = document.getElementById(m.dataset.meterFor), label = $('[data-meter-label="' + m.dataset.meterFor + '"]');
    var words = ['', 'Weak', 'Fair', 'Good', 'Strong'];
    inp.addEventListener('input', function () { var s = inp.value ? strength(inp.value) : 0; m.dataset.level = s; if (label) label.textContent = s ? words[s] : ''; });
  });
  function fieldOf(el) { return el.closest('.field'); }
  function setMsg(el, text, bad) {
    var f = fieldOf(el); if (!f) return;
    var m = $('.field-msg', f); if (!f.dataset.hint) f.dataset.hint = m.textContent;
    f.classList.toggle('has-error', !!bad); f.classList.toggle('is-valid', !bad && !!el.value && el.checkValidity());
    m.textContent = bad ? text : f.dataset.hint; el.setAttribute('aria-invalid', bad ? 'true' : 'false');
  }
  function checkField(el) {
    if (el.dataset.match) { var o = document.getElementById(el.dataset.match); el.setCustomValidity(o && el.value !== o.value ? 'Passwords do not match.' : ''); }
    var bad = !el.checkValidity();
    var msg = el.dataset.msg || el.validationMessage;
    if (el.validity.valueMissing) msg = 'This field is required.';
    if (el.validity.customError) msg = el.validationMessage;
    setMsg(el, msg, bad);
  }
  document.addEventListener('blur', function (e) { var el = e.target; if (el.matches && el.matches('input:not([type=hidden]):not([type=file]), select, textarea') && fieldOf(el) && (el.value || el.dataset.touched)) { el.dataset.touched = '1'; checkField(el); } }, true);
  document.addEventListener('input', function (e) { var el = e.target; if (el.dataset && el.dataset.touched) checkField(el); });
  document.addEventListener('invalid', function (e) { e.preventDefault(); var el = e.target; el.dataset.touched = '1'; checkField(el); }, true);
  document.addEventListener('submit', function (e) {
    var f = e.target; if (e.defaultPrevented || f.method === 'dialog') return;
    var first = $$('input,select,textarea', f).filter(function (el) { return el.dataset.match; });
    first.forEach(function (el) { checkField(el); });
    var btn = e.submitter || $('button[type=submit]', f);
    if (btn && f.checkValidity() && !f.hasAttribute('data-no-loading')) { setTimeout(function () { btn.classList.add('is-loading'); }, 0); }
  });
  $$('input[data-match]').forEach(function (el) { var o = document.getElementById(el.dataset.match); if (o) o.addEventListener('input', function () { if (el.value) checkField(el); }); });

  /* ---------- debounced auto-submit search forms ---------- */
  $$('[data-autosubmit]').forEach(function (form) {
    var go = debounce(function () { form.requestSubmit(); }, 380);
    $$('input[type=search], input[type=text]', form).forEach(function (i) { i.addEventListener('input', go); });
    $$('select', form).forEach(function (s) { s.addEventListener('change', function () { form.requestSubmit(); }); });
    var s = $('input[type=search]', form); if (s && new URLSearchParams(location.search).get('q')) { s.focus(); s.setSelectionRange(s.value.length, s.value.length); }
  });

  /* ---------- dropzone with client-side checks (server re-checks everything) ---------- */
  $$('[data-dropzone]').forEach(function (dz) {
    var input = $('input[type=file]', dz), prev = $('.dz-preview', dz), err = dz.parentNode.querySelector('.dz-error');
    var max = parseInt(dz.dataset.max || '2097152', 10), types = (dz.dataset.types || 'image/jpeg,image/png,image/webp').split(',');
    function show(file) {
      err.textContent = '';
      if (!file) return;
      if (types.indexOf(file.type) < 0) { err.textContent = 'Only JPEG, PNG or WebP images are accepted.'; input.value = ''; return; }
      if (file.size > max) { err.textContent = 'That file is ' + (file.size / 1048576).toFixed(1) + ' MB. The limit is ' + (max / 1048576) + ' MB.'; input.value = ''; return; }
      var r = new FileReader(); r.onload = function () { prev.innerHTML = '<img alt="Poster preview" src="' + r.result + '"><span>' + esc(file.name) + '</span>'; }; r.readAsDataURL(file);
    }
    input.addEventListener('change', function () { show(input.files[0]); });
    ['dragenter', 'dragover'].forEach(function (n) { dz.addEventListener(n, function () { dz.classList.add('over'); }); });
    ['dragleave', 'drop'].forEach(function (n) { dz.addEventListener(n, function () { dz.classList.remove('over'); }); });
  });

  /* ---------- count-up numbers, progress bars ---------- */
  var reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  $$('[data-count]').forEach(function (el) {
    var to = parseInt(el.dataset.count, 10) || 0; if (reduce || to < 2) { el.textContent = to; return; }
    var t0 = performance.now(), dur = 900;
    (function tick(t) { var p = Math.min(1, (t - t0) / dur); el.textContent = Math.round(to * (1 - Math.pow(1 - p, 3))); if (p < 1) requestAnimationFrame(tick); })(t0);
  });
  requestAnimationFrame(function () { $$('[data-bar]').forEach(function (b) { b.style.width = b.dataset.bar + '%'; }); });

  /* ---------- charts (inline SVG) ---------- */
  function barChart(el, d) {
    var W = 560, H = 250, pl = 30, pb = 28, pt = 18, n = d.labels.length, max = Math.max.apply(null, d.values.concat([1]));
    var step = Math.max(1, Math.ceil(max / 4)); max = step * 4;
    var bw = (W - pl) / n, out = '<svg viewBox="0 0 ' + W + ' ' + H + '" role="img" aria-label="' + esc(d.title || 'Bar chart') + '">';
    for (var i = 0; i <= 4; i++) { var y = pt + (H - pt - pb) * (1 - i / 4); out += '<line class="grid-line" x1="' + pl + '" x2="' + W + '" y1="' + y + '" y2="' + y + '"/><text x="' + (pl - 8) + '" y="' + (y + 4) + '" text-anchor="end">' + step * i + '</text>'; }
    d.values.forEach(function (v, k) {
      var h = (H - pt - pb) * v / max, x = pl + k * bw + bw * .2, y2 = H - pb - h;
      out += '<rect class="bar-rect" tabindex="0" style="animation-delay:' + (k * 60) + 'ms" x="' + x + '" y="' + y2 + '" width="' + bw * .6 + '" height="' + Math.max(h, 1) + '" rx="6"><title>' + esc(d.labels[k]) + ': ' + v + '</title></rect>';
      out += '<text class="val" x="' + (x + bw * .3) + '" y="' + (y2 - 6) + '" text-anchor="middle">' + v + '</text><text x="' + (x + bw * .3) + '" y="' + (H - 8) + '" text-anchor="middle">' + esc(d.labels[k]) + '</text>';
    });
    el.innerHTML = out + '</svg>';
  }
  function donut(el, d) {
    var total = d.values.reduce(function (a, b) { return a + b; }, 0) || 1, R = 54, C = 2 * Math.PI * R, off = 0, cols = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)'];
    var s = '<div class="donut-wrap"><svg class="donut" viewBox="0 0 140 140" role="img" aria-label="' + esc(d.title || 'Donut chart') + '"><circle cx="70" cy="70" r="' + R + '" fill="none" stroke="var(--line)" stroke-width="18"/>';
    d.values.forEach(function (v, i) { var len = C * v / total; s += '<circle class="seg" cx="70" cy="70" r="' + R + '" stroke="' + cols[i % 4] + '" stroke-dasharray="' + (reduce ? len : 0) + ' ' + C + '" data-len="' + len + '" stroke-dashoffset="' + (-off) + '"/>'; off += len; });
    s += '<text class="big" x="70" y="72">' + total + '</text><text class="small" x="70" y="88">' + esc(d.center || 'total') + '</text></svg><ul class="legend">';
    d.labels.forEach(function (l, i) { s += '<li><i style="background:' + cols[i % 4] + '"></i>' + esc(l) + '<b>' + d.values[i] + '</b></li>'; });
    el.innerHTML = s + '</ul></div>';
    requestAnimationFrame(function () { $$('.seg', el).forEach(function (c) { c.setAttribute('stroke-dasharray', c.dataset.len + ' ' + C); }); });
  }
  function spark(el, vals) {
    var W = 84, H = 30, max = Math.max.apply(null, vals.concat([1])), pts = vals.map(function (v, i) { return [i * W / Math.max(vals.length - 1, 1), H - 3 - (H - 6) * v / max]; });
    el.innerHTML = '<svg viewBox="0 0 ' + W + ' ' + H + '" aria-hidden="true"><path d="M' + pts.map(function (p) { return p[0].toFixed(1) + ' ' + p[1].toFixed(1); }).join(' L') + '"/></svg>';
  }
  $$('[data-chart]').forEach(function (el) { try { var d = JSON.parse(el.dataset.chart); (d.type === 'donut' ? donut : d.type === 'spark' ? function (e, x) { spark(e, x.values); } : barChart)(el, d); } catch (e) {} });

  /* ---------- command palette ---------- */
  var pal = $('#palette'), pin = $('#palette-input'), plist = $('#palette-list'), pitems = [], psel = 0, pabort;
  var menu = []; try { menu = JSON.parse(body.dataset.menu || '[]'); } catch (e) {}
  function paletteRender(extra) {
    var q = pin.value.trim().toLowerCase();
    pitems = menu.filter(function (m) { return !q || m.label.toLowerCase().indexOf(q) >= 0; }).map(function (m) { return { label: m.label, href: m.href, hint: 'Page' }; }).concat(extra || []);
    psel = 0;
    plist.innerHTML = pitems.length ? pitems.map(function (it, i) { return '<li role="option" data-i="' + i + '" aria-selected="' + (i === 0) + '">' + esc(it.label) + '<small>' + esc(it.hint) + '</small></li>'; }).join('') : '<li aria-disabled="true">No matches</li>';
  }
  var remote = debounce(function () {
    var q = pin.value.trim(); if (q.length < 2 || body.dataset.role === 'guest') return;
    if (pabort) pabort.abort(); pabort = new AbortController();
    api('api/search.php?q=' + encodeURIComponent(q), { signal: pabort.signal }).then(function (r) {
      if (r.ok && r.data && r.data.data) paletteRender(r.data.data.map(function (x) { return { label: x.label, href: BASE + '/' + x.href, hint: x.type }; }));
    }).catch(function () {});
  }, 250);
  function paletteMove(d) { var lis = $$('li[data-i]', plist); if (!lis.length) return; lis[psel].setAttribute('aria-selected', 'false'); psel = (psel + d + lis.length) % lis.length; lis[psel].setAttribute('aria-selected', 'true'); lis[psel].scrollIntoView({ block: 'nearest' }); }
  function paletteGo() { var it = pitems[psel]; if (it) location.href = it.href; }
  if (pal) {
    document.addEventListener('click', function (e) { if (e.target.closest('[data-palette-open]')) { pin.value = ''; paletteRender(); pal.showModal(); pin.focus(); } });
    pal.addEventListener('click', function (e) { if (e.target === pal) pal.close(); var li = e.target.closest('li[data-i]'); if (li) { psel = +li.dataset.i; paletteGo(); } });
    pin.addEventListener('input', function () { paletteRender(); remote(); });
    pin.addEventListener('keydown', function (e) { if (e.key === 'ArrowDown') { e.preventDefault(); paletteMove(1); } else if (e.key === 'ArrowUp') { e.preventDefault(); paletteMove(-1); } else if (e.key === 'Enter') { e.preventDefault(); paletteGo(); } });
    document.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); if (pal.open) pal.close(); else { pin.value = ''; paletteRender(); pal.showModal(); pin.focus(); } }
    });
  }
  $$('dialog').forEach(function (d) { d.addEventListener('click', function (e) { if (e.target === d && d.id !== 'session-dialog') d.close('cancel'); }); });

  /* ---------- session countdown + keep-alive ---------- */
  var left = parseInt(body.dataset.timeout, 10);
  if (!isNaN(left) && body.dataset.timeout !== '') {
    var chip = $('#session-chip'), sd = $('#session-dialog'), cnt = $('#session-count'), end = Date.now() + left * 1000;
    var fmt = function (s) { return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2); };
    setInterval(function () {
      var s = Math.max(0, Math.round((end - Date.now()) / 1000));
      if (chip) { chip.hidden = s > 300; $('span', chip).textContent = fmt(s); chip.classList.toggle('low', s <= 60); }
      if (s <= 60 && s > 0 && sd && !sd.open) sd.showModal();
      if (sd && sd.open) cnt.textContent = s;
      if (Date.now() > end + 1500) location.href = BASE + '/login.php?reason=timeout';   // small grace so the server has certainly expired the session
    }, 1000);
    if (sd) sd.addEventListener('close', function () {
      if (sd.returnValue === 'stay') api('api/session.php', { method: 'POST' }).then(function (r) { if (r.ok) { end = Date.now() + r.data.data.remaining * 1000; toast('success', 'Session extended.'); } else location.reload(); });
    });
  }

  window.SH = { toast: toast, api: api, confirm: confirmBox, debounce: debounce, esc: esc, svg: svg, base: BASE };
})();
