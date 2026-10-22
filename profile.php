<?php
declare(strict_types=1);
/** Students edit their own details and change their password (current password required). */
require __DIR__ . '/includes/bootstrap.php';

$user = require_login('student');
$sid = (int) $user['student_id'];
$row = q_one('SELECT full_name, phone, semester, enrollment_no FROM students WHERE student_id = ?', [$sid]);
$errors = [];
$pwErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (($_POST['form'] ?? '') === 'details') {
        $row['full_name'] = clean($_POST['full_name'] ?? '', 100);
        $row['phone'] = clean($_POST['phone'] ?? '', 20);
        $row['semester'] = (int) ($_POST['semester'] ?? 1);
        if ($e = Rules::name($row['full_name'])) $errors['full_name'] = $e;
        if ($e = Rules::phone($row['phone'])) $errors['phone'] = $e;
        if ($row['semester'] < 1 || $row['semester'] > 8) $errors['semester'] = 'Semester must be between 1 and 8.';
        if (!$errors) {
            q('UPDATE students SET full_name = ?, phone = ?, semester = ? WHERE student_id = ?', [$row['full_name'], $row['phone'] !== '' ? $row['phone'] : null, $row['semester'], $sid]);
            audit('profile.updated', 'student', $sid);
            flash('success', 'Profile saved.');
            redirect('profile.php');
        }
        http_response_code(422);
    } elseif (($_POST['form'] ?? '') === 'password') {
        $cur = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $hash = (string) q_val('SELECT password_hash FROM users WHERE user_id = ?', [(int) $user['user_id']]);
        if (!password_verify($cur, $hash)) $pwErrors['current_password'] = 'Your current password is incorrect.';
        if ($e = Rules::password($new)) $pwErrors['new_password'] = $e;
        elseif ($new === $cur) $pwErrors['new_password'] = 'Choose a password you have not used just now.';
        if (($new !== (string) ($_POST['confirm_password'] ?? ''))) $pwErrors['confirm_password'] = 'Passwords do not match.';
        if (!$pwErrors) {
            q('UPDATE users SET password_hash = ? WHERE user_id = ?', [password_hash($new, PASSWORD_DEFAULT), (int) $user['user_id']]);
            q('DELETE FROM remember_tokens WHERE user_id = ?', [(int) $user['user_id']]);   // sign out other devices
            session_regenerate_id(true);
            audit('password.changed', 'user', (int) $user['user_id']);
            flash('success', 'Password changed. Other devices were signed out.');
            redirect('profile.php');
        }
        http_response_code(422);
    }
}
$semesters = array_combine(range(1, 8), array_map(static fn($n) => "Semester $n", range(1, 8)));
render_header('My profile', ['active' => 'profile', 'subtitle' => 'Keep your details current.']);
?>
<div class="grid g-2">
  <section class="card">
    <div class="row" style="margin-bottom:18px"><?= avatar($row['full_name'], 'lg') ?><div><h2><?= h($row['full_name']) ?></h2><p class="muted" style="margin:0"><?= h($user['email']) ?> · <?= h($user['course']) ?></p></div></div>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="form" value="details">
      <?= field('full_name', 'Full name', ['value' => $row['full_name'], 'error' => $errors['full_name'] ?? null, 'required' => true, 'pattern' => Rules::NAME_PATTERN, 'maxlength' => 100, 'msg' => 'Use 2–100 letters.']) ?>
      <div class="form-grid">
        <?= field('semester', 'Semester', ['type' => 'select', 'options' => $semesters, 'value' => (string) $row['semester'], 'error' => $errors['semester'] ?? null, 'required' => true]) ?>
        <?= field('phone', 'Phone', ['type' => 'tel', 'value' => (string) $row['phone'], 'error' => $errors['phone'] ?? null, 'pattern' => Rules::PHONE_PATTERN, 'maxlength' => 20, 'msg' => 'Use 7–20 digits; + and - are allowed.']) ?>
      </div>
      <dl class="kv" style="margin-bottom:16px"><dt>Enrollment no.</dt><dd><?= h($row['enrollment_no'] ?: 'Not assigned yet') ?></dd></dl>
      <button class="btn btn-primary" type="submit">Save profile</button>
    </form>
  </section>
  <section class="card">
    <h2 style="margin-bottom:14px">Change password</h2>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="form" value="password">
      <?= field('current_password', 'Current password', ['type' => 'password', 'error' => $pwErrors['current_password'] ?? null, 'required' => true, 'autocomplete' => 'current-password', 'maxlength' => 72]) ?>
      <?= field('new_password', 'New password', ['type' => 'password', 'error' => $pwErrors['new_password'] ?? null, 'required' => true, 'pattern' => Rules::PASSWORD_PATTERN, 'autocomplete' => 'new-password', 'maxlength' => 72, 'meter' => true, 'msg' => 'Use 8–72 characters with an uppercase letter, a lowercase letter and a number.']) ?>
      <?= field('confirm_password', 'Confirm new password', ['type' => 'password', 'error' => $pwErrors['confirm_password'] ?? null, 'required' => true, 'autocomplete' => 'new-password', 'match' => 'f-new_password', 'maxlength' => 72]) ?>
      <button class="btn btn-primary" type="submit">Update password</button>
    </form>
  </section>
</div>
<?php render_footer();
