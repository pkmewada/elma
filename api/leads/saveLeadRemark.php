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
        'message' => 'Unauthorized access.'
    ]);


    exit;

}



/*
|--------------------------------------------------------------------------
| Inputs
|--------------------------------------------------------------------------
*/


$leadId =
    (int)(
        $_POST['leadId'] ?? 0
    );



$remark =
    trim(
        (string)(
            $_POST['remark'] ?? ''
        )
    );



$followUpDateTime =
    trim(
        (string)(
            $_POST['followUpDateTime'] ?? ''
        )
    );



$createdByCandidateId =
    !empty($_SESSION['candidateId'])
        ? (int)$_SESSION['candidateId']
        : (int)($_SESSION['userId'] ?? 0);




if (
    $leadId <= 0 ||
    $remark === ''
) {


    echo json_encode([
        'success' => false,
        'message' => 'Remark is required.'
    ]);


    exit;

}



/*
|--------------------------------------------------------------------------
| Insert Remark
|--------------------------------------------------------------------------
*/


$stmt =
    mysqli_prepare(
        $con,
        "
        INSERT INTO leadRemarks
        (
            leadId,
            remark,
            followUpDateTime,
            createdByCandidateId
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?
        )
        "
    );



if (!$stmt) {


    echo json_encode([
        'success' => false,
        'message' => 'Unable to save remark.'
    ]);


    exit;

}




$followUpValue =
    $followUpDateTime !== ''
        ? date(
            'Y-m-d H:i:s',
            strtotime($followUpDateTime)
        )
        : null;




mysqli_stmt_bind_param(

    $stmt,

    "issi",

    $leadId,

    $remark,

    $followUpValue,

    $createdByCandidateId

);



$success =
    mysqli_stmt_execute(
        $stmt
    );



$remarkId =
    mysqli_insert_id(
        $con
    );



mysqli_stmt_close(
    $stmt
);



/*
|--------------------------------------------------------------------------
| Activity Logger
|--------------------------------------------------------------------------
*/


if ($success) {



    $leadName = "";


    $leadStmt =
        mysqli_prepare(
            $con,
            "
            SELECT fullName

            FROM leads

            WHERE id = ?

            LIMIT 1
            "
        );



    if ($leadStmt) {


        mysqli_stmt_bind_param(
            $leadStmt,
            "i",
            $leadId
        );


        mysqli_stmt_execute(
            $leadStmt
        );


        $leadResult =
            mysqli_stmt_get_result(
                $leadStmt
            );


        $lead =
            mysqli_fetch_assoc(
                $leadResult
            );


        $leadName =
            $lead['fullName'] ?? "";


        mysqli_stmt_close(
            $leadStmt
        );

    }



    saveActivityLog(

        $con,

        "Lead",

        $leadId,

        "REMARK",

        "Remark added : " . $leadName,

        null,

        [

            "remarkId" =>
                $remarkId,


            "remark" =>
                $remark,


            "followUpDateTime" =>
                $followUpValue

        ]

    );


}



/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/


echo json_encode([

    'success' => $success,


    'message' =>
        $success
            ? 'Remark saved successfully.'
            : 'Failed to save remark.'

]);

?>