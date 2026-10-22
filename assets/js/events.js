// Register / cancel a seat from any ticket without reloading the page.
document.addEventListener('click', function (e) {
  var btn = e.target.closest('[data-reg]');
  if (!btn || !window.SH) return;
  var action = btn.dataset.reg, id = parseInt(btn.dataset.event, 10);
  var run = function () {
    btn.classList.add('is-loading');
    SH.api('api/register.php', { method: 'POST', body: { event_id: id, action: action } }).then(function (r) {
      btn.classList.remove('is-loading');
      if (!r.ok) { SH.toast('danger', (r.data && r.data.error && r.data.error.message) || 'Something went wrong. Try again.'); return; }
      SH.toast('success', r.data.data.message);
      var ev = r.data.data.event, t = btn.closest('.ticket');
      if (btn.closest('[data-reload-on-change]')) { setTimeout(function () { location.reload(); }, 500); return; }
      if (t) {
        t.querySelector('[data-seats]').textContent = ev.seats_left;
        var pct = Math.min(100, Math.round(ev.registered_count / ev.capacity * 100)), bar = t.querySelector('.bar');
        bar.firstElementChild.style.width = pct + '%'; bar.className = 'bar ' + (pct >= 90 ? 'hot' : pct >= 65 ? 'warm' : '');
        var slot = t.querySelector('.stub-action');
        slot.innerHTML = action === 'register'
          ? '<button class="btn btn-ghost btn-sm" type="button" data-reg="cancel" data-event="' + id + '">Cancel seat</button>'
          : (ev.seats_left > 0 ? '<button class="btn btn-accent btn-sm" type="button" data-reg="register" data-event="' + id + '">Register</button>' : '<button class="btn btn-ghost btn-sm" type="button" disabled>Sold out</button>');
      }
    }).catch(function () { btn.classList.remove('is-loading'); SH.toast('danger', 'Network problem. Check your connection and try again.'); });
  };
  if (action === 'cancel') SH.confirm('Cancel your seat?', 'The seat goes back to the pool and you can register again while seats remain.', 'Cancel seat').then(function (y) { if (y) run(); });
  else run();
});
