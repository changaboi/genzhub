<?php
declare(strict_types=1);
/**
 * Students JSON API (admin only) — Practical 13, same conventions as api/events.php.
 *   GET    ?q=&course=&status=active|archived|all&page=&per=
 *   GET    ?id=3
 *   POST   {full_name,email,course_id,semester,[enrollment_no,phone,password]}  -> 201 (+ temp_password when generated)
 *   PUT|PATCH ?id=3 {...}
 *   DELETE ?id=3           soft delete (archive); add &permanent=1 to purge an archived student
 */
require __DIR__ . '/../includes/api.php';

$method = api_method(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);
$user = api_user('admin');
if ($method !== 'GET') api_csrf();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($method === 'GET') {
    if ($id > 0) {
        $row = q_one('SELECT ' . STUDENT_COLUMNS . STUDENT_FROM . ' WHERE s.student_id = ?', [$id]);
        $row ?? api_fail(404, 'not_found', 'Student not found.');
        api_ok($row);
    }
    [$where, $params] = student_filter(['q' => clean($_GET['q'] ?? '', 80), 'course' => $_GET['course'] ?? '', 'status' => $_GET['status'] ?? 'active']);
    $per = api_int('per', 10, 1, 100);
    $total = (int) q_val('SELECT COUNT(*)' . STUDENT_FROM . " WHERE $where", $params);
    $p = paginate($total, api_int('page', 1), $per);
    $rows = q_all('SELECT ' . STUDENT_COLUMNS . STUDENT_FROM . " WHERE $where ORDER BY s.full_name LIMIT $per OFFSET {$p['offset']}", $params);
    api_ok($rows, 200, ['total' => $total, 'page' => $p['page'], 'pages' => $p['pages'], 'per' => $per]);
}

if ($method === 'POST') {
    [$v, $err] = validate_student(api_body());
    if ($err) api_fail(422, 'validation', 'Some fields need attention.', $err);
    [$sid, $plain] = create_student($v);
    $row = q_one('SELECT ' . STUDENT_COLUMNS . STUDENT_FROM . ' WHERE s.student_id = ?', [$sid]);
    if ($v['password'] === '') $row['temp_password'] = $plain;
    api_ok($row, 201);
}

$id > 0 || api_fail(400, 'missing_id', 'Add ?id=<student id> to the URL.');
$old = q_one('SELECT s.*, u.email FROM students s JOIN users u ON u.user_id = s.user_id WHERE s.student_id = ?', [$id]);
$old ?? api_fail(404, 'not_found', 'Student not found.');

if ($method === 'DELETE') {
    if (!empty($_GET['permanent'])) {
        purge_student($id) || api_fail(409, 'not_archived', 'Only archived students can be deleted permanently.');
        api_ok(['purged' => $id]);
    }
    archive_student($id) || api_fail(409, 'already_archived', 'This student is already archived.');
    api_ok(['archived' => $id]);
}

$input = api_body();
if ($method === 'PATCH') {
    $input += ['full_name' => $old['full_name'], 'email' => $old['email'], 'course_id' => $old['course_id'], 'enrollment_no' => $old['enrollment_no'] ?? '', 'semester' => $old['semester'], 'phone' => $old['phone'] ?? ''];
}
[$v, $err] = validate_student($input, $id);
if ($err) api_fail(422, 'validation', 'Some fields need attention.', $err);
update_student($id, $v);
api_ok(q_one('SELECT ' . STUDENT_COLUMNS . STUDENT_FROM . ' WHERE s.student_id = ?', [$id]));
