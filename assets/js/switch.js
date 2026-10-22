// Submits the surrounding form when an account switch is flipped (confirm dialog handled in app.js).
document.addEventListener('change', function (e) {
  var s = e.target.closest('[data-switch-submit]'); if (!s) return;
  var f = s.closest('form'); s.checked = !s.checked;           // keep the visual state until the server confirms
  f.requestSubmit();
});
