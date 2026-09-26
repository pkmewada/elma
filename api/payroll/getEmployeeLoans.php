<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/EmployeeLoanEngine.php';

$filters = [
    'employeeId' => (int)($_GET['employeeId'] ?? 0),
    'status' => trim((string)($_GET['status'] ?? '')),
    'search' => trim((string)($_GET['search'] ?? '')),
];

try {
    $engine = new EmployeeLoanEngine($con);
    echo json_encode(['success' => true, 'data' => $engine->getList($filters)]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
