<?php

/*
|--------------------------------------------------------------------------
| Environment
|--------------------------------------------------------------------------
| All deployment-specific values come from CRM_* environment variables
| (e.g. `SetEnv CRM_DB_NAME ...` in the server's Apache/LiteSpeed config or
| hPanel). The prefix is deliberately NOT MODLUS_* so this CRM copy can never
| pick up a Modlus server's configuration by accident.
|
| Local (XAMPP/WAMP on localhost or a private IP) gets safe development
| defaults; any other host must set the variables explicitly.
*/
/*
| Optional .env file in the project root (gitignored, web-blocked by
| .htaccess): KEY=VALUE lines, # comments. Real environment variables
| always win. Used for local overrides and on hosts where SetEnv is not
| practical. See .env.example for the supported keys.
*/
if (!function_exists('loadCrmEnvFile')) {
    function loadCrmEnvFile(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));

            if (!preg_match('/^CRM_[A-Z0-9_]+$/', $key) || getenv($key) !== false) {
                continue;
            }

            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
                $value = substr($value, 1, -1);
            }

            $GLOBALS['crmEnvFileValues'][$key] = $value;
        }
    }

    loadCrmEnvFile(dirname(__DIR__) . '/.env');
}

// Real environment first, then .env values (kept in memory: putenv() is not
// thread-safe under Windows/threaded Apache).
if (!function_exists('crmEnv')) {
    function crmEnv(string $key)
    {
        $value = getenv($key);

        return $value !== false ? $value : ($GLOBALS['crmEnvFileValues'][$key] ?? false);
    }
}

if (!function_exists('isCrmLocalEnvironment')) {
    function isCrmLocalEnvironment(): bool
    {
        $serverHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $isLocalHost = preg_match('/^(localhost|127\.0\.0\.1|\[::1\]|10\.\d+\.\d+\.\d+|172\.(1[6-9]|2\d|3[01])\.\d+\.\d+|192\.168\.\d+\.\d+)(?::\d+)?$/', $serverHost) === 1;
        $normalizedDir = str_replace('\\', '/', __DIR__);
        $isLocalPath = preg_match('#/(xampp|wamp64)/(htdocs|www)/#i', $normalizedDir) === 1;

        return $isLocalHost || ($serverHost === '' && $isLocalPath);
    }
}

// Separate session cookie so a login on another app on the same host
// (e.g. Modlus on localhost) is never accepted here, and vice versa.
if (session_status() === PHP_SESSION_NONE) {
    session_name('ELMACRMSESSID');
}

// Override for any environment (production or otherwise): if set, this wins
// over every other rule below. Trailing slashes are normalized off so
// BASE_URL . '/uploads/...' never produces a double slash.
$configuredBaseUrl = trim((string)crmEnv('CRM_BASE_URL'));

if ($configuredBaseUrl !== '') {
    define('BASE_URL', rtrim($configuredBaseUrl, '/'));
} elseif (PHP_SAPI === 'cli') {
    // CLI/cron has no HTTP_HOST; without CRM_BASE_URL assume local dev.
    define('BASE_URL', 'http://localhost/elma');
} else {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $projectRoot = realpath(dirname(__DIR__));
    $basePath = '';

    if ($documentRoot !== false && $projectRoot !== false && strpos($projectRoot, $documentRoot) === 0) {
        $relativePath = trim(str_replace('\\', '/', substr($projectRoot, strlen($documentRoot))), '/');
        $basePath = $relativePath === '' ? '' : '/' . $relativePath;
    }

    define('BASE_URL', $protocol . '://' . $host . $basePath);
}

// ✅ Separate asset URL
define('ASSET_URL', BASE_URL . '/dist');

define('UPLOAD_URL', BASE_URL . '/uploads');


if (!defined('SITE_URL')) {
    define('SITE_URL', BASE_URL);
}

if (!defined('ENCRYPTION_KEY')) {
    // Required outside local dev (CRM_ENCRYPTION_KEY). Empty means
    // includes/Crypto.php refuses to encrypt/decrypt rather than using a
    // guessable default key.
    $encryptionKey = (string)(crmEnv('CRM_ENCRYPTION_KEY') ?: '');

    if ($encryptionKey === '' && isCrmLocalEnvironment()) {
        $encryptionKey = 'local-development-only-key';
    }

    define('ENCRYPTION_KEY', $encryptionKey);
}

if (!defined('BRAND_NAME')) {
    // Client-facing product name (page titles, toasts, auth emails).
    define('BRAND_NAME', trim((string)crmEnv('CRM_BRAND_NAME')) ?: 'Elma Real Estate');
}
