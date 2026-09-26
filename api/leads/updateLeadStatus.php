<?php

header('Content-Type: application/json');


require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadActivityLogger.php';


if (session_status() !== PHP_SESSION_ACTIVE) {

    session_start();

}


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['candidateId']) &&
    empty($_SESSION['userId'])
) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.',
    ]);

    exit();

}


/*
|--------------------------------------------------------------------------
| Request Method
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {


    http_response_code(405);


    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.',
    ]);


    exit();

}


/*
|--------------------------------------------------------------------------
| Payload
|--------------------------------------------------------------------------
*/


$rawInput =
    file_get_contents(
        'php://input'
    );


$payload =
    json_decode(
        (string)$rawInput,
        true
    );


if (!is_array($payload)) {

    $payload = $_POST;

}


/*
|--------------------------------------------------------------------------
| Inputs
|--------------------------------------------------------------------------
*/


$id =
    (int)(
        $payload['id'] ?? 0
    );


$status =
    trim(
        (string)(
            $payload['status'] ?? ''
        )
    );


$allowedStatuses = [

    'open',

    'interested',

    'connected',

    'converted',

    'not_interested',

    'not_connected'

];



if (
    $id <= 0 ||
    !in_array(
        $status,
        $allowedStatuses,
        true
    )
) {


    http_response_code(422);


    echo json_encode([
        'success' => false,
        'message' => 'Invalid status update request.',
    ]);


    exit();

}



/*
|--------------------------------------------------------------------------
| Fetch Old Status
|--------------------------------------------------------------------------
*/


$oldStatus = null;


$oldStmt =
    mysqli_prepare(
        $con,
        "
        SELECT 
            status,
            fullName

        FROM leads

        WHERE id = ?

        LIMIT 1
        "
    );


if ($oldStmt) {


    mysqli_stmt_bind_param(
        $oldStmt,
        "i",
        $id
    );


    mysqli_stmt_execute(
        $oldStmt
    );


    $oldResult =
        mysqli_stmt_get_result(
            $oldStmt
        );


    $oldLead =
        mysqli_fetch_assoc(
            $oldResult
        );


    mysqli_stmt_close(
        $oldStmt
    );

}


if (
    empty($oldLead)
) {


    echo json_encode([
        'success' => false,
        'message' => 'Lead not found.'
    ]);


    exit();

}


/*
|--------------------------------------------------------------------------
| Update Status
|--------------------------------------------------------------------------
*/


$updateStmt =
    mysqli_prepare(
        $con,
        "
        UPDATE leads

        SET status = ?

        WHERE id = ?
        "
    );



if (!$updateStmt) {


    http_response_code(500);


    echo json_encode([
        'success' => false,
        'message' => 'Failed to update lead status.',
    ]);


    exit();

}



mysqli_stmt_bind_param(

    $updateStmt,

    'si',

    $status,

    $id

);



$updated =
    mysqli_stmt_execute(
        $updateStmt
    );



mysqli_stmt_close(
    $updateStmt
);



if (!$updated) {


    http_response_code(500);


    echo json_encode([
        'success' => false,
        'message' => 'Failed to update lead status.',
    ]);


    exit();

}



/*
|--------------------------------------------------------------------------
| Activity Logger
|--------------------------------------------------------------------------
*/


if (
    $oldLead['status'] !== $status
) {


    saveActivityLog(

        $con,

        "Lead",

        $id,

        "STATUS",

        "Lead status changed",

        [

            "status" =>
                $oldLead['status']

        ],

        [

            "status" =>
                $status

        ]

    );

}



/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/


echo json_encode([

    'success' => true,


    'message' =>
        'Status updated successfully',


    'data' => [

        'id' =>
            $id,


        'status' =>
            $status,

    ],

]);

?>