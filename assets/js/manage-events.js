// Add / edit dialog for manage-events.php (multipart form, poster preview).
(function () {
  var dlg = document.getElementById('event-dialog'); if (!dlg) return;
  var form = document.getElementById('event-form'), title = document.getElementById('ed-title'), id = document.getElementById('ed-id');
  var prev = dlg.querySelector('.dz-preview'), blank = prev.innerHTML, rm = document.getElementById('remove-poster-row');
  function reset() {
    form.reset(); prev.innerHTML = blank; rm.hidden = true; dlg.querySelector('.dz-error').textContent = '';
    Array.prototype.forEach.call(form.querySelectorAll('.has-error'), function (f) { f.classList.remove('has-error'); var m = f.querySelector('.field-msg'); if (f.dataset.hint) m.textContent = f.dataset.hint; });
  }
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-event-add]')) { reset(); id.value = 0; title.textContent = 'Add event'; form.elements.capacity.value = 50; form.elements.status.value = 'published'; dlg.showModal(); form.elements.title.focus(); }
    var ed = e.target.closest('[data-event-edit]');
    if (ed) {
      var d = JSON.parse(ed.dataset.eventEdit); reset(); id.value = d.event_id; title.textContent = 'Edit event';
      ['title', 'category_id', 'description', 'location', 'starts_at', 'ends_at', 'capacity', 'status'].forEach(function (k) { form.elements[k].value = d[k] == null ? '' : d[k]; });
      if (d.poster) { prev.innerHTML = '<img alt="Current poster" src="' + d.poster + '"><span>Current poster. Drop a new image to replace it.</span>'; rm.hidden = false; }
      dlg.showModal(); form.elements.title.focus();
    }
    if (e.target.closest('[data-dialog-close]')) dlg.close();
  });
  if (dlg.hasAttribute('data-autoopen')) dlg.showModal();
})();
