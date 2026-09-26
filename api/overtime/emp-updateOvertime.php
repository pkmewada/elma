<?php

require_once __DIR__ . '/../../includes/emp-auth.php';

require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

/*
|--------------------------------------------------------------------------
| Response Helper
|--------------------------------------------------------------------------
*/

function respond(
    bool $success,
    string $message,
    array $data = []
): void {

    echo json_encode([

        'success' => $success,

        'message' => $message,

        'data' => $data

    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Validate Request
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    respond(
        false,
        'Invalid request method.'
    );
}

/*
|--------------------------------------------------------------------------
| Logged Employee
|--------------------------------------------------------------------------
*/

$employeeId =
    (int)($_SESSION['candidateId'] ?? 0);

if ($employeeId <= 0) {

    respond(
        false,
        'Invalid employee session.'
    );
}

/*
|--------------------------------------------------------------------------
| Request Data
|--------------------------------------------------------------------------
*/

$id =
    (int)($_POST['id'] ?? 0);

$date =
    trim($_POST['date'] ?? '');

$startTime =
    trim($_POST['startTime'] ?? '');

$endTime =
    trim($_POST['endTime'] ?? '');

$reason =
    trim($_POST['reason'] ?? '');

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if (
    $id <= 0 ||
    empty($date) ||
    empty($startTime) ||
    empty($endTime)
) {

    respond(
        false,
        'All required fields are mandatory.'
    );
}

if ($startTime >= $endTime) {

    respond(
        false,
        'End time must be greater than start time.'
    );
}

/*
|--------------------------------------------------------------------------
| Check Existing Overtime
|--------------------------------------------------------------------------
*/

$checkQuery = "

    SELECT

        id,
        status

    FROM overtimeRequests

    WHERE

        id = ?
        AND employeeId = ?

    LIMIT 1

";

$checkStmt =
    mysqli_prepare(
        $con,
        $checkQuery
    );

mysqli_stmt_bind_param(
    $checkStmt,
    'ii',
    $id,
    $employeeId
);

mysqli_stmt_execute(
    $checkStmt
);

$checkResult =
    mysqli_stmt_get_result(
        $checkStmt
    );

$existing =
    mysqli_fetch_assoc(
        $checkResult
    );

mysqli_stmt_close(
    $checkStmt
);

if (!$existing) {

    respond(
        false,
        'Overtime request not found.'
    );
}

/*
|--------------------------------------------------------------------------
| Allow Pending Only
|--------------------------------------------------------------------------
*/

if (
    strtolower($existing['status']) !== 'pending'
) {

    respond(
        false,
        'Only pending overtime can be updated.'
    );
}

/*
|--------------------------------------------------------------------------
| Calculate Hours
|--------------------------------------------------------------------------
*/

$startTimestamp =
    strtotime($startTime);

$endTimestamp =
    strtotime($endTime);

$totalHours =
    round(
        ($endTimestamp - $startTimestamp) / 3600,
        2
    );

/*
|--------------------------------------------------------------------------
| Calculate OT Hours
|--------------------------------------------------------------------------
*/

$otHours = $totalHours;

/*
|--------------------------------------------------------------------------
| Update Overtime
|--------------------------------------------------------------------------
*/

$updateQuery = "

    UPDATE overtimeRequests

    SET

        date = ?,
        startTime = ?,
        endTime = ?,
        totalHours = ?,
        calculatedOtHours = ?,
        reason = ?

    WHERE

        id = ?
        AND employeeId = ?

";

$updateStmt =
    mysqli_prepare(
        $con,
        $updateQuery
    );

if (!$updateStmt) {

    respond(
        false,
        'Unable to prepare update.'
    );
}

mysqli_stmt_bind_param(

    $updateStmt,

    'sssddsii',

    $date,

    $startTime,

    $endTime,

    $totalHours,

    $otHours,

    $reason,

    $id,

    $employeeId

);

$updated =
    mysqli_stmt_execute(
        $updateStmt
    );

mysqli_stmt_close(
    $updateStmt
);

if (!$updated) {

    respond(
        false,
        'Failed to update overtime.'
    );
}

/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

respond(

    true,

    'Overtime updated successfully.',

    [

        'id' => $id,

        'date' => $date,

        'startTime' =>

            date(
                'h:i A',
                strtotime($startTime)
            ),

        'endTime' =>

            date(
                'h:i A',
                strtotime($endTime)
            ),

        'rawStartTime' =>

            $startTime,

        'rawEndTime' =>

            $endTime,

        'rawDate' =>

            $date,

        'totalHours' =>

            number_format(
                $totalHours,
                2
            ),

        'otHours' =>

            number_format(
                $otHours,
                2
            ),

        'reason' =>

            $reason

    ]

);