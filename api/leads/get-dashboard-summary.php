<?php

/*
|--------------------------------------------------------------------------
| Lead Dashboard Data
|--------------------------------------------------------------------------
|
| Single combined endpoint (summary cards + all charts + recent follow up
| table in one response) -- same "one analytics call per filter change"
| pattern already used elsewhere, since every widget on the dashboard
| shares the exact same filter set anyway.
*/

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadDashboardEngine.php';
require_once __DIR__ . '/../../includes/leadAccess.php';

// Dashboard view permission (admin or employee dashboard route). Users
// without full lead scope only ever get their own figures.
requireApiPermission(['/lead-dashboard', '/emp-lead-dashboard', '/emp-dashboard'], 'canView');
$scopeEmployeeId = getLeadScopeEmployeeId();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['candidateId']) && empty($_SESSION['userId'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$filters = [
    'employeeId' => $scopeEmployeeId !== 0 ? $scopeEmployeeId : (int)($_GET['employeeId'] ?? 0),
    'source' => trim((string)($_GET['source'] ?? '')),
    'projectId' => (int)($_GET['projectId'] ?? 0),
    'dateFrom' => trim((string)($_GET['dateFrom'] ?? '')),
    'dateTo' => trim((string)($_GET['dateTo'] ?? '')),
];

try {
    $engine = new LeadDashboardEngine($con);
    echo json_encode(['success' => true, 'data' => $engine->getDashboardData($filters)]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
