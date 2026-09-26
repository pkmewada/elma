<?php
require_once __DIR__ . '/auth-functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['candidateId'])) {
    redirectTo('candidate-login');
}
