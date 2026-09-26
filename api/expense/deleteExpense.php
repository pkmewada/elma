<?php

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
    string $message
): void {

    echo json_encode([
        'success' => $success,
        'message' => $message
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

$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {

    respond(false, 'Invalid expense ID.');
}

/*
|--------------------------------------------------------------------------
| Fetch Expense Details
|--------------------------------------------------------------------------
*/

$expenseQuery = "
    SELECT

        ee.id,
        ee.employeeId,
        ee.employeeName,
        ee.expenseType,
        ee.amount,
        ee.invoiceNumber,
        ee.expenseDate,
        ee.remark,

        eu.emailAddress

    FROM employeeExpenses ee

    LEFT JOIN employeeusers eu
        ON eu.id = ee.employeeId

    WHERE ee.id = ?

    LIMIT 1
";

$expenseStmt = mysqli_prepare(
    $con,
    $expenseQuery
);

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
    mysqli_stmt_get_result(
        $expenseStmt
    );

$expenseData =
    mysqli_fetch_assoc(
        $expenseResult
    );

mysqli_stmt_close($expenseStmt);

if (!$expenseData) {

    respond(false, 'Expense not found.');
}

/*
|--------------------------------------------------------------------------
| Delete Expense
|--------------------------------------------------------------------------
*/

$query = "
    DELETE FROM employeeExpenses
    WHERE id = ?
";

$stmt = mysqli_prepare($con, $query);

if (!$stmt) {

    respond(false, 'Unable to prepare delete query.');
}

mysqli_stmt_bind_param($stmt, 'i', $id);

$executed = mysqli_stmt_execute($stmt);

mysqli_stmt_close($stmt);

if (!$executed) {

    respond(false, 'Unable to delete expense.');
}


/*
|--------------------------------------------------------------------------
| Send Expense Delete Mail
|--------------------------------------------------------------------------
*/

try {

    $employeeEmail =
        trim((string) (
            $expenseData['emailAddress'] ?? ''
        ));

    if (
        $employeeEmail !== '' &&
        function_exists(
            'sendExpenseDeletedEmail'
        )
    ) {

        sendExpenseDeletedEmail(

            (int) $expenseData['id'],

            $employeeEmail,

            (string) $expenseData['employeeName'],

            (string) $expenseData['expenseType'],

            (float) $expenseData['amount'],

            (string) $expenseData['expenseDate'],

            (string) $expenseData['invoiceNumber'],

            (string) $expenseData['remark']
        );
    }

} catch (\Throwable $e) {

    error_log(
        'Expense delete mail error: ' .
        $e->getMessage()
    );
}

/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

respond(true, 'Expense deleted successfully.');