// Demo-account helper on the login page.
document.addEventListener('click', function (e) {
  var b = e.target.closest('[data-fill]'); if (!b) return;
  var p = b.dataset.fill.split('|');
  document.getElementById('f-email').value = p[0];
  document.getElementById('f-password').value = p[1];
  document.getElementById('f-password').focus();
});
