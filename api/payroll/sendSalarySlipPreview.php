<?php

// Source - https://stackoverflow.com/q/1053424
// Posted by Abs, modified by community. See post 'Timeline' for change history
// Retrieved 2026-07-23, License - CC BY-SA 4.0

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/PayrollApprovalEngine.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$employeeId = (int)($input['employeeId'] ?? 0);
$periodStart = trim($input['periodStart'] ?? '');
$periodEnd = trim($input['periodEnd'] ?? '');

if (!$employeeId || !$periodStart || !$periodEnd) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

$engine = new PayrollApprovalEngine($con);
$result = $engine->sendSalarySlipPreviewEmail($employeeId, $periodStart, $periodEnd);

echo json_encode($result);
?>