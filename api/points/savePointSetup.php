<?php

date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

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
| Decode Payload
|--------------------------------------------------------------------------
*/

$payload =
    json_decode(
        file_get_contents('php://input'),
        true
    );

if (
    !$payload ||
    !is_array($payload)
) {

    respond(false, 'Invalid payload.');
}

/*
|--------------------------------------------------------------------------
| Inputs
|--------------------------------------------------------------------------
*/

$categories =
    $payload['pointCategories'] ?? [];

$settings =
    $payload['pointSettings'] ?? [];

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if (!is_array($categories)) {

    respond(false, 'Invalid categories.');
}

if (!is_array($settings)) {

    respond(false, 'Invalid settings.');
}

/*
|--------------------------------------------------------------------------
| Settings
|--------------------------------------------------------------------------
*/

$monthlyAllocation =
    (int) ($settings['monthlyAllocation'] ?? 0);

$warningThreshold =
    (int) ($settings['warningThreshold'] ?? 0);

$payrollThreshold =
    (int) ($settings['payrollThreshold'] ?? 0);

$enableWarningMail =
    (int) ($settings['enableWarningMail'] ?? 0);

$enablePayrollImpact =
    (int) ($settings['enablePayrollImpact'] ?? 0);

$autoResetMonthly =
    (int) ($settings['autoResetMonthly'] ?? 0);

$carryForward =
    (int) ($settings['carryForward'] ?? 0);

$carryForwardLimit =
    (int) ($settings['carryForwardLimit'] ?? 0);

$approvalWorkflow =
    trim((string) (
        $settings['approvalWorkflow'] ?? ''
    ));
    
    
$createdBy =
    trim((string) (
        $_SESSION['employeeName']
        ?? 'System'
    ));

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if ($monthlyAllocation <= 0) {

    respond(
        false,
        'Monthly allocation must be greater than zero.'
    );
}

if ($warningThreshold < 0) {

    respond(false, 'Invalid warning threshold.');
}

if ($payrollThreshold < 0) {

    respond(false, 'Invalid payroll threshold.');
}

/*
|--------------------------------------------------------------------------
| Database Transaction
|--------------------------------------------------------------------------
*/

mysqli_begin_transaction($con);

