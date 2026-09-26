<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {

    respond(false, 'Invalid request method.');
}

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {

    respond(false, 'Invalid expense ID.');
}

/*
|--------------------------------------------------------------------------
| Fetch Expense
|--------------------------------------------------------------------------
*/

$query = "
    SELECT
        id,
        employeeId,
        employeeName,
        expenseType,
        amount,
        invoiceNumber,
        invoiceImage,
        expenseDate,
        remark
    FROM employeeExpenses
    WHERE id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($con, $query);

if (!$stmt) {

    respond(false, 'Unable to prepare query.');
}

mysqli_stmt_bind_param($stmt, 'i', $id);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$row = $result
    ? mysqli_fetch_assoc($result)
    : null;

mysqli_stmt_close($stmt);

if (!$row) {

    respond(false, 'Expense not found.');
}

/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

respond(true, 'Expense fetched successfully.', [

    'id' => (int) $row['id'],

    'employeeId' => (int) $row['employeeId'],

    'employeeName' => $row['employeeName'],

    'expenseType' => $row['expenseType'],

    'amount' => $row['amount'],
    
    'invoiceNumber' => $row['invoiceNumber'],
    
    'invoiceImage' => $row['invoiceImage'],

    'expenseDate' => $row['expenseDate'],

    'remark' => $row['remark']
]);