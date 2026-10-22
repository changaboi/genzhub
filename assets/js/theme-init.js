// Runs before first paint so the saved theme never flashes.
(function () {
  try {
    var t = localStorage.getItem('sh-theme');
    if (t === 'dark' || t === 'light') document.documentElement.setAttribute('data-theme', t);
  } catch (e) {}
})();
