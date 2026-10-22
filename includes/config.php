<?php
declare(strict_types=1);

/**
 * StudentHub configuration. Defaults match a fresh XAMPP install
 * (user "root", empty password). Override with environment variables
 * or edit the fallbacks below.
 */
return [
    'db' => [
        'host'     => getenv('STUDENTHUB_DB_HOST') ?: '127.0.0.1',
        'port'     => (int) (getenv('STUDENTHUB_DB_PORT') ?: 3306),
        'name'     => getenv('STUDENTHUB_DB_NAME') ?: 'studenthub',
        'user'     => getenv('STUDENTHUB_DB_USER') ?: 'root',
        'password' => getenv('STUDENTHUB_DB_PASSWORD') !== false ? getenv('STUDENTHUB_DB_PASSWORD') : '',
    ],
    'timezone'        => getenv('STUDENTHUB_TZ') ?: 'Asia/Kolkata',
    'session_timeout' => (int) (getenv('STUDENTHUB_SESSION_TIMEOUT') ?: 1800), // seconds of inactivity
    'remember_days'   => 30,
    'max_login_fails' => 5,     // per email, inside the lockout window
    'lockout_minutes' => 15,
    'poster_max_bytes' => 2 * 1024 * 1024,
    'base_url'        => getenv('STUDENTHUB_BASE_URL') ?: null, // null = auto-detect (e.g. "/studenthub")
    'debug'           => getenv('STUDENTHUB_DEBUG') === '1',
];
