<?php
declare(strict_types=1);
/**
 * POST {"event_id":1,"action":"register"|"cancel"}  (students only)
 * Registration runs through the stored procedure sp_register_for_event, which locks
 * the event row, checks status/date/capacity/duplicates and inserts atomically.
 */
require __DIR__ . '/../includes/api.php';

api_method(['POST']);
api_csrf();
$user = api_user('student');
$in = api_body();
$eventId = (int) ($in['event_id'] ?? 0);
$action = (string) ($in['action'] ?? '');
if ($eventId < 1 || !in_array($action, ['register', 'cancel'], true)) {
    api_fail(422, 'validation', 'Provide a valid event_id and an action of "register" or "cancel".');
}
$studentId = (int) $user['student_id'];

if ($action === 'register') {
    $stmt = db()->prepare('CALL sp_register_for_event(?, ?, @result)');
    $stmt->bind_param('ii', $studentId, $eventId);
    $stmt->execute();
    $stmt->close();
    $result = (string) q_val('SELECT @result');
    $messages = [
        'ok' => 'You are registered. See you there!',
        'duplicate' => 'You are already registered for this event.',
        'full' => 'This event is full.',
        'closed' => 'Registration for this event is closed.',
        'not_found' => 'That event does not exist.',
    ];
    if ($result !== 'ok') {
        api_fail($result === 'not_found' ? 404 : ($result === 'error' ? 500 : 409), $result, $messages[$result] ?? 'Could not register.');
    }
    audit('registration.created', 'event', $eventId, ['student_id' => $studentId]);
} else {
    $stmt = q("UPDATE registrations r JOIN events e ON e.event_id = r.event_id
               SET r.status = 'cancelled'
               WHERE r.student_id = ? AND r.event_id = ? AND r.status = 'registered' AND e.starts_at > NOW()", [$studentId, $eventId]);
    if ($stmt->affected_rows < 1) {
        api_fail(409, 'not_cancellable', 'You have no active registration to cancel for this event.');
    }
    audit('registration.cancelled', 'event', $eventId, ['student_id' => $studentId]);
}
$e = q_one('SELECT event_id, registered_count, seats_left, capacity FROM v_event_summary WHERE event_id = ?', [$eventId]);
api_ok(['event' => event_row($e), 'action' => $action, 'message' => $action === 'register' ? 'You are registered. See you there!' : 'Your seat was released.'], $action === 'register' ? 201 : 200);
