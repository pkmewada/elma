<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/basic-config.php';


if (!function_exists('getDbConnection')) {
    /*
    |--------------------------------------------------------------------------
    | CRM database connection
    |--------------------------------------------------------------------------
    | Credentials come only from CRM_DB_* environment variables. Local dev
    | falls back to the dedicated `elma_crm` MySQL user, which is granted
    | access to `elma_realestate_crm` only. There are no production
    | credentials in code; a non-local host without CRM_DB_* fails closed.
    |
    | Guard: this CRM copy must never connect to a Modlus database, so any
    | database or user name containing "modlus" is refused outright.
    */
    function getDbConnection()
    {
        static $connection = null;

        if ($connection === null) {
            $isLocalEnvironment = isCrmLocalEnvironment();

            $defaults = $isLocalEnvironment
                ? [
                    'host' => 'localhost',
                    'user' => 'elma_crm',
                    'pass' => '',
                    'db' => 'elma_realestate_crm',
                    'port' => 3306,
                ]
                : [
                    'host' => 'localhost',
                    'user' => '',
                    'pass' => '',
                    'db' => '',
                    'port' => 3306,
                ];

            $host = crmEnv('CRM_DB_HOST') ?: $defaults['host'];
            $user = crmEnv('CRM_DB_USER') ?: $defaults['user'];
            $pass = crmEnv('CRM_DB_PASS') !== false ? crmEnv('CRM_DB_PASS') : $defaults['pass'];
            $db = crmEnv('CRM_DB_NAME') ?: $defaults['db'];
            $port = (int)(crmEnv('CRM_DB_PORT') ?: $defaults['port']);

            if ($user === '' || $db === '') {
                error_log('CRM database is not configured (set CRM_DB_HOST/CRM_DB_USER/CRM_DB_PASS/CRM_DB_NAME).');
                http_response_code(500);
                die('Database is not configured.');
            }

            if (stripos($db, 'modlus') !== false || stripos($user, 'modlus') !== false) {
                error_log('Refused CRM database connection to a Modlus database/user: ' . $db);
                http_response_code(500);
                die('Database configuration refused.');
            }

            mysqli_report(MYSQLI_REPORT_OFF);

            $connection = @mysqli_connect($host, $user, $pass, $db, $port);

            if (!$connection) {
                error_log('CRM database connection failed: ' . mysqli_connect_error());
                http_response_code(500);
                die($isLocalEnvironment ? 'Connection failed: ' . mysqli_connect_error() : 'Database connection failed.');
            }

            mysqli_set_charset($connection, 'utf8mb4');
        }

        return $connection;
    }
}

$con = getDbConnection();
$config = getBasicConfig();

mysqli_query($con, "SET time_zone = '+05:30'");
date_default_timezone_set('Asia/Kolkata');
?>
