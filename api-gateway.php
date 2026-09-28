<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$apiGatewayRespond = static function (int $statusCode, string $message): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $message,
    ]);
    exit;
};

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$basePath = rtrim((string)(parse_url(BASE_URL, PHP_URL_PATH) ?? ''), '/');

if ($basePath !== '' && strpos($requestPath, $basePath) === 0) {
    $requestPath = substr($requestPath, strlen($basePath));
}

$requestPath = '/' . ltrim($requestPath, '/');

if (
    strpos($requestPath, '/api/') !== 0
    || !preg_match('#^/api/[a-zA-Z0-9._/-]+\.php$#', $requestPath)
    || strpos($requestPath, '..') !== false
) {
    $apiGatewayRespond(404, 'API endpoint not found.');
}

$apiRoot = realpath(__DIR__ . '/api');
$relativeEndpoint = substr($requestPath, strlen('/api/'));
$targetFile = realpath(__DIR__ . '/api/' . $relativeEndpoint);

if (
    $apiRoot === false
    || $targetFile === false
    || strpos($targetFile, $apiRoot . DIRECTORY_SEPARATOR) !== 0
    || !is_file($targetFile)
) {
    $apiGatewayRespond(404, 'API endpoint not found.');
}

$requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

/*
|--------------------------------------------------------------------------
| Session + CSRF for every API, except public integration endpoints
|--------------------------------------------------------------------------
| Every CRM API requires a logged-in session + CSRF EXCEPT the three public
| lead-capture endpoints below, which are external machine endpoints (Meta/
| Google webhooks, the website's enquiry form) authenticated by the
| provider's own signature/shared-secret/API-key check inside each file
| (includes/integrationAccess.php) instead -- never a CRM session, per
| CLAUDE.md Phase 6. Path-traversal/file-existence checks above still apply
| to them unchanged.
*/
$publicIntegrationEndpoints = [
    '/api/integrations/meta-webhook.php',
    '/api/integrations/google-lead.php',
    '/api/integrations/website-lead.php',
];

if (in_array($requestPath, $publicIntegrationEndpoints, true)) {
    require $targetFile;
    exit;
}

require_once __DIR__ . '/includes/permission-helper.php';
require_once __DIR__ . '/includes/Csrf.php';

if (!isLoggedIn()) {
    $apiGatewayRespond(401, 'Unauthorized access.');
}

if (!in_array($requestMethod, ['GET', 'HEAD'], true)) {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrfToken'] ?? null);

    if (!validateCsrfToken(is_string($csrfToken) ? $csrfToken : null)) {
        $apiGatewayRespond(403, 'Your session could not be verified. Please refresh the page and try again.');
    }
}

$permissionStmt = $con->prepare("
    SELECT
        rm.routePath,
        pa.actionKey,
        pa.actionLabel
    FROM permissionActions pa
    INNER JOIN routesMaster rm ON rm.id = pa.routeId
    WHERE pa.apiEndpoint = ?
    AND (pa.httpMethod = ? OR pa.httpMethod = 'ANY')
    AND pa.isActive = 1
    AND rm.isActive = 1
    LIMIT 1
");

if (!$permissionStmt) {
    error_log('API permission query failed: ' . $con->error);
    require $targetFile;
    exit;
}

$permissionStmt->bind_param('ss', $requestPath, $requestMethod);
$permissionStmt->execute();
$protectedAction = $permissionStmt->get_result()->fetch_assoc();
$permissionStmt->close();

if ($protectedAction) {
    require_once __DIR__ . '/includes/permission-helper.php';

    $protectedRoutePath = (string)$protectedAction['routePath'];
    $hasPageAccess = hasRoutePermission($protectedRoutePath, 'canView');
    $hasActionAccess = hasActionPermission(
        $protectedRoutePath,
        (string)$protectedAction['actionKey']
    );

    if ($hasPageAccess && $hasActionAccess) {
        require $targetFile;
        exit;
    }

    $apiGatewayRespond(
        403,
        'You do not have permission for ' . (string)$protectedAction['actionLabel'] . '.'
    );
}

require $targetFile;
