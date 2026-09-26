<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/EmployeeLoanEngine.php';

$loanId = (int)($_GET['loanId'] ?? 0);

if ($loanId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid loan.']);
    exit;
}

try {
    $engine = new EmployeeLoanEngine($con);
    echo json_encode(['success' => true, 'data' => $engine->getRepaymentSchedule($loanId)]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
