<?php
// Post-password-reset landing used by CandidateAuthController. The old
// Modlus "temporary test dashboard" is gone: employees go straight to the
// employee panel dashboard.
require_once __DIR__ . '/../includes/auth-functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['candidateId'])) {
    redirectTo('candidate-login');
}

if (!empty($_SESSION['candidateForceReset'])) {
    redirectTo('candidate-reset-password');
}

redirectTo('emp-dashboard');
