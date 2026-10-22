<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
if ($user['role'] === 'admin') redirect('admin.php');
$sid = (int) $user['student_id'];

$profile = q_one('SELECT s.enrollment_no, s.semester, s.phone, c.name AS course FROM students s JOIN courses c ON c.course_id = s.course_id WHERE s.student_id = ?', [$sid]);
$mine = q_all("SELECT v.*, r.status AS my_status, r.registered_at FROM registrations r JOIN v_event_summary v ON v.event_id = r.event_id
               WHERE r.student_id = ? AND r.status <> 'cancelled' ORDER BY v.starts_at DESC", [$sid]);
$upcoming = array_values(array_filter($mine, static fn($e) => strtotime($e['starts_at']) >= time()));
$attended = count(array_filter($mine, static fn($e) => $e['my_status'] === 'attended'));
$myIds = array_column($mine, 'event_id');
$suggest = array_values(array_filter(
    q_all(EVENT_SELECT . " WHERE status = 'published' AND starts_at >= NOW() AND seats_left > 0 ORDER BY starts_at LIMIT 8"),
    static fn($e) => !in_array($e['event_id'], $myIds, false)
));
$suggest = array_slice($suggest, 0, 3);
$hour = (int) date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$first = explode(' ', (string) $user['full_name'])[0];

render_header($greet . ', ' . $first, ['active' => 'dashboard', 'subtitle' => ($profile['course'] ?? '') . ' · Semester ' . ($profile['semester'] ?? '-'), 'scripts' => ['events.js'],
    'actions' => '<a class="btn btn-primary" href="' . h(url('events.php')) . '">' . icon('ticket') . 'Browse events</a>']);
?>
<div class="stack">
  <div class="grid g-stats">
    <div class="card stat hi"><span class="label"><?= icon('ticket') ?>Upcoming tickets</span><span class="num" data-count="<?= count($upcoming) ?>">0</span><span class="sub">events you are registered for</span></div>
    <div class="card stat"><span class="label"><?= icon('check') ?>Events attended</span><span class="num" data-count="<?= $attended ?>">0</span><span class="sub">marked attended by staff</span></div>
    <div class="card stat"><span class="label"><?= icon('calendar') ?>Open to join</span><span class="num" data-count="<?= count($suggest) ?>">0</span><span class="sub">upcoming events with free seats</span></div>
    <div class="card stat"><span class="label"><?= icon('clock') ?>Last sign-in</span><span class="num" style="font-size:1.25rem;padding-top:8px"><?= h($user['last_login_at'] ? fmt_dt($user['last_login_at'], 'j M, g:i A') : 'First visit') ?></span><span class="sub">from this account</span></div>
  </div>

  <section aria-labelledby="mt">
    <div class="section-head"><h2 id="mt">My tickets</h2></div>
    <?php if (!$mine): ?>
      <div class="card empty"><?= icon('ticket') ?><h3>No tickets yet</h3><p>Pick an event and your ticket will show up here.</p><a class="btn btn-accent btn-sm" href="<?= h(url('events.php')) ?>">Find an event</a></div>
    <?php else: ?>
      <div class="tickets" data-reload-on-change><?php foreach ($mine as $e): ?><?= render_ticket($e, $user, $e['my_status']) ?><?php endforeach; ?></div>
    <?php endif; ?>
  </section>

  <?php if ($suggest): ?>
  <section aria-labelledby="sg">
    <div class="section-head"><h2 id="sg">Seats still open</h2></div>
    <div class="tickets" data-reload-on-change><?php foreach ($suggest as $e): ?><?= render_ticket($e, $user, null) ?><?php endforeach; ?></div>
  </section>
  <?php endif; ?>
</div>
<?php render_footer();
