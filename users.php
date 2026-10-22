<?php
declare(strict_types=1);
/** Practical 14 (intermediate): active / inactive toggle for user accounts. Admin only. */
require __DIR__ . '/includes/bootstrap.php';

$admin = require_login('admin');
$q = clean($_GET['q'] ?? '', 80);
$role = in_array($_GET['role'] ?? '', ['admin', 'student'], true) ? $_GET['role'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $uid = (int) ($_POST['user_id'] ?? 0);
    $to = ($_POST['to'] ?? '') === 'active' ? 'active' : 'inactive';
    $target = q_one('SELECT user_id, email, role, status FROM users WHERE user_id = ?', [$uid]);
    if (!$target) {
        flash('danger', 'That user no longer exists.');
    } elseif ($uid === (int) $admin['user_id'] && $to === 'inactive') {
        flash('warning', 'You cannot deactivate your own account.');
    } elseif ($target['role'] === 'admin' && $to === 'inactive' && (int) q_val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'") <= 1) {
        flash('warning', 'Keep at least one active administrator.');
    } else {
        q('UPDATE users SET status = ? WHERE user_id = ?', [$to, $uid]);
        if ($to === 'inactive') q('DELETE FROM remember_tokens WHERE user_id = ?', [$uid]);   // existing sessions end on their next request
        audit('user.' . ($to === 'active' ? 'activated' : 'deactivated'), 'user', $uid, ['email' => $target['email']]);
        flash('success', $target['email'] . ' is now ' . $to . '.');
    }
    redirect('users.php' . ($_GET ? '?' . http_build_query($_GET) : ''));
}

$where = ['1=1']; $params = [];
if ($q !== '') { $where[] = '(u.email LIKE ? OR s.full_name LIKE ?)'; $like = '%' . addcslashes($q, '%_\\') . '%'; array_push($params, $like, $like); }
if ($role !== '') { $where[] = 'u.role = ?'; $params[] = $role; }
$W = implode(' AND ', $where);
$total = (int) q_val("SELECT COUNT(*) FROM users u LEFT JOIN students s ON s.user_id = u.user_id WHERE $W", $params);
$p = paginate($total, (int) ($_GET['page'] ?? 1), 10);
$rows = q_all("SELECT u.user_id, u.email, u.role, u.status, u.last_login_at, u.created_at, s.full_name FROM users u LEFT JOIN students s ON s.user_id = u.user_id WHERE $W ORDER BY u.role, u.email LIMIT {$p['per']} OFFSET {$p['offset']}", $params);
$query = array_filter(['q' => $q, 'role' => $role]);

render_header('Users', ['active' => 'users', 'subtitle' => 'Switch accounts on or off. Inactive users cannot sign in.']);
?>
<div class="card card-flush">
  <form method="get" class="toolbar" data-autosubmit role="search">
    <div class="search"><?= icon('search') ?><input type="search" name="q" value="<?= h($q) ?>" placeholder="Search name or email" aria-label="Search users"></div>
    <select name="role" aria-label="Role"><option value="">All roles</option><option value="admin"<?= $role === 'admin' ? ' selected' : '' ?>>Admins</option><option value="student"<?= $role === 'student' ? ' selected' : '' ?>>Students</option></select>
  </form>
  <?php if (!$rows): ?><div class="empty"><?= icon('users') ?><h3>No users found</h3></div><?php else: ?>
  <div class="table-wrap"><table class="data">
    <thead><tr><th scope="col">User</th><th scope="col">Role</th><th scope="col">Last sign-in</th><th scope="col">Created</th><th scope="col">Active</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $self = (int) $r['user_id'] === (int) $admin['user_id']; ?>
      <tr>
        <td><div class="cell-person"><?= avatar($r['full_name'] ?? $r['email'], 'sm') ?><div><strong><?= h($r['full_name'] ?? 'Administrator') ?></strong><small><?= h($r['email']) ?></small></div></div></td>
        <td><?= badge($r['role']) ?></td>
        <td class="muted nowrap"><?= h($r['last_login_at'] ? fmt_dt($r['last_login_at'], 'j M Y, g:i A') : 'Never') ?></td>
        <td class="muted nowrap"><?= h(fmt_dt($r['created_at'], 'j M Y')) ?></td>
        <td>
          <form method="post" <?= $r['status'] === 'active' ? 'data-confirm="' . h(($r['full_name'] ?? $r['email']) . ' will no longer be able to sign in.') . '" data-confirm-title="Deactivate this account?" data-confirm-ok="Deactivate"' : '' ?>>
            <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $r['user_id'] ?>"><input type="hidden" name="to" value="<?= $r['status'] === 'active' ? 'inactive' : 'active' ?>">
            <label class="switch" title="<?= $self ? 'You cannot deactivate yourself' : 'Toggle account' ?>"><input type="checkbox" <?= $r['status'] === 'active' ? 'checked' : '' ?> <?= $self ? 'disabled' : '' ?> data-switch-submit aria-label="Account active for <?= h($r['email']) ?>"><span></span></label>
            <noscript><button class="btn btn-ghost btn-sm" type="submit">Apply</button></noscript>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= pager($p, $query) ?>
  <?php endif; ?>
</div>
<script src="<?= h(asset('assets/js/switch.js')) ?>" defer></script>
<?php render_footer();
