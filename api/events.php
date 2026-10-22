<?php
declare(strict_types=1);
/**
 * Events JSON API (Practical 13).
 *   GET    api/events.php?q=&category=&state=open|closed|all&page=&per=   list (signed-in users)
 *   GET    api/events.php?id=7                                           one event
 *   POST   api/events.php            {title,...}                          create        (admin)  -> 201
 *   PUT    api/events.php?id=7       {all fields}                         replace       (admin)
 *   PATCH  api/events.php?id=7       {any fields}                         partial edit  (admin)
 *   DELETE api/events.php?id=7                                            delete        (admin)
 * Writes require JSON bodies and the X-CSRF-Token header.
 */
require __DIR__ . '/../includes/api.php';

$method = api_method(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);
$user = api_user();
$isAdmin = $user['role'] === 'admin';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($method === 'GET') {
    if ($id > 0) {
        $row = q_one(EVENT_SELECT . ' WHERE event_id = ?' . ($isAdmin ? '' : " AND status <> 'draft'"), [$id]);
        $row ?? api_fail(404, 'not_found', 'Event not found.');
        api_ok(event_row($row));
    }
    [$where, $params] = event_filter([
        'q' => clean($_GET['q'] ?? '', 80), 'category' => $_GET['category'] ?? '', 'state' => $_GET['state'] ?? 'all',
    ], $isAdmin);
    $per = api_int('per', 10, 1, 50);
    $total = (int) q_val("SELECT COUNT(*) FROM v_event_summary WHERE $where", $params);
    $p = paginate($total, api_int('page', 1), $per);
    $rows = q_all(EVENT_SELECT . " WHERE $where ORDER BY starts_at DESC, event_id DESC LIMIT $per OFFSET {$p['offset']}", $params);
    api_ok(array_map('event_row', $rows), 200, ['total' => $total, 'page' => $p['page'], 'pages' => $p['pages'], 'per' => $per]);
}

// ---- writes: admin only, CSRF, JSON ----
api_user('admin');
api_csrf();

if ($method === 'POST') {
    [$v, $err] = validate_event(api_body());
    if ($err) api_fail(422, 'validation', 'Some fields need attention.', $err);
    $newId = insert_event($v, null, (int) $user['user_id']);
    header('Location: ' . url('api/events.php?id=' . $newId));
    api_ok(event_row(q_one(EVENT_SELECT . ' WHERE event_id = ?', [$newId])), 201);
}

$id > 0 || api_fail(400, 'missing_id', 'Add ?id=<event id> to the URL.');
$old = q_one('SELECT * FROM events WHERE event_id = ?', [$id]);
$old ?? api_fail(404, 'not_found', 'Event not found.');

if ($method === 'DELETE') {
    delete_event($id);
    api_ok(['deleted' => $id]);
}

$input = api_body();
if ($method === 'PATCH') {
    $input += [
        'title' => $old['title'], 'category_id' => $old['category_id'], 'description' => $old['description'], 'location' => $old['location'],
        'starts_at' => $old['starts_at'], 'ends_at' => $old['ends_at'] ?? '', 'capacity' => $old['capacity'], 'status' => $old['status'],
    ];
}
[$v, $err] = validate_event($input);
if ($err) api_fail(422, 'validation', 'Some fields need attention.', $err);
$taken = (int) q_val("SELECT COUNT(*) FROM registrations WHERE event_id = ? AND status IN ('registered','attended')", [$id]);
if ($v['capacity'] < $taken) {
    api_fail(422, 'validation', 'Some fields need attention.', ['capacity' => "$taken students are already registered; capacity cannot go below that."]);
}
update_event($id, $v, $old);
api_ok(event_row(q_one(EVENT_SELECT . ' WHERE event_id = ?', [$id])));
