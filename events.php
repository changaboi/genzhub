<?php
declare(strict_types=1);
/** Event browser: category chips, open/closed filter, debounced search, one-tap registration. */
require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
$q = clean($_GET['q'] ?? '', 80);
$cat = (int) ($_GET['category'] ?? 0);
$state = in_array($_GET['state'] ?? '', ['open', 'closed', 'all'], true) ? $_GET['state'] : 'open';
[$where, $params] = event_filter(['q' => $q, 'category' => $cat, 'state' => $state], false);
$where .= " AND status <> 'cancelled'";
$rows = q_all(EVENT_SELECT . " WHERE $where ORDER BY " . ($state === 'closed' ? 'starts_at DESC' : 'starts_at ASC'), $params);
$cats = q_all('SELECT category_id, name FROM event_categories ORDER BY name');

$mine = [];
if ($user && $user['role'] === 'student') {
    foreach (q_all('SELECT event_id, status FROM registrations WHERE student_id = ?', [(int) $user['student_id']]) as $r) $mine[(int) $r['event_id']] = $r['status'];
}
$qs = static fn(array $over) => '?' . http_build_query(array_filter(array_merge(['q' => $q, 'category' => $cat ?: null, 'state' => $state], $over), static fn($v) => $v !== null && $v !== ''));

render_header('Events', ['active' => 'events', 'subtitle' => 'Workshops, showcases and sessions across campus.', 'scripts' => ['events.js']]);
?>
<?php if ($user): ?><div class="stack"><?php else: ?><div class="wrap stack"><?php endif; ?>
  <form method="get" class="row" data-autosubmit role="search">
    <div class="search" style="flex:1 1 260px;max-width:420px">
      <?= icon('search') ?>
      <input type="search" name="q" value="<?= h($q) ?>" placeholder="Search title, venue or category"  aria-label="Search events">
    </div>
    <input type="hidden" name="state" value="<?= h($state) ?>">
    <?php if ($cat): ?><input type="hidden" name="category" value="<?= $cat ?>"><?php endif; ?>
    <div class="tabs" role="group" aria-label="Event state">
      <?php foreach (['open' => 'Open', 'closed' => 'Closed', 'all' => 'All'] as $k => $label): ?>
        <a href="<?= h($qs(['state' => $k])) ?>" class="<?= $state === $k ? 'on' : '' ?>"<?= $state === $k ? ' aria-current="true"' : '' ?>><?= $label ?></a>
      <?php endforeach; ?>
    </div>
  </form>
  <div class="chips" aria-label="Categories">
    <a class="chip<?= $cat === 0 ? ' on' : '' ?>" href="<?= h($qs(['category' => null])) ?>">All categories</a>
    <?php foreach ($cats as $c): ?><a class="chip<?= $cat === (int) $c['category_id'] ? ' on' : '' ?>" href="<?= h($qs(['category' => $c['category_id']])) ?>"><?= h($c['name']) ?></a><?php endforeach; ?>
  </div>
  <?php if (!$rows): ?>
    <div class="card empty"><?= icon('ticket') ?><h3>No events match</h3><p>Try another category or switch to “All” to include past events.</p><a class="btn btn-ghost btn-sm" href="<?= h(url('events.php?state=all')) ?>">Show all events</a></div>
  <?php else: ?>
    <div class="tickets">
      <?php foreach ($rows as $e): ?><?= render_ticket($e, $user, $mine[(int) $e['event_id']] ?? null) ?><?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php render_footer();
