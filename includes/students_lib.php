<?php
declare(strict_types=1);

/** Student/user domain logic shared by students.php (HTML) and api/students.php (JSON). */

const STUDENT_COLUMNS = "s.student_id, s.user_id, s.full_name, s.enrollment_no, s.semester, s.phone, s.status, s.archived_at, s.created_at,
    u.email, u.status AS account_status, u.last_login_at, c.course_id, c.name AS course, c.code AS course_code";
const STUDENT_FROM = " FROM students s JOIN users u ON u.user_id = s.user_id JOIN courses c ON c.course_id = s.course_id";

/** @return array{0: array, 1: array} [clean values, errors] */
function validate_student(array $in, ?int $editingStudentId = null, bool $needPassword = false): array
{
    $v = [
        'full_name' => clean($in['full_name'] ?? '', 100),
        'email' => mb_strtolower(clean($in['email'] ?? '', 254)),
        'course_id' => (int) ($in['course_id'] ?? 0),
        'enrollment_no' => strtoupper(clean($in['enrollment_no'] ?? '', 20)),
        'semester' => (int) ($in['semester'] ?? 1),
        'phone' => clean($in['phone'] ?? '', 20),
        'password' => (string) ($in['password'] ?? ''),
    ];
    $err = [];
    if ($m = Rules::name($v['full_name'])) $err['full_name'] = $m;
    if ($m = Rules::email($v['email'])) $err['email'] = $m;
    if (q_val('SELECT COUNT(*) FROM courses WHERE course_id = ?', [$v['course_id']]) < 1) $err['course_id'] = 'Choose a valid course.';
    if ($m = Rules::enrollment($v['enrollment_no'])) $err['enrollment_no'] = $m;
    if ($v['semester'] < 1 || $v['semester'] > 8) $err['semester'] = 'Semester must be between 1 and 8.';
    if ($m = Rules::phone($v['phone'])) $err['phone'] = $m;
    if ($v['password'] !== '' || $needPassword) {
        if ($m = Rules::password($v['password'])) $err['password'] = $m;
    }
    if (!isset($err['email'])) {
        $own = $editingStudentId ? (int) q_val('SELECT user_id FROM students WHERE student_id = ?', [$editingStudentId]) : 0;
        if (q_val('SELECT COUNT(*) FROM users WHERE email = ? AND user_id <> ?', [$v['email'], $own]) > 0) {
            $err['email'] = 'Another account already uses this email.';
        }
    }
    if ($v['enrollment_no'] !== '' && !isset($err['enrollment_no'])) {
        if (q_val('SELECT COUNT(*) FROM students WHERE enrollment_no = ? AND student_id <> ?', [$v['enrollment_no'], (int) $editingStudentId]) > 0) {
            $err['enrollment_no'] = 'This enrollment number is already assigned.';
        }
    }
    return [$v, $err];
}

function random_password(): string
{
    $chars = 'abcdefghjkmnpqrstuvwxyz';
    $pw = strtoupper($chars[random_int(0, strlen($chars) - 1)]) . $chars[random_int(0, strlen($chars) - 1)];
    for ($i = 0; $i < 6; $i++) $pw .= $chars[random_int(0, strlen($chars) - 1)];
    return $pw . random_int(10, 99);
}

