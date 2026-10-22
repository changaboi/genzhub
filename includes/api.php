<?php
declare(strict_types=1);

/**
 * Tiny JSON API kernel (Practical 13).
 *  - every response is {"ok":true,"data":...,"meta":...} or {"ok":false,"error":{"code","message","fields"}}
 *  - proper HTTP status codes: 200/201/204, 400, 401, 403, 404, 405, 409, 415, 419, 422, 500
 *  - one central exception handler, so endpoints just throw ApiException
 */
define('STUDENTHUB_API', true);
require_once __DIR__ . '/bootstrap.php';

final class ApiException extends RuntimeException
{
    public function __construct(public readonly int $status, public readonly string $errorCode, string $message, public readonly array $fields = [])
    {
        parent::__construct($message);
    }
}

function api_send(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if ($status === 204) {
        exit;
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function api_ok(mixed $data, int $status = 200, array $meta = []): never
{
    api_send($status, ['ok' => true, 'data' => $data] + ($meta ? ['meta' => $meta] : []));
}

function api_fail(int $status, string $code, string $message, array $fields = []): never
{
    throw new ApiException($status, $code, $message, $fields);
}

set_exception_handler(static function (Throwable $e): never {
    if ($e instanceof ApiException) {
        $err = ['code' => $e->errorCode, 'message' => $e->getMessage()];
        if ($e->fields) {
            $err['fields'] = $e->fields;
        }
        api_send($e->status, ['ok' => false, 'error' => $err]);
    }
    if ($e instanceof mysqli_sql_exception && $e->getCode() === 1062) {
        api_send(409, ['ok' => false, 'error' => ['code' => 'duplicate', 'message' => 'That value already exists.']]);
    }
    error_log('API error: ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    api_send(500, ['ok' => false, 'error' => ['code' => 'server_error', 'message' => cfg('debug') ? $e->getMessage() : 'Unexpected server error.']]);
});

function api_user(?string $role = null): array
{
    $u = current_user();
    if ($u === null) {
        api_fail(401, 'unauthenticated', 'Sign in to use this endpoint.');
    }
    if ($role !== null && $u['role'] !== $role) {
        api_fail(403, 'forbidden', 'This action needs ' . $role . ' access.');
    }
    return $u;
}

function api_method(array $allowed): string
{
    $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($m, $allowed, true)) {
        header('Allow: ' . implode(', ', $allowed));
        api_fail(405, 'method_not_allowed', 'Use ' . implode(' or ', $allowed) . ' for this endpoint.');
    }
    return $m;
}

/** CSRF for state-changing requests: header X-CSRF-Token (sent by assets/js/app.js). */
function api_csrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!csrf_valid(is_string($token) ? $token : null)) {
        api_fail(419, 'csrf', 'Security token missing or expired. Refresh the page.');
    }
}

function api_body(): array
{
    $type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (!str_contains(strtolower($type), 'application/json')) {
        api_fail(415, 'unsupported_media_type', 'Send the request body as application/json.');
    }
    $raw = file_get_contents('php://input') ?: '';
    if (strlen($raw) > 65536) {
        api_fail(400, 'too_large', 'Request body is too large.');
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        api_fail(400, 'invalid_json', 'The request body is not valid JSON.');
    }
    return $data;
}

function api_int(string $key, int $default, int $min = 1, int $max = PHP_INT_MAX): int
{
    return min($max, max($min, (int) ($_GET[$key] ?? $default)));
}
