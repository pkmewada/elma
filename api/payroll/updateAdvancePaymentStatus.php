<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/permission-helper.php';
require_once __DIR__ . '/../../includes/AdvancePaymentEngine.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$id = (int)($payload['id'] ?? 0);
$action = trim((string)($payload['action'] ?? ''));

try {
    $engine = new AdvancePaymentEngine($con);

    if ($action === 'approve') {
        // Same gate as salary slip approval (reviewSalarySlipApproval.php)
        // -- advances directly reduce future net pay, same sensitivity.
        if (!isLoggedInUserSuperAdmin()) {
            throw new Exception('Only Super Admin can approve advance payments.');
        }
        $engine->approve($id, getLoggedInUserId());
        $message = 'Advance payment approved successfully.';
    } elseif ($action === 'cancel') {
        $engine->cancel($id);
        $message = 'Advance payment cancelled successfully.';
    } else {
        throw new Exception('Invalid action.');
    }

    echo json_encode(['success' => true, 'message' => $message]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
