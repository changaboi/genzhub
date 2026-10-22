<?php
declare(strict_types=1);
/**
 * Practical 8 — connection test. Shows that PDO and MySQLi both connect, that prepared statements
 * work, that the schema is present (row counts), and that the stored procedure runs.
 * No credentials or host names are printed.
 */
require __DIR__ . '/includes/bootstrap.php';

$checks = [];
$run = static function (string $label, callable $fn) use (&$checks): void {
    try {
        $checks[] = ['label' => $label, 'ok' => true, 'detail' => (string) $fn()];
    } catch (Throwable $e) {
        error_log('test_connection: ' . $e->getMessage());
        $checks[] = ['label' => $label, 'ok' => false, 'detail' => 'Failed. Check that MySQL is running and database/studenthub.sql is imported.'];
    }
};
$run('PDO connection', static fn() => 'Connected · server ' . pdo()->getAttribute(PDO::ATTR_SERVER_VERSION));
$run('MySQLi connection', static fn() => 'Connected · charset ' . db()->character_set_name());
$run('PDO prepared statement', static function () {
    $st = pdo()->prepare('SELECT full_name, email FROM students s JOIN users u ON u.user_id = s.user_id WHERE s.student_id = :id');
    $st->execute([':id' => 1]);
    $r = $st->fetch();
    return 'Bound :id = 1 → ' . ($r['full_name'] ?? 'no row');
});
$run('MySQLi prepared statement', static fn() => 'Bound ? = "admin" → ' . q_val('SELECT COUNT(*) FROM users WHERE role = ?', ['admin']) . ' admin account(s)');
$run('Schema tables', static function () {
    $out = [];
    foreach (['users', 'students', 'courses', 'events', 'event_categories', 'registrations', 'remember_tokens', 'login_attempts', 'audit_logs'] as $t) {
        $out[] = $t . ' ' . q_val("SELECT COUNT(*) FROM `$t`");
    }
    return implode(' · ', $out);
});
$run('Foreign keys', static fn() => q_val("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()") . ' constraints enforced');
$run('Stored procedure sp_dashboard_stats', static function () {
    $st = pdo()->query('CALL sp_dashboard_stats()');
    $r = $st->fetch();
    $st->closeCursor();
    return $r['students'] . ' students, ' . $r['events'] . ' events, ' . $r['registrations'] . ' registrations';
});
$allOk = !in_array(false, array_column($checks, 'ok'), true);

render_header('Connection test', ['layout' => 'public', 'active' => '', 'subtitle' => 'Practical 8 · PDO, MySQLi, prepared statements and the stored procedure.']);
?>
<div class="wrap stack" style="max-width:820px">
  <div class="alert alert-<?= $allOk ? 'success' : 'danger' ?>" role="status"><?= $allOk ? 'All checks passed. The database layer is working.' : 'One or more checks failed.' ?></div>
  <div class="card card-flush"><ul class="list" style="padding:0 22px">
    <?php foreach ($checks as $c): ?>
      <li><span class="badge badge-<?= $c['ok'] ? 'ok' : 'bad' ?>"><?= $c['ok'] ? 'Pass' : 'Fail' ?></span><div class="grow"><strong><?= h($c['label']) ?></strong><small><?= h($c['detail']) ?></small></div></li>
    <?php endforeach; ?>
  </ul></div>
</div>
<?php render_footer();
