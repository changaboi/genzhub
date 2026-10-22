<?php
declare(strict_types=1);
/**
 * Practical 9 — Secure user registration (MySQLi).
 * Frontend rules (HTML pattern + assets/js/app.js) and backend rules (Rules class)
 * come from the same patterns. Duplicate e-mails are rejected before AND after the
 * insert (unique index, errno 1062) so a race cannot create two accounts.
 */
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect(home_for(current_user()['role']));
}

$courses = q_all('SELECT course_id, name FROM courses ORDER BY name');
$courseOptions = ['' => 'Choose your course'];
foreach ($courses as $c) {
    $courseOptions[(string) $c['course_id']] = $c['name'];
}
$values = ['full_name' => '', 'email' => '', 'course_id' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $values['full_name'] = clean($_POST['full_name'] ?? '', 100);
    $values['email'] = mb_strtolower(clean($_POST['email'] ?? '', 254));
    $values['course_id'] = clean($_POST['course_id'] ?? '', 6);
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if ($e = Rules::name($values['full_name'])) $errors['full_name'] = $e;
    if ($e = Rules::email($values['email'])) $errors['email'] = $e;
    if (!isset($courseOptions[$values['course_id']]) || $values['course_id'] === '') $errors['course_id'] = 'Choose a course from the list.';
    if ($e = Rules::password($password)) $errors['password'] = $e;
    if ($password !== $confirm) $errors['confirm_password'] = 'Passwords do not match.';

    if (!$errors && q_val('SELECT COUNT(*) FROM users WHERE email = ?', [$values['email']]) > 0) {
        $errors['email'] = 'An account with this email already exists. Try logging in.';
    }

    if (!$errors) {
        $conn = db();
        try {
            $conn->begin_transaction();
            $hash = password_hash($password, PASSWORD_DEFAULT);          // bcrypt, salted
            q('INSERT INTO users (email, password_hash, role) VALUES (?, ?, \'student\')', [$values['email'], $hash]);
            $userId = (int) $conn->insert_id;
            q('INSERT INTO students (user_id, course_id, full_name) VALUES (?, ?, ?)', [$userId, (int) $values['course_id'], $values['full_name']]);
            $conn->commit();
            audit('user.registered', 'user', $userId, ['email' => $values['email']], $userId);
            flash('success', 'Account created. Log in to browse events.');
            redirect('login.php');
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            if ($e->getCode() === 1062) {
                $errors['email'] = 'An account with this email already exists. Try logging in.';
            } else {
                throw $e;
            }
        }
    }
    http_response_code(422);
}

render_header('Create your account', ['layout' => 'auth']);
?>
<div class="auth-card">
  <h1>Create your account</h1>
  <p class="lede">Register for campus events in two taps.</p>
  <?php if ($errors): ?><div class="alert alert-danger" role="alert">Fix the highlighted fields and try again.</div><?php endif; ?>
  <form method="post" action="<?= h(url('register.php')) ?>">
    <?= csrf_field() ?>
    <?= field('full_name', 'Full name', ['value' => $values['full_name'], 'error' => $errors['full_name'] ?? null, 'required' => true, 'pattern' => Rules::NAME_PATTERN, 'autocomplete' => 'name', 'maxlength' => 100, 'msg' => 'Use 2–100 letters; spaces, . \' and - are fine.']) ?>
    <?= field('email', 'Email', ['type' => 'email', 'value' => $values['email'], 'error' => $errors['email'] ?? null, 'required' => true, 'autocomplete' => 'email', 'maxlength' => 254, 'msg' => 'Enter a valid email address.']) ?>
    <?= field('course_id', 'Course', ['type' => 'select', 'options' => $courseOptions, 'value' => $values['course_id'], 'error' => $errors['course_id'] ?? null, 'required' => true]) ?>
    <?= field('password', 'Password', ['type' => 'password', 'error' => $errors['password'] ?? null, 'required' => true, 'pattern' => Rules::PASSWORD_PATTERN, 'autocomplete' => 'new-password', 'maxlength' => 72, 'meter' => true, 'hint' => '8+ characters with upper case, lower case and a number.', 'msg' => 'Use 8–72 characters with an uppercase letter, a lowercase letter and a number.']) ?>
    <?= field('confirm_password', 'Confirm password', ['type' => 'password', 'error' => $errors['confirm_password'] ?? null, 'required' => true, 'autocomplete' => 'new-password', 'match' => 'f-password', 'maxlength' => 72]) ?>
    <button class="btn btn-primary btn-block" type="submit">Create account</button>
  </form>
  <p class="auth-foot">Already registered? <a href="<?= h(url('login.php')) ?>">Log in</a></p>
</div>
<?php render_footer();
