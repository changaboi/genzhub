<?php
declare(strict_types=1);
/**
 * Practical 7 — PHP form processing with server-side validation and CSV/JSON storage.
 *  1. POST only, CSRF token checked          3. flock() + atomic writes, folder is web-protected
 *  2. trim / strip control chars / strip_tags, validate, escape on output    4. clear success + error messages
 */
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/contact_lib.php';

$subjects = ['General question', 'Event registration help', 'Report a problem', 'Feedback'];
$values = ['name' => '', 'email' => '', 'subject' => $subjects[0], 'message' => ''];
$errors = [];
$user = current_user();
if ($user && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $values['name'] = $user['full_name'] ?? '';
    $values['email'] = $user['email'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    foreach (['name' => 100, 'email' => 254, 'subject' => 80, 'message' => 2000] as $f => $max) {
        $values[$f] = trim(strip_tags(clean($_POST[$f] ?? '', $max)));
    }
    $recent = array_filter($_SESSION['msg_times'] ?? [], static fn($t) => $t > time() - 600);

    if (($_POST['website'] ?? '') !== '') {                      // honeypot: bots fill hidden fields
        flash('success', 'Thanks! Your message was sent.');
        redirect('contact.php');
    }
    if ($e = Rules::name($values['name'])) $errors['name'] = $e;
    if ($e = Rules::email($values['email'])) $errors['email'] = $e;
    if (!in_array($values['subject'], $subjects, true)) $errors['subject'] = 'Choose a subject from the list.';
    $len = mb_strlen($values['message']);
    if ($len < 10 || $len > 2000) $errors['message'] = 'Write between 10 and 2000 characters (you have ' . $len . ').';
    if (count($recent) >= 5) $errors['message'] = 'You have sent several messages in a short time. Wait a few minutes and try again.';

    if (!$errors) {
        $rec = ['id' => bin2hex(random_bytes(6)), 'submitted_at' => date('c'), 'name' => $values['name'], 'email' => $values['email'],
                'subject' => $values['subject'], 'message' => $values['message'], 'ip' => client_ip() ?? ''];
        if (store_message($rec)) {
            $recent[] = time();
            $_SESSION['msg_times'] = array_values($recent);
            flash('success', 'Thanks, ' . $values['name'] . '! Your message was sent. Reference ' . $rec['id'] . '.');
            redirect('contact.php');
        }
        http_response_code(500);
        $errors['_form'] = 'Your message could not be saved on the server. Please try again shortly.';
    } else {
        http_response_code(422);
    }
}

render_header('Contact', ['active' => 'contact', 'subtitle' => 'Questions about events or your account? Send us a note.']);
?>
<?php if ($user): ?><div class="stack"><?php else: ?><div class="wrap stack"><?php endif; ?>
  <div class="grid g-main">
    <section class="card" aria-labelledby="cf">
      <h2 id="cf" class="sr">Contact form</h2>
      <?php if ($errors): ?><div class="alert alert-danger" role="alert"><?= h($errors['_form'] ?? 'Fix the highlighted fields and send again.') ?></div><?php endif; ?>
      <form method="post" action="<?= h(url('contact.php')) ?>">
        <?= csrf_field() ?>
        <div class="form-grid">
          <?= field('name', 'Your name', ['value' => $values['name'], 'error' => $errors['name'] ?? null, 'required' => true, 'pattern' => Rules::NAME_PATTERN, 'maxlength' => 100, 'autocomplete' => 'name', 'msg' => 'Use 2–100 letters.']) ?>
          <?= field('email', 'Email', ['type' => 'email', 'value' => $values['email'], 'error' => $errors['email'] ?? null, 'required' => true, 'maxlength' => 254, 'autocomplete' => 'email']) ?>
          <div class="full"><?= field('subject', 'Subject', ['type' => 'select', 'options' => array_combine($subjects, $subjects), 'value' => $values['subject'], 'error' => $errors['subject'] ?? null, 'required' => true]) ?></div>
          <div class="full"><?= field('message', 'Message', ['type' => 'textarea', 'rows' => 6, 'value' => $values['message'], 'error' => $errors['message'] ?? null, 'required' => true, 'maxlength' => 2000, 'attrs' => ['minlength' => '10'], 'hint' => '10–2000 characters.']) ?></div>
        </div>
        <div style="position:absolute;left:-9999px" aria-hidden="true"><label>Leave empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <button class="btn btn-primary" type="submit">Send message</button>
      </form>
    </section>
    <aside class="card">
      <h2>What happens next</h2>
      <p class="muted">Messages are validated on the server, cleaned of markup, and saved to a CSV and a JSON file in a protected folder. An administrator reads them from the Messages page.</p>
      <ul class="list">
        <li><?= icon('check') ?><div class="grow">Replies go to the email you enter above.</div></li>
        <li><?= icon('lock') ?><div class="grow">Your message is never shown publicly.</div></li>
        <li><?= icon('clock') ?><div class="grow">We answer within two working days.</div></li>
      </ul>
    </aside>
  </div>
</div>
<?php render_footer();
