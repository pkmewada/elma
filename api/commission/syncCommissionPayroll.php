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

    /*
    |--------------------------------------------------------------------------
    | Inputs
    |--------------------------------------------------------------------------
    */

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
    | Prevent Reverted
    |--------------------------------------------------------------------------
    */

    if (
        intval($transaction['isReverted']) === 1
    ) {

        respond(
            false,
            'Reverted transactions cannot sync.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Approval Validation
    |--------------------------------------------------------------------------
    */

    if (
        strtolower(
            $transaction['approvalStatus']
        ) !== 'approved'
    ) {

        respond(
            false,
            'Only approved transactions can sync to payroll.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Prevent Duplicate Sync
    |--------------------------------------------------------------------------
    */

    if (
        strtolower(
            $transaction['payrollStatus']
        ) !== 'pending'
    ) {

        respond(
            false,
            'Transaction already synced.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Sync Payroll
    |--------------------------------------------------------------------------
    */

    $synced =
        CommissionBonusEngine::syncToPayroll(
            $con,
            $transactionId,
            $_SESSION['user_id'] ?? 0
        );

    if (!$synced) {

        respond(
            false,
            'Unable to sync payroll.'
        );
    }
    
    
    /*
    |--------------------------------------------------------------------------
    | Send Payroll Sync Mail
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
    
            sendCommissionPayrollSyncedEmail(
    
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
            'Commission payroll sync mail failed: ' .
            $e->getMessage()
        );
    }
    
    
    /*
    |--------------------------------------------------------------------------
    | Success Response
    |--------------------------------------------------------------------------
    */

    respond(
        true,
        'Transaction synced to payroll successfully.'
    );

} catch (Exception $e) {

    respond(
        false,
        $e->getMessage()
    );
}