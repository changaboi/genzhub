<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
$upcoming = q_all(EVENT_SELECT . " WHERE status = 'published' AND starts_at >= NOW() ORDER BY starts_at LIMIT 6");
$hero = array_slice($upcoming, 0, 3);
$stats = [
    'events' => (int) q_val("SELECT COUNT(*) FROM events WHERE status IN ('published','closed')"),
    'students' => (int) q_val("SELECT COUNT(*) FROM students WHERE status = 'active'"),
    'seats' => (int) q_val("SELECT COUNT(*) FROM registrations WHERE status <> 'cancelled'"),
];
$mine = [];
if ($user && $user['role'] === 'student') {
    foreach (q_all('SELECT event_id, status FROM registrations WHERE student_id = ?', [(int) $user['student_id']]) as $r) $mine[(int) $r['event_id']] = $r['status'];
}

render_header('Home', ['active' => 'home', 'layout' => 'public', 'bare' => true, 'scripts' => ['events.js']]);
?>
<section class="hero wrap">
  <div class="hero-grid">
    <div>
      <h1>Find your next campus event. Keep your seat.</h1>
      <p class="lede">Browse workshops, showcases and career sessions, register in one tap, and see exactly how many seats are left before you plan your week.</p>
      <div class="row">
        <a class="btn btn-primary" href="<?= h(url('events.php')) ?>">Browse events</a>
        <?php if ($user): ?>
          <a class="btn btn-ghost" href="<?= h(url(home_for($user['role']))) ?>">Open my dashboard</a>
        <?php else: ?>
          <a class="btn btn-ghost" href="<?= h(url('register.php')) ?>">Create an account</a>
        <?php endif; ?>
      </div>
      <div class="hero-stats">
        <div><strong data-count="<?= $stats['events'] ?>">0</strong><span>events run this term</span></div>
        <div><strong data-count="<?= $stats['students'] ?>">0</strong><span>active students</span></div>
        <div><strong data-count="<?= $stats['seats'] ?>">0</strong><span>seats booked</span></div>
      </div>
    </div>
    <div class="fan" aria-label="Upcoming events">
      <?php foreach ($hero as $e): ?><?= render_ticket($e, $user, $mine[(int) $e['event_id']] ?? null) ?><?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section wrap" aria-labelledby="how">
  <div class="section-head"><h2 id="how">How it works</h2></div>
  <div class="steps">
    <div class="card"><h3>Create an account</h3><p class="muted">Sign up with your college email and pick your course. Your password is hashed before it is stored.</p></div>
    <div class="card"><h3>Pick an event</h3><p class="muted">Filter by category, check the date and venue, and watch the seat counter on each ticket.</p></div>
    <div class="card"><h3>Show up</h3><p class="muted">Your tickets live on your dashboard. Cancel in one click if plans change and someone else gets the seat.</p></div>
  </div>
</section>

<section class="section wrap" aria-labelledby="soon">
  <div class="section-head"><h2 id="soon">Coming up</h2><a class="btn btn-ghost btn-sm" href="<?= h(url('events.php')) ?>">See all events</a></div>
  <div class="tickets">
    <?php foreach ($upcoming as $e): ?><?= render_ticket($e, $user, $mine[(int) $e['event_id']] ?? null) ?><?php endforeach; ?>
  </div>
</section>
<?php render_footer();
