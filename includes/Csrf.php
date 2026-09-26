<?php

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function generateCsrfToken(): string
{
    if (empty($_SESSION['csrfToken'])) {
        $_SESSION['csrfToken'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrfToken'];
}

function regenerateCsrfToken(): string
{
    $_SESSION['csrfToken'] = bin2hex(random_bytes(32));

    return $_SESSION['csrfToken'];
}

function validateCsrfToken(?string $token): bool
{
    $sessionToken = (string)($_SESSION['csrfToken'] ?? '');
    $submittedToken = (string)$token;

    return $sessionToken !== '' && $submittedToken !== '' && hash_equals($sessionToken, $submittedToken);
}

function getCsrfInput(): string
{
    $token = htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8');

    return '<input type="hidden" name="csrfToken" value="' . $token . '">';
}

/**
 * Reads the token from the X-CSRF-Token header (AJAX) or the csrfToken POST
 * field (regular form submit), and throws if it doesn't match the session.
 * Callers are expected to catch Throwable and respond without echoing details.
 */
function requireValidCsrfToken(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrfToken'] ?? null);

    if (!validateCsrfToken($token)) {
        throw new RuntimeException('Your session could not be verified. Please refresh the page and try again.');
    }
}
