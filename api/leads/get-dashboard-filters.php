<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadDashboardEngine.php';
require_once __DIR__ . '/../../includes/leadAccess.php';

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

try {
    $engine = new LeadDashboardEngine($con);

    echo json_encode([
        'success' => true,
        'data' => [
            // Restricted users see only themselves in the employee filter.
            'employees' => $scopeEmployeeId === 0
                ? $engine->getAssignedEmployees()
                : array_values(array_filter($engine->getAssignedEmployees(), static fn($employee) => (int)$employee['id'] === $scopeEmployeeId)),
            'sources' => $engine->getSources(),
            'projects' => $engine->getProjects(),
        ],
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
