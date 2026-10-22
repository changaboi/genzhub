<?php
declare(strict_types=1);
/**
 * Practical 10 — Secure login with sessions, RBAC redirect, timeout and remember-me.
 *  - password_verify() against a bcrypt hash (a dummy hash keeps timing equal for unknown e-mails)
 *  - session_regenerate_id() on success (see start_session_for)
 *  - lock-out after repeated failures, last_login_at, audit trail
 *  - remember-me: random token in a cookie, only its SHA-256 hash stored in the DB
 */
require __DIR__ . '/includes/bootstrap.php';

if ($user = current_user()) {
    redirect(home_for($user['role']));
}
if (($_GET['reason'] ?? '') === 'timeout' && empty($_SESSION['flash'])) {
    flash('warning', 'You were signed out after a period of inactivity. Log in again to continue.');
}

$email = '';
$error = null;
const DUMMY_HASH = '$2y$10$PvE4roipWTa/MnqCSenul.xzaiX0yCmw4VHISbgtisOx/myQFzlHS'; // real bcrypt hash: keeps timing equal for unknown emails

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = mb_strtolower(clean($_POST['email'] ?? '', 254));
    $password = (string) ($_POST['password'] ?? '');
    $remember = !empty($_POST['remember']);

    $fails = (int) q_val('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)', [$email, (int) cfg('lockout_minutes')]);

    if ($email === '' || $password === '') {
        $error = 'Enter your email and password.';
    } elseif ($fails >= (int) cfg('max_login_fails')) {
        audit('login.locked', 'user', null, ['email' => $email]);
        $error = 'Too many failed attempts. Wait ' . (int) cfg('lockout_minutes') . ' minutes and try again.';
    } else {
        $row = q_one('SELECT user_id, password_hash, role, status FROM users WHERE email = ? LIMIT 1', [$email]);
        $ok = password_verify($password, $row['password_hash'] ?? DUMMY_HASH) && $row !== null;
        if (!$ok) {
            q('INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)', [$email, client_ip()]);
            audit('login.failed', 'user', $row ? (int) $row['user_id'] : null, ['email' => $email]);
            $error = 'Incorrect email or password.';
            http_response_code(401);
        } elseif ($row['status'] !== 'active') {
            $error = 'This account is inactive. Contact an administrator.';
            http_response_code(403);
        } else {
            q('DELETE FROM login_attempts WHERE email = ?', [$email]);
            if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
                q('UPDATE users SET password_hash = ? WHERE user_id = ?', [password_hash($password, PASSWORD_DEFAULT), (int) $row['user_id']]);
            }
            $next = $_SESSION['next'] ?? null;
            start_session_for(['user_id' => $row['user_id'], 'role' => $row['role']]);
            q('UPDATE users SET last_login_at = NOW() WHERE user_id = ?', [(int) $row['user_id']]);
            audit('login', 'user', (int) $row['user_id'], [], (int) $row['user_id']);
            if ($remember) {
                issue_remember_token((int) $row['user_id']);
            }
            $base = base_path();
            $safe = is_string($next) && str_starts_with($next, $base . '/') && !str_contains($next, '//') && !str_contains($next, '..');
            if ($safe && $row['role'] === 'student' && preg_match('#/(admin|students|manage-events|events-live|users|audit|messages)\.php#', $next)) {
                $safe = false;
            }
            redirect($safe ? $next : home_for($row['role']));
        }
    }
}

render_header('Log in', ['layout' => 'auth', 'scripts' => ['auth.js']]);
?>
<div class="auth-card">
  <h1>Welcome back</h1>
  <p class="lede">Log in to see your tickets and upcoming events.</p>
  <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= h($error) ?></div><?php endif; ?>
  <form method="post" action="<?= h(url('login.php')) ?>" id="login-form">
    <?= csrf_field() ?>
    <?= field('email', 'Email', ['type' => 'email', 'value' => $email, 'required' => true, 'autocomplete' => 'username', 'maxlength' => 254, 'msg' => 'Enter a valid email address.']) ?>
    <?= field('password', 'Password', ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password', 'maxlength' => 72]) ?>
    <label class="check" style="margin-bottom:16px"><input type="checkbox" name="remember" value="1"> Keep me signed in on this device for 30 days</label>
    <button class="btn btn-primary btn-block" type="submit">Log in</button>
  </form>
  <div class="demo-box">
    Demo accounts (local use only):
    <button type="button" data-fill="admin@studenthub.test|Admin123!">Fill admin</button> ·
    <button type="button" data-fill="avery.lee@studenthub.test|Student123!">Fill student</button>
  </div>
  <p class="auth-foot">New here? <a href="<?= h(url('register.php')) ?>">Create an account</a></p>
</div>
<?php render_footer();
