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
| Method Validation
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    respond(
        false,
        'Invalid request method'
    );
}

/*
|--------------------------------------------------------------------------
| Payload
|--------------------------------------------------------------------------
*/

$payload =
    json_decode(
        file_get_contents('php://input'),
        true
    ) ?: [];

$leaveId =
    (int)(
        $payload['leaveId'] ?? 0
    );

if ($leaveId <= 0) {

    respond(
        false,
        'Invalid leave ID'
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
| Cancel Leave
|--------------------------------------------------------------------------
*/

$result =
    $employeeEngine->cancelLeave(
        $leaveId
    );

/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

respond(

    $result['success'] ?? false,

    $result['message'] ?? 'Something went wrong',

    $result['data'] ?? []

);