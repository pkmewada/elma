<?php

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

$rejectedBy =
    trim((string) (
        $_SESSION['employeeName'] ?? 'System'
    ));

/*
|--------------------------------------------------------------------------
| Fetch Expense Details
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
        invoiceImage,

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
| Expense Lock Validation
|--------------------------------------------------------------------------
*/

if (
    $expense['expenseStatus'] === 'Approved'
) {

    respond(
        false,
        'Approved expense cannot be rejected.'
    );
}

if (
    $expense['expenseStatus'] === 'Rejected'
) {

    respond(
        false,
        'Expense already rejected.'
    );
}

/*
|--------------------------------------------------------------------------
| Reject Expense
|--------------------------------------------------------------------------
*/

$updateQuery = "
    UPDATE employeeExpenses
    SET

        expenseStatus = 'Rejected',

        rejectedBy = ?,
        rejectedAt = NOW(),

        updatedAt = NOW()

    WHERE id = ?

    LIMIT 1
";

$updateStmt =
    mysqli_prepare($con, $updateQuery);

if (!$updateStmt) {

    respond(false, 'Unable to prepare rejection query.');
}

mysqli_stmt_bind_param(
    $updateStmt,
    'si',
    $rejectedBy,
    $id
);

$executed =
    mysqli_stmt_execute($updateStmt);

if (!$executed) {

    $sqlError =
        mysqli_stmt_error($updateStmt);

    mysqli_stmt_close($updateStmt);

    respond(false, $sqlError);
}

mysqli_stmt_close($updateStmt);

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
| Send Rejection Mail
|--------------------------------------------------------------------------
*/

if (
    $employeeEmail !== '' &&
    function_exists(
        'sendExpenseRejectedEmail'
    )
) {

    try {

        $mailSent =
            sendExpenseRejectedEmail(

                (int) $expense['id'],

                $employeeEmail,

                $expense['employeeName'],

                $expense['expenseType'],

                (float) $expense['amount'],

                $expense['expenseDate'],

                $expense['invoiceNumber'],

                $expense['remark'],

                $rejectedBy
            );

        if (!$mailSent) {

            error_log(
                'Expense rejection mail failed for Expense ID: ' .
                $expense['id']
            );
        }

    } catch (\Throwable $e) {

        error_log(
            'Expense rejection mail error: ' .
            $e->getMessage()
        );
    }
}
/*
|--------------------------------------------------------------------------
| Final Response
|--------------------------------------------------------------------------
*/

respond(true, 'Expense rejected successfully.', [

    'id' => $id,

    'expenseStatus' => 'Rejected',

    'rejectedBy' => $rejectedBy
]);