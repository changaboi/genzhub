<?php
declare(strict_types=1);
require __DIR__ . '/../includes/api.php';

api_method(['POST']);
api_csrf();
api_user();                                   // current_user() refreshes last_activity
api_ok(['remaining' => (int) cfg('session_timeout')]);
