<?php

require_once __DIR__ . '/../../includes/emp-auth.php';

require_once __DIR__ . '/../../includes/db.php';

require_once __DIR__ . '/../../includes/mailer.php';

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
| Validate Request Method
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
| Validate Required Fields
|--------------------------------------------------------------------------
*/

if (
    empty($date) ||
    empty($startTime) ||
    empty($endTime)
) {

    respond(
        false,
        'All required fields are mandatory.'
    );
}

/*
|--------------------------------------------------------------------------
| Validate Time
|--------------------------------------------------------------------------
*/

if ($startTime >= $endTime) {

    respond(
        false,
        'End time must be greater than start time.'
    );
}

/*
|--------------------------------------------------------------------------
| Fetch Employee
|--------------------------------------------------------------------------
*/

$employeeQuery = "

    SELECT

        id,

        fullName,

        emailAddress

    FROM employeeusers

    WHERE id = ?

    LIMIT 1

";

$employeeStmt =
    mysqli_prepare(
        $con,
        $employeeQuery
    );

if (!$employeeStmt) {

    respond(
        false,
        'Unable to fetch employee.'
    );
}

mysqli_stmt_bind_param(
    $employeeStmt,
    'i',
    $employeeId
);

mysqli_stmt_execute(
    $employeeStmt
);

$employeeResult =
    mysqli_stmt_get_result(
        $employeeStmt
    );

$employee =
    mysqli_fetch_assoc(
        $employeeResult
    );

mysqli_stmt_close(
    $employeeStmt
);

if (!$employee) {

    respond(
        false,
        'Employee not found.'
    );
}

/*
|--------------------------------------------------------------------------
| Calculate Total Hours
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
| Fetch Active Overtime Settings
|--------------------------------------------------------------------------
*/

$settingsQuery = "

    SELECT

        minHoursRequired,

        maxHoursPerDay,

        roundingRule,

        autoApprove

    FROM overtimeSettings

    WHERE status = 'active'

    ORDER BY effectiveFrom DESC

    LIMIT 1

";

$settingsResult =
    mysqli_query(
        $con,
        $settingsQuery
    );

$settings =
    $settingsResult
        ? mysqli_fetch_assoc($settingsResult)
        : null;

/*
|--------------------------------------------------------------------------
| Calculate Overtime Hours
|--------------------------------------------------------------------------
*/

$otHours = 0;

if (
    $settings &&
    $totalHours >=
    (float)$settings['minHoursRequired']
) {

    $otHours = $totalHours;

    /*
    |--------------------------------------------------------------------------
    | Apply Maximum Daily Cap
    |--------------------------------------------------------------------------
    */

    if (
        (float)$settings['maxHoursPerDay'] > 0
    ) {

        $otHours = min(
            $otHours,
            (float)$settings['maxHoursPerDay']
        );

    }

    /*
    |--------------------------------------------------------------------------
    | Apply Rounding Rules
    |--------------------------------------------------------------------------
    */

    switch ($settings['roundingRule']) {

        case '15min':

            $otHours =
                round($otHours * 4) / 4;

            break;

        case '30min':

            $otHours =
                round($otHours * 2) / 2;

            break;

        default:

            $otHours =
                round($otHours, 2);

            break;

    }

}

/*
|--------------------------------------------------------------------------
| Determine Status
|--------------------------------------------------------------------------
*/

$status = (

    !empty($settings['autoApprove']) &&
    (int)$settings['autoApprove'] === 1

)

    ? 'approved'

    : 'pending';

/*
|--------------------------------------------------------------------------
| Insert Overtime Request
|--------------------------------------------------------------------------
*/

$insertQuery = "

    INSERT INTO overtimeRequests (

        employeeId,

        date,

        startTime,

        endTime,

        totalHours,

        calculatedOtHours,

        reason,

        status

    )

    VALUES (

        ?, ?, ?, ?, ?, ?, ?, ?

    )

";

$insertStmt =
    mysqli_prepare(
        $con,
        $insertQuery
    );

if (!$insertStmt) {

    respond(
        false,
        'Unable to prepare overtime insert.'
    );
}

mysqli_stmt_bind_param(

    $insertStmt,

    'isssddss',

    $employeeId,

    $date,

    $startTime,

    $endTime,

    $totalHours,

    $otHours,

    $reason,

    $status

);

$insertSuccess =
    mysqli_stmt_execute(
        $insertStmt
    );

if (!$insertSuccess) {

    mysqli_stmt_close(
        $insertStmt
    );

    respond(
        false,
        'Failed to save overtime request.'
    );
}

$insertId =
    mysqli_insert_id($con);

mysqli_stmt_close(
    $insertStmt
);

/*
|--------------------------------------------------------------------------
| Send Email Notification
|--------------------------------------------------------------------------
*/

if (!empty($employee['emailAddress'])) {

    sendOvertimeAppliedEmail(

        $insertId,

        $employee['emailAddress'],

        $employee['fullName'],

        $date,

        $totalHours

    );

}

/*
|--------------------------------------------------------------------------
| Response Data
|--------------------------------------------------------------------------
*/

$data = [

    'id' =>

        $insertId,

    'employeeId' =>

        $employeeId,

    'employeeName' =>

        $employee['fullName'] ?? '',

    'date' =>

        $date,

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

        $reason,

    'status' =>

        strtolower($status)

];

/*
|--------------------------------------------------------------------------
| Final Response
|--------------------------------------------------------------------------
*/

respond(

    true,

    'Overtime added successfully.',

    $data

);
