<?php

date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/mailer.php';

mysqli_query($con, "SET time_zone = '+05:30'");

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
| Request Validation
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    respond(false, 'Invalid request method.');
}

/*
|--------------------------------------------------------------------------
| Inputs
|--------------------------------------------------------------------------
*/

$employeeId = (int) ($_POST['employeeId'] ?? 0);

$expenseType = trim((string) ($_POST['expenseType'] ?? ''));

$amount = trim((string) ($_POST['amount'] ?? ''));

$expenseDate = trim((string) ($_POST['expenseDate'] ?? ''));

$remark = trim((string) ($_POST['remark'] ?? ''));

$invoiceNumber =
    trim((string) ($_POST['invoiceNumber'] ?? ''));

$invoiceImage = '';

/*
|--------------------------------------------------------------------------
| Validations
|--------------------------------------------------------------------------
*/

if ($employeeId <= 0) {

    respond(false, 'Please select employee.');
}

if ($expenseType === '') {

    respond(false, 'Please select expense type.');
}

if ($amount === '' || !is_numeric($amount)) {

    respond(false, 'Please enter valid amount.');
}

if ((float)$amount <= 0) {

    respond(false, 'Amount must be greater than zero.');
}

if ($expenseDate === '') {

    respond(false, 'Please select expense date.');
}


/*
|--------------------------------------------------------------------------
| Upload Invoice Image
|--------------------------------------------------------------------------
*/

