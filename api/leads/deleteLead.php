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



$id =
    (int)(
        $payload['id'] ?? 0
    );



if ($id <= 0) {


    http_response_code(422);


    echo json_encode([
        'success' => false,
        'message' => 'Invalid lead ID.',
    ]);


    exit();

}



/*
|--------------------------------------------------------------------------
| Fetch Lead Before Delete
|--------------------------------------------------------------------------
*/


$oldLead = null;


$oldStmt =
    mysqli_prepare(
        $con,
        "
        SELECT

            id,
            fullName,
            email,
            phone,
            source,
            orgName,
            categoryId,
            planId,
            status,
            createdAt

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



if (!$oldLead) {


    echo json_encode([
        'success' => false,
        'message' => 'Lead not found.'
    ]);


    exit();

}




/*
|--------------------------------------------------------------------------
| Delete Lead
|--------------------------------------------------------------------------
*/


$deleteStmt =
    mysqli_prepare(
        $con,
        "
        DELETE FROM leads

        WHERE id = ?
        "
    );



if (!$deleteStmt) {


    http_response_code(500);


    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete lead.',
    ]);


    exit();

}



mysqli_stmt_bind_param(

    $deleteStmt,

    'i',

    $id

);



$deleted =
    mysqli_stmt_execute(
        $deleteStmt
    );



mysqli_stmt_close(
    $deleteStmt
);



if (!$deleted) {


    http_response_code(500);


    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete lead.',
    ]);


    exit();

}




/*
|--------------------------------------------------------------------------
| Activity Logger
|--------------------------------------------------------------------------
*/


saveActivityLog(

    $con,

    "Lead",

    $id,

    "DELETE",

    "Lead deleted : " . $oldLead['fullName'],

    $oldLead,

    null

);



/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/


echo json_encode([

    'success' => true,

    'message' => 'Lead deleted successfully'

]);

?>