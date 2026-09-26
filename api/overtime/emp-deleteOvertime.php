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
    string $message
): void {

    echo json_encode([

        'success' => $success,

        'message' => $message

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
| Overtime ID
|--------------------------------------------------------------------------
*/

$id =
    (int)($_POST['id'] ?? 0);

if ($id <= 0) {

    respond(
        false,
        'Invalid overtime ID.'
    );
}

/*
|--------------------------------------------------------------------------
| Verify Ownership + Pending Status
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

$row =
    mysqli_fetch_assoc(
        $checkResult
    );

mysqli_stmt_close(
    $checkStmt
);

if (!$row) {

    respond(
        false,
        'Overtime request not found.'
    );
}

/*
|--------------------------------------------------------------------------
| Allow Delete Only For Pending
|--------------------------------------------------------------------------
*/

if (
    strtolower($row['status']) !== 'pending'
) {

    respond(
        false,
        'Only pending overtime can be deleted.'
    );
}

/*
|--------------------------------------------------------------------------
| Delete Overtime
|--------------------------------------------------------------------------
*/

$deleteQuery = "

    DELETE FROM overtimeRequests

    WHERE

        id = ?
        AND employeeId = ?

";

$deleteStmt =
    mysqli_prepare(
        $con,
        $deleteQuery
    );

if (!$deleteStmt) {

    respond(
        false,
        'Unable to prepare delete.'
    );
}

mysqli_stmt_bind_param(
    $deleteStmt,
    'ii',
    $id,
    $employeeId
);

$deleted =
    mysqli_stmt_execute(
        $deleteStmt
    );

mysqli_stmt_close(
    $deleteStmt
);

if (!$deleted) {

    respond(
        false,
        'Failed to delete overtime.'
    );
}

/*
|--------------------------------------------------------------------------
| Final Response
|--------------------------------------------------------------------------
*/

respond(
    true,
    'Overtime deleted successfully.'
);