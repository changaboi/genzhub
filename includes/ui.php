<?php
declare(strict_types=1);

/** Layout + UI helpers: page shell, icons, form fields, badges, error pages. */

function icon(string $name, string $class = 'icon'): string
{
    static $paths = [
        'home' => 'M3 11l9-8 9 8M5 10v10h14V10',
        'calendar' => 'M4 6h16v14H4zM4 10h16M8 3v4M16 3v4',
        'users' => 'M16 19v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1M9.5 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7M21 19v-1a4 4 0 0 0-3-3.9M16 4.2a3.5 3.5 0 0 1 0 6.6',
        'user' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1',
        'chart' => 'M4 20V10M10 20V4M16 20v-7M22 20H2',
        'ticket' => 'M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4zM14 6v12',
        'shield' => 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z',
        'log' => 'M6 3h9l4 4v14H6zM14 3v5h5M9 13h6M9 17h6',
        'mail' => 'M3 6h18v12H3zM3 7l9 6 9-6',
        'search' => 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14M20 20l-4-4',
        'plus' => 'M12 5v14M5 12h14',
        'edit' => 'M4 20h4L19 9l-4-4L4 16zM13 7l4 4',
        'trash' => 'M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13',
        'download' => 'M12 4v11M7 11l5 5 5-5M4 20h16',
        'sun' => 'M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5L19 19M5 19l1.5-1.5M17.5 6.5L19 5',
        'moon' => 'M20 14A8 8 0 0 1 10 4a8 8 0 1 0 10 10z',
        'logout' => 'M9 4H5v16h4M16 8l4 4-4 4M20 12H9',
        'menu' => 'M4 7h16M4 12h16M4 17h16',
        'x' => 'M6 6l12 12M18 6L6 18',
        'check' => 'M5 12l5 5 9-11',
        'alert' => 'M12 4l9 16H3zM12 10v4M12 17v.5',
        'info' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18M12 11v5M12 8v.5',
        'upload' => 'M12 16V4M7 9l5-5 5 5M4 20h16',
        'eye' => 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6',
        'archive' => 'M3 5h18v4H3zM5 9v10h14V9M10 13h4',
        'restore' => 'M4 12a8 8 0 1 1 3 6.2M4 19v-5h5',
        'pin' => 'M12 21s-7-6-7-11a7 7 0 0 1 14 0c0 5-7 11-7 11M12 12a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5',
        'clock' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18M12 7v5l3 2',
        'bolt' => 'M13 3L5 14h6l-1 7 8-11h-6z',
        'sort' => 'M8 9l4-4 4 4M8 15l4 4 4-4',
        'left' => 'M15 6l-6 6 6 6',
        'right' => 'M9 6l6 6-6 6',
        'lock' => 'M6 11h12v9H6zM8 11V8a4 4 0 0 1 8 0v3',
        'image' => 'M4 5h16v14H4zM4 16l4-4 4 4 3-3 5 5M9 10a1 1 0 1 0 0-.01',
        'sliders' => 'M4 7h10M18 7h2M4 17h2M10 17h10M14 5v4M6 15v4',
    ];
    $d = $paths[$name] ?? $paths['info'];
    return '<svg class="' . h($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="' . $d . '"/></svg>';
}

function badge(string $status): string
{
    static $map = [
        'active' => 'ok', 'published' => 'ok', 'registered' => 'ok', 'open' => 'ok', 'attended' => 'info',
        'inactive' => 'muted', 'archived' => 'muted', 'draft' => 'muted', 'cancelled' => 'bad', 'closed' => 'warn', 'full' => 'warn',
        'admin' => 'brand', 'student' => 'info',
    ];
    return '<span class="badge badge-' . ($map[$status] ?? 'muted') . '">' . h(ucfirst($status)) . '</span>';
}

function avatar(string $name, string $size = ''): string
{
    $hue = abs(crc32($name)) % 360;
    return '<span class="avatar ' . h($size) . '" style="--hue:' . $hue . '" aria-hidden="true">' . h(initials($name)) . '</span>';
}

/**
 * Render a labelled form control with error/hint text.
 * $o: type, value, error, hint, required, pattern, options (select), rows, attrs[], autocomplete, maxlength, rule
 */
function field(string $name, string $label, array $o = []): string
{
    $type = $o['type'] ?? 'text';
    $id = $o['id'] ?? ('f-' . $name);
    $value = (string) ($o['value'] ?? '');
    $error = $o['error'] ?? null;
    $hint = $o['hint'] ?? '';
    $msgId = $id . '-msg';
    $attrs = ' id="' . h($id) . '" name="' . h($name) . '"';
    if (!empty($o['required'])) $attrs .= ' required';
    if (!empty($o['pattern'])) $attrs .= ' pattern="' . h($o['pattern']) . '"';
    if (!empty($o['maxlength'])) $attrs .= ' maxlength="' . (int) $o['maxlength'] . '"';
    if (!empty($o['autocomplete'])) $attrs .= ' autocomplete="' . h($o['autocomplete']) . '"';
    if (!empty($o['msg'])) $attrs .= ' data-msg="' . h($o['msg']) . '"';
    if (!empty($o['match'])) $attrs .= ' data-match="' . h($o['match']) . '"';
    foreach (($o['attrs'] ?? []) as $k => $v) {
        $attrs .= ' ' . h($k) . ($v === true ? '' : '="' . h((string) $v) . '"');
    }
    $attrs .= ' aria-describedby="' . h($msgId) . '"' . ($error ? ' aria-invalid="true"' : '');

    if ($type === 'select') {
        $control = '<select' . $attrs . '>';
        foreach (($o['options'] ?? []) as $val => $text) {
            $control .= '<option value="' . h((string) $val) . '"' . ((string) $val === $value ? ' selected' : '') . '>' . h($text) . '</option>';
        }
        $control .= '</select>';
    } elseif ($type === 'textarea') {
        $control = '<textarea rows="' . (int) ($o['rows'] ?? 4) . '"' . $attrs . '>' . h($value) . '</textarea>';
    } elseif ($type === 'password') {
        $control = '<div class="pw-wrap"><input type="password"' . $attrs . '><button type="button" class="pw-toggle" data-toggle-password="' . h($id) . '" aria-label="Show password">' . icon('eye') . '</button></div>';
    } else {
        $control = '<input type="' . h($type) . '" value="' . h($value) . '"' . $attrs . '>';
    }
    $extra = '';
    if (!empty($o['meter'])) {
        $extra = '<div class="meter" data-meter-for="' . h($id) . '" aria-hidden="true"><i></i><i></i><i></i><i></i></div><p class="meter-label" data-meter-label="' . h($id) . '"></p>';
    }
    return '<div class="field' . ($error ? ' has-error' : '') . '"><label for="' . h($id) . '">' . h($label) . (!empty($o['required']) ? '' : ' <span class="opt">optional</span>') . '</label>'
        . $control . $extra
        . '<p class="field-msg" id="' . h($msgId) . '">' . h($error ?: $hint) . '</p></div>';
}

function csrf_meta(): string
{
    return '<meta name="csrf-token" content="' . h(csrf_token()) . '">';
}

function pager(array $p, array $query = [], string $path = ''): string
{
    if ($p['pages'] <= 1) {
        return '<p class="pager-count">' . (int) $p['total'] . ' result' . ($p['total'] === 1 ? '' : 's') . '</p>';
    }
    $link = static function (int $n, string $label, bool $disabled = false, bool $current = false) use ($query, $path): string {
        if ($disabled) {
            return '<span class="pg disabled" aria-disabled="true">' . $label . '</span>';
        }
        $href = $path . '?' . http_build_query(array_merge($query, ['page' => $n]));
        return '<a class="pg' . ($current ? ' current' : '') . '" href="' . h($href) . '"' . ($current ? ' aria-current="page"' : '') . '>' . $label . '</a>';
    };
    $out = '<nav class="pager" aria-label="Pagination"><p class="pager-count">Showing ' . ($p['offset'] + 1) . '–' . min($p['offset'] + $p['per'], $p['total']) . ' of ' . $p['total'] . '</p><div class="pager-links">';
    $out .= $link($p['page'] - 1, icon('left') . '<span class="sr">Previous</span>', $p['page'] <= 1);
    $start = max(1, $p['page'] - 2);
    $end = min($p['pages'], $p['page'] + 2);
    for ($i = $start; $i <= $end; $i++) {
        $out .= $link($i, (string) $i, false, $i === $p['page']);
    }
    $out .= $link($p['page'] + 1, '<span class="sr">Next</span>' . icon('right'), $p['page'] >= $p['pages']);
    return $out . '</div></nav>';
}

/** Sort header link for tables. */
function sort_th(string $label, string $col, string $cur, string $dir, array $query): string
{
    $next = ($cur === $col && $dir === 'asc') ? 'desc' : 'asc';
    $href = '?' . http_build_query(array_merge($query, ['sort' => $col, 'dir' => $next, 'page' => 1]));
    $aria = $cur === $col ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none';
    return '<th scope="col" aria-sort="' . $aria . '"><a class="th-sort' . ($cur === $col ? ' on' : '') . '" href="' . h($href) . '">' . h($label) . icon('sort') . '</a></th>';
}

/* ------------------------------------------------------------------ layout */

/**
 * Print the page head. Options:
 *  active   menu key to highlight
 *  layout   app | public | auth (default: app when signed in, otherwise public)
 *  subtitle one-line description under the title
 *  actions  raw HTML for buttons at the right of the title
 *  scripts  extra JS files (relative to /assets/js)
 */
function render_header(string $title, array $o = []): void
{
    $user = current_user();
    $layout = $o['layout'] ?? ($user ? 'app' : 'public');
    $menu = menu_for($user);
    $flat = [];
    foreach ($menu as $items) {
        foreach ($items as $it) {
            $flat[] = ['label' => $it[1], 'href' => url($it[2])];
        }
    }
    $remaining = $user ? max(0, (int) cfg('session_timeout') - (time() - (int) ($_SESSION['last_activity'] ?? time()))) : 0;
    $body = [
        'class' => 'layout-' . $layout . ' ' . ($o['bodyClass'] ?? ''),
        'data-base' => base_path(),
        'data-csrf' => csrf_token(),
        'data-role' => $user['role'] ?? 'guest',
        'data-timeout' => $user ? (string) $remaining : '',
        'data-menu' => json_encode($flat, JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT),
    ];
    $bodyAttrs = '';
    foreach ($body as $k => $v) {
        $bodyAttrs .= ' ' . $k . '="' . h($v) . '"';
    }
    $flashes = take_flash();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?= csrf_meta() ?>
  <title><?= h($title) ?> · StudentHub</title>
  <meta name="description" content="StudentHub — events, registrations and student records for campus.">
  <meta name="color-scheme" content="light dark">
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%230f3d3e'/%3E%3Cpath d='M8 12h16v3a2 2 0 0 0 0 4v3H8v-3a2 2 0 0 0 0-4z' fill='%23f2a900'/%3E%3C/svg%3E">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Public+Sans:ital,wght@0,400;0,500;0,600;1,400&display=swap">
  <script src="<?= h(asset('assets/js/theme-init.js')) ?>"></script>
  <link rel="stylesheet" href="<?= h(asset('assets/css/app.css')) ?>">
</head>
<body<?= $bodyAttrs ?>>
<a class="skip" href="#main">Skip to content</a>
<?php if ($layout === 'app' && $user): ?>
  <div class="shell">
    <div class="scrim" data-sidebar-close hidden></div>
    <aside class="sidebar" id="sidebar" aria-label="Main navigation">
      <a class="brand" href="<?= h(url(home_for($user['role']))) ?>"><span class="brand-mark"><?= icon('ticket') ?></span><span>StudentHub</span></a>
      <nav>
        <?php foreach ($menu as $section => $items): ?>
          <?php if ($section !== ''): ?><p class="nav-section"><?= h($section) ?></p><?php endif; ?>
          <ul>
            <?php foreach ($items as [$ic, $text, $href, $key]): ?>
              <li><a href="<?= h(url($href)) ?>"<?= ($o['active'] ?? '') === $key ? ' class="active" aria-current="page"' : '' ?>><?= icon($ic) ?><span><?= h($text) ?></span></a></li>
            <?php endforeach; ?>
          </ul>
        <?php endforeach; ?>
      </nav>
      <div class="sidebar-foot">
        <div class="me">
          <?= avatar($user['full_name'] ?? $user['email']) ?>
          <div><strong><?= h($user['full_name'] ?? 'Administrator') ?></strong><small><?= h($user['email']) ?></small></div>
        </div>
        <form action="<?= h(url('logout.php')) ?>" method="post"><?= csrf_field() ?><button class="btn btn-ghost btn-block" type="submit"><?= icon('logout') ?>Log out</button></form>
      </div>
    </aside>
    <div class="shell-main">
      <header class="topbar">
        <button class="icon-btn menu-btn" type="button" data-sidebar-open aria-label="Open menu" aria-controls="sidebar"><?= icon('menu') ?></button>
        <button class="palette-trigger" type="button" data-palette-open><?= icon('search') ?><span>Jump to a page or search</span><kbd>Ctrl K</kbd></button>
        <span class="session-chip" id="session-chip" title="Time until automatic sign-out" hidden><?= icon('clock') ?><span></span></span>
        <button class="icon-btn" type="button" data-theme-toggle aria-label="Toggle colour theme"><?= icon('moon', 'icon i-moon') . icon('sun', 'icon i-sun') ?></button>
      </header>
      <main id="main" class="page" tabindex="-1">
        <div class="page-head">
          <div>
            <h1><?= h($title) ?></h1>
            <?php if (!empty($o['subtitle'])): ?><p class="lede"><?= h($o['subtitle']) ?></p><?php endif; ?>
          </div>
          <?php if (!empty($o['actions'])): ?><div class="page-actions"><?= $o['actions'] ?></div><?php endif; ?>
        </div>
<?php elseif ($layout === 'auth'): ?>
  <div class="auth">
    <aside class="auth-art" aria-hidden="true">
      <a class="brand" href="<?= h(url('index.php')) ?>"><span class="brand-mark"><?= icon('ticket') ?></span><span>StudentHub</span></a>
      <div class="art-stack">
        <div class="mini-ticket t1"><b>Tech Showcase</b><span>12 Nov · Main Auditorium</span><i>139 left</i></div>
        <div class="mini-ticket t2"><b>Web Workshop</b><span>5 Nov · Lab 2</span><i>25 left</i></div>
        <div class="mini-ticket t3"><b>Hackathon</b><span>4 Dec · Innovation Hub</span><i>7 left</i></div>
      </div>
      <p class="art-line">One place for every campus event, seat and student record.</p>
    </aside>
    <main id="main" class="auth-panel" tabindex="-1">
      <button class="icon-btn auth-theme" type="button" data-theme-toggle aria-label="Toggle colour theme"><?= icon('moon', 'icon i-moon') . icon('sun', 'icon i-sun') ?></button>
<?php else: ?>
  <header class="pubbar">
    <div class="pubbar-in">
      <a class="brand" href="<?= h(url('index.php')) ?>"><span class="brand-mark"><?= icon('ticket') ?></span><span>StudentHub</span></a>
      <nav aria-label="Main navigation">
        <?php foreach ($menu[''] ?? [] as [$ic, $text, $href, $key]): ?>
          <a href="<?= h(url($href)) ?>"<?= ($o['active'] ?? '') === $key ? ' class="active" aria-current="page"' : '' ?>><?= h($text) ?></a>
        <?php endforeach; ?>
      </nav>
      <div class="pubbar-end">
        <button class="icon-btn" type="button" data-theme-toggle aria-label="Toggle colour theme"><?= icon('moon', 'icon i-moon') . icon('sun', 'icon i-sun') ?></button>
        <?php if ($user): ?>
          <a class="btn btn-primary" href="<?= h(url(home_for($user['role']))) ?>">Open dashboard</a>
        <?php else: ?>
          <a class="btn btn-ghost" href="<?= h(url('login.php')) ?>">Log in</a>
          <a class="btn btn-primary" href="<?= h(url('register.php')) ?>">Create account</a>
        <?php endif; ?>
      </div>
    </div>
  </header>
  <main id="main" class="public-main" tabindex="-1">
    <?php if (empty($o['bare'])): ?><div class="wrap page-head"><div><h1><?= h($title) ?></h1><?php if (!empty($o['subtitle'])): ?><p class="lede"><?= h($o['subtitle']) ?></p><?php endif; ?></div><?php if (!empty($o['actions'])): ?><div class="page-actions"><?= $o['actions'] ?></div><?php endif; ?></div><?php endif; ?>
<?php endif; ?>
    <noscript><?php foreach ($flashes as $f): ?><div class="alert alert-<?= h($f['type']) ?>" role="status"><?= h($f['message']) ?></div><?php endforeach; ?></noscript>
    <script type="application/json" id="flash-data"><?= json_encode($flashes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php
    $GLOBALS['__layout'] = $layout;
    $GLOBALS['__scripts'] = $o['scripts'] ?? [];
}

function render_footer(): void
{
    $layout = $GLOBALS['__layout'] ?? 'public';
    $scripts = $GLOBALS['__scripts'] ?? [];
    if ($layout === 'app') {
        echo "      </main>\n    </div>\n  </div>\n";
    } elseif ($layout === 'auth') {
        echo "    </main>\n  </div>\n";
    } else {
        echo "  </main>\n  <footer class=\"pubfoot\"><div class=\"wrap\"><span>StudentHub · a semester project in PHP and MySQL</span><span>Built for Practicals 7–15</span></div></footer>\n";
    }
    ?>
  <dialog id="confirm-dialog" class="dialog" aria-labelledby="confirm-title">
    <form method="dialog">
      <h2 id="confirm-title">Are you sure?</h2>
      <p id="confirm-text"></p>
      <div class="dialog-actions">
        <button class="btn btn-ghost" value="cancel">Cancel</button>
        <button class="btn btn-danger" value="ok" id="confirm-ok">Confirm</button>
      </div>
    </form>
  </dialog>
  <dialog id="palette" class="dialog palette" aria-label="Command palette">
    <div class="palette-box">
      <div class="palette-input"><?= icon('search') ?><input id="palette-input" type="text" placeholder="Type a page name, or a student or event…" autocomplete="off" aria-label="Search"></div>
      <ul id="palette-list" role="listbox" aria-label="Results"></ul>
      <p class="palette-hint"><kbd>↑</kbd><kbd>↓</kbd> to move <kbd>Enter</kbd> to open <kbd>Esc</kbd> to close</p>
    </div>
  </dialog>
  <dialog id="session-dialog" class="dialog" aria-labelledby="session-title">
    <form method="dialog">
      <h2 id="session-title">Still there?</h2>
      <p>You will be signed out in <strong id="session-count">60</strong> seconds because of inactivity.</p>
      <div class="dialog-actions"><button class="btn btn-primary" value="stay" id="session-stay">Stay signed in</button></div>
    </form>
  </dialog>
  <div class="toasts" id="toasts" role="region" aria-label="Notifications" aria-live="polite"></div>
  <script src="<?= h(asset('assets/js/app.js')) ?>" defer></script>
  <?php foreach ($scripts as $s): ?><script src="<?= h(asset('assets/js/' . $s)) ?>" defer></script>
  <?php endforeach; ?>
</body>
</html>
<?php
}

function render_error_page(int $code, string $title, string $message, ?array $user = null): never
{
    render_header($title, ['layout' => 'public', 'bare' => true]);
    ?>
    <section class="wrap error-page">
      <p class="error-code"><?= (int) $code ?></p>
      <h1><?= h($title) ?></h1>
      <p class="lede"><?= h($message) ?></p>
      <p><a class="btn btn-primary" href="<?= h(url($user ? home_for($user['role']) : 'index.php')) ?>">Go to <?= $user ? 'your dashboard' : 'the home page' ?></a></p>
    </section>
    <?php
    render_footer();
    exit;
}

/** Catch-all for pages: friendly message, details only in the PHP error log. */
function render_exception(Throwable $e): never
{
    error_log('Unhandled: ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    $msg = $e instanceof RuntimeException ? $e->getMessage() : 'Something went wrong on our side. Try again in a moment.';
    if (cfg('debug')) {
        $msg .= ' [' . $e->getMessage() . ']';
    }
    render_error_page(500, 'Something went wrong', $msg);
}
