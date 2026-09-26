<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/permission-helper.php';
require_once __DIR__ . '/../../includes/PayrollApprovalEngine.php';

header('Content-Type: application/json; charset=UTF-8');

function respond(bool $success, string $message, array $data = []): void
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ]);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.');
}

if (!isLoggedInUserSuperAdmin()) {
    respond(false, 'Only Super Admin can approve or reject salary slips.');
}

$payload = json_decode((string)file_get_contents('php://input'), true);

if (!is_array($payload)) {
    respond(false, 'Invalid payload.');
}

$salarySlipId = (int)($payload['salarySlipId'] ?? 0);
$action = trim((string)($payload['action'] ?? ''));
$remark = trim((string)($payload['remark'] ?? ''));

if ($salarySlipId <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    respond(false, 'Invalid review request.');
}

try {
    $engine = new PayrollApprovalEngine($con);

    if ($action === 'approve') {
        $paymentPayload = [
            'salarySlipId' => $salarySlipId,
            'paymentAmount' => (float)($payload['paymentAmount'] ?? 0),
            'paymentMode' => trim((string)($payload['paymentMode'] ?? '')),
            'transactionReference' => trim((string)($payload['transactionReference'] ?? '')),
            'transactionDate' => trim((string)($payload['transactionDate'] ?? '')),
            'remarks' => trim((string)($payload['remarks'] ?? '')),
        ];

        if (
            $paymentPayload['paymentAmount'] <= 0 ||
            $paymentPayload['paymentMode'] === '' ||
            $paymentPayload['transactionReference'] === '' ||
            $paymentPayload['transactionDate'] === ''
        ) {
            respond(false, 'Payment amount, mode, reference, and date are required for approval.');
        }

        $slip = $engine->getSlip($salarySlipId);

        if (!$slip) {
            respond(false, 'Salary slip not found.');
        }

        // A leave application (or other attendance data) may have been
        // approved/changed after this slip was submitted but before a
        // reviewer acts on it -- refresh it here so approval is never
        // based on a stale submission-time snapshot. No-op once the slip
        // is no longer 'pending'.
        $engine->refreshPendingCalculation($salarySlipId);
        $slip = $engine->getSlip($salarySlipId);

        if ((float)$paymentPayload['paymentAmount'] > (float)$slip['netPay']) {
            respond(false, 'Payment amount cannot be greater than net payable amount.');
        }

        mysqli_begin_transaction($con);

        $result = $engine->approveSlip($salarySlipId, getLoggedInUserId(), false);

        if (!empty($result['success'])) {
            $paymentResult = $engine->addPayment($paymentPayload, getLoggedInUserId());

            if (empty($paymentResult['success'])) {
                if (!empty($result['pdfPath'])) {
                    $pdfFullPath = dirname(__DIR__, 2) . '/' . ltrim((string)$result['pdfPath'], '/');

                    if (is_file($pdfFullPath)) {
                        @unlink($pdfFullPath);
                    }
                }

                mysqli_rollback($con);
                respond(false, 'Unable to approve salary slip: ' . $paymentResult['message']);
            } else {
                mysqli_commit($con);
                $mailSent = $engine->sendApprovedSlipEmail($salarySlipId);
                $result['mailSent'] = $mailSent;
                $result['paymentSaved'] = true;
                $result['message'] = $mailSent
                    ? 'Salary slip approved, payment recorded, and email sent to employee.'
                    : 'Salary slip approved and payment recorded, but email could not be sent.';
            }
        } else {
            mysqli_rollback($con);
        }
    } else {
        $result = $engine->rejectSlip($salarySlipId, getLoggedInUserId(), $remark);
    }

    respond((bool)$result['success'], (string)$result['message'], $result);
} catch (Throwable $e) {
    @mysqli_rollback($con);
    respond(false, $e->getMessage());
}