try {

    /*
    |--------------------------------------------------------------------------
    | Save Main Settings
    |--------------------------------------------------------------------------
    */

    $checkQuery = "
        SELECT id
        FROM employeePointSettings
        LIMIT 1
    ";

    $checkResult =
        mysqli_query($con, $checkQuery);

    $existing =
        mysqli_fetch_assoc($checkResult);

    if ($existing) {

        $settingsId =
            (int) $existing['id'];

        $updateQuery = "
            UPDATE employeePointSettings
            SET

                monthlyAllocation = ?,
                warningThreshold = ?,
                payrollThreshold = ?,

                enableWarningMail = ?,
                enablePayrollImpact = ?,

                autoResetMonthly = ?,

                carryForward = ?,
                carryForwardLimit = ?,

                approvalWorkflow = ?,
                
                createdBy = ?,

                updatedAt = NOW()

            WHERE id = ?
        ";

        $stmt =
            mysqli_prepare(
                $con,
                $updateQuery
            );

        mysqli_stmt_bind_param(
            $stmt,
            'iiiiiiiissi',

            $monthlyAllocation,
            $warningThreshold,
            $payrollThreshold,

            $enableWarningMail,
            $enablePayrollImpact,

            $autoResetMonthly,

            $carryForward,
            $carryForwardLimit,

            $approvalWorkflow,
            
            $createdBy,

            $settingsId
        );

        mysqli_stmt_execute($stmt);

        mysqli_stmt_close($stmt);

    } else {

        $insertQuery = "
            INSERT INTO employeePointSettings (

                monthlyAllocation,
                warningThreshold,
                payrollThreshold,

                enableWarningMail,
                enablePayrollImpact,

                autoResetMonthly,

                carryForward,
                carryForwardLimit,

                approvalWorkflow

            ) VALUES (

                ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ";

        $stmt =
            mysqli_prepare(
                $con,
                $insertQuery
            );

        mysqli_stmt_bind_param(
            $stmt,
            'iiiiiiiis',

            $monthlyAllocation,
            $warningThreshold,
            $payrollThreshold,

            $enableWarningMail,
            $enablePayrollImpact,

            $autoResetMonthly,

            $carryForward,
            $carryForwardLimit,

            $approvalWorkflow
        );

        mysqli_stmt_execute($stmt);

        mysqli_stmt_close($stmt);
    }

    /*
    |--------------------------------------------------------------------------
    | Save Categories
    |--------------------------------------------------------------------------
    */

    foreach ($categories as $item) {

        $id =
            (int) ($item['id'] ?? 0);

        $categoryName =
            trim((string) (
                $item['categoryName'] ?? ''
            ));

        $categoryCode =
            strtoupper(
                trim((string) (
                    $item['categoryCode'] ?? ''
                ))
            );

        $transactionType =
            trim((string) (
                $item['transactionType'] ?? ''
            ));

        $defaultPoints =
            (int) (
                $item['defaultPoints'] ?? 0
            );

        $severityLevel =
            trim((string) (
                $item['severityLevel'] ?? ''
            ));

        $autoWarning =
            (int) (
                $item['autoWarning'] ?? 0
            );

        $payrollImpact =
            (int) (
                $item['payrollImpact'] ?? 0
            );

        $isActive =
            (int) (
                $item['isActive'] ?? 1
            );

        if (
            $categoryName === '' ||
            $categoryCode === ''
        ) {

            continue;
        }
        
        
        
        
        $duplicateQuery = "
            SELECT id
            FROM employeePointCategories
            WHERE categoryCode = ?
            AND id != ?
            LIMIT 1
        ";
        
        $duplicateStmt =
            mysqli_prepare(
                $con,
                $duplicateQuery
            );
        
        mysqli_stmt_bind_param(
            $duplicateStmt,
            'si',
            $categoryCode,
            $id
        );
        
        mysqli_stmt_execute($duplicateStmt);
        
        $duplicateResult =
            mysqli_stmt_get_result($duplicateStmt);
        
        $duplicateRow =
            mysqli_fetch_assoc($duplicateResult);
        
        mysqli_stmt_close($duplicateStmt);
        
        if ($duplicateRow) {
        
            throw new Exception(
                'Duplicate category code: ' .
                $categoryCode
            );
        }
        
        /*
        |--------------------------------------------------------------------------
        | Update Existing
        |--------------------------------------------------------------------------
        */

        if ($id > 0) {

            $query = "
                UPDATE employeePointCategories
                SET

                    categoryName = ?,
                    categoryCode = ?,

                    transactionType = ?,

                    defaultPoints = ?,

                    severityLevel = ?,

                    autoWarning = ?,
                    payrollImpact = ?,

                    isActive = ?,

                    updatedAt = NOW()

                WHERE id = ?
            ";

            $stmt =
                mysqli_prepare(
                    $con,
                    $query
                );

            mysqli_stmt_bind_param(
                $stmt,
                'sssisiiii',

                $categoryName,
                $categoryCode,

                $transactionType,

                $defaultPoints,

                $severityLevel,

                $autoWarning,
                $payrollImpact,

                $isActive,

                $id
            );

            mysqli_stmt_execute($stmt);

            mysqli_stmt_close($stmt);

        } else {

            /*
            |--------------------------------------------------------------------------
            | Insert New
            |--------------------------------------------------------------------------
            */

            $query = "
                INSERT INTO employeePointCategories (

                    categoryName,
                    categoryCode,

                    transactionType,

                    defaultPoints,

                    severityLevel,

                    autoWarning,
                    payrollImpact,

                    isActive

                ) VALUES (

                    ?, ?, ?, ?, ?, ?, ?, ?
                )
            ";

            $stmt =
                mysqli_prepare(
                    $con,
                    $query
                );

            mysqli_stmt_bind_param(
                $stmt,
                'sssisiii',

                $categoryName,
                $categoryCode,

                $transactionType,

                $defaultPoints,

                $severityLevel,

                $autoWarning,
                $payrollImpact,

                $isActive
            );

            mysqli_stmt_execute($stmt);

            mysqli_stmt_close($stmt);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    mysqli_commit($con);

    respond(
        true,
        'Point setup saved successfully.'
    );

} catch (\Throwable $e) {

    mysqli_rollback($con);

    error_log(
        'Point setup save error: ' .
        $e->getMessage()
    );

    respond(
        false,
        'Unable to save point setup.'
    );
}