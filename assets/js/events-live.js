// Practical 13: Fetch-based CRUD against api/events.php with loading, success and error states.
(function () {
  var S = { q: '', category: '', state: 'all', page: 1 }, esc = SH.esc;
  var body = document.getElementById('live-body'), pager = document.getElementById('live-pager');
  var dlg = document.getElementById('live-dialog'), form = document.getElementById('live-form');
  var idEl = document.getElementById('ld-id'), alertEl = document.getElementById('ld-alert'), saveBtn = document.getElementById('ld-save');
  var F = { title: 'ld-title-in', category_id: 'ld-category', status: 'ld-status', starts_at: 'ld-starts', ends_at: 'ld-ends', location: 'ld-location', capacity: 'ld-capacity', description: 'ld-description' };
  var abort, cache = {};

  function inspect(method, url, res, ms) {
    document.getElementById('insp-line').textContent = method + ' ' + url + ' → ' + res.status + ' · ' + ms + ' ms';
    var t = JSON.stringify(res.data, null, 2) || '(empty)'; document.getElementById('insp-json').textContent = t.length > 2500 ? t.slice(0, 2500) + '\n…' : t;
  }
  function call(method, path, payload, signal) {
    var t0 = performance.now();
    return SH.api(path, { method: method, body: payload, signal: signal }).then(function (r) { inspect(method, path, r, Math.round(performance.now() - t0)); return r; });
  }
  function skeleton() { var s = ''; for (var i = 0; i < 5; i++) s += '<div class="sk-row"><div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div></div>'; body.innerHTML = s; body.setAttribute('aria-busy', 'true'); }
  function fmt(d) { var x = new Date(d.replace(' ', 'T')); return x.toLocaleString('en-IN', { day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' }); }
  var BADGE = { published: 'ok', draft: 'muted', closed: 'warn', cancelled: 'bad' };

  function load() {
    if (abort) abort.abort(); abort = new AbortController(); skeleton();
    var qs = new URLSearchParams({ q: S.q, category: S.category, state: S.state, page: S.page, per: 8 }).toString();
    call('GET', 'api/events.php?' + qs, undefined, abort.signal).then(function (r) {
      body.setAttribute('aria-busy', 'false');
      if (!r.ok) return fail(r);
      cache = {}; r.data.data.forEach(function (e) { cache[e.event_id] = e; }); render(r.data.data, r.data.meta);
    }).catch(function (err) { if (err.name !== 'AbortError') fail(null); });
  }
  function fail(r) {
    var msg = r && r.data && r.data.error ? r.data.error.message : 'The server could not be reached.';
    body.innerHTML = '<div class="empty">' + SH.svg('alert') + '<h3>Could not load events</h3><p>' + esc(msg) + '</p><button class="btn btn-ghost btn-sm" type="button" id="live-retry">Try again</button></div>';
    pager.hidden = true;
  }
  function render(rows, meta) {
    if (!rows.length) { body.innerHTML = '<div class="empty">' + SH.svg('info') + '<h3>No events match</h3><p>Change the search or add an event.</p></div>'; pager.hidden = true; return; }
    var h = '<div class="table-wrap"><table class="data"><thead><tr><th scope="col">Event</th><th scope="col">Category</th><th scope="col">When</th><th scope="col">Seats</th><th scope="col">Status</th><th scope="col" class="right"><span class="sr">Actions</span></th></tr></thead><tbody>';
    rows.forEach(function (e) {
      h += '<tr data-id="' + e.event_id + '"><td><strong>' + esc(e.title) + '</strong><div class="muted" style="font-size:.82rem">' + esc(e.location) + '</div></td><td>' + esc(e.category) + '</td><td class="nowrap">' + esc(fmt(e.starts_at)) + '</td><td class="nowrap">' + e.registered_count + ' / ' + e.capacity + '</td>' +
        '<td><span class="badge badge-' + (BADGE[e.status] || 'muted') + '">' + esc(e.status.charAt(0).toUpperCase() + e.status.slice(1)) + '</span></td>' +
        '<td><div class="actions"><button class="icon-btn sm" data-act="edit" aria-label="Edit ' + esc(e.title) + '" title="Edit">' + SH.svg('x').replace(/<path[^>]*>/, '<path d="M4 20h4L19 9l-4-4L4 16zM13 7l4 4"/>') + '</button>' +
        (e.status !== 'cancelled' ? '<button class="icon-btn sm" data-act="cancel" aria-label="Cancel ' + esc(e.title) + '" title="Cancel event">' + SH.svg('x') + '</button>' : '') +
        '<button class="icon-btn sm danger" data-act="delete" aria-label="Delete ' + esc(e.title) + '" title="Delete">' + SH.svg('x').replace(/<path[^>]*>/, '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>') + '</button></div></td></tr>';
    });
    body.innerHTML = h + '</tbody></table></div>';
    pager.hidden = false;
    pager.innerHTML = '<p class="pager-count">' + meta.total + ' event' + (meta.total === 1 ? '' : 's') + ' · page ' + meta.page + ' of ' + meta.pages + '</p><div class="pager-links"><button class="pg" data-page="' + (meta.page - 1) + '"' + (meta.page <= 1 ? ' disabled' : '') + ' aria-label="Previous page">‹</button><button class="pg" data-page="' + (meta.page + 1) + '"' + (meta.page >= meta.pages ? ' disabled' : '') + ' aria-label="Next page">›</button></div>';
  }

  /* filters */
  var qEl = document.getElementById('live-q');
  qEl.addEventListener('input', SH.debounce(function () { S.q = qEl.value.trim(); S.page = 1; load(); }, 300));
  document.getElementById('live-cat').addEventListener('change', function (e) { S.category = e.target.value; S.page = 1; load(); });
  document.getElementById('live-state').addEventListener('click', function (e) {
    var b = e.target.closest('[data-state]'); if (!b) return; S.state = b.dataset.state; S.page = 1;
    Array.prototype.forEach.call(this.children, function (c) { c.classList.toggle('on', c === b); }); load();
  });
  document.getElementById('live-filters').addEventListener('submit', function (e) { e.preventDefault(); });
  pager.addEventListener('click', function (e) { var b = e.target.closest('[data-page]'); if (b && !b.disabled) { S.page = +b.dataset.page; load(); } });
  body.addEventListener('click', function (e) {
    if (e.target.closest('#live-retry')) return load();
    var b = e.target.closest('[data-act]'); if (!b) return;
    var ev = cache[b.closest('tr').dataset.id], act = b.dataset.act;
    if (act === 'edit') openForm(ev);
    if (act === 'cancel') SH.confirm('Cancel “' + ev.title + '”?', 'The event stays listed as cancelled and students can no longer register.', 'Cancel event').then(function (y) {
      if (y) call('PATCH', 'api/events.php?id=' + ev.event_id, { status: 'cancelled' }).then(function (r) { done(r, 'Event cancelled.'); });
    });
    if (act === 'delete') SH.confirm('Delete “' + ev.title + '”?', ev.registered_count + ' registration(s) will be removed with it. This cannot be undone.', 'Delete event').then(function (y) {
      if (y) call('DELETE', 'api/events.php?id=' + ev.event_id).then(function (r) { done(r, 'Event deleted.'); });
    });
  });
  function done(r, msg) {
    if (r.ok) { SH.toast('success', msg); load(); } else SH.toast('danger', (r.data && r.data.error && r.data.error.message) || 'Request failed.');
  }

  /* dialog form */
  function clearErrors() {
    alertEl.hidden = true; Array.prototype.forEach.call(form.querySelectorAll('.field'), function (f) { f.classList.remove('has-error'); var m = f.querySelector('.field-msg'); if (f.dataset.hint !== undefined) m.textContent = f.dataset.hint; });
  }
  Array.prototype.forEach.call(form.querySelectorAll('.field'), function (f) { f.dataset.hint = f.querySelector('.field-msg').textContent; });
  function openForm(ev) {
    form.reset(); clearErrors(); idEl.value = ev ? ev.event_id : '';
    document.getElementById('ld-title').textContent = ev ? 'Edit event' : 'Add event';
    var d = ev ? { title: ev.title, category_id: ev.category_id, status: ev.status, starts_at: ev.starts_at.slice(0, 16).replace(' ', 'T'), ends_at: ev.ends_at ? ev.ends_at.slice(0, 16).replace(' ', 'T') : '', location: ev.location, capacity: ev.capacity, description: ev.description } : { status: 'published', capacity: 50 };
    Object.keys(F).forEach(function (k) { document.getElementById(F[k]).value = d[k] == null ? '' : d[k]; });
    dlg.showModal(); document.getElementById(F.title).focus();
  }
  document.getElementById('live-add').addEventListener('click', function () { openForm(null); });
  dlg.addEventListener('click', function (e) { if (e.target.closest('[data-dialog-close]')) dlg.close(); });
  form.addEventListener('submit', function (e) {
    e.preventDefault(); clearErrors();
    var payload = {}; Object.keys(F).forEach(function (k) { payload[k] = document.getElementById(F[k]).value; });
    var id = idEl.value; saveBtn.classList.add('is-loading');
    call(id ? 'PUT' : 'POST', 'api/events.php' + (id ? '?id=' + id : ''), payload).then(function (r) {
      saveBtn.classList.remove('is-loading');
      if (r.ok) { dlg.close(); SH.toast('success', id ? 'Event updated.' : 'Event created.'); if (!id) { S.page = 1; } load(); return; }
      var err = (r.data && r.data.error) || {}; alertEl.textContent = err.message || 'Something went wrong.'; alertEl.hidden = false;
      Object.keys(err.fields || {}).forEach(function (k) { var el = document.getElementById(F[k]); if (!el) return; var f = el.closest('.field'); f.classList.add('has-error'); f.querySelector('.field-msg').textContent = err.fields[k]; });
    }).catch(function () { saveBtn.classList.remove('is-loading'); alertEl.textContent = 'The server could not be reached. Try again.'; alertEl.hidden = false; });
  });
  load();
})();
