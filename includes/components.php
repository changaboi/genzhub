<?php
declare(strict_types=1);

/** Event "ticket" card used on the home page, event browser and dashboard. */
function render_ticket(array $e, ?array $user = null, ?string $myStatus = null, array $opt = []): string
{
    $past = strtotime((string) $e['starts_at']) < time();
    $cap = max(1, (int) $e['capacity']);
    $taken = (int) $e['registered_count'];
    $left = max(0, (int) $e['seats_left']);
    $pct = (int) min(100, round($taken / $cap * 100));
    $barClass = $pct >= 90 ? 'hot' : ($pct >= 65 ? 'warm' : '');
    $open = $e['status'] === 'published' && !$past;

    $poster = $e['poster_path']
        ? '<img src="' . h(url($e['poster_path'])) . '" alt="" loading="lazy" width="600" height="320">'
        : '';
    $state = '';
    if ($e['status'] === 'cancelled') $state = badge('cancelled');
    elseif ($e['status'] === 'draft') $state = badge('draft');
    elseif ($past || $e['status'] === 'closed') $state = badge('closed');
    elseif ($left === 0) $state = badge('full');

    if ($user === null) {
        $action = $open && $left > 0
            ? '<a class="btn btn-accent btn-sm" href="' . h(url('login.php')) . '">Log in to join</a>'
            : '<span class="badge badge-muted">Unavailable</span>';
    } elseif ($user['role'] === 'admin') {
        $action = '<a class="btn btn-ghost btn-sm" href="' . h(url('manage-events.php?q=' . urlencode($e['title']))) . '">Manage</a>';
    } elseif ($myStatus === 'registered' || $myStatus === 'attended') {
        $action = $open
            ? '<button class="btn btn-ghost btn-sm" type="button" data-reg="cancel" data-event="' . (int) $e['event_id'] . '">Cancel seat</button>'
            : badge($myStatus);
    } elseif (!$open) {
        $action = '<button class="btn btn-ghost btn-sm" type="button" disabled>Closed</button>';
    } elseif ($left === 0) {
        $action = '<button class="btn btn-ghost btn-sm" type="button" disabled>Sold out</button>';
    } else {
        $action = '<button class="btn btn-accent btn-sm" type="button" data-reg="register" data-event="' . (int) $e['event_id'] . '">Register</button>';
    }

    ob_start(); ?>
    <article class="ticket<?= $past || !$open ? ' is-past' : '' ?>" data-event="<?= (int) $e['event_id'] ?>" data-capacity="<?= $cap ?>">
      <div class="ticket-body">
        <div class="poster"><?= $poster ?><span class="chip-cat"><?= h($e['category']) ?></span><?php if ($state): ?><span class="state"><?= $state ?></span><?php endif; ?></div>
        <div class="ticket-info">
          <h3><?= h($e['title']) ?></h3>
          <div class="meta"><?= icon('calendar') ?><span><?= h(fmt_dt($e['starts_at'], 'D j M, g:i A')) ?></span></div>
          <div class="meta"><?= icon('pin') ?><span><?= h($e['location']) ?></span></div>
        </div>
        <div class="stub">
          <div class="seats"><span data-seats><?= $left ?></span><small>of <?= $cap ?> seats left</small></div>
          <div class="stub-action"><?= $action ?></div>
          <div class="bar <?= $barClass ?>"><i data-bar="<?= $pct ?>"></i></div>
        </div>
      </div>
    </article>
    <?php
    return (string) ob_get_clean();
}
