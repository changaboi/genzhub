<?php
declare(strict_types=1);

/** Shared configuration accessor. */
function cfg(string $key, mixed $default = null): mixed
{
    static $config = null;
    $config ??= require __DIR__ . '/config.php';
    $value = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/**
 * MySQLi connection (used by registration, login, students, events).
 * Strict error mode turns SQL errors into exceptions, which the page
 * handlers catch and log without exposing details to the visitor.
 */
function db(): mysqli
{
    static $conn = null;
    if ($conn instanceof mysqli) {
        return $conn;
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $conn = new mysqli(cfg('db.host'), cfg('db.user'), cfg('db.password'), cfg('db.name'), cfg('db.port'));
        $conn->set_charset('utf8mb4');
        $conn->query("SET time_zone = '" . (new DateTime('now', new DateTimeZone(cfg('timezone'))))->format('P') . "'");
    } catch (mysqli_sql_exception $e) {
        error_log('StudentHub DB connect failed: ' . $e->getMessage());
        throw new RuntimeException('The database is not reachable. Start MySQL and import database/studenthub.sql.', 0, $e);
    }
    return $conn;
}

/** PDO connection (used by the connection test and the PDO/prepared-statement demo). */
function pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', cfg('db.host'), cfg('db.port'), cfg('db.name'));
    try {
        $pdo = new PDO($dsn, cfg('db.user'), cfg('db.password'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('StudentHub PDO connect failed: ' . $e->getMessage());
        throw new RuntimeException('The database is not reachable. Start MySQL and import database/studenthub.sql.', 0, $e);
    }
    return $pdo;
}

/**
 * Prepared-statement helper: run a query with bound params and return the
 * mysqli_stmt. Types are inferred (int => i, float => d, everything else => s).
 */
function q(string $sql, array $params = []): mysqli_stmt
{
    $stmt = db()->prepare($sql);
    if ($params) {
        $types = '';
        foreach ($params as $p) {
            $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
        }
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

/** All rows of a prepared SELECT. */
function q_all(string $sql, array $params = []): array
{
    return q($sql, $params)->get_result()->fetch_all(MYSQLI_ASSOC);
}

/** First row of a prepared SELECT, or null. */
function q_one(string $sql, array $params = []): ?array
{
    return q($sql, $params)->get_result()->fetch_assoc() ?: null;
}

/** First column of the first row. */
function q_val(string $sql, array $params = []): mixed
{
    $row = q($sql, $params)->get_result()->fetch_row();
    return $row[0] ?? null;
}
