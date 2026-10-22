<?php
declare(strict_types=1);
/**
 * Practical 13 — AJAX/Fetch CRUD without page reload.
 * This page only renders the shell; assets/js/events-live.js talks to api/events.php (JSON).
 */
require __DIR__ . '/includes/bootstrap.php';

require_login('admin');
$cats = q_all('SELECT category_id, name FROM event_categories ORDER BY name');
$catOptions = ['' => 'Choose a category'];
foreach ($cats as $c) $catOptions[(string) $c['category_id']] = $c['name'];

render_header('Live events', ['active' => 'events-live', 'scripts' => ['events-live.js'], 'subtitle' => 'Create, edit and delete events through the JSON API. Nothing here reloads the page.',
    'actions' => '<button class="btn btn-primary" type="button" id="live-add">' . icon('plus') . 'Add event</button>']);
?>
<div class="stack" id="live" data-categories="<?= h(json_encode(array_map(null, array_keys($catOptions), array_values($catOptions)), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
  <div class="card card-flush">
    <form class="toolbar" id="live-filters" role="search" data-no-loading>
      <div class="search"><?= icon('search') ?><input type="search" id="live-q" placeholder="Search as you type" aria-label="Search events" autocomplete="off"></div>
      <select id="live-cat" aria-label="Category"><option value="">All categories</option><?php foreach ($cats as $c): ?><option value="<?= (int) $c['category_id'] ?>"><?= h($c['name']) ?></option><?php endforeach; ?></select>
      <div class="tabs" role="group" aria-label="Event state" id="live-state">
        <button type="button" data-state="all" class="on">All</button><button type="button" data-state="open">Open</button><button type="button" data-state="closed">Closed</button>
      </div>
    </form>
    <div id="live-body" aria-live="polite" aria-busy="true"></div>
    <div class="pager" id="live-pager" hidden></div>
  </div>

  <details class="card inspector">
    <summary><?= icon('bolt') ?> API inspector <span class="muted" id="insp-line">No requests yet</span></summary>
    <pre id="insp-json" aria-label="Last JSON response">Make a request to see the raw response.</pre>
  </details>
</div>

<dialog id="live-dialog" class="dialog wide" aria-labelledby="ld-title">
  <div class="dialog-in">
    <div class="dialog-head"><h2 id="ld-title">Add event</h2><button class="icon-btn sm" type="button" data-dialog-close aria-label="Close"><?= icon('x') ?></button></div>
    <form id="live-form" novalidate data-no-loading>
      <input type="hidden" id="ld-id" value="">
      <div class="alert alert-danger" id="ld-alert" role="alert" hidden></div>
      <div class="form-grid">
        <div class="full"><?= field('title', 'Title', ['required' => true, 'maxlength' => 150, 'id' => 'ld-title-in']) ?></div>
        <?= field('category_id', 'Category', ['type' => 'select', 'options' => $catOptions, 'required' => true, 'id' => 'ld-category']) ?>
        <?= field('status', 'Status', ['type' => 'select', 'options' => ['published' => 'Published (open)', 'draft' => 'Draft (hidden)', 'closed' => 'Closed', 'cancelled' => 'Cancelled'], 'value' => 'published', 'required' => true, 'id' => 'ld-status']) ?>
        <?= field('starts_at', 'Starts', ['type' => 'datetime-local', 'required' => true, 'id' => 'ld-starts']) ?>
        <?= field('ends_at', 'Ends', ['type' => 'datetime-local', 'id' => 'ld-ends']) ?>
        <?= field('location', 'Venue', ['required' => true, 'maxlength' => 150, 'id' => 'ld-location']) ?>
        <?= field('capacity', 'Capacity', ['type' => 'number', 'value' => '50', 'required' => true, 'attrs' => ['min' => '1', 'max' => '5000'], 'id' => 'ld-capacity']) ?>
        <div class="full"><?= field('description', 'Description', ['type' => 'textarea', 'rows' => 3, 'required' => true, 'maxlength' => 2000, 'id' => 'ld-description']) ?></div>
      </div>
      <p class="muted" style="font-size:.84rem">Posters are uploaded from <a href="<?= h(url('manage-events.php')) ?>">Manage events</a>.</p>
      <div class="dialog-actions"><button class="btn btn-ghost" type="button" data-dialog-close>Cancel</button><button class="btn btn-primary" type="submit" id="ld-save">Save event</button></div>
    </form>
  </div>
</dialog>
<?php render_footer();
