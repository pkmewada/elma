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


$userId =
    (int)($_SESSION['userId'] ?? 0);


$candidateId =
    (int)($_SESSION['candidateId'] ?? 0);



$createdByCandidateId =
    $candidateId > 0
        ? $candidateId
        : $userId;



$leadId =
    (int)(
        $_POST['leadId'] ?? 0
    );



if (
    $createdByCandidateId <= 0 ||
    $leadId <= 0
) {


    echo json_encode([
        'success' => false,
        'message' => 'Invalid request.'
    ]);


    exit;

}



/*
|--------------------------------------------------------------------------
| File Validation
|--------------------------------------------------------------------------
*/


if (
    empty($_FILES['document'])
) {


    echo json_encode([
        'success' => false,
        'message' => 'Please select a PDF.'
    ]);


    exit;

}



$file =
    $_FILES['document'];



$ext =
    strtolower(
        pathinfo(
            $file['name'],
            PATHINFO_EXTENSION
        )
    );



if ($ext !== 'pdf') {


    echo json_encode([
        'success' => false,
        'message' => 'Only PDF allowed.'
    ]);


    exit;

}




/*
|--------------------------------------------------------------------------
| Upload File
|--------------------------------------------------------------------------
*/


$uploadDir =
    __DIR__ .
    '/../uploads/leads/' .
    $leadId .
    '/';



if (!is_dir($uploadDir)) {


    mkdir(
        $uploadDir,
        0777,
        true
    );

}



$fileName =
    time() .
    '_' .
    preg_replace(
        '/[^a-zA-Z0-9._-]/',
        '',
        $file['name']
    );



$destination =
    $uploadDir .
    $fileName;




if (
    !move_uploaded_file(
        $file['tmp_name'],
        $destination
    )
) {


    echo json_encode([
        'success' => false,
        'message' => 'Upload failed.'
    ]);


    exit;

}



/*
|--------------------------------------------------------------------------
| Save Document
|--------------------------------------------------------------------------
*/


$stmt =
    mysqli_prepare(
        $con,
        "
        INSERT INTO leadDocuments
        (
            leadId,
            fileName,
            originalFileName,
            uploadedByCandidateId
        )
        VALUES
        (
            ?, ?, ?, ?
        )
        "
    );



mysqli_stmt_bind_param(

    $stmt,

    'issi',

    $leadId,

    $fileName,

    $file['name'],

    $createdByCandidateId

);



$uploaded =
    mysqli_stmt_execute(
        $stmt
    );



$documentId =
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


if ($uploaded) {



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

        "DOCUMENT",

        "Document uploaded : " . $leadName,

        null,

        [

            "documentId" =>
                $documentId,


            "fileName" =>
                $fileName,


            "originalFileName" =>
                $file['name']

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

    'message' => 'Document uploaded successfully.'

]);

?>