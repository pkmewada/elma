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

$deductionType = trim((string) ($_POST['deductionType'] ?? ''));

$amount = trim((string) ($_POST['amount'] ?? ''));

$deductionDate = trim((string) ($_POST['deductionDate'] ?? ''));

$remark = trim((string) ($_POST['remark'] ?? ''));

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if ($id <= 0) {

    respond(false, 'Invalid deduction ID.');
}

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
| Update Deduction
|--------------------------------------------------------------------------
*/

$query = "
    UPDATE employeeDeductions
    SET

        employeeId = ?,
        employeeName = ?,

        deductionType = ?,

        amount = ?,

        deductionDate = ?,

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
    'issdssi',

    $employeeId,
    $employeeName,

    $deductionType,

    $amountValue,

    $deductionDate,

    $remark,

    $id
);

$executed = mysqli_stmt_execute($stmt);

mysqli_stmt_close($stmt);

if (!$executed) {

    respond(false, 'Unable to update deduction.');
}

/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

respond(true, 'Deduction updated successfully.', [

    'id' => $id
]);