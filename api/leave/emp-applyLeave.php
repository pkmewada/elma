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

/*
|--------------------------------------------------------------------------
| Engine
|--------------------------------------------------------------------------
*/

$employeeEngine =
    new EmployeeInfoEngine($con);

/*
|--------------------------------------------------------------------------
| Apply Leave
|--------------------------------------------------------------------------
*/

$result =
    $employeeEngine->applyLeave(
        $payload
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