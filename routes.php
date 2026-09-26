<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/permission-helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Get Request Path
|--------------------------------------------------------------------------
*/
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH) ?? '/';

/*
|--------------------------------------------------------------------------
| Remove Base Path From BASE_URL
|--------------------------------------------------------------------------
*/
$basePath = parse_url(BASE_URL, PHP_URL_PATH);
$basePath = rtrim((string)$basePath, '/');

if ($basePath !== '' && strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}

/*
|--------------------------------------------------------------------------
| Normalize Path
|--------------------------------------------------------------------------
*/
$path = '/' . ltrim($path, '/');
$path = rtrim($path, '/');

if ($path === '') {
    $path = '/';
}

/*
|--------------------------------------------------------------------------
| Root Redirect
|--------------------------------------------------------------------------
*/
if ($path === '/') {
    header('Location: ' . BASE_URL . '/login');
    exit();
}

/*
|--------------------------------------------------------------------------
| Fetch Route From routesMaster
|--------------------------------------------------------------------------
*/
$route = getRouteByPath($path);

if (!$route) {
    http_response_code(404);
    echo '404 Not Found: Route "' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '" is not configured.';
    exit();
}

/*
|--------------------------------------------------------------------------
| Authentication + Permission Guard
|--------------------------------------------------------------------------
*/
if ((int)$route['isPublic'] !== 1) {

    if (!isLoggedIn()) {

        $loginRoute = '/login';

        if (
            strpos($path, '/emp-') === 0 ||
            strpos($path, '/employee-') === 0 ||
            strpos($path, '/candidate-') === 0
        ) {
            $loginRoute = '/candidate-login';
        }

        header('Location: ' . BASE_URL . $loginRoute);
        exit();
    }

    if (!hasRoutePermission($path, 'canView')) {
        header('Location: ' . BASE_URL . '/permission-denied?from=' . urlencode($path));
        exit();
    }
}

/*
|--------------------------------------------------------------------------
| Controller Based Routes
|--------------------------------------------------------------------------
*/
switch ($path) {

    case '/candidate-reset-password':
        require_once __DIR__ . '/app/controllers/CandidateAuthController.php';
        $controller = new CandidateAuthController();
        $controller->resetPassword();
        exit();

    case '/candidate-profile':
        require_once __DIR__ . '/app/controllers/CandidateProfileController.php';
        $controller = new CandidateProfileController();
        $controller->index();
        exit();

    case '/candidate-forgot-password':
        require_once __DIR__ . '/app/controllers/CandidateAuthController.php';
        $controller = new CandidateAuthController();
        $controller->forgotPassword();
        exit();

    case '/candidate-verify-reset-otp':
        require_once __DIR__ . '/app/controllers/CandidateAuthController.php';
        $controller = new CandidateAuthController();
        $controller->verifyResetOtp();
        exit();

    case '/candidate-reset-forgot-password':
        require_once __DIR__ . '/app/controllers/CandidateAuthController.php';
        $controller = new CandidateAuthController();
        $controller->resetForgotPassword();
        exit();
}

/*
|--------------------------------------------------------------------------
| Normal Page Include
|--------------------------------------------------------------------------
*/
$pageFile = trim((string)($route['pageFile'] ?? ''));

if ($pageFile === '') {
    http_response_code(500);
    echo 'Route file is missing for "' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '".';
    exit();
}

$fullPagePath = __DIR__ . $pageFile;

if (!file_exists($fullPagePath)) {
    http_response_code(500);
    echo 'Route file not found: ' . htmlspecialchars($pageFile, ENT_QUOTES, 'UTF-8');
    exit();
}

require_once $fullPagePath;
exit();