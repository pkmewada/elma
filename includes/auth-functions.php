<?php

require_once __DIR__ . '/db.php';

// Dev mode skips real OTP checks, so it is off unless explicitly enabled for a
// local machine with the environment variable CRM_DEV_MODE=1
// (e.g. `SetEnv CRM_DEV_MODE 1` in the local-only .htaccess).
if (!defined('DEV_MODE')) {
    define('DEV_MODE', getenv('CRM_DEV_MODE') === '1');
}

function redirectTo($path)
{
    require_once __DIR__ . '/config.php';

    $url = rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');

    header("Location: $url");
    exit();
}

function sanitizeInput($value)
{
    return trim($value);
}

function getPost($key, $default = '')
{
    return sanitizeInput($_POST[$key] ?? $default);
}

function getQuery($key, $default = '')
{
    return sanitizeInput($_GET[$key] ?? $default);
}

function generateOtp()
{
    return str_pad((string) rand(0, 9999), 4, '0', STR_PAD_LEFT);
}

function isVerifiedOtp($otp)
{
    return trim((string) $otp) === '';
}

function isDevMode()
{
    return defined('DEV_MODE') && DEV_MODE === true;
}

function destroySession()
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
