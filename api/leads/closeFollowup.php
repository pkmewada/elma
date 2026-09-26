<?php

header('Content-Type: application/json');


require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadActivityLogger.php';



if (session_status() !== PHP_SESSION_ACTIVE) {

    session_start();

}



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
        'message' => 'Remark required.'
    ]);


    exit;

}



/*
|--------------------------------------------------------------------------
| Get Current Open Followup
|--------------------------------------------------------------------------
*/


$oldFollowup = null;


$oldStmt =
    mysqli_prepare(
        $con,
        "
        SELECT

            id,
            remark,
            followUpDateTime,
            followUpremark

        FROM leadRemarks

        WHERE leadId = ?

        AND followUpremark = 'open'

        ORDER BY id DESC

        LIMIT 1
        "
    );



if ($oldStmt) {


    mysqli_stmt_bind_param(
        $oldStmt,
        "i",
        $leadId
    );


    mysqli_stmt_execute(
        $oldStmt
    );


    $oldResult =
        mysqli_stmt_get_result(
            $oldStmt
        );


    $oldFollowup =
        mysqli_fetch_assoc(
            $oldResult
        );


    mysqli_stmt_close(
        $oldStmt
    );

}





/*
|--------------------------------------------------------------------------
| Insert Closing Remark
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
            createdByCandidateId,
            followUpremark
        )
        VALUES
        (
            ?,
            ?,
            NULL,
            ?,
            'close'
        )
        "
    );



if (!$stmt) {


    echo json_encode([
        'success' => false,
        'message' => 'Unable to close follow up.'
    ]);


    exit;

}




mysqli_stmt_bind_param(

    $stmt,

    "isi",

    $leadId,

    $remark,

    $createdByCandidateId

);




$success =
    mysqli_stmt_execute(
        $stmt
    );



$closeRemarkId =
    mysqli_insert_id(
        $con
    );



mysqli_stmt_close(
    $stmt
);





/*
|--------------------------------------------------------------------------
| Mark Previous Open Followup Closed
|--------------------------------------------------------------------------
*/


if ($success) {



    $updateStmt =
        mysqli_prepare(
            $con,
            "
            UPDATE leadRemarks

            SET followUpremark = 'close'

            WHERE leadId = ?

            AND followUpremark = 'open'
            "
        );



    if ($updateStmt) {



        mysqli_stmt_bind_param(
            $updateStmt,
            "i",
            $leadId
        );



        mysqli_stmt_execute(
            $updateStmt
        );



        mysqli_stmt_close(
            $updateStmt
        );


    }


}




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



        $result =
            mysqli_stmt_get_result(
                $leadStmt
            );



        $lead =
            mysqli_fetch_assoc(
                $result
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

        "FOLLOWUP_CLOSE",

        "Follow up closed : " . $leadName,

        $oldFollowup,

        [

            "closeRemarkId" =>
                $closeRemarkId,


            "closingRemark" =>
                $remark,


            "status" =>
                "close"

        ]

    );


}


echo json_encode([


    'success' => $success,


    'message' =>
        $success
            ? 'Follow up closed successfully.'
            : 'Failed to close follow up.'

]);

?>