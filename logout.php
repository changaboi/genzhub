<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_post();
require_csrf();
logout_user();
session_start();
flash('success', 'You have been signed out.');
redirect('login.php');
