<?php

require_once __DIR__ . '/../../includes/emp-auth.php';

require_once __DIR__ . '/../../includes/db.php';

require_once __DIR__ . '/../../includes/employeeInfoEngine.php';

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
| Input
|--------------------------------------------------------------------------
*/

$leaveTypeId =
    (int)(
        $_GET['leaveTypeId'] ?? 0
    );

if ($leaveTypeId <= 0) {

    respond(
        false,
        'Invalid leave type'
    );
}

/*
|--------------------------------------------------------------------------
| Engine
|--------------------------------------------------------------------------
*/

$employeeEngine =
    new EmployeeInfoEngine($con);

/*
|--------------------------------------------------------------------------
| Balance
|--------------------------------------------------------------------------
*/

$balance =
    $employeeEngine->getLeaveBalance(
        $leaveTypeId
    );

/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

respond(

    true,

    'Leave balance fetched successfully.',

    $balance

);