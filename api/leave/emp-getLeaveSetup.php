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
| Engine
|--------------------------------------------------------------------------
*/

$employeeEngine =
    new EmployeeInfoEngine($con);

/*
|--------------------------------------------------------------------------
| Fetch Settings
|--------------------------------------------------------------------------
*/

$settings =
    $employeeEngine->getLeaveSettings();

/*
|--------------------------------------------------------------------------
| Fetch Leave Types
|--------------------------------------------------------------------------
*/

$leaveTypes =
    $employeeEngine->getLeaveTypes();

$activeLeaveTypes =
    array_filter(

        $leaveTypes,

        function ($item) {

            return
                (int)$item['isActive'] === 1;
        }

    );

/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

respond(

    true,

    'Leave setup loaded successfully.',

    [

        'leaveSettings' =>
            $settings,

        'leaveTypes' =>
            array_values($leaveTypes),

        'activeLeaveTypes' =>
            array_values($activeLeaveTypes)

    ]

);