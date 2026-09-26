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
$candidateId =
    (int)($_SESSION['candidateId'] ?? 0);

if ($candidateId <= 0) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Method Validation
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Inputs
|--------------------------------------------------------------------------
*/
$leadId =
    (int)($_POST['leadId'] ?? 0);

$finalPrice =
    (float)($_POST['finalPrice'] ?? 0);

$statusRemark =
    trim(
        (string)(
            $_POST['statusRemark']
            ?? ''
        )
    );

$nextPriceIncrementDate =
    trim(
        (string)(
            $_POST['nextPriceIncrementDate']
            ?? ''
        )
    );

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/
if (
    $leadId <= 0 ||
    $finalPrice <= 0 ||
    $statusRemark === ''
) {

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Please fill all required fields.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Upload PDF
|--------------------------------------------------------------------------
*/
$quotationFile = '';

if (
    !empty(
        $_FILES['quotationDocument']['name']
    )
) {

    $extension =
        strtolower(
            pathinfo(
                $_FILES['quotationDocument']['name'],
                PATHINFO_EXTENSION
            )
        );

    if ($extension !== 'pdf') {

        echo json_encode([
            'success' => false,
            'message' => 'Only PDF files are allowed.'
        ]);

        exit;
    }

    $uploadDir =
        __DIR__ .
        '/../uploads/lead-conversions/';

    if (!is_dir($uploadDir)) {

        mkdir(
            $uploadDir,
            0777,
            true
        );
    }

    $quotationFile =
        time() .
        '_' .
        preg_replace(
            '/[^a-zA-Z0-9._-]/',
            '',
            $_FILES['quotationDocument']['name']
        );

    $destination =
        $uploadDir .
        $quotationFile;

    if (
        !move_uploaded_file(
            $_FILES['quotationDocument']['tmp_name'],
            $destination
        )
    ) {

        echo json_encode([
            'success' => false,
            'message' => 'Failed to upload PDF.'
        ]);

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Save Conversion
|--------------------------------------------------------------------------
*/
$stmt =
    mysqli_prepare(
        $con,
        "
        INSERT INTO leadConversions
        (
            leadId,
            finalPrice,
            statusRemark,
            nextPriceIncrementDate,
            quotationFile,
            createdByCandidateId
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?
        )
        "
    );

if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Failed to save conversion.'
    ]);

    exit;
}

mysqli_stmt_bind_param(
    $stmt,
    'idsssi',
    $leadId,
    $finalPrice,
    $statusRemark,
    $nextPriceIncrementDate,
    $quotationFile,
    $candidateId
);

$saved =
    mysqli_stmt_execute(
        $stmt
    );

$conversionId = mysqli_insert_id($con);

mysqli_stmt_close(
    $stmt
);

if (!$saved) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Failed to save conversion.'
    ]);

    exit;
}

saveActivityLog(
    $con,
    'Lead',
    $leadId,
    'CONVERSION',
    'Lead conversion details added',
    null,
    [
        'conversionId' => $conversionId,
        'finalPrice' => $finalPrice,
        'statusRemark' => $statusRemark,
        'nextPriceIncrementDate' => $nextPriceIncrementDate ?: null,
        'quotationFile' => $quotationFile ?: null,
    ]
);

/*
|--------------------------------------------------------------------------
| Success
|--------------------------------------------------------------------------
*/
echo json_encode([
    'success' => true,
    'message' => 'Lead converted successfully.'
]);
