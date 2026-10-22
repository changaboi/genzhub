<?php
declare(strict_types=1);

/** Practical 7 storage: every message is written to BOTH storage/submissions.csv and storage/submissions.json. */

const MSG_CSV = __DIR__ . '/../storage/submissions.csv';
const MSG_JSON = __DIR__ . '/../storage/submissions.json';
const MSG_COLUMNS = ['id', 'submitted_at', 'name', 'email', 'subject', 'message', 'ip'];

function store_message(array $rec): bool
{
    $dir = dirname(MSG_CSV);
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        return false;
    }
    // --- CSV: append one row under an exclusive lock; formulas are neutralised
    $new = !is_file(MSG_CSV) || filesize(MSG_CSV) === 0;
    $csv = @fopen(MSG_CSV, 'ab');
    if ($csv === false || !flock($csv, LOCK_EX)) {
        return false;
    }
    if ($new) {
        fputcsv($csv, MSG_COLUMNS, ',', '"', '');
    }
    fputcsv($csv, array_map(static fn($k) => csv_safe($rec[$k] ?? ''), MSG_COLUMNS), ',', '"', '');
    fflush($csv);
    flock($csv, LOCK_UN);
    fclose($csv);

    // --- JSON: read, append, rewrite atomically under the same kind of lock
    $fh = @fopen(MSG_JSON, 'c+b');
    if ($fh === false || !flock($fh, LOCK_EX)) {
        return false;
    }
    $raw = stream_get_contents($fh);
    $all = $raw === '' || $raw === false ? [] : json_decode($raw, true);
    if (!is_array($all)) {
        flock($fh, LOCK_UN);
        fclose($fh);
        return false;                       // refuse to overwrite a file we cannot parse
    }
    $all[] = $rec;
    $out = json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    rewind($fh);
    ftruncate($fh, 0);
    $ok = fwrite($fh, $out) === strlen($out);
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return $ok;
}

/** @return array<int, array<string,string>> newest first */
function read_messages(string $format = 'json'): array
{
    $rows = [];
    if ($format === 'csv') {
        if (!is_file(MSG_CSV) || ($fh = fopen(MSG_CSV, 'rb')) === false) return [];
        flock($fh, LOCK_SH);
        $head = fgetcsv($fh, 0, ',', '"', '');
        while ($head && ($r = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            if (count($r) === count($head)) $rows[] = array_combine($head, $r);
        }
        flock($fh, LOCK_UN);
        fclose($fh);
    } else {
        $data = is_file(MSG_JSON) ? json_decode((string) file_get_contents(MSG_JSON), true) : [];
        $rows = is_array($data) ? $data : [];
    }
    return array_reverse($rows);
}
