<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leave-application-days.php';
require_once __DIR__ . '/../../includes/mailer.php';

header('Content-Type: application/json');

function respond(bool $success, string $message, array $data = []): void
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

$adminId = (int)($_SESSION['userId'] ?? 0);

if ($adminId <= 0) {
    respond(false, 'Invalid session');
}

$payload = json_decode(file_get_contents('php://input'), true);

if (!is_array($payload)) {
    $payload = $_POST;
}

$leaveDayId = (int)($payload['leaveDayId'] ?? $payload['id'] ?? 0);
$status = strtolower(trim((string)($payload['status'] ?? '')));

if ($leaveDayId <= 0 || !in_array($status, ['approved', 'rejected'], true)) {
    respond(false, 'Invalid request');
}

$leave = null;
$oldParentStatus = '';
$newParentStatus = '';
$oldDayStatus = '';
$balance = [];

mysqli_begin_transaction($con);

try {
    $stmt = mysqli_prepare(
        $con,
        "SELECT
            lad.id AS leaveDayId,
            lad.status AS leaveDayStatus,
            lad.leaveDate,
            lad.dayType AS leaveDayType,
            la.id,
            la.status,
            la.employeeId,
            la.leaveTypeId,
            la.fromDate,
            la.toDate,
            eu.emailAddress,
            eu.fullName,
            lt.name AS leaveTypeName,
            lt.allowNegative
         FROM leaveApplicationDays lad
         INNER JOIN leaveApplications la
            ON la.id = lad.leaveApplicationId
         LEFT JOIN employeeusers eu
            ON eu.id = la.employeeId
         LEFT JOIN leaveTypes lt
            ON lt.id = la.leaveTypeId
         WHERE lad.id = ?
         LIMIT 1
         FOR UPDATE"
    );

    if (!$stmt) {
        throw new Exception('Failed to prepare leave day query');
    }

    mysqli_stmt_bind_param($stmt, 'i', $leaveDayId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $leave = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$leave) {
        throw new Exception('Leave date not found');
    }

    $oldParentStatus = strtolower((string)($leave['status'] ?? 'pending'));
    $oldDayStatus = strtolower((string)($leave['leaveDayStatus'] ?? 'pending'));

    if ($oldParentStatus === 'cancelled') {
        throw new Exception('Cancelled leave cannot be reviewed');
    }

    if ($oldDayStatus !== $status) {
        $updateStmt = mysqli_prepare(
            $con,
            "UPDATE leaveApplicationDays
             SET status = ?, reviewedBy = ?, reviewedAt = NOW()
             WHERE id = ?"
        );

        if (!$updateStmt) {
            throw new Exception('Failed to prepare leave day update');
        }

        mysqli_stmt_bind_param($updateStmt, 'sii', $status, $adminId, $leaveDayId);

        if (!mysqli_stmt_execute($updateStmt)) {
            mysqli_stmt_close($updateStmt);
            throw new Exception('Failed to update leave date');
        }

        mysqli_stmt_close($updateStmt);
    }

    $newParentStatus = updateLeaveParentSummaryStatus($con, (int)$leave['id']);

    if ($status === 'approved' && $oldDayStatus !== 'approved') {
        assertLeaveBalanceAllowsApproval($con, $leave);
    }

    $balance = recalculateLeaveBalance(
        $con,
        (int)$leave['employeeId'],
        (int)$leave['leaveTypeId']
    );

    mysqli_commit($con);
} catch (Throwable $e) {
    mysqli_rollback($con);
    respond(false, $e->getMessage());
}

try {
    if (
        $leave &&
        $oldDayStatus !== $status &&
        $newParentStatus !== $oldParentStatus &&
        !empty($leave['emailAddress']) &&
        in_array($newParentStatus, ['approved', 'rejected'], true)
    ) {
        if ($newParentStatus === 'approved') {
            sendLeaveApprovedEmail(
                (int)$leave['id'],
                $leave['emailAddress'],
                $leave['fullName'],
                $leave['leaveTypeName'],
                $leave['fromDate'],
                $leave['toDate']
            );
        } else {
            sendLeaveRejectedEmail(
                (int)$leave['id'],
                $leave['emailAddress'],
                $leave['fullName'],
                $leave['leaveTypeName'],
                $leave['fromDate'],
                $leave['toDate']
            );
        }
    }
} catch (Throwable $e) {
    error_log('Leave Day Status Mail Error: ' . $e->getMessage());
}

respond(true, 'Leave date updated successfully.', [
    'status' => $newParentStatus,
    'balance' => $balance,
]);