if (
    isset($_FILES['invoiceImage']) &&
    !empty($_FILES['invoiceImage']['name'])
) {

    $allowedExtensions = [

        'jpg',
        'jpeg',
        'png',
        'pdf'
    ];

    $allowedMimeTypes = [

        'image/jpeg',
        'image/png',
        'application/pdf'
    ];

    $fileName =
        (string) $_FILES['invoiceImage']['name'];

    $tmpName =
        (string) $_FILES['invoiceImage']['tmp_name'];

    $fileSize =
        (int) $_FILES['invoiceImage']['size'];

    $extension =
        strtolower(
            pathinfo(
                $fileName,
                PATHINFO_EXTENSION
            )
        );

    if (
        !in_array(
            $extension,
            $allowedExtensions,
            true
        )
    ) {

        respond(false, 'Invalid invoice file type.');
    }

    $finfo =
        finfo_open(FILEINFO_MIME_TYPE);

    $mimeType =
        finfo_file($finfo, $tmpName);

    finfo_close($finfo);

    if (
        !in_array(
            $mimeType,
            $allowedMimeTypes,
            true
        )
    ) {

        respond(false, 'Invalid invoice file.');
    }

    /*
    |--------------------------------------------------------------------------
    | Max 5MB
    |--------------------------------------------------------------------------
    */

    if ($fileSize > (5 * 1024 * 1024)) {

        respond(false, 'Invoice file exceeds 5MB.');
    }

    /*
    |--------------------------------------------------------------------------
    | Upload Directory
    |--------------------------------------------------------------------------
    */

    $uploadDir =
        __DIR__ .
        '/../uploads/expenses/';

    if (!is_dir($uploadDir)) {

        if (
            !mkdir($uploadDir, 0777, true) &&
            !is_dir($uploadDir)
        ) {

            respond(false, 'Unable to create upload directory.');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Generate File Name
    |--------------------------------------------------------------------------
    */

    $invoiceImage =
        'expense_' .
        time() .
        '_' .
        mt_rand(1000, 9999) .
        '.' .
        $extension;

    $destination =
        $uploadDir .
        $invoiceImage;

    if (
        !move_uploaded_file(
            $tmpName,
            $destination
        )
    ) {

        respond(false, 'Unable to upload invoice image.');
    }
}

/*
|--------------------------------------------------------------------------
| Fetch Employee
|--------------------------------------------------------------------------
*/

$employeeName = '';

$employeeQuery = "
    SELECT
        fullName
    FROM employeeusers
    WHERE id = ?
    LIMIT 1
";

$employeeStmt = mysqli_prepare($con, $employeeQuery);

if (!$employeeStmt) {

    respond(false, 'Unable to validate employee.');
}

mysqli_stmt_bind_param(
    $employeeStmt,
    'i',
    $employeeId
);

mysqli_stmt_execute($employeeStmt);

$employeeResult = mysqli_stmt_get_result($employeeStmt);

$employeeRow = $employeeResult
    ? mysqli_fetch_assoc($employeeResult)
    : null;

mysqli_stmt_close($employeeStmt);

if (!$employeeRow) {

    respond(false, 'Employee not found.');
}

$employeeName = trim((string) ($employeeRow['fullName'] ?? ''));

/*
|--------------------------------------------------------------------------
| Current User
|--------------------------------------------------------------------------
*/

$createdBy = trim((string) ($_SESSION['employeeName'] ?? 'System'));

/*
|--------------------------------------------------------------------------
| Insert Expense
|--------------------------------------------------------------------------
*/

$insertQuery = "
    INSERT INTO employeeExpenses (

        employeeId,
        employeeName,

        expenseType,

        amount,

        invoiceNumber,
        invoiceImage,

        expenseDate,

        remark,

        expenseStatus,

        createdBy

    ) VALUES (

        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )
";

$insertStmt = mysqli_prepare(
    $con,
    $insertQuery
);

if (!$insertStmt) {

    respond(
        false,
        'Unable to prepare expense query.'
    );
}

$amountValue = (float) $amount;

$expenseStatus = 'Pending';

mysqli_stmt_bind_param(
    $insertStmt,
    'issdssssss',

    $employeeId,
    $employeeName,

    $expenseType,

    $amountValue,

    $invoiceNumber,
    $invoiceImage,

    $expenseDate,

    $remark,

    $expenseStatus,

    $createdBy
);

$executed = mysqli_stmt_execute($insertStmt);

if (!$executed) {

    mysqli_stmt_close($insertStmt);

    respond(false, 'Unable to save expense.');
}

$insertId = mysqli_insert_id($con);

mysqli_stmt_close($insertStmt);


/*
|--------------------------------------------------------------------------
| Send Expense Mail
|--------------------------------------------------------------------------
*/

try {

    $employeeEmail = '';

    $mailQuery = "
        SELECT
            emailAddress
        FROM employeeusers
        WHERE id = ?
        LIMIT 1
    ";

    $mailStmt = mysqli_prepare(
        $con,
        $mailQuery
    );

    if ($mailStmt) {

        mysqli_stmt_bind_param(
            $mailStmt,
            'i',
            $employeeId
        );

        mysqli_stmt_execute($mailStmt);

        $mailResult =
            mysqli_stmt_get_result(
                $mailStmt
            );

        $mailRow =
            $mailResult
                ? mysqli_fetch_assoc(
                    $mailResult
                )
                : null;

        mysqli_stmt_close($mailStmt);

        $employeeEmail =
            trim((string) (
                $mailRow['emailAddress'] ?? ''
            ));
    }

    if (
        $employeeEmail !== '' &&
        function_exists(
            'sendExpenseCreatedEmail'
        )
    ) {

        sendExpenseCreatedEmail(

            $insertId,

            $employeeEmail,

            $employeeName,

            $expenseType,

            $amountValue,

            $expenseDate,

            $invoiceNumber,

            $remark
        );
    }

} catch (\Throwable $e) {

    error_log(
        'Expense mail error: ' .
        $e->getMessage()
    );
}





/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

respond(true, 'Expense added successfully.', [

    'id' => $insertId,

    'employeeId' => $employeeId,

    'employeeName' => $employeeName,

    'expenseType' => $expenseType,

    'amount' => number_format($amountValue, 2, '.', ''),
    
    'invoiceNumber' => $invoiceNumber,

    'invoiceImage' => $invoiceImage,

    'expenseDate' => date(
        'd M Y',
        strtotime($expenseDate)
    ),

    'remark' => $remark,

    'createdBy' => $createdBy,

    'createdAt' => date('d M Y h:i A')
]);