<?php
declare(strict_types=1);
/**
 * Practical 11 — Student management (MySQLi prepared statements).
 * List with search (name / email / course / enrollment), course + status filters, sortable columns,
 * pagination, add / edit, CSV export, SOFT delete (status column), restore, and a confirmed permanent delete
 * that is only possible for archived students.
 */
require __DIR__ . '/includes/bootstrap.php';

$admin = require_login('admin');
$courses = q_all('SELECT course_id, name FROM courses ORDER BY name');
$courseOptions = [];
foreach ($courses as $c) $courseOptions[(string) $c['course_id']] = $c['name'];

/* ---------- filters (GET) ---------- */
$f = [
    'q' => clean($_GET['q'] ?? '', 80),
    'course' => (int) ($_GET['course'] ?? 0) ?: '',
    'status' => in_array($_GET['status'] ?? 'active', ['active', 'archived', 'all'], true) ? ($_GET['status'] ?? 'active') : 'active',
];
$sorts = ['name' => 's.full_name', 'email' => 'u.email', 'course' => 'c.name', 'sem' => 's.semester', 'joined' => 's.created_at'];
$sort = array_key_exists($_GET['sort'] ?? '', $sorts) ? $_GET['sort'] : 'name';
$dir = ($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
$query = array_filter(['q' => $f['q'], 'course' => $f['course'], 'status' => $f['status'] !== 'active' ? $f['status'] : '', 'sort' => $sort !== 'name' ? $sort : '', 'dir' => $dir !== 'asc' ? $dir : ''], static fn($v) => $v !== '' && $v !== null);

/* ---------- POST actions ---------- */
$form = ['student_id' => 0, 'full_name' => '', 'email' => '', 'course_id' => '', 'enrollment_no' => '', 'semester' => 1, 'phone' => '', 'password' => ''];
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $sid = (int) ($_POST['student_id'] ?? 0);
    $back = 'students.php' . ($query ? '?' . http_build_query($query) : '');

    if ($action === 'save') {
        [$v, $errors] = validate_student($_POST, $sid ?: null, false);
        $form = ['student_id' => $sid] + $v;
        if (!$errors) {
            if ($sid) {
                update_student($sid, $v);
                flash('success', $v['full_name'] . ' was updated.');
            } else {
                [, $plain] = create_student($v);
                flash('success', $v['full_name'] . ' was added.' . ($v['password'] === '' ? ' Temporary password: ' . $plain . ' (shown once).' : ''));
            }
            redirect($back);
        }
        http_response_code(422);
    } else {
        $ok = match ($action) {
            'archive' => archive_student($sid),
            'restore' => restore_student($sid),
            'purge' => purge_student($sid),
            default => false,
        };
        flash($ok ? 'success' : 'danger', $ok ? ['archive' => 'Student archived. Restore them any time from the Archived view.', 'restore' => 'Student restored.', 'purge' => 'Student permanently deleted.'][$action] : 'Nothing changed. The student may already have been updated.');
        redirect($back);
    }
}

/* ---------- query ---------- */
[$where, $params] = student_filter($f);

