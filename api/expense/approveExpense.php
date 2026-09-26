<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/mailer.php';

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

if ($id <= 0) {

    respond(false, 'Invalid expense ID.');
}

/*
|--------------------------------------------------------------------------
| Current User
|--------------------------------------------------------------------------
*/

$approvedBy =
    trim((string) (
        $_SESSION['employeeName'] ?? 'System'
    ));

/*
|--------------------------------------------------------------------------
| Fetch Expense
|--------------------------------------------------------------------------
*/

$expenseQuery = "
    SELECT

        id,
        employeeId,
        employeeName,

        expenseType,

        amount,

        invoiceNumber,

        expenseDate,

        remark,

        expenseStatus

    FROM employeeExpenses

    WHERE id = ?

    LIMIT 1
";

$expenseStmt =
    mysqli_prepare($con, $expenseQuery);

if (!$expenseStmt) {

    respond(false, 'Unable to validate expense.');
}

mysqli_stmt_bind_param(
    $expenseStmt,
    'i',
    $id
);

mysqli_stmt_execute($expenseStmt);

$expenseResult =
    mysqli_stmt_get_result($expenseStmt);

$expense =
    $expenseResult
    ? mysqli_fetch_assoc($expenseResult)
    : null;

mysqli_stmt_close($expenseStmt);

if (!$expense) {

    respond(false, 'Expense not found.');
}

/*
|--------------------------------------------------------------------------
| Already Processed
|--------------------------------------------------------------------------
*/

if (
    $expense['expenseStatus'] === 'Approved'
) {

    respond(false, 'Expense already approved.');
}

if (
    $expense['expenseStatus'] === 'Rejected'
) {

    respond(false, 'Rejected expense cannot be approved.');
}

/*
|--------------------------------------------------------------------------
| Approve Expense
|--------------------------------------------------------------------------
*/

$updateQuery = "
    UPDATE employeeExpenses
    SET

        expenseStatus = 'Approved',

        approvedBy = ?,
        approvedAt = NOW(),

        updatedAt = NOW()

    WHERE id = ?

    LIMIT 1
";

$updateStmt =
    mysqli_prepare($con, $updateQuery);

if (!$updateStmt) {

    respond(false, 'Unable to prepare approval query.');
}

mysqli_stmt_bind_param(
    $updateStmt,
    'si',
    $approvedBy,
    $id
);

$executed =
    mysqli_stmt_execute($updateStmt);

mysqli_stmt_close($updateStmt);

if (!$executed) {

    respond(false, 'Unable to approve expense.');
}


/*
|--------------------------------------------------------------------------
| Fetch Employee Email
|--------------------------------------------------------------------------
*/

$employeeEmail = '';

$mailQuery = "
    SELECT
        emailAddress
    FROM employeeusers
    WHERE id = ?
    LIMIT 1
";

$mailStmt =
    mysqli_prepare($con, $mailQuery);

if ($mailStmt) {

    mysqli_stmt_bind_param(
        $mailStmt,
        'i',
        $expense['employeeId']
    );

    mysqli_stmt_execute($mailStmt);

    $mailResult =
        mysqli_stmt_get_result($mailStmt);

    $mailRow =
        $mailResult
        ? mysqli_fetch_assoc($mailResult)
        : null;

    mysqli_stmt_close($mailStmt);

    $employeeEmail =
        trim((string) (
            $mailRow['emailAddress'] ?? ''
        ));
}


/*
|--------------------------------------------------------------------------
| Send Email
|--------------------------------------------------------------------------
*/

if (
    $employeeEmail !== '' &&
    function_exists(
        'sendExpenseApprovedEmail'
    )
) {

    try {

        sendExpenseApprovedEmail(

        (int) $expense['id'],
    
        $employeeEmail,
    
        $expense['employeeName'],
    
        $expense['expenseType'],
    
        (float) $expense['amount'],
    
        $expense['expenseDate'],
    
        $approvedBy
    );

    } catch (\Throwable $e) {

        error_log(
            'Expense approval mail error: ' .
            $e->getMessage()
        );
    }
}


/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

respond(true, 'Expense approved successfully.', [

    'id' => $id,

    'expenseStatus' => 'Approved',

    'approvedBy' => $approvedBy
]);