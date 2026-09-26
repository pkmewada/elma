<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leave-application-days.php';
require_once __DIR__ . '/../../includes/mailer.php';

header('Content-Type: application/json');

function respond($success, $message, $data = [])
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

$id = (int)($_POST['id'] ?? 0);
$status = strtolower(trim((string)($_POST['status'] ?? '')));

if ($id <= 0 || !in_array($status, ['approved', 'rejected'], true)) {
    respond(false, 'Invalid request');
}

$leave = null;
$newParentStatus = 'pending';

mysqli_begin_transaction($con);

try {
    $stmt = mysqli_prepare(
        $con,
        "SELECT
            la.id,
            la.status,
            la.employeeId,
            la.leaveTypeId,
            la.totalDays,
            la.dayType,
            la.fromDate,
            la.toDate,
            eu.emailAddress,
            eu.fullName,
            lt.name AS leaveTypeName,
            lt.allowNegative
         FROM leaveApplications la
         LEFT JOIN employeeusers eu
            ON eu.id = la.employeeId
         LEFT JOIN leaveTypes lt
            ON lt.id = la.leaveTypeId
         WHERE la.id = ?
         LIMIT 1
         FOR UPDATE"
    );

    if (!$stmt) {
        throw new Exception('Failed to prepare leave query');
    }

    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $leave = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$leave) {
        throw new Exception('Leave not found');
    }

    if (strtolower((string)$leave['status']) === 'cancelled') {
        throw new Exception('Cancelled leave cannot be reviewed');
    }

    $settings = getLeaveSettingsForDayRows($con);
    ensureLeaveApplicationDays($con, $leave, $settings);

    $pendingStmt = mysqli_prepare(
        $con,
        "SELECT id
         FROM leaveApplicationDays
         WHERE leaveApplicationId = ?
         AND status = 'pending'
         FOR UPDATE"
    );

    if (!$pendingStmt) {
        throw new Exception('Failed to check pending leave dates');
    }

    mysqli_stmt_bind_param($pendingStmt, 'i', $id);
    mysqli_stmt_execute($pendingStmt);
    $pendingResult = mysqli_stmt_get_result($pendingStmt);
    $pendingDays = mysqli_num_rows($pendingResult);
    mysqli_stmt_close($pendingStmt);

    if ($pendingDays <= 0) {
        throw new Exception('No pending leave dates to update');
    }

    $updateStmt = mysqli_prepare(
        $con,
        "UPDATE leaveApplicationDays
         SET status = ?, reviewedBy = ?, reviewedAt = NOW()
         WHERE leaveApplicationId = ?
         AND status = 'pending'"
    );

    if (!$updateStmt) {
        throw new Exception('Failed to prepare leave day update');
    }

    mysqli_stmt_bind_param($updateStmt, 'sii', $status, $adminId, $id);

    if (!mysqli_stmt_execute($updateStmt)) {
        mysqli_stmt_close($updateStmt);
        throw new Exception('Failed to update leave dates');
    }

    mysqli_stmt_close($updateStmt);

    $newParentStatus = updateLeaveParentSummaryStatus($con, $id);

    if ($status === 'approved') {
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
        !empty($leave['emailAddress']) &&
        in_array($newParentStatus, ['approved', 'rejected'], true)
    ) {
        if ($newParentStatus === 'approved') {
            sendLeaveApprovedEmail(
                $id,
                $leave['emailAddress'],
                $leave['fullName'],
                $leave['leaveTypeName'],
                $leave['fromDate'],
                $leave['toDate']
            );
        } else {
            sendLeaveRejectedEmail(
                $id,
                $leave['emailAddress'],
                $leave['fullName'],
                $leave['leaveTypeName'],
                $leave['fromDate'],
                $leave['toDate']
            );
        }
    }
} catch (Throwable $e) {
    error_log('Leave Status Mail Error: ' . $e->getMessage());
}

respond(true, 'Leave status updated successfully.', [
    'status' => $newParentStatus,
    'balance' => $balance ?? [],
]);
