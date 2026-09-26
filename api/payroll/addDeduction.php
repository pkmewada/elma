<?php

date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/../../includes/auth.php';

require_once __DIR__ . '/../../includes/db.php';

/*
|--------------------------------------------------------------------------
| Set MySQL Timezone (IST)
|--------------------------------------------------------------------------
*/

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

$id = (int) ($_POST['id'] ?? 0);

$employeeId = (int) ($_POST['employeeId'] ?? 0);

$deductionType = trim((string) ($_POST['deductionType'] ?? ''));

$amount = trim((string) ($_POST['amount'] ?? ''));

$deductionDate = trim((string) ($_POST['deductionDate'] ?? ''));

$remark = trim((string) ($_POST['remark'] ?? ''));

/*
|--------------------------------------------------------------------------
| Validations
|--------------------------------------------------------------------------
*/

if ($employeeId <= 0) {

    respond(false, 'Please select employee.');
}

if ($deductionType === '') {

    respond(false, 'Please select deduction type.');
}

if ($amount === '' || !is_numeric($amount)) {

    respond(false, 'Please enter valid amount.');
}

if ((float)$amount <= 0) {

    respond(false, 'Amount must be greater than zero.');
}

if ($deductionDate === '') {

    respond(false, 'Please select deduction date.');
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
| Insert Deduction
|--------------------------------------------------------------------------
*/

$insertQuery = "
    INSERT INTO employeeDeductions (

        employeeId,
        employeeName,

        deductionType,

        amount,

        deductionDate,

        remark,

        createdBy

    ) VALUES (

        ?, ?, ?, ?, ?, ?, ?

    )
";

$insertStmt = mysqli_prepare($con, $insertQuery);

if (!$insertStmt) {

    respond(false, 'Unable to prepare deduction query.');
}

$amountValue = (float) $amount;

mysqli_stmt_bind_param(
    $insertStmt,
    'issdsss',

    $employeeId,
    $employeeName,

    $deductionType,

    $amountValue,

    $deductionDate,

    $remark,

    $createdBy
);

$executed = mysqli_stmt_execute($insertStmt);

if (!$executed) {

    mysqli_stmt_close($insertStmt);

    respond(false, 'Unable to save deduction.');
}

$insertId = mysqli_insert_id($con);

mysqli_stmt_close($insertStmt);

/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

respond(true, 'Deduction added successfully.', [

    'id' => $insertId,

    'employeeId' => $employeeId,

    'employeeName' => $employeeName,

    'deductionType' => $deductionType,

    'amount' => number_format($amountValue, 2, '.', ''),

    'deductionDate' => date(
        'd M Y',
        strtotime($deductionDate)
    ),

    'remark' => $remark,

    'createdBy' => $createdBy,

    'createdAt' => date('d M Y h:i A')
]);