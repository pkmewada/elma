<?php
require_once __DIR__ . '/auth-functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['candidateId'])) {
    redirectTo('candidate-login');
}

// A temporary password must be replaced before any employee page is usable.
if (!empty($_SESSION['candidateForceReset'])) {
    redirectTo('candidate-reset-password');
}
