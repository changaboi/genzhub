<?php
declare(strict_types=1);
/** Practical 7 (intermediate): show the stored CSV / JSON records on a web page. Admin only. */
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/contact_lib.php';

require_login('admin');
$format = ($_GET['source'] ?? 'json') === 'csv' ? 'csv' : 'json';

if (($_GET['download'] ?? '') !== '') {
    $file = $_GET['download'] === 'csv' ? MSG_CSV : MSG_JSON;
    if (!is_file($file)) { http_response_code(404); exit('Nothing stored yet.'); }
    header('Content-Type: ' . ($_GET['download'] === 'csv' ? 'text/csv' : 'application/json') . '; charset=utf-8');
    header('Content-Disposition: attachment; filename="submissions.' . ($_GET['download'] === 'csv' ? 'csv' : 'json') . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($file);
    exit;
}

$rows = read_messages($format);
$actions = '<a class="btn btn-ghost btn-sm" href="?download=csv">' . icon('download') . 'CSV</a><a class="btn btn-ghost btn-sm" href="?download=json">' . icon('download') . 'JSON</a>';
render_header('Messages', ['active' => 'messages', 'subtitle' => 'Contact-form submissions saved by Practical 7.', 'actions' => $actions]);
?>
<div class="card card-flush">
  <div class="toolbar">
    <div class="tabs" role="group" aria-label="Storage source">
      <a href="?source=json" class="<?= $format === 'json' ? 'on' : '' ?>">Read from JSON</a>
      <a href="?source=csv" class="<?= $format === 'csv' ? 'on' : '' ?>">Read from CSV</a>
    </div>
    <span class="muted"><?= count($rows) ?> message<?= count($rows) === 1 ? '' : 's' ?></span>
  </div>
  <?php if (!$rows): ?>
    <div class="empty"><?= icon('mail') ?><h3>No messages yet</h3><p>Submissions from the contact form appear here.</p></div>
  <?php else: ?>
    <div class="table-wrap"><table class="data">
      <thead><tr><th scope="col">Received</th><th scope="col">From</th><th scope="col">Subject</th><th scope="col">Message</th><th scope="col">Ref</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="nowrap"><?= h(fmt_dt($r['submitted_at'] ?? null, 'j M Y, g:i A')) ?></td>
          <td><div class="cell-person"><?= avatar($r['name'] ?? '?', 'sm') ?><div><strong><?= h($r['name'] ?? '') ?></strong><small><?= h($r['email'] ?? '') ?></small></div></div></td>
          <td><?= h($r['subject'] ?? '') ?></td>
          <td style="max-width:420px"><?= h(mb_strimwidth($r['message'] ?? '', 0, 140, '…')) ?></td>
          <td class="muted"><code><?= h($r['id'] ?? '') ?></code></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php render_footer();
