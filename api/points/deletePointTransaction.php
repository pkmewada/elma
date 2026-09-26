<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
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
    | VALIDATE INPUT
    |--------------------------------------------------------------------------
    */

    $transactionId = (int)($_POST['transactionId'] ?? 0);

    if (!$transactionId) {

        respond(
            false,
            'Invalid transaction'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LOAD TRANSACTION DETAILS
    |--------------------------------------------------------------------------
    |
    | We fetch:
    | - transaction info
    | - category info
    | - employee info
    |
    | Required for:
    | - validation
    | - email notifications
    | - audit safety
    |--------------------------------------------------------------------------
    */

    $stmt = mysqli_prepare(
        $con,
        "SELECT
            t.id,
            t.points,

            c.categoryName,

            e.fullName,
            e.emailAddress

         FROM employeePointTransactions t

         LEFT JOIN employeePointCategories c
            ON c.id = t.categoryId

         LEFT JOIN employeeusers e
            ON e.id = t.employeeId

         WHERE t.id = ?
         LIMIT 1"
    );

    if (!$stmt) {

        throw new Exception(
            'Failed to prepare transaction query'
        );
    }

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $transactionId
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    $transaction = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    /*
    |--------------------------------------------------------------------------
    | VALIDATE TRANSACTION
    |--------------------------------------------------------------------------
    */

    if (!$transaction) {

        respond(
            false,
            'Transaction not found'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | START DATABASE TRANSACTION
    |--------------------------------------------------------------------------
    |
    | Using DB transaction ensures:
    | - atomic operation
    | - rollback safety
    | - audit consistency
    |--------------------------------------------------------------------------
    */

    mysqli_begin_transaction($con);

    /*
    |--------------------------------------------------------------------------
    | SOFT DELETE / REVERT TRANSACTION
    |--------------------------------------------------------------------------
    |
    | Instead of permanently deleting records,
    | we mark them as reverted.
    |
    | This preserves:
    | - audit history
    | - HR traceability
    | - payroll consistency
    |--------------------------------------------------------------------------
    */

    $stmt = mysqli_prepare(
        $con,
        "UPDATE employeePointTransactions
         SET isReverted = 1
         WHERE id = ?"
    );

    if (!$stmt) {

        throw new Exception(
            'Failed to prepare delete query'
        );
    }

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $transactionId
    );

    $execute = mysqli_stmt_execute($stmt);

    /*
    |--------------------------------------------------------------------------
    | EXECUTION VALIDATION
    |--------------------------------------------------------------------------
    */

    if (!$execute) {

        throw new Exception(
            'Failed to delete transaction'
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
    | SEND EMPLOYEE EMAIL NOTIFICATION
    |--------------------------------------------------------------------------
    |
    | Mail is sent AFTER successful commit
    | to avoid notifying users for failed transactions.
    |--------------------------------------------------------------------------
    */

    if (!empty($transaction['emailAddress'])) {

        sendPointTransactionDeletedEmail(

            $transactionId,

            $transaction['emailAddress'],

            $transaction['fullName'],

            $transaction['categoryName'],

            (float)$transaction['points']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    respond(
        true,
        'Transaction deleted successfully'
    );

} catch (Exception $e) {

    /*
    |--------------------------------------------------------------------------
    | ROLLBACK SAFETY
    |--------------------------------------------------------------------------
    |
    | If anything fails:
    | - rollback DB changes
    | - preserve data integrity
    |--------------------------------------------------------------------------
    */

    mysqli_rollback($con);

    respond(
        false,
        $e->getMessage()
    );
}