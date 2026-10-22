<?php
declare(strict_types=1);
/**
 * Practical 12 — Event management with poster upload (admin only).
 * Add / edit / delete, category + open/closed filter, validated uploads
 * (size, real MIME type, image decode, random filename) and an audit entry for every change.
 */
require __DIR__ . '/includes/bootstrap.php';

$admin = require_login('admin');
$cats = q_all('SELECT category_id, name FROM event_categories ORDER BY name');
$catOptions = [];
foreach ($cats as $c) $catOptions[(string) $c['category_id']] = $c['name'];
$statusOptions = ['published' => 'Published (open)', 'draft' => 'Draft (hidden)', 'closed' => 'Closed', 'cancelled' => 'Cancelled'];

$f = ['q' => clean($_GET['q'] ?? '', 80), 'category' => (int) ($_GET['category'] ?? 0) ?: '', 'state' => in_array($_GET['state'] ?? '', ['all', 'open', 'closed', 'draft', 'cancelled'], true) ? $_GET['state'] : 'all'];
$query = array_filter(['q' => $f['q'], 'category' => $f['category'], 'state' => $f['state'] !== 'all' ? $f['state'] : ''], static fn($v) => $v !== '' && $v !== null);

$form = ['event_id' => 0, 'title' => '', 'category_id' => '', 'description' => '', 'location' => '', 'starts_at' => '', 'ends_at' => '', 'capacity' => 50, 'status' => 'published', 'poster_path' => null];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {            // body larger than post_max_size
        flash('danger', 'That upload is larger than the server allows. Use a poster under 2 MB.');
        redirect('manage-events.php');
    }
    require_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['event_id'] ?? 0);
    $back = 'manage-events.php' . ($query ? '?' . http_build_query($query) : '');

    if ($action === 'delete') {
        $ok = delete_event($id);
        flash($ok ? 'success' : 'danger', $ok ? 'Event deleted.' : 'That event no longer exists.');
        redirect($back);
    }
    if ($action === 'save') {
        [$v, $errors] = validate_event($_POST);
        $old = $id ? q_one('SELECT * FROM events WHERE event_id = ?', [$id]) : null;
        if ($id && !$old) { flash('danger', 'That event no longer exists.'); redirect($back); }
        if ($id) {
            $taken = (int) q_val("SELECT COUNT(*) FROM registrations WHERE event_id = ? AND status IN ('registered','attended')", [$id]);
            if (!isset($errors['capacity']) && $v['capacity'] < $taken) $errors['capacity'] = "$taken students are already registered; capacity cannot go below that.";
        }
        $poster = null;
        [$poster, $posterErr] = save_poster($_FILES['poster'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
        if ($posterErr) $errors['poster'] = $posterErr;
        $form = ['event_id' => $id, 'poster_path' => $old['poster_path'] ?? null] + $v;
        if ($errors) {
            remove_poster($poster);                // do not keep an upload from a rejected form
            http_response_code(422);
        } else {
            if ($id) {
                $removeOld = $poster !== null || !empty($_POST['remove_poster']);
                update_event($id, $v, $old, $poster);
                if (!empty($_POST['remove_poster']) && $poster === null) {
                    q('UPDATE events SET poster_path = NULL WHERE event_id = ?', [$id]);
                    audit('event.updated', 'event', $id, ['poster' => 'removed']);
                }
                if ($removeOld) remove_poster($old['poster_path']);
                flash('success', '“' . $v['title'] . '” was updated.');
            } else {
                insert_event($v, $poster, (int) $admin['user_id']);
                flash('success', '“' . $v['title'] . '” was created.');
            }
            redirect($back);
        }
    }
}


[$where, $params] = event_filter($f, true);
$total = (int) q_val("SELECT COUNT(*) FROM v_event_summary WHERE $where", $params);
$p = paginate($total, (int) ($_GET['page'] ?? 1), 8);
$rows = q_all(EVENT_SELECT . " WHERE $where ORDER BY starts_at DESC, event_id DESC LIMIT {$p['per']} OFFSET {$p['offset']}", $params);
$qs = static fn(array $o) => '?' . http_build_query(array_filter(array_merge($query, $o), static fn($v) => $v !== '' && $v !== null && $v !== 'all'));

render_header('Manage events', ['active' => 'manage-events', 'scripts' => ['manage-events.js'], 'subtitle' => $total . ' event' . ($total === 1 ? '' : 's') . ' match your filters.',
    'actions' => '<button class="btn btn-primary" type="button" data-event-add>' . icon('plus') . 'Add event</button>']);
?>
<div class="card card-flush">
  <form method="get" class="toolbar" data-autosubmit role="search">
    <div class="search"><?= icon('search') ?><input type="search" name="q" value="<?= h($f['q']) ?>" placeholder="Search title, venue or category" aria-label="Search events"></div>
    <select name="category" aria-label="Category"><option value="">All categories</option><?php foreach ($catOptions as $id => $n): ?><option value="<?= h($id) ?>"<?= (string) $f['category'] === (string) $id ? ' selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select>
    <input type="hidden" name="state" value="<?= h($f['state']) ?>">
    <div class="tabs" role="group" aria-label="Event state">
      <?php foreach (['all' => 'All', 'open' => 'Open', 'closed' => 'Closed', 'draft' => 'Drafts', 'cancelled' => 'Cancelled'] as $k => $l): ?><a href="<?= h($qs(['state' => $k, 'page' => 1])) ?>" class="<?= $f['state'] === $k ? 'on' : '' ?>"><?= $l ?></a><?php endforeach; ?>
    </div>
  </form>
  <?php if (!$rows): ?>
    <div class="empty"><?= icon('ticket') ?><h3>No events found</h3><p>Change the filters or add a new event.</p></div>
  <?php else: ?>
  <div class="table-wrap"><table class="data">
    <thead><tr><th scope="col">Event</th><th scope="col">Category</th><th scope="col">When</th><th scope="col">Seats</th><th scope="col">Status</th><th scope="col" class="right"><span class="sr">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
        $data = ['event_id' => (int) $r['event_id'], 'title' => $r['title'], 'category_id' => (int) $r['category_id'], 'description' => $r['description'], 'location' => $r['location'],
                 'starts_at' => date('Y-m-d\TH:i', strtotime($r['starts_at'])), 'ends_at' => $r['ends_at'] ? date('Y-m-d\TH:i', strtotime($r['ends_at'])) : '', 'capacity' => (int) $r['capacity'], 'status' => $r['status'], 'poster' => $r['poster_path'] ? url($r['poster_path']) : '']; ?>
      <tr>
        <td><div class="cell-person">
          <span class="thumb"><?php if ($r['poster_path']): ?><img src="<?= h(url($r['poster_path'])) ?>" alt="" width="64" height="40" loading="lazy"><?php else: ?><?= icon('image') ?><?php endif; ?></span>
          <div><strong><?= h($r['title']) ?></strong><small><?= h($r['location']) ?></small></div></div></td>
        <td><?= h($r['category']) ?></td>
        <td class="nowrap"><?= h(fmt_dt($r['starts_at'], 'j M Y, g:i A')) ?></td>
        <td class="nowrap"><?= (int) $r['registered_count'] ?> / <?= (int) $r['capacity'] ?></td>
        <td><?= badge($r['status']) ?></td>
        <td><div class="actions">
          <button class="icon-btn sm" type="button" data-event-edit="<?= h(json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>" aria-label="Edit <?= h($r['title']) ?>" title="Edit"><?= icon('edit') ?></button>
          <form method="post" data-confirm="“<?= h($r['title']) ?>” and its <?= (int) $r['registered_count'] ?> registration(s) will be deleted. This cannot be undone." data-confirm-title="Delete this event?" data-confirm-ok="Delete event"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="event_id" value="<?= (int) $r['event_id'] ?>"><button class="icon-btn sm danger" type="submit" aria-label="Delete <?= h($r['title']) ?>" title="Delete"><?= icon('trash') ?></button></form>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= pager($p, $query) ?>
  <?php endif; ?>
</div>

<dialog id="event-dialog" class="dialog wide" aria-labelledby="ed-title"<?= $errors ? ' data-autoopen' : '' ?>>
  <div class="dialog-in">
    <div class="dialog-head"><h2 id="ed-title"><?= $form['event_id'] ? 'Edit event' : 'Add event' ?></h2><button class="icon-btn sm" type="button" data-dialog-close aria-label="Close"><?= icon('x') ?></button></div>
    <form method="post" enctype="multipart/form-data" id="event-form" data-no-loading><?= csrf_field() ?>
      <input type="hidden" name="action" value="save"><input type="hidden" name="event_id" id="ed-id" value="<?= (int) $form['event_id'] ?>">
      <?php if ($errors): ?><div class="alert alert-danger" role="alert">Fix the highlighted fields and save again.</div><?php endif; ?>
      <div class="form-grid">
        <div class="full"><?= field('title', 'Title', ['value' => $form['title'], 'error' => $errors['title'] ?? null, 'required' => true, 'maxlength' => 150, 'attrs' => ['minlength' => '3']]) ?></div>
        <?= field('category_id', 'Category', ['type' => 'select', 'options' => ['' => 'Choose a category'] + $catOptions, 'value' => (string) $form['category_id'], 'error' => $errors['category_id'] ?? null, 'required' => true]) ?>
        <?= field('status', 'Status', ['type' => 'select', 'options' => $statusOptions, 'value' => $form['status'], 'error' => $errors['status'] ?? null, 'required' => true]) ?>
        <?= field('starts_at', 'Starts', ['type' => 'datetime-local', 'value' => $form['starts_at'] ? date('Y-m-d\TH:i', strtotime((string) $form['starts_at'])) : '', 'error' => $errors['starts_at'] ?? null, 'required' => true]) ?>
        <?= field('ends_at', 'Ends', ['type' => 'datetime-local', 'value' => $form['ends_at'] ? date('Y-m-d\TH:i', strtotime((string) $form['ends_at'])) : '', 'error' => $errors['ends_at'] ?? null]) ?>
        <?= field('location', 'Venue', ['value' => $form['location'], 'error' => $errors['location'] ?? null, 'required' => true, 'maxlength' => 150]) ?>
        <?= field('capacity', 'Capacity', ['type' => 'number', 'value' => (string) $form['capacity'], 'error' => $errors['capacity'] ?? null, 'required' => true, 'attrs' => ['min' => '1', 'max' => '5000']]) ?>
        <div class="full"><?= field('description', 'Description', ['type' => 'textarea', 'rows' => 3, 'value' => $form['description'], 'error' => $errors['description'] ?? null, 'required' => true, 'maxlength' => 2000, 'attrs' => ['minlength' => '10']]) ?></div>
        <div class="full field">
          <label for="f-poster">Poster <span class="opt">optional · JPEG, PNG or WebP · max 2 MB</span></label>
          <div class="dropzone" data-dropzone data-max="<?= (int) cfg('poster_max_bytes') ?>">
            <input type="file" id="f-poster" name="poster" accept="image/jpeg,image/png,image/webp">
            <div class="dz-preview"><?= icon('upload') ?><span><strong>Drop an image here</strong> or click to browse</span></div>
          </div>
          <p class="dz-error" role="alert"><?= h($errors['poster'] ?? '') ?></p>
          <label class="check" id="remove-poster-row" hidden><input type="checkbox" name="remove_poster" value="1"> Remove the current poster</label>
        </div>
      </div>
      <div class="dialog-actions"><button class="btn btn-ghost" type="button" data-dialog-close>Cancel</button><button class="btn btn-primary" type="submit">Save event</button></div>
    </form>
  </div>
</dialog>
<?php render_footer();
