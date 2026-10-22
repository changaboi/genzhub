<?php
declare(strict_types=1);

/**
 * Shared bootstrap: sessions, security headers, CSRF, flash messages,
 * authentication helpers, validation rules, audit logging, small utilities.
 * Every page starts with: require __DIR__ . '/includes/bootstrap.php';
 */

require_once __DIR__ . '/db.php';

date_default_timezone_set((string) cfg('timezone', 'Asia/Kolkata'));
ini_set('display_errors', cfg('debug') ? '1' : '0');
ini_set('log_errors', '1');

if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
}

if (session_status() !== PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli') {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => is_https(),
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('studenthub_sid');
    session_start();
}

/* ------------------------------------------------------------------ utils */

function is_https(): bool
{
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

function h(string|int|float|null $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Web path of the project folder, e.g. "" (virtual host) or "/studenthub" (htdocs subfolder). */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $configured = cfg('base_url');
    if (is_string($configured)) {
        return $base = rtrim($configured, '/');
    }
    $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $proj = realpath(__DIR__ . '/..') ?: '';
    $root = str_replace('\\', '/', $root);
    $proj = str_replace('\\', '/', $proj);
    if ($root !== '' && str_starts_with($proj, $root)) {
        return $base = rtrim(substr($proj, strlen($root)), '/');
    }
    return $base = '';
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = __DIR__ . '/../' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : 1;
    return url($path) . '?v=' . $v;
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)), true, 303);
    exit;
}

function client_ip(): ?string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($ip, FILTER_VALIDATE_IP) ?: null;
}

function paginate(int $total, int $page, int $per): array
{
    $pages = max(1, (int) ceil($total / $per));
    $page = min(max(1, $page), $pages);
    return ['page' => $page, 'pages' => $pages, 'per' => $per, 'offset' => ($page - 1) * $per, 'total' => $total];
}

/** Neutralise spreadsheet formula injection in CSV cells (=, +, -, @, tab, CR). */
function csv_safe(string|int|float|null $v): string
{
    $s = (string) $v;
    return $s !== '' && strpbrk($s[0], "=+-@\t\r") !== false ? "'" . $s : $s;
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out ?: '?';
}

function fmt_dt(?string $dt, string $format = 'D, j M Y · g:i A'): string
{
    return $dt ? date($format, strtotime($dt)) : '—';
}

/* ------------------------------------------------------------------- CSRF */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_valid(?string $token): bool
{
    return is_string($token) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

/** For HTML form posts. Fails with a friendly 419 page. */
function require_csrf(): void
{
    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        render_error_page(419, 'Form expired', 'Your form session expired or the request could not be verified. Go back, refresh the page and try again.');
    }
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        render_error_page(405, 'Method not allowed', 'This action only accepts POST requests.');
    }
}

/* ------------------------------------------------------------------ flash */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flash(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($f) ? $f : [];
}

/* ------------------------------------------------------------- validation */

/**
 * Single source of truth for input rules. The same patterns are printed into
 * the HTML `pattern` attributes and read by assets/js/app.js, so the browser
 * and the server always agree on what is valid.
 */
final class Rules
{
    public const NAME_PATTERN     = "[\\p{L}][\\p{L} .'\\-]{1,99}";
    public const PASSWORD_PATTERN = '(?=.*[a-z])(?=.*[A-Z])(?=.*\\d).{8,72}';
    public const PHONE_PATTERN    = '[0-9+\\-\\s]{7,20}';
    public const ENROLL_PATTERN   = '[A-Za-z0-9\\-]{5,20}';

    public static function name(string $v): ?string
    {
        return preg_match('/^' . self::NAME_PATTERN . '$/u', $v) === 1 ? null : 'Use 2–100 letters (spaces, . \' and - allowed).';
    }

    public static function email(string $v): ?string
    {
        if (strlen($v) > 254 || filter_var($v, FILTER_VALIDATE_EMAIL) === false) {
            return 'Enter a valid email address.';
        }
        return null;
    }

    public static function password(string $v): ?string
    {
        return preg_match('/^' . self::PASSWORD_PATTERN . '$/', $v) === 1
            ? null : 'Use 8–72 characters with an uppercase letter, a lowercase letter and a number.';
    }

    public static function phone(string $v): ?string
    {
        return $v === '' || preg_match('/^' . self::PHONE_PATTERN . '$/', $v) === 1 ? null : 'Use 7–20 digits; + and - are allowed.';
    }

    public static function enrollment(string $v): ?string
    {
        return $v === '' || preg_match('/^' . self::ENROLL_PATTERN . '$/', $v) === 1 ? null : 'Use 5–20 letters, numbers or hyphens.';
    }
}

/** Trim + strip control characters. Output escaping happens at render time with h(). */
function clean(mixed $v, int $max = 1000): string
{
    $s = is_string($v) ? trim($v) : '';
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
    return mb_substr($s, 0, $max);
}

/* ------------------------------------------------------------------ audit */

