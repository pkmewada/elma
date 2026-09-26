<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/permission-helper.php';
require_once __DIR__ . '/../../includes/EmployeeLoanEngine.php';

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
    $engine = new EmployeeLoanEngine($con);

    if ($action === 'approve') {
        // Same gate as salary slip approval -- approving generates the
        // full repayment schedule that future payroll runs will deduct.
        if (!isLoggedInUserSuperAdmin()) {
            throw new Exception('Only Super Admin can approve loans.');
        }
        $engine->approve($id, getLoggedInUserId());
        $message = 'Loan approved and repayment schedule generated.';
    } elseif ($action === 'cancel') {
        $engine->cancel($id);
        $message = 'Loan cancelled successfully.';
    } else {
        throw new Exception('Invalid action.');
    }

    echo json_encode(['success' => true, 'message' => $message]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
