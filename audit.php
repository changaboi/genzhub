<?php
declare(strict_types=1);
/** Practical 14: audit trail of important actions (login, registrations, student/event changes). Admin only. */
require __DIR__ . '/includes/bootstrap.php';

require_login('admin');
$action = clean($_GET['action'] ?? '', 40);
$q = clean($_GET['q'] ?? '', 80);
$where = ['1=1']; $params = [];
if ($action !== '') { $where[] = 'a.action LIKE ?'; $params[] = addcslashes($action, '%_\\') . '%'; }
if ($q !== '') { $where[] = '(u.email LIKE ? OR a.details LIKE ? OR a.entity_type LIKE ?)'; $like = '%' . addcslashes($q, '%_\\') . '%'; array_push($params, $like, $like, $like); }
$W = implode(' AND ', $where);
$total = (int) q_val("SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON u.user_id = a.user_id WHERE $W", $params);
$p = paginate($total, (int) ($_GET['page'] ?? 1), 15);
$rows = q_all("SELECT a.*, u.email FROM audit_logs a LEFT JOIN users u ON u.user_id = a.user_id WHERE $W ORDER BY a.audit_id DESC LIMIT {$p['per']} OFFSET {$p['offset']}", $params);
$groups = ['' => 'All actions', 'login' => 'Sign-ins', 'user.' => 'Accounts', 'student.' => 'Students', 'event.' => 'Events', 'registration.' => 'Registrations', 'access.' => 'Access denied'];
$tone = static fn(string $a): string => str_contains($a, 'failed') || str_contains($a, 'denied') || str_contains($a, 'locked') || str_contains($a, 'deleted') || str_contains($a, 'purged') ? 'bad' : (str_contains($a, 'created') || $a === 'login' || str_contains($a, 'registered') ? 'ok' : 'info');
$query = array_filter(['q' => $q, 'action' => $action]);

render_header('Audit log', ['active' => 'audit', 'subtitle' => $total . ' recorded action' . ($total === 1 ? '' : 's') . '. Entries cannot be edited from the app.']);
?>
<div class="card card-flush">
  <form method="get" class="toolbar" data-autosubmit role="search">
    <div class="search"><?= icon('search') ?><input type="search" name="q" value="<?= h($q) ?>" placeholder="Search user, entity or details" aria-label="Search audit log"></div>
    <select name="action" aria-label="Action group"><?php foreach ($groups as $k => $l): ?><option value="<?= h($k) ?>"<?= $action === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select>
  </form>
  <?php if (!$rows): ?><div class="empty"><?= icon('log') ?><h3>No entries match</h3></div><?php else: ?>
  <div class="table-wrap"><table class="data">
    <thead><tr><th scope="col">When</th><th scope="col">Who</th><th scope="col">Action</th><th scope="col">Target</th><th scope="col">Details</th><th scope="col">IP</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td class="nowrap muted"><?= h(fmt_dt($r['created_at'], 'j M Y, g:i:s A')) ?></td>
        <td><?= h($r['email'] ?? 'system / guest') ?></td>
        <td><span class="badge badge-<?= $tone($r['action']) ?>"><?= h($r['action']) ?></span></td>
        <td class="nowrap"><?= h($r['entity_type']) ?><?= $r['entity_id'] ? ' #' . (int) $r['entity_id'] : '' ?></td>
        <td style="max-width:340px"><?php if ($r['details']): ?><code style="word-break:break-word"><?= h(mb_strimwidth($r['details'], 0, 160, '…')) ?></code><?php else: ?><span class="muted">—</span><?php endif; ?></td>
        <td class="muted"><?= h($r['ip_address'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= pager($p, $query) ?>
  <?php endif; ?>
</div>
<?php render_footer();
