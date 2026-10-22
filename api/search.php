<?php
declare(strict_types=1);
/** Command-palette search: students (admin only) and events. */
require __DIR__ . '/../includes/api.php';

api_method(['GET']);
$user = api_user();
$term = clean($_GET['q'] ?? '', 60);
if (mb_strlen($term) < 2) {
    api_ok([]);
}
$like = '%' . addcslashes($term, '%_\\') . '%';
$out = [];

$events = q_all("SELECT event_id, title FROM events WHERE title LIKE ? " . ($user['role'] === 'admin' ? '' : "AND status IN ('published','closed')") . ' ORDER BY starts_at DESC LIMIT 5', [$like]);
foreach ($events as $e) {
    $out[] = ['type' => 'Event', 'label' => $e['title'], 'href' => $user['role'] === 'admin' ? 'manage-events.php?q=' . urlencode($e['title']) : 'events.php?q=' . urlencode($e['title'])];
}
if ($user['role'] === 'admin') {
    $students = q_all("SELECT s.student_id, s.full_name, u.email FROM students s JOIN users u ON u.user_id = s.user_id WHERE s.full_name LIKE ? OR u.email LIKE ? OR s.enrollment_no LIKE ? ORDER BY s.full_name LIMIT 6", [$like, $like, $like]);
    foreach ($students as $s) {
        $out[] = ['type' => 'Student', 'label' => $s['full_name'] . ' — ' . $s['email'], 'href' => 'students.php?q=' . urlencode($s['email'])];
    }
}
api_ok($out);