function audit(string $action, string $entityType, ?int $entityId = null, array $details = [], ?int $userId = null): void
{
    try {
        $userId ??= isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : null;
        $json = $details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        q('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, $action, $entityType, $entityId, $json, client_ip()]);
    } catch (Throwable $e) {
        error_log('audit failed: ' . $e->getMessage());
    }
}

/* ----------------------------------------------------------- authentication */

const USER_SELECT = "SELECT u.user_id, u.email, u.role, u.status, u.last_login_at,
        s.student_id, s.full_name, s.course_id, c.name AS course
    FROM users u
    LEFT JOIN students s ON s.user_id = u.user_id
    LEFT JOIN courses c ON c.course_id = s.course_id";

/** Returns the signed-in user (fresh from the DB) or null. Enforces inactivity timeout. */
function current_user(): ?array
{
    static $cached = false;
    if ($cached !== false) {
        return $cached;
    }
    $uid = $_SESSION['uid'] ?? null;
    if ($uid !== null) {
        $idle = time() - (int) ($_SESSION['last_activity'] ?? 0);
        if ($idle > (int) cfg('session_timeout')) {
            logout_user(false);
            session_start();
            flash('warning', 'You were signed out after a period of inactivity.');
            $_SESSION['timed_out'] = true;
            return $cached = null;
        }
        $user = q_one(USER_SELECT . ' WHERE u.user_id = ? LIMIT 1', [(int) $uid]);
        if ($user === null || $user['status'] !== 'active') {
            logout_user(false);
            session_start();
            if ($user !== null) {
                flash('danger', 'This account has been deactivated. Contact an administrator.');
            }
            return $cached = null;
        }
        $_SESSION['last_activity'] = time();
        return $cached = $user;
    }

    $token = $_COOKIE['studenthub_remember'] ?? '';
    if (is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token) === 1) {
        $user = q_one(USER_SELECT . ' JOIN remember_tokens t ON t.user_id = u.user_id
            WHERE t.token_hash = ? AND t.expires_at > NOW() AND u.status = \'active\' LIMIT 1', [hash('sha256', $token)]);
        if ($user !== null) {
            q('DELETE FROM remember_tokens WHERE token_hash = ?', [hash('sha256', $token)]);
            start_session_for($user);
            issue_remember_token((int) $user['user_id']); // rotate on every use
            return $cached = $user;
        }
        clear_remember_cookie();
    }
    return $cached = null;
}

function start_session_for(array $user): void
{
    session_regenerate_id(true);                 // defeats session fixation
    $_SESSION['uid'] = (int) $user['user_id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['last_activity'] = time();
    unset($_SESSION['csrf'], $_SESSION['timed_out']);
}

function home_for(string $role): string
{
    return $role === 'admin' ? 'admin.php' : 'dashboard.php';
}

function require_login(?string $role = null): array
{
    $user = current_user();
    if ($user === null) {
        $to = $_SESSION['timed_out'] ?? false ? 'login.php?reason=timeout' : 'login.php';
        if (!($_SESSION['timed_out'] ?? false)) {
            flash('info', 'Sign in to continue.');
        }
        $_SESSION['next'] = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? ($_SERVER['REQUEST_URI'] ?? null) : null;
        redirect($to);
    }
    if ($role !== null && $user['role'] !== $role) {
        audit('access.denied', 'page', null, ['path' => $_SERVER['REQUEST_URI'] ?? '', 'role' => $user['role']]);
        http_response_code(403);
        render_error_page(403, 'Access denied', 'This page is only available to ' . $role . ' accounts.', $user);
    }
    return $user;
}

function logout_user(bool $log = true): void
{
    $uid = $_SESSION['uid'] ?? null;
    $token = $_COOKIE['studenthub_remember'] ?? '';
    if (is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token) === 1) {
        q('DELETE FROM remember_tokens WHERE token_hash = ?', [hash('sha256', $token)]);
    }
    if ($log && $uid) {
        audit('logout', 'user', (int) $uid);
    }
    clear_remember_cookie();
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    session_destroy();
}

function remember_cookie_opts(int $expires): array
{
    return ['expires' => $expires, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax'];
}

/** Random token; only its SHA-256 hash is stored, so a DB leak cannot be replayed. */
function issue_remember_token(int $userId): void
{
    $token = bin2hex(random_bytes(32));
    $days = (int) cfg('remember_days');
    q('INSERT INTO remember_tokens (token_hash, user_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))',
        [hash('sha256', $token), $userId, $days]);
    setcookie('studenthub_remember', $token, remember_cookie_opts(time() + 86400 * $days));
}

function clear_remember_cookie(): void
{
    setcookie('studenthub_remember', '', remember_cookie_opts(time() - 3600));
}

/* --------------------------------------------------------------- navigation */

/** Role-based menu. Returns sections: [label => [[icon, text, href, key], ...]]. */
function menu_for(?array $user): array
{
    if ($user === null) {
        return ['' => [['home', 'Home', 'index.php', 'home'], ['ticket', 'Events', 'events.php', 'events'], ['mail', 'Contact', 'contact.php', 'contact']]];
    }
    if ($user['role'] === 'admin') {
        return [
            'Overview' => [['chart', 'Dashboard', 'admin.php', 'admin']],
            'Manage' => [
                ['users', 'Students', 'students.php', 'students'],
                ['ticket', 'Events', 'manage-events.php', 'manage-events'],
                ['bolt', 'Live events', 'events-live.php', 'events-live'],
                ['shield', 'Users', 'users.php', 'users'],
            ],
            'Records' => [
                ['log', 'Audit log', 'audit.php', 'audit'],
                ['mail', 'Messages', 'messages.php', 'messages'],
            ],
        ];
    }
    return [
        'Student' => [
            ['home', 'My dashboard', 'dashboard.php', 'dashboard'],
            ['ticket', 'Browse events', 'events.php', 'events'],
            ['user', 'My profile', 'profile.php', 'profile'],
            ['mail', 'Contact', 'contact.php', 'contact'],
        ],
    ];
}

/* --------------------------------------------------------------------- misc */

function db_stats_safe(callable $fn, mixed $fallback = null): mixed
{
    try {
        return $fn();
    } catch (Throwable $e) {
        error_log($e->getMessage());
        return $fallback;
    }
}

require_once __DIR__ . '/ui.php';

if (PHP_SAPI !== 'cli' && !defined('STUDENTHUB_API')) {
    set_exception_handler('render_exception');
}

require_once __DIR__ . '/components.php';

require_once __DIR__ . '/students_lib.php';
require_once __DIR__ . '/events_lib.php';
