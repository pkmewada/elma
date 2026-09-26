<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/AdvancePaymentEngine.php';

$advanceId = (int)($_GET['advanceId'] ?? 0);

if ($advanceId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid advance payment.']);
    exit;
}

try {
    $engine = new AdvancePaymentEngine($con);
    echo json_encode(['success' => true, 'data' => $engine->getRepaymentSchedule($advanceId)]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
