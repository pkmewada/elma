<?php

require_once __DIR__ . '/../../includes/auth.php';

require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

/*
|--------------------------------------------------------------------------
| Response Helper
|--------------------------------------------------------------------------
*/

function respond(
    $success,
    $message,
    $data = []
) {

    echo json_encode([

        'success' => $success,

        'message' => $message,

        'data' => $data

    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Load Leave Requests
|--------------------------------------------------------------------------
*/

$sql = "

    SELECT

        la.id,

        la.leaveTypeId,

        lt.name AS leaveType,

        lt.code AS leaveCode,

        la.employeeId,

        eu.fullName AS employeeName,

        la.fromDate,

        la.toDate,

        la.totalDays,
        la.dayType,

        la.reason,

        la.status,

        la.createdAt,

        eu.accountStatus,

        COALESCE(daySummary.approvedDays, 0) AS approvedDays,
        COALESCE(daySummary.rejectedDays, 0) AS rejectedDays,
        COALESCE(daySummary.pendingDays, 0) AS pendingDays

    FROM leaveApplications la

    LEFT JOIN leaveTypes lt
        ON lt.id = la.leaveTypeId

    LEFT JOIN employeeusers eu
        ON eu.id = la.employeeId

    LEFT JOIN (
        SELECT
            leaveApplicationId,
            SUM(CASE WHEN status = 'approved' THEN IF(dayType = 'half', 0.5, 1) ELSE 0 END) AS approvedDays,
            SUM(CASE WHEN status = 'rejected' THEN IF(dayType = 'half', 0.5, 1) ELSE 0 END) AS rejectedDays,
            SUM(CASE WHEN status = 'pending' THEN IF(dayType = 'half', 0.5, 1) ELSE 0 END) AS pendingDays
        FROM leaveApplicationDays
        GROUP BY leaveApplicationId
    ) daySummary
        ON daySummary.leaveApplicationId = la.id

    WHERE eu.accountStatus = 'Active'

    ORDER BY la.id DESC

";

$stmt =
    mysqli_prepare(
        $con,
        $sql
    );

if (!$stmt) {

    respond(
        false,
        'Unable to prepare query.'
    );
}

mysqli_stmt_execute($stmt);

$result =
    mysqli_stmt_get_result($stmt);

$data = [];

while (
    $row = mysqli_fetch_assoc($result)
) {

    $data[] = [

        'id' =>
            (int)$row['id'],

        'leaveTypeId' =>
            (int)$row['leaveTypeId'],

        'leaveType' =>
            $row['leaveType'] ?? '',

        'leaveCode' =>
            $row['leaveCode'] ?? '',

        'employeeId' =>
            (int)$row['employeeId'],

        'employeeName' =>
            $row['employeeName'] ?? '',

        'fromDate' =>
            $row['fromDate'] ?? '',

        'toDate' =>
            $row['toDate'] ?? '',

        'totalDays' =>
            (float)$row['totalDays'],

        'dayType' =>
            $row['dayType'] ?? 'full',

        'reason' =>
            $row['reason'] ?? '',

        'status' =>
            $row['status'] ?? 'pending',

        'approvedDays' =>
            (float)$row['approvedDays'],

        'rejectedDays' =>
            (float)$row['rejectedDays'],

        'pendingDays' =>
            (float)$row['pendingDays'],

        'createdAt' =>
            $row['createdAt'] ?? ''
    ];
}

mysqli_stmt_close($stmt);

/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

respond(
    true,
    'Data loaded successfully.',
    $data
);
