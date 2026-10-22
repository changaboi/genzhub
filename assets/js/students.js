// Add / edit dialog for students.php. The same form is posted for both modes.
(function () {
  var dlg = document.getElementById('student-dialog'); if (!dlg) return;
  var form = document.getElementById('student-form'), title = document.getElementById('sd-title'), id = document.getElementById('sd-id');
  function reset() {
    form.reset(); $$('.has-error', form).forEach(function (f) { f.classList.remove('has-error'); var m = f.querySelector('.field-msg'); if (f.dataset.hint) m.textContent = f.dataset.hint; });
  }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function mode(edit) {
    title.textContent = edit ? 'Edit student' : 'Add student';
    var hint = form.querySelector('#f-password-msg'); hint.textContent = edit ? 'Leave blank to keep the current password.' : 'Leave blank to generate a temporary password.';
    hint.closest('.field').dataset.hint = hint.textContent;
  }
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-student-add]')) { reset(); id.value = 0; mode(false); form.elements.semester.value = 1; dlg.showModal(); form.elements.full_name.focus(); }
    var ed = e.target.closest('[data-student-edit]');
    if (ed) {
      var d = JSON.parse(ed.dataset.studentEdit); reset(); mode(true); id.value = d.student_id;
      ['full_name', 'email', 'course_id', 'enrollment_no', 'semester', 'phone'].forEach(function (k) { form.elements[k].value = d[k] == null ? '' : d[k]; });
      dlg.showModal(); form.elements.full_name.focus();
    }
    if (e.target.closest('[data-dialog-close]')) dlg.close();
  });
  if (dlg.hasAttribute('data-autoopen')) { mode(parseInt(id.value, 10) > 0); dlg.showModal(); }
})();