/** Creates the login account and the student profile in one transaction. Returns [student_id, plain password]. */
function create_student(array $v): array
{
    $conn = db();
    $plain = $v['password'] !== '' ? $v['password'] : random_password();
    try {
        $conn->begin_transaction();
        q("INSERT INTO users (email, password_hash, role) VALUES (?, ?, 'student')", [$v['email'], password_hash($plain, PASSWORD_DEFAULT)]);
        $uid = (int) $conn->insert_id;
        q('INSERT INTO students (user_id, course_id, enrollment_no, full_name, semester, phone) VALUES (?, ?, ?, ?, ?, ?)',
            [$uid, $v['course_id'], $v['enrollment_no'] !== '' ? $v['enrollment_no'] : null, $v['full_name'], $v['semester'], $v['phone'] !== '' ? $v['phone'] : null]);
        $sid = (int) $conn->insert_id;
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
    audit('student.created', 'student', $sid, ['email' => $v['email'], 'name' => $v['full_name']]);
    return [$sid, $plain];
}

function update_student(int $sid, array $v): void
{
    $old = q_one('SELECT s.*, u.email FROM students s JOIN users u ON u.user_id = s.user_id WHERE s.student_id = ?', [$sid]);
    $conn = db();
    try {
        $conn->begin_transaction();
        q('UPDATE users SET email = ? WHERE user_id = ?', [$v['email'], (int) $old['user_id']]);
        q('UPDATE students SET course_id = ?, enrollment_no = ?, full_name = ?, semester = ?, phone = ? WHERE student_id = ?',
            [$v['course_id'], $v['enrollment_no'] !== '' ? $v['enrollment_no'] : null, $v['full_name'], $v['semester'], $v['phone'] !== '' ? $v['phone'] : null, $sid]);
        if ($v['password'] !== '') {
            q('UPDATE users SET password_hash = ? WHERE user_id = ?', [password_hash($v['password'], PASSWORD_DEFAULT), (int) $old['user_id']]);
        }
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
    $changes = [];
    foreach (['full_name', 'email', 'course_id', 'enrollment_no', 'semester', 'phone'] as $k) {
        if ((string) ($old[$k] ?? '') !== (string) $v[$k]) $changes[$k] = ['from' => $old[$k] ?? null, 'to' => $v[$k]];
    }
    if ($v['password'] !== '') $changes['password'] = 'reset';
    audit('student.updated', 'student', $sid, $changes);
}

/** Soft delete: row stays, status flips to archived and the account can no longer sign in. */
function archive_student(int $sid): bool
{
    $s = q("UPDATE students SET status = 'archived', archived_at = NOW() WHERE student_id = ? AND status = 'active'", [$sid]);
    if ($s->affected_rows < 1) return false;
    q("UPDATE users u JOIN students s ON s.user_id = u.user_id SET u.status = 'inactive' WHERE s.student_id = ?", [$sid]);
    audit('student.archived', 'student', $sid);
    return true;
}

function restore_student(int $sid): bool
{
    $s = q("UPDATE students SET status = 'active', archived_at = NULL WHERE student_id = ? AND status = 'archived'", [$sid]);
    if ($s->affected_rows < 1) return false;
    q("UPDATE users u JOIN students s ON s.user_id = u.user_id SET u.status = 'active' WHERE s.student_id = ?", [$sid]);
    audit('student.restored', 'student', $sid);
    return true;
}

/** Permanent delete is only allowed for archived students (two-step safety). */
function purge_student(int $sid): bool
{
    $row = q_one("SELECT user_id FROM students WHERE student_id = ? AND status = 'archived'", [$sid]);
    if (!$row) return false;
    q('DELETE FROM users WHERE user_id = ?', [(int) $row['user_id']]);    // cascades to students + registrations
    audit('student.purged', 'student', $sid);
    return true;
}

/**
 * Filtered student query shared by the list page, CSV export and the API.
 * @return array{0: string, 1: array} [WHERE sql, params]
 */
function student_filter(array $f): array
{
    $where = ['1=1'];
    $params = [];
    if (($f['q'] ?? '') !== '') {
        $like = '%' . addcslashes($f['q'], '%_\\') . '%';
        $where[] = '(s.full_name LIKE ? OR u.email LIKE ? OR c.name LIKE ? OR s.enrollment_no LIKE ?)';
        array_push($params, $like, $like, $like, $like);
    }
    if (!empty($f['course'])) {
        $where[] = 's.course_id = ?';
        $params[] = (int) $f['course'];
    }
    $status = $f['status'] ?? 'active';
    if ($status === 'active' || $status === 'archived') {
        $where[] = 's.status = ?';
        $params[] = $status;
    }
    return [implode(' AND ', $where), $params];
}
