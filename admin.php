<?php
declare(strict_types=1);
/**
 * Practical 14 — Admin dashboard (admin only).
 * Numbers come from the stored procedure sp_dashboard_stats (called through PDO); charts from SQL aggregation.
 */
require __DIR__ . '/includes/bootstrap.php';

$admin = require_login('admin');

$stmt = pdo()->query('CALL sp_dashboard_stats()');
$stats = $stmt->fetch();
$stmt->closeCursor();

// registrations per month, last 6 months (months with no rows are filled with 0)
$byMonth = [];
for ($i = 5; $i >= 0; $i--) {
    $byMonth[date('Y-m', strtotime("first day of -$i month"))] = 0;
}
foreach (q_all("SELECT DATE_FORMAT(registered_at, '%Y-%m') AS ym, COUNT(*) AS n FROM registrations
                WHERE status <> 'cancelled' AND registered_at >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 5 MONTH), '%Y-%m-01')
                GROUP BY ym") as $r) {
    if (isset($byMonth[$r['ym']])) $byMonth[$r['ym']] = (int) $r['n'];
}
$monthChart = ['type' => 'bars', 'title' => 'Registrations by month', 'labels' => array_map(static fn($ym) => date('M', strtotime($ym . '-01')), array_keys($byMonth)), 'values' => array_values($byMonth)];

$byCourse = q_all("SELECT c.name, COUNT(r.registration_id) AS n FROM courses c
    LEFT JOIN students s ON s.course_id = c.course_id
    LEFT JOIN registrations r ON r.student_id = s.student_id AND r.status <> 'cancelled'
    GROUP BY c.course_id, c.name ORDER BY n DESC");
$courseChart = ['type' => 'donut', 'title' => 'Registrations by course', 'center' => 'seats booked', 'labels' => array_column($byCourse, 'name'), 'values' => array_map('intval', array_column($byCourse, 'n'))];

$latest = q_all("SELECT s.full_name, c.code, e.title, r.registered_at, r.status FROM registrations r
    JOIN students s ON s.student_id = r.student_id JOIN courses c ON c.course_id = s.course_id JOIN events e ON e.event_id = r.event_id
    ORDER BY r.registered_at DESC, r.registration_id DESC LIMIT 7");
$fill = q_all("SELECT title, registered_count, capacity FROM v_event_summary WHERE status = 'published' AND starts_at >= NOW() ORDER BY registered_count / capacity DESC LIMIT 5");
$audit = q_all("SELECT a.action, a.entity_type, a.entity_id, a.created_at, u.email FROM audit_logs a LEFT JOIN users u ON u.user_id = a.user_id ORDER BY a.audit_id DESC LIMIT 6");

render_header('Dashboard', ['active' => 'admin', 'subtitle' => 'Live numbers from the database · ' . date('l, j F'),
    'actions' => '<a class="btn btn-ghost" href="' . h(url('students.php?export=csv')) . '">' . icon('download') . 'Export students</a><a class="btn btn-primary" href="' . h(url('manage-events.php')) . '">' . icon('plus') . 'New event</a>']);
?>
<div class="stack">
  <div class="grid g-stats">
    <div class="card stat hi"><span class="label"><?= icon('users') ?>Students</span><span class="num" data-count="<?= (int) $stats['students'] ?>">0</span><span class="sub"><?= (int) $stats['archived_students'] ?> archived</span><span class="spark" data-chart='<?= h(json_encode(['type' => 'spark', 'values' => array_values($byMonth)])) ?>'></span></div>
    <div class="card stat"><span class="label"><?= icon('ticket') ?>Events</span><span class="num" data-count="<?= (int) $stats['events'] ?>">0</span><span class="sub"><?= (int) $stats['open_events'] ?> open for registration</span></div>
    <div class="card stat"><span class="label"><?= icon('check') ?>Registrations</span><span class="num" data-count="<?= (int) $stats['registrations'] ?>">0</span><span class="sub">active seats across all events</span></div>
    <div class="card stat"><span class="label"><?= icon('shield') ?>Accounts</span><span class="num" data-count="<?= (int) $stats['active_users'] ?>">0</span><span class="sub">of <?= (int) $stats['total_users'] ?> total are active</span></div>
  </div>

  <div class="grid g-main">
    <section class="card"><div class="card-head"><h2>Registrations by month</h2><span class="muted">last 6 months</span></div><div class="chart" data-chart='<?= h(json_encode($monthChart)) ?>' role="group"></div></section>
    <section class="card"><div class="card-head"><h2>By course</h2></div><div class="chart" data-chart='<?= h(json_encode($courseChart)) ?>'></div></section>
  </div>

  <div class="grid g-2">
    <section class="card">
      <div class="card-head"><h2>Latest registrations</h2></div>
      <?php if (!$latest): ?><p class="muted">No registrations yet.</p><?php else: ?>
      <ul class="list">
        <?php foreach ($latest as $r): ?>
          <li><?= avatar($r['full_name'], 'sm') ?><div class="grow"><strong><?= h($r['full_name']) ?></strong> <span class="muted">· <?= h($r['code']) ?></span><small><?= h($r['title']) ?></small></div><div class="right"><?= badge($r['status']) ?><small><?= h(fmt_dt($r['registered_at'], 'j M, g:i A')) ?></small></div></li>
        <?php endforeach; ?>
      </ul><?php endif; ?>
    </section>
    <div class="stack">
      <section class="card">
        <div class="card-head"><h2>Filling up fastest</h2></div>
        <ul class="list">
          <?php foreach ($fill as $e): $pct = (int) round($e['registered_count'] / max(1, $e['capacity']) * 100); ?>
            <li><div class="grow"><strong><?= h($e['title']) ?></strong><div class="bar <?= $pct >= 90 ? 'hot' : ($pct >= 65 ? 'warm' : '') ?>" style="margin-top:7px"><i data-bar="<?= min(100, $pct) ?>"></i></div></div><strong><?= $pct ?>%</strong></li>
          <?php endforeach; ?>
        </ul>
      </section>
      <section class="card">
        <div class="card-head"><h2>Recent admin activity</h2><a class="btn btn-ghost btn-sm" href="<?= h(url('audit.php')) ?>">Full audit log</a></div>
        <ol class="timeline">
          <?php foreach ($audit as $a): ?>
            <li><code><?= h($a['action']) ?></code> <span class="muted"><?= h($a['entity_type']) ?><?= $a['entity_id'] ? ' #' . (int) $a['entity_id'] : '' ?></span><div class="when"><?= h($a['email'] ?? 'system') ?> · <?= h(fmt_dt($a['created_at'], 'j M, g:i A')) ?></div></li>
          <?php endforeach; ?>
        </ol>
      </section>
    </div>
  </div>
</div>
<?php render_footer();
