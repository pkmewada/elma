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

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

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
    | Prevent Paid Transaction Delete
    |--------------------------------------------------------------------------
    */

    if (
        strtolower(
            $transaction['payrollStatus']
        ) === 'paid'
    ) {

        respond(
            false,
            'Paid transactions cannot be deleted.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Soft Delete Transaction
    |--------------------------------------------------------------------------
    */

    $deleted =
        CommissionBonusEngine::deleteTransaction(
            $con,
            $transactionId,
            $_SESSION['user_id'] ?? 0
        );

    if (!$deleted) {

        respond(
            false,
            'Unable to delete transaction.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Send Delete Mail
    |--------------------------------------------------------------------------
    */

    try {

        /*
        |--------------------------------------------------------------------------
        | Get Employee + Category
        |--------------------------------------------------------------------------
        */

        $mailSql = "
            SELECT

                eu.fullName,
                eu.emailAddress,

                cbc.categoryName

            FROM employeeusers eu

            LEFT JOIN commissionBonusCategories cbc
                ON cbc.id = ?

            WHERE eu.id = ?

            LIMIT 1
        ";

        $mailStmt =
            mysqli_prepare(
                $con,
                $mailSql
            );

        mysqli_stmt_bind_param(
            $mailStmt,
            "ii",
            $transaction['categoryId'],
            $transaction['employeeId']
        );

        mysqli_stmt_execute(
            $mailStmt
        );

        $mailResult =
            mysqli_stmt_get_result(
                $mailStmt
            );

        $mailData =
            mysqli_fetch_assoc(
                $mailResult
            );

        mysqli_stmt_close(
            $mailStmt
        );

        /*
        |--------------------------------------------------------------------------
        | Send Mail
        |--------------------------------------------------------------------------
        */

        if (
            !empty($mailData['emailAddress'])
        ) {

            sendCommissionTransactionDeletedEmail(

                $transactionId,

                $mailData['emailAddress'],

                $mailData['fullName'],

                $transaction['transactionCode'],

                $mailData['categoryName'],

                floatval(
                    $transaction['amount']
                )
            );
        }

    } catch (Throwable $e) {

        error_log(
            'Commission delete mail failed: ' .
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
        'Transaction deleted successfully.'
    );

} catch (Exception $e) {

    respond(
        false,
        $e->getMessage()
    );
}