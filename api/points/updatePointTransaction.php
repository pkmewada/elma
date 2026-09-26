<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/employeePointEngine.php';
require_once __DIR__ . '/../../includes/mailer.php';

header('Content-Type: application/json');

/*
|--------------------------------------------------------------------------
| JSON RESPONSE HELPER
|--------------------------------------------------------------------------
*/

function respond($success, $message = '', $data = [])
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);

    exit;
}

try {

    /*
    |--------------------------------------------------------------------------
    | INPUTS
    |--------------------------------------------------------------------------
    */

    $transactionId = (int)($_POST['transactionId'] ?? 0);

    $employeeId = (int)($_POST['employeeId'] ?? 0);

    $categoryId = (int)($_POST['categoryId'] ?? 0);

    $points = (float)($_POST['points'] ?? 0);

    $remarks = trim($_POST['remarks'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | VALIDATE TRANSACTION ID
    |--------------------------------------------------------------------------
    */

    if (!$transactionId) {

        respond(
            false,
            'Invalid transaction'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LOAD POINT ENGINE
    |--------------------------------------------------------------------------
    */

    $pointEngine = new EmployeePointEngine($con);

    /*
    |--------------------------------------------------------------------------
    | VALIDATE TRANSACTION DATA
    |--------------------------------------------------------------------------
    |
    | Validates:
    | - employee
    | - category
    | - points
    |--------------------------------------------------------------------------
    */

    $validation = $pointEngine->validateTransaction([

        'employeeId' => $employeeId,

        'categoryId' => $categoryId,

        'points' => $points
    ]);

    /*
    |--------------------------------------------------------------------------
    | VALIDATION FAILED
    |--------------------------------------------------------------------------
    */

    if (!$validation['success']) {

        respond(
            false,
            $validation['message']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CATEGORY DETAILS
    |--------------------------------------------------------------------------
    */

    $category = $validation['category'];

    $transactionType = $category['transactionType'];

    /*
    |--------------------------------------------------------------------------
    | START DATABASE TRANSACTION
    |--------------------------------------------------------------------------
    */

    mysqli_begin_transaction($con);

    /*
    |--------------------------------------------------------------------------
    | UPDATE TRANSACTION
    |--------------------------------------------------------------------------
    */

    $stmt = mysqli_prepare(
        $con,
        "UPDATE employeePointTransactions
         SET
            employeeId = ?,
            categoryId = ?,
            transactionType = ?,
            points = ?,
            remarks = ?
         WHERE id = ?"
    );

    /*
    |--------------------------------------------------------------------------
    | VALIDATE QUERY PREPARATION
    |--------------------------------------------------------------------------
    */

    if (!$stmt) {

        throw new Exception(
            'Failed to prepare update query'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | BIND PARAMETERS
    |--------------------------------------------------------------------------
    */

    mysqli_stmt_bind_param(
        $stmt,
        "iisdsi",
        $employeeId,
        $categoryId,
        $transactionType,
        $points,
        $remarks,
        $transactionId
    );

    /*
    |--------------------------------------------------------------------------
    | EXECUTE UPDATE
    |--------------------------------------------------------------------------
    */

    $execute = mysqli_stmt_execute($stmt);

    /*
    |--------------------------------------------------------------------------
    | EXECUTION VALIDATION
    |--------------------------------------------------------------------------
    */

    if (!$execute) {

        throw new Exception(
            'Failed to update transaction'
        );
    }

    mysqli_stmt_close($stmt);

    /*
    |--------------------------------------------------------------------------
    | COMMIT DATABASE CHANGES
    |--------------------------------------------------------------------------
    */

    mysqli_commit($con);

    /*
    |--------------------------------------------------------------------------
    | LOAD EMPLOYEE DETAILS
    |--------------------------------------------------------------------------
    |
    | Required for:
    | - email notifications
    | - audit communication
    |--------------------------------------------------------------------------
    */

    $employeeStmt = mysqli_prepare(
        $con,
        "SELECT
            fullName,
            emailAddress
         FROM employeeusers
         WHERE id = ?
         LIMIT 1"
    );

    if (!$employeeStmt) {

        throw new Exception(
            'Failed to prepare employee query'
        );
    }

    mysqli_stmt_bind_param(
        $employeeStmt,
        "i",
        $employeeId
    );

    mysqli_stmt_execute($employeeStmt);

    $employeeResult = mysqli_stmt_get_result($employeeStmt);

    $employee = mysqli_fetch_assoc($employeeResult);

    mysqli_stmt_close($employeeStmt);

    /*
    |--------------------------------------------------------------------------
    | SEND EMPLOYEE EMAIL NOTIFICATION
    |--------------------------------------------------------------------------
    |
    | Mail is sent AFTER successful commit
    | to ensure DB consistency.
    |--------------------------------------------------------------------------
    */

    if (
        $employee &&
        !empty($employee['emailAddress'])
    ) {

        sendPointTransactionUpdatedEmail(

            $transactionId,

            $employee['emailAddress'],

            $employee['fullName'],

            $transactionType,

            $category['categoryName'],

            $points,

            $remarks
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    respond(
        true,
        'Transaction updated successfully'
    );

} catch (Exception $e) {

    /*
    |--------------------------------------------------------------------------
    | ROLLBACK SAFETY
    |--------------------------------------------------------------------------
    */

    mysqli_rollback($con);

    respond(
        false,
        $e->getMessage()
    );
}