if (($_GET['export'] ?? '') === 'csv') {
    $rows = q_all('SELECT ' . STUDENT_COLUMNS . STUDENT_FROM . " WHERE $where ORDER BY {$sorts[$sort]} $dir", $params);
    audit('students.exported', 'student', null, ['rows' => count($rows), 'filters' => $query]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="students-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Student ID', 'Enrollment no', 'Name', 'Email', 'Course', 'Semester', 'Phone', 'Status', 'Last login'], ',', '"', '');
    foreach ($rows as $r) {
        fputcsv($out, array_map('csv_safe', [$r['student_id'], $r['enrollment_no'], $r['full_name'], $r['email'], $r['course'], $r['semester'], $r['phone'], $r['status'], $r['last_login_at']]), ',', '"', '');
    }
    exit;
}

$total = (int) q_val('SELECT COUNT(*)' . STUDENT_FROM . " WHERE $where", $params);
$p = paginate($total, (int) ($_GET['page'] ?? 1), 10);
$rows = q_all('SELECT ' . STUDENT_COLUMNS . STUDENT_FROM . " WHERE $where ORDER BY {$sorts[$sort]} $dir, s.student_id LIMIT {$p['per']} OFFSET {$p['offset']}", $params);
$counts = q_one("SELECT SUM(status = 'active') AS active, SUM(status = 'archived') AS archived FROM students") ?? ['active' => 0, 'archived' => 0];
$semesters = array_combine(range(1, 8), array_map(static fn($n) => "Semester $n", range(1, 8)));
$exportQs = http_build_query(array_merge($query, ['export' => 'csv']));

render_header('Students', ['active' => 'students', 'scripts' => ['students.js'], 'subtitle' => (int) $counts['active'] . ' active, ' . (int) $counts['archived'] . ' archived.',
    'actions' => '<a class="btn btn-ghost" href="?' . h($exportQs) . '">' . icon('download') . 'Export CSV</a><button class="btn btn-primary" type="button" data-student-add>' . icon('plus') . 'Add student</button>']);
?>
<div class="card card-flush">
  <form method="get" class="toolbar" data-autosubmit role="search">
    <div class="search"><?= icon('search') ?><input type="search" name="q" value="<?= h($f['q']) ?>" placeholder="Search name, email, course or enrollment no." aria-label="Search students"></div>
    <select name="course" aria-label="Filter by course"><option value="">All courses</option><?php foreach ($courseOptions as $id => $name): ?><option value="<?= h($id) ?>"<?= (string) $f['course'] === (string) $id ? ' selected' : '' ?>><?= h($name) ?></option><?php endforeach; ?></select>
    <select name="status" aria-label="Filter by status"><?php foreach (['active' => 'Active', 'archived' => 'Archived', 'all' => 'All students'] as $k => $l): ?><option value="<?= $k ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <?php if ($sort !== 'name'): ?><input type="hidden" name="sort" value="<?= h($sort) ?>"><input type="hidden" name="dir" value="<?= h($dir) ?>"><?php endif; ?>
    <?php if ($f['q'] !== '' || $f['course'] || $f['status'] !== 'active'): ?><a class="btn btn-ghost btn-sm" href="<?= h(url('students.php')) ?>">Clear</a><?php endif; ?>
  </form>
  <?php if (!$rows): ?>
    <div class="empty"><?= icon('users') ?><h3>No students found</h3><p>Try a different search or clear the filters.</p></div>
  <?php else: ?>
  <div class="table-wrap"><table class="data">
    <thead><tr>
      <?= sort_th('Student', 'name', $sort, $dir, $query) ?>
      <th scope="col">Enrollment</th>
      <?= sort_th('Course', 'course', $sort, $dir, $query) ?>
      <?= sort_th('Sem', 'sem', $sort, $dir, $query) ?>
      <th scope="col">Status</th>
      <?= sort_th('Joined', 'joined', $sort, $dir, $query) ?>
      <th scope="col" class="right"><span class="sr">Actions</span></th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
        $data = ['student_id' => (int) $r['student_id'], 'full_name' => $r['full_name'], 'email' => $r['email'], 'course_id' => (int) $r['course_id'], 'enrollment_no' => $r['enrollment_no'] ?? '', 'semester' => (int) $r['semester'], 'phone' => $r['phone'] ?? '']; ?>
      <tr class="<?= $r['status'] === 'archived' ? 'is-archived' : '' ?>">
        <td><div class="cell-person"><?= avatar($r['full_name']) ?><div><strong><?= h($r['full_name']) ?></strong><small><?= h($r['email']) ?></small></div></div></td>
        <td class="nowrap"><?= h($r['enrollment_no'] ?: '—') ?></td>
        <td><?= h($r['course']) ?></td>
        <td><?= (int) $r['semester'] ?></td>
        <td><?= badge($r['status']) ?><?php if ($r['account_status'] === 'inactive' && $r['status'] === 'active'): ?> <?= badge('inactive') ?><?php endif; ?></td>
        <td class="nowrap muted"><?= h(fmt_dt($r['created_at'], 'j M Y')) ?></td>
        <td>
          <div class="actions">
            <?php if ($r['status'] === 'active'): ?>
              <button class="icon-btn sm" type="button" data-student-edit="<?= h(json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>" aria-label="Edit <?= h($r['full_name']) ?>" title="Edit"><?= icon('edit') ?></button>
              <form method="post" data-confirm="<?= h($r['full_name']) ?> will be archived and can no longer sign in. You can restore them later." data-confirm-title="Archive this student?" data-confirm-ok="Archive"><?= csrf_field() ?><input type="hidden" name="action" value="archive"><input type="hidden" name="student_id" value="<?= (int) $r['student_id'] ?>">
                <button class="icon-btn sm danger" type="submit" aria-label="Archive <?= h($r['full_name']) ?>" title="Archive"><?= icon('archive') ?></button></form>
            <?php else: ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="restore"><input type="hidden" name="student_id" value="<?= (int) $r['student_id'] ?>"><button class="icon-btn sm" type="submit" aria-label="Restore <?= h($r['full_name']) ?>" title="Restore"><?= icon('restore') ?></button></form>
              <form method="post" data-confirm="This permanently removes <?= h($r['full_name']) ?>, their login and all registrations. This cannot be undone." data-confirm-title="Delete permanently?" data-confirm-ok="Delete forever"><?= csrf_field() ?><input type="hidden" name="action" value="purge"><input type="hidden" name="student_id" value="<?= (int) $r['student_id'] ?>"><button class="icon-btn sm danger" type="submit" aria-label="Delete <?= h($r['full_name']) ?> permanently" title="Delete permanently"><?= icon('trash') ?></button></form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= pager($p, $query) ?>
  <?php endif; ?>
</div>

<dialog id="student-dialog" class="dialog wide" aria-labelledby="sd-title"<?= $errors ? ' data-autoopen' : '' ?>>
  <div class="dialog-in">
    <div class="dialog-head"><h2 id="sd-title"><?= $form['student_id'] ? 'Edit student' : 'Add student' ?></h2><button class="icon-btn sm" type="button" data-dialog-close aria-label="Close"><?= icon('x') ?></button></div>
    <form method="post" id="student-form" data-no-loading><?= csrf_field() ?>
      <input type="hidden" name="action" value="save"><input type="hidden" name="student_id" id="sd-id" value="<?= (int) $form['student_id'] ?>">
      <?php if ($errors): ?><div class="alert alert-danger" role="alert">Fix the highlighted fields and save again.</div><?php endif; ?>
      <div class="form-grid">
        <?= field('full_name', 'Full name', ['value' => $form['full_name'], 'error' => $errors['full_name'] ?? null, 'required' => true, 'pattern' => Rules::NAME_PATTERN, 'maxlength' => 100, 'msg' => 'Use 2–100 letters.']) ?>
        <?= field('email', 'Email', ['type' => 'email', 'value' => $form['email'], 'error' => $errors['email'] ?? null, 'required' => true, 'maxlength' => 254]) ?>
        <?= field('course_id', 'Course', ['type' => 'select', 'options' => $courseOptions, 'value' => (string) $form['course_id'], 'error' => $errors['course_id'] ?? null, 'required' => true]) ?>
        <?= field('semester', 'Semester', ['type' => 'select', 'options' => $semesters, 'value' => (string) $form['semester'], 'error' => $errors['semester'] ?? null, 'required' => true]) ?>
        <?= field('enrollment_no', 'Enrollment no.', ['value' => $form['enrollment_no'], 'error' => $errors['enrollment_no'] ?? null, 'pattern' => Rules::ENROLL_PATTERN, 'maxlength' => 20, 'hint' => 'For example D26DCS146.', 'msg' => 'Use 5–20 letters, numbers or hyphens.']) ?>
        <?= field('phone', 'Phone', ['type' => 'tel', 'value' => $form['phone'], 'error' => $errors['phone'] ?? null, 'pattern' => Rules::PHONE_PATTERN, 'maxlength' => 20, 'msg' => 'Use 7–20 digits; + and - are allowed.']) ?>
        <div class="full"><?= field('password', 'Password', ['type' => 'password', 'error' => $errors['password'] ?? null, 'pattern' => Rules::PASSWORD_PATTERN, 'maxlength' => 72, 'autocomplete' => 'new-password', 'hint' => 'Leave blank to generate a temporary password.', 'msg' => 'Use 8–72 characters with an uppercase letter, a lowercase letter and a number.']) ?></div>
      </div>
      <div class="dialog-actions"><button class="btn btn-ghost" type="button" data-dialog-close>Cancel</button><button class="btn btn-primary" type="submit">Save student</button></div>
    </form>
  </div>
</dialog>
<?php render_footer();
