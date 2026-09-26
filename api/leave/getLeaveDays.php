<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leave-application-days.php';

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

$leaveApplicationId = (int)($_GET['leaveApplicationId'] ?? $_GET['id'] ?? 0);
$transactionStarted = false;

if ($leaveApplicationId <= 0) {
    respond(false, 'Invalid leave request');
}

try {
    $stmt = mysqli_prepare(
        $con,
        "SELECT
            la.id,
            la.employeeId,
            eu.fullName AS employeeName,
            la.leaveTypeId,
            lt.name AS leaveType,
            lt.code AS leaveCode,
            la.fromDate,
            la.toDate,
            la.totalDays,
            la.dayType,
            la.reason,
            la.status,
            la.createdAt
         FROM leaveApplications la
         LEFT JOIN employeeusers eu
            ON eu.id = la.employeeId
         LEFT JOIN leaveTypes lt
            ON lt.id = la.leaveTypeId
         WHERE la.id = ?
         LIMIT 1"
    );

    if (!$stmt) {
        throw new Exception('Failed to prepare leave query');
    }

    mysqli_stmt_bind_param($stmt, 'i', $leaveApplicationId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $leave = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$leave) {
        respond(false, 'Leave not found');
    }

    if (strtolower((string)$leave['status']) !== 'cancelled') {
        mysqli_begin_transaction($con);
        $transactionStarted = true;
        ensureLeaveApplicationDays($con, $leave, getLeaveSettingsForDayRows($con));
        mysqli_commit($con);
        $transactionStarted = false;
    }

    $dayStmt = mysqli_prepare(
        $con,
        "SELECT
            id,
            leaveDate,
            dayType,
            status,
            reviewedBy,
            reviewedAt
         FROM leaveApplicationDays
         WHERE leaveApplicationId = ?
         ORDER BY leaveDate ASC"
    );

    if (!$dayStmt) {
        throw new Exception('Failed to prepare leave days query');
    }

    mysqli_stmt_bind_param($dayStmt, 'i', $leaveApplicationId);
    mysqli_stmt_execute($dayStmt);
    $dayResult = mysqli_stmt_get_result($dayStmt);

    $days = [];
    $approvedDays = 0.0;
    $rejectedDays = 0.0;
    $pendingDays = 0.0;

    while ($row = mysqli_fetch_assoc($dayResult)) {
        $unit = ($row['dayType'] ?? 'full') === 'half' ? 0.5 : 1.0;
        $status = strtolower((string)($row['status'] ?? 'pending'));

        if ($status === 'approved') {
            $approvedDays += $unit;
        } elseif ($status === 'rejected') {
            $rejectedDays += $unit;
        } else {
            $pendingDays += $unit;
        }

        $days[] = [
            'id' => (int)$row['id'],
            'leaveDate' => $row['leaveDate'],
            'dayType' => $row['dayType'] ?? 'full',
            'status' => $status,
            'reviewedBy' => $row['reviewedBy'] !== null ? (int)$row['reviewedBy'] : null,
            'reviewedAt' => $row['reviewedAt'],
            'unit' => $unit,
        ];
    }

    mysqli_stmt_close($dayStmt);

    respond(true, 'Leave days loaded successfully.', [
        'leave' => [
            'id' => (int)$leave['id'],
            'employeeId' => (int)$leave['employeeId'],
            'employeeName' => $leave['employeeName'] ?? '',
            'leaveTypeId' => (int)$leave['leaveTypeId'],
            'leaveType' => $leave['leaveType'] ?? '',
            'leaveCode' => $leave['leaveCode'] ?? '',
            'fromDate' => $leave['fromDate'] ?? '',
            'toDate' => $leave['toDate'] ?? '',
            'totalDays' => (float)$leave['totalDays'],
            'dayType' => $leave['dayType'] ?? 'full',
            'reason' => $leave['reason'] ?? '',
            'status' => strtolower((string)($leave['status'] ?? 'pending')),
            'createdAt' => $leave['createdAt'] ?? '',
        ],
        'days' => $days,
        'summary' => [
            'approvedDays' => $approvedDays,
            'rejectedDays' => $rejectedDays,
            'pendingDays' => $pendingDays,
        ],
    ]);
} catch (Throwable $e) {
    if ($transactionStarted) {
        mysqli_rollback($con);
    }

    respond(false, $e->getMessage());
}
