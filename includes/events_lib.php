<?php
declare(strict_types=1);

/** Event domain logic shared by manage-events.php (HTML) and api/events.php (JSON). */

const EVENT_STATUSES = ['draft', 'published', 'closed', 'cancelled'];
const EVENT_SELECT = 'SELECT event_id, title, description, location, starts_at, ends_at, capacity, status, poster_path, category_id, category, registered_count, seats_left FROM v_event_summary';

/** MySQL returns SUM()/GREATEST() as strings; give API clients real numbers. */
function event_row(?array $r): ?array
{
    if ($r === null) return null;
    foreach (['event_id', 'capacity', 'category_id', 'registered_count', 'seats_left'] as $k) {
        if (isset($r[$k])) $r[$k] = (int) $r[$k];
    }
    return $r;
}

function parse_dt(string $v): ?string
{
    foreach (['Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i:s'] as $fmt) {
        $d = DateTime::createFromFormat($fmt, $v);
        if ($d !== false && $d->format($fmt) === $v) {
            return $d->format('Y-m-d H:i:s');
        }
    }
    return null;
}

/** @return array{0: array, 1: array} [clean values, errors] */
function validate_event(array $in): array
{
    $v = [
        'title' => clean($in['title'] ?? '', 150),
        'category_id' => (int) ($in['category_id'] ?? 0),
        'description' => clean($in['description'] ?? '', 2000),
        'location' => clean($in['location'] ?? '', 150),
        'starts_at' => clean($in['starts_at'] ?? '', 25),
        'ends_at' => clean($in['ends_at'] ?? '', 25),
        'capacity' => (int) ($in['capacity'] ?? 0),
        'status' => clean($in['status'] ?? 'published', 20),
    ];
    $err = [];
    $len = mb_strlen($v['title']);
    if ($len < 3 || $len > 150) $err['title'] = 'Title must be 3–150 characters.';
    if (q_val('SELECT COUNT(*) FROM event_categories WHERE category_id = ?', [$v['category_id']]) < 1) $err['category_id'] = 'Choose a category.';
    if (mb_strlen($v['description']) < 10) $err['description'] = 'Describe the event in at least 10 characters.';
    if (mb_strlen($v['location']) < 2) $err['location'] = 'Enter the venue.';
    $start = parse_dt($v['starts_at']);
    if ($start === null) $err['starts_at'] = 'Enter a valid start date and time.';
    $end = $v['ends_at'] === '' ? null : parse_dt($v['ends_at']);
    if ($v['ends_at'] !== '' && $end === null) $err['ends_at'] = 'Enter a valid end date and time.';
    if ($start && $end && $end <= $start) $err['ends_at'] = 'The event must end after it starts.';
    if ($v['capacity'] < 1 || $v['capacity'] > 5000) $err['capacity'] = 'Capacity must be between 1 and 5000.';
    if (!in_array($v['status'], EVENT_STATUSES, true)) $err['status'] = 'Choose a valid status.';
    $v['starts_at'] = $start ?? $v['starts_at'];
    $v['ends_at'] = $end;
    return [$v, $err];
}

/** Field-by-field difference for the audit log. */
function event_diff(array $old, array $new): array
{
    $out = [];
    foreach (['title', 'category_id', 'description', 'location', 'starts_at', 'ends_at', 'capacity', 'status'] as $k) {
        if ((string) ($old[$k] ?? '') !== (string) ($new[$k] ?? '')) {
            $out[$k] = $k === 'description' ? 'changed' : ['from' => $old[$k] ?? null, 'to' => $new[$k] ?? null];
        }
    }
    return $out;
}

