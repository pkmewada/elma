<?php

require_once __DIR__ . '/../includes/permission-helper.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Please login to search pages.',
        'results' => []
    ]);
    exit;
}

$keyword = trim((string)($_GET['q'] ?? $_POST['q'] ?? ''));

if (strlen($keyword) < 2) {
    echo json_encode([
        'success' => true,
        'message' => 'Type at least 2 characters to search.',
        'results' => []
    ]);
    exit;
}

$likeKeyword = '%' . $keyword . '%';

$stmt = $con->prepare("
    SELECT
        routePath,
        routeTitle,
        moduleName,
        layoutType
    FROM routesMaster
    WHERE isActive = 1
    AND isPublic = 0
    AND isMenuVisible = 1
    AND routeTitle LIKE ?
    ORDER BY moduleName ASC, sortOrder ASC, routeTitle ASC
    LIMIT 50
");

$stmt->bind_param('s', $likeKeyword);
$stmt->execute();
$routeResult = $stmt->get_result();

$results = [];
$matchedRoutes = 0;
$userType = getLoggedInUserType();

while ($route = $routeResult->fetch_assoc()) {
    $routePath = '/' . ltrim((string)$route['routePath'], '/');
    $routePath = rtrim($routePath, '/');

    if ($routePath === '') {
        $routePath = '/';
    }

    $layoutType = (string)($route['layoutType'] ?? 'admin');
    $isEmployeeRoute = strpos($routePath, '/emp-') === 0
        || $routePath === '/employee-attendance'
        || $layoutType === 'employee';

    if ($userType === 'admin' && $isEmployeeRoute) {
        continue;
    }

    $matchedRoutes++;

    if (!hasRoutePermission($routePath, 'canView')) {
        continue;
    }

    $results[] = [
        'title' => (string)$route['routeTitle'],
        'moduleName' => trim((string)($route['moduleName'] ?? '')) ?: 'Other',
        'routePath' => $routePath,
        'url' => BASE_URL . $routePath
    ];

    if (count($results) >= 8) {
        break;
    }
}

$message = '';

if (!$results) {
    $message = $matchedRoutes > 0
        ? 'No permitted pages found for this keyword.'
        : 'No Result Found. Please try with another keyword.';
}

echo json_encode([
    'success' => true,
    'message' => $message,
    'results' => $results
]);
