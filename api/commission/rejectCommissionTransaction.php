<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
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

    $reason =
        trim($_POST['reason'] ?? '');

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
    | Only Pending
    |--------------------------------------------------------------------------
    */

    if (
        strtolower(
            $transaction['approvalStatus']
        ) !== 'pending'
    ) {

        respond(
            false,
            'Only pending transactions can be rejected.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Reject
    |--------------------------------------------------------------------------
    */

    $rejected =
        CommissionBonusEngine::rejectTransaction(
            $con,
            $transactionId,
            $_SESSION['user_id'] ?? 0,
            $reason
        );

    if (!$rejected) {

        respond(
            false,
            'Unable to reject transaction.'
        );
    }
    
    
    /*
    |--------------------------------------------------------------------------
    | Send Rejection Mail
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
    
            sendCommissionTransactionRejectedEmail(
    
                $transactionId,
    
                $employee['emailAddress'],
    
                $employee['fullName'],
    
                $transaction['transactionCode'],
    
                $reason
            );
        }
    
    } catch (Exception $e) {
    
        error_log(
            'Commission reject mail failed: ' .
            $e->getMessage()
        );
    }
    
    respond(
        true,
        'Transaction rejected successfully.'
    );

} catch (Exception $e) {

    respond(
        false,
        $e->getMessage()
    );
}