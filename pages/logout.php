<?php
require_once __DIR__ . '/../includes/auth-functions.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

destroySession();
redirectTo('login');
