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

    $currentUserId =
        $_SESSION['user_id'] ?? 0;

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
    | Prepare Transaction
    |--------------------------------------------------------------------------
    */

    $transactionData = [

        'transactionCode' =>
            CommissionBonusEngine::generateTransactionCode(),

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

        'attachment' =>
            '',

        'effectiveMonth' =>
            $effectiveMonth,

        'payrollStatus' =>
            'Pending',

        'approvalStatus' =>
            (
                intval(
                    $category['requiresApproval']
                ) === 1
            )
                ? 'Pending'
                : 'Approved',

        'createdBy' =>
            $currentUserId
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
    }

    /*
    |--------------------------------------------------------------------------
    | Prevent Duplicate Submission
    |--------------------------------------------------------------------------
    */

    $duplicateSql = "
        SELECT id
        FROM employeeCommissionTransactions
        WHERE

            id != ?
            AND employeeId = ?
            AND categoryId = ?
            AND (
                amount = ?
                OR baseAmount = ?
            )
            AND effectiveMonth = ?
            AND createdBy = ?
            AND isReverted = 0
            AND createdAt >= (
                NOW() - INTERVAL 10 SECOND
            )

        LIMIT 1
    ";

    $duplicateStmt = mysqli_prepare(
        $con,
        $duplicateSql
    );

    mysqli_stmt_bind_param(
        $duplicateStmt,
        "iiiddsi",
        $transactionId,
        $employeeId,
        $categoryId,
        $finalAmount,
        $baseAmount,
        $effectiveMonth,
        $currentUserId
    );

    mysqli_stmt_execute(
        $duplicateStmt
    );

    $duplicateResult =
        mysqli_stmt_get_result(
            $duplicateStmt
        );

    if (
        mysqli_num_rows(
            $duplicateResult
        ) > 0
    ) {

        mysqli_stmt_close(
            $duplicateStmt
        );

        respond(
            false,
            'Duplicate transaction detected. Please wait before submitting again.'
        );
    }

    mysqli_stmt_close(
        $duplicateStmt
    );

    /*
    |--------------------------------------------------------------------------
    | Save Transaction
    |--------------------------------------------------------------------------
    */

    $saved =
        CommissionBonusEngine::saveTransaction(
            $con,
            $transactionData
        );

    if (!$saved) {

        respond(
            false,
            'Unable to save transaction.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Get Inserted Transaction ID
    |--------------------------------------------------------------------------
    */

    $insertedTransactionId =
        mysqli_insert_id($con);
        
        
        /*
|--------------------------------------------------------------------------
| Send Employee Mail
|--------------------------------------------------------------------------
*/

try {

    $mailSql = "
        SELECT

            ect.id,
            ect.transactionCode,
            ect.amount,
            ect.effectiveMonth,
            ect.remarks,

            cbc.categoryName,
            cbc.categoryType,

            e.fullName,
            e.emailAddress

        FROM employeeCommissionTransactions ect

        LEFT JOIN commissionBonusCategories cbc
            ON cbc.id = ect.categoryId

        LEFT JOIN employeeusers e
            ON e.id = ect.employeeId

        WHERE ect.id = ?

        LIMIT 1
    ";

    $mailStmt =
        mysqli_prepare(
            $con,
            $mailSql
        );

    mysqli_stmt_bind_param(
        $mailStmt,
        'i',
        $insertedTransactionId
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

        sendCommissionTransactionCreatedEmail(

            (int) $mailData['id'],

            $mailData['emailAddress'],

            $mailData['fullName'],

            $mailData['transactionCode'],

            $mailData['categoryName'],

            $mailData['categoryType'],

            (float) $mailData['amount'],

            $mailData['effectiveMonth'],

            $mailData['remarks'] ?? ''
        );
    }

} catch (Throwable $e) {

    error_log(
        'Commission Create Mail Error: ' .
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
        'Transaction saved successfully.'
    );

} catch (Exception $e) {

    respond(
        false,
        $e->getMessage()
    );
}