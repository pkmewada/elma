<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/commissionBonusEngine.php';

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

    $employeeId =
        intval($_POST['employeeId'] ?? 0);

    $categoryId =
        intval($_POST['categoryId'] ?? 0);

    /*
    |--------------------------------------------------------------------------
    | Input Amount
    |--------------------------------------------------------------------------
    */

    $inputAmount =
        floatval($_POST['amount'] ?? 0);

    $effectiveMonth =
        trim($_POST['effectiveMonth'] ?? '');

    $remarks =
        trim($_POST['remarks'] ?? '');

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
    | Get Existing Transaction
    |--------------------------------------------------------------------------
    */

    $existing =
        CommissionBonusEngine::getTransactionById(
            $con,
            $transactionId
        );

    if (!$existing) {

        respond(
            false,
            'Transaction not found.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Prevent Paid Edit
    |--------------------------------------------------------------------------
    */

    if (
        strtolower(
            $existing['payrollStatus']
        ) === 'paid'
    ) {

        respond(
            false,
            'Paid transactions cannot be edited.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Get Category
    |--------------------------------------------------------------------------
    */

    $category =
        CommissionBonusEngine::getCategoryById(
            $con,
            $categoryId
        );

    if (!$category) {

        respond(
            false,
            'Invalid category selected.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Commission / Bonus Logic
    |--------------------------------------------------------------------------
    */

    $finalAmount = 0;

    $baseAmount = null;

    $commissionPercentage = null;

    /*
    |--------------------------------------------------------------------------
    | Commission
    |--------------------------------------------------------------------------
    */

    if (
        strtolower($category['categoryType']) === 'commission'
    ) {

        $baseAmount =
            $inputAmount;

        $commissionPercentage =
            floatval(
                $category['commissionPercentage'] ?? 0
            );

        if ($commissionPercentage <= 0) {

            respond(
                false,
                'Invalid commission percentage configured.'
            );
        }

        $finalAmount = round(

            (
                $baseAmount *
                $commissionPercentage
            ) / 100,

            2
        );

    } else {

        /*
        |--------------------------------------------------------------------------
        | Bonus
        |--------------------------------------------------------------------------
        */

        $finalAmount =
            $inputAmount;
    }

    /*
    |--------------------------------------------------------------------------
    | Prepare Update Data
    |--------------------------------------------------------------------------
    */

    $transactionData = [

        'employeeId' =>
            $employeeId,

        'categoryId' =>
            $categoryId,

        'transactionType' =>
            $category['categoryType'],

        'amount' =>
            $finalAmount,

        'baseAmount' =>
            $baseAmount,

        'commissionPercentage' =>
            $commissionPercentage,

        'remarks' =>
            $remarks,

        'effectiveMonth' =>
            $effectiveMonth,

        'updatedBy' =>
            $_SESSION['user_id'] ?? 0
    ];

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    $errors =
        CommissionBonusEngine::validateTransaction(
            $transactionData
        );

    if (!empty($errors)) {

        respond(
            false,
            implode(', ', $errors)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Attachment Upload
    |--------------------------------------------------------------------------
    */

    if (
        isset($_FILES['attachment']) &&
        !empty($_FILES['attachment']['name'])
    ) {

        $uploadDir =
            __DIR__ .
            '/../uploads/commission-bonus/';

        /*
        |--------------------------------------------------------------------------
        | Create Folder
        |--------------------------------------------------------------------------
        */

        if (!is_dir($uploadDir)) {

            mkdir(
                $uploadDir,
                0777,
                true
            );
        }

        $fileName =
            time() .
            '_' .
            preg_replace(
                '/[^A-Za-z0-9\.\-_]/',
                '',
                $_FILES['attachment']['name']
            );

        $targetPath =
            $uploadDir . $fileName;

        /*
        |--------------------------------------------------------------------------
        | Upload File
        |--------------------------------------------------------------------------
        */

        if (
            move_uploaded_file(
                $_FILES['attachment']['tmp_name'],
                $targetPath
            )
        ) {

            $transactionData['attachment'] =
                $fileName;

        } else {

            respond(
                false,
                'Unable to upload attachment.'
            );
        }

    } else {

        /*
        |--------------------------------------------------------------------------
        | Keep Existing Attachment
        |--------------------------------------------------------------------------
        */

        $transactionData['attachment'] =
            $existing['attachment'];
    }

    /*
    |--------------------------------------------------------------------------
    | Update Transaction
    |--------------------------------------------------------------------------
    */

    $updated =
        CommissionBonusEngine::updateTransaction(
            $con,
            $transactionId,
            $transactionData
        );

    if (!$updated) {

        respond(
            false,
            'Unable to update transaction.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Success Response
    |--------------------------------------------------------------------------
    */

    respond(
        true,
        'Transaction updated successfully.'
    );

} catch (Exception $e) {

    respond(
        false,
        $e->getMessage()
    );
}