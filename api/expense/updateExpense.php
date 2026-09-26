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
| Validate Request
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

$id = (int) ($_POST['id'] ?? 0);

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
| Validation
|--------------------------------------------------------------------------
*/

if ($id <= 0) {

    respond(false, 'Invalid expense ID.');
}

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
| Existing Expense
|--------------------------------------------------------------------------
*/

$existingQuery = "
    SELECT invoiceImage
    FROM employeeExpenses
    WHERE id = ?
    LIMIT 1
";

$existingStmt = mysqli_prepare($con, $existingQuery);

if (!$existingStmt) {

    respond(false, 'Unable to validate expense.');
}

mysqli_stmt_bind_param(
    $existingStmt,
    'i',
    $id
);

mysqli_stmt_execute($existingStmt);

$existingResult =
    mysqli_stmt_get_result($existingStmt);

$existingExpense =
    mysqli_fetch_assoc($existingResult);

mysqli_stmt_close($existingStmt);

if (!$existingExpense) {

    respond(false, 'Expense not found.');
}

$invoiceImage =
    trim((string) (
        $existingExpense['invoiceImage'] ?? ''
    ));
    
    
/*
|--------------------------------------------------------------------------
| Fetch Current Expense
|--------------------------------------------------------------------------
*/

$checkQuery = "
    SELECT expenseStatus
    FROM employeeExpenses
    WHERE id = ?
    LIMIT 1
";

$checkStmt =
    mysqli_prepare($con, $checkQuery);

mysqli_stmt_bind_param(
    $checkStmt,
    'i',
    $id
);

mysqli_stmt_execute($checkStmt);

$result =
    mysqli_stmt_get_result($checkStmt);

$row =
    mysqli_fetch_assoc($result);

mysqli_stmt_close($checkStmt);

if (!$row) {

    respond(false, 'Expense not found.');
}

if (
    $row['expenseStatus'] === 'Approved'
) {

    respond(
        false,
        'Approved expense cannot be modified.'
    );
}

if (
    $row['expenseStatus'] === 'Rejected'
) {

    respond(
        false,
        'Rejected expense cannot be modified.'
    );
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

    $newFileName =
        'expense_' .
        time() .
        '_' .
        mt_rand(1000, 9999) .
        '.' .
        $extension;

    $destination =
        $uploadDir .
        $newFileName;

    if (
        !move_uploaded_file(
            $tmpName,
            $destination
        )
    ) {

        respond(false, 'Unable to upload invoice image.');
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Old File
    |--------------------------------------------------------------------------
    */

    if (!empty($invoiceImage)) {

        $oldFile =
            $uploadDir .
            $invoiceImage;

        if (is_file($oldFile)) {

            @unlink($oldFile);
        }
    }

    $invoiceImage = $newFileName;
}

/*
|--------------------------------------------------------------------------
| Fetch Employee
|--------------------------------------------------------------------------
*/

$employeeQuery = "
    SELECT fullName
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

$employeeResult =
    mysqli_stmt_get_result($employeeStmt);

$employeeRow =
    mysqli_fetch_assoc($employeeResult);

mysqli_stmt_close($employeeStmt);

if (!$employeeRow) {

    respond(false, 'Employee not found.');
}

$employeeName =
    trim((string) (
        $employeeRow['fullName'] ?? ''
    ));





/*
|--------------------------------------------------------------------------
| Update Expense
|--------------------------------------------------------------------------
*/

$query = "
    UPDATE employeeExpenses
    SET

        employeeId = ?,
        employeeName = ?,

        expenseType = ?,

        amount = ?,

        invoiceNumber = ?,
        invoiceImage = ?,

        expenseDate = ?,

        remark = ?,

        updatedAt = NOW()

    WHERE id = ?
";

$stmt = mysqli_prepare($con, $query);

if (!$stmt) {

    respond(false, 'Unable to prepare update query.');
}

$amountValue = (float) $amount;

mysqli_stmt_bind_param(
    $stmt,
    'issdssssi',

    $employeeId,
    $employeeName,

    $expenseType,

    $amountValue,

    $invoiceNumber,
    $invoiceImage,

    $expenseDate,

    $remark,

    $id
);

$executed = mysqli_stmt_execute($stmt);

if (!$executed) {

    $sqlError =
        mysqli_stmt_error($stmt);

    mysqli_stmt_close($stmt);

    respond(false, $sqlError);
}

mysqli_stmt_close($stmt);


/*
|--------------------------------------------------------------------------
| Send Expense Update Mail
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
            'sendExpenseUpdatedEmail'
        )
    ) {

        sendExpenseUpdatedEmail(

            $id,

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
        'Expense update mail error: ' .
        $e->getMessage()
    );
}




/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

respond(true, 'Expense updated successfully.', [

    'id' => $id
]);