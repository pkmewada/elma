<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/commissionBonusEngine.php';
require_once __DIR__ . '/../../includes/mailer.php';

header('Content-Type: application/json');

function respond(
    $success,
    $message = '',
    $data = []
) {

    echo json_encode([

        'success' => $success,

        'message' => $message,

        'data' => $data
    ]);

    exit;
}

try {

    $transactionId =
        intval($_POST['transactionId'] ?? 0);

    if ($transactionId <= 0) {

        respond(
            false,
            'Invalid transaction ID.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Get Transaction
    |--------------------------------------------------------------------------
    */

    $transaction =
        CommissionBonusEngine::getTransactionById(
            $con,
            $transactionId
        );

    if (!$transaction) {

        respond(
            false,
            'Transaction not found.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Prevent Duplicate Approval
    |--------------------------------------------------------------------------
    */

    if (
        strtolower(
            $transaction['approvalStatus']
        ) !== 'pending'
    ) {

        respond(
            false,
            'Only pending transactions can be approved.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Prevent Paid Edit
    |--------------------------------------------------------------------------
    */

    if (
        strtolower(
            $transaction['payrollStatus']
        ) === 'paid'
    ) {

        respond(
            false,
            'Paid transactions cannot be modified.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Approve
    |--------------------------------------------------------------------------
    */

    $approved =
        CommissionBonusEngine::approveTransaction(
            $con,
            $transactionId,
            $_SESSION['user_id'] ?? 0
        );

    if (!$approved) {

        respond(
            false,
            'Unable to approve transaction.'
        );
    }
    
    /*
|--------------------------------------------------------------------------
| Send Approval Mail
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | Get Employee
    |--------------------------------------------------------------------------
    */

    $employeeSql = "
        SELECT
            fullName,
            emailAddress
        FROM employeeusers
        WHERE id = ?
        LIMIT 1
    ";

    $employeeStmt = mysqli_prepare(
        $con,
        $employeeSql
    );

    mysqli_stmt_bind_param(
        $employeeStmt,
        "i",
        $transaction['employeeId']
    );

    mysqli_stmt_execute(
        $employeeStmt
    );

    $employeeResult =
        mysqli_stmt_get_result(
            $employeeStmt
        );

    $employee =
        mysqli_fetch_assoc(
            $employeeResult
        );
        
    mysqli_stmt_close(
            $employeeStmt
        );

    /*
    |--------------------------------------------------------------------------
    | Send Mail
    |--------------------------------------------------------------------------
    */

    if (!empty($employee['emailAddress'])) {

        sendCommissionTransactionApprovedEmail(

            $transactionId,

            $employee['emailAddress'],

            $employee['fullName'],

            $transaction['transactionCode'],

            floatval(
                $transaction['amount']
            )
        );
    }

} catch (Exception $e) {

    error_log(
        'Commission approve mail failed: ' .
        $e->getMessage()
    );
}

    respond(
        true,
        'Transaction approved successfully.'
    );

} catch (Exception $e) {

    respond(
        false,
        $e->getMessage()
    );
}