function insert_event(array $v, ?string $poster, int $userId): int
{
    q('INSERT INTO events (category_id, title, description, location, starts_at, ends_at, capacity, status, poster_path, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$v['category_id'], $v['title'], $v['description'], $v['location'], $v['starts_at'], $v['ends_at'], $v['capacity'], $v['status'], $poster, $userId]);
    $id = (int) db()->insert_id;
    audit('event.created', 'event', $id, ['title' => $v['title']]);
    return $id;
}

function update_event(int $id, array $v, array $old, ?string $poster = null): void
{
    q('UPDATE events SET category_id = ?, title = ?, description = ?, location = ?, starts_at = ?, ends_at = ?, capacity = ?, status = ?, poster_path = COALESCE(?, poster_path) WHERE event_id = ?',
        [$v['category_id'], $v['title'], $v['description'], $v['location'], $v['starts_at'], $v['ends_at'], $v['capacity'], $v['status'], $poster, $id]);
    $diff = event_diff($old, $v);
    if ($poster !== null) $diff['poster'] = 'replaced';
    audit('event.updated', 'event', $id, $diff);
}

function delete_event(int $id): bool
{
    $row = q_one('SELECT title, poster_path FROM events WHERE event_id = ?', [$id]);
    if (!$row) return false;
    q('DELETE FROM events WHERE event_id = ?', [$id]);
    remove_poster($row['poster_path']);
    audit('event.deleted', 'event', $id, ['title' => $row['title']]);
    return true;
}

/* ------------------------------------------------------------ poster upload */

/**
 * Validates and stores an uploaded poster. Checks: upload error, size, real MIME via
 * finfo (not the client-sent type), that GD can decode it as an image, and
 * re-saves under a random filename so user input never touches the file system path.
 * @return array{0: ?string, 1: ?string} [relative path|null, error|null]
 */
function save_poster(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, $file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE ? 'The poster is larger than the server allows.' : 'The upload failed. Try again.'];
    }
    $max = (int) cfg('poster_max_bytes');
    if ($file['size'] > $max) {
        return [null, 'Poster must be ' . ($max / 1048576) . ' MB or smaller (yours is ' . round($file['size'] / 1048576, 1) . ' MB).'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return [null, 'Invalid upload.'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if ($ext === null) {
        return [null, 'Only JPEG, PNG or WebP images are accepted.'];
    }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false || $info[0] < 200 || $info[1] < 100 || $info[0] > 6000 || $info[1] > 6000) {
        return [null, 'The image is unreadable or its dimensions are out of range (min 200×100, max 6000×6000).'];
    }
    $dir = __DIR__ . '/../uploads/posters';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return [null, 'The upload folder is not writable.'];
    }
    $name = bin2hex(random_bytes(16)) . '.' . $ext;     // image renaming: never trust the client filename
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return [null, 'The poster could not be saved. Check folder permissions.'];
    }
    @chmod($dir . '/' . $name, 0644);
    return ['uploads/posters/' . $name, null];
}

function remove_poster(?string $path): void
{
    if ($path && preg_match('#^uploads/posters/[a-f0-9]{32}\.(jpg|png|webp)$#', $path)) {   // never touch seed images or odd paths
        @unlink(__DIR__ . '/../' . $path);
    }
}

function event_filter(array $f, bool $isAdmin): array
{
    $where = ['1=1'];
    $params = [];
    if (!$isAdmin) $where[] = "status <> 'draft'";
    if (($f['q'] ?? '') !== '') {
        $like = '%' . addcslashes($f['q'], '%_\\') . '%';
        $where[] = '(title LIKE ? OR location LIKE ? OR category LIKE ?)';
        array_push($params, $like, $like, $like);
    }
    if (!empty($f['category'])) { $where[] = 'category_id = ?'; $params[] = (int) $f['category']; }
    $state = $f['state'] ?? 'all';
    if ($state === 'open') { $where[] = "status = 'published' AND starts_at >= NOW()"; }
    elseif ($state === 'closed') { $where[] = "(status IN ('closed','cancelled') OR starts_at < NOW())"; }
    elseif (in_array($state, EVENT_STATUSES, true)) { $where[] = 'status = ?'; $params[] = $state; }
    return [implode(' AND ', $where), $params];
}
