<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

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

try {

    /*
    |--------------------------------------------------------------------------
    | Fetch Main Settings
    |--------------------------------------------------------------------------
    */

    $settings = [];

    $settingsQuery = "
        SELECT

            id,

            monthlyAllocation,
            warningThreshold,
            payrollThreshold,

            enableWarningMail,
            enablePayrollImpact,

            autoResetMonthly,

            carryForward,
            carryForwardLimit,

            approvalWorkflow

        FROM employeePointSettings

        WHERE isActive = 1

        LIMIT 1
    ";

    $settingsResult =
        mysqli_query(
            $con,
            $settingsQuery
        );

    if (!$settingsResult) {

        throw new Exception(
            mysqli_error($con)
        );
    }

    $settings =
        mysqli_fetch_assoc(
            $settingsResult
        ) ?: [];

    /*
    |--------------------------------------------------------------------------
    | Fetch Categories
    |--------------------------------------------------------------------------
    */

    $categories = [];

    $categoryQuery = "
        SELECT

            id,

            categoryName,
            categoryCode,

            transactionType,

            defaultPoints,

            severityLevel,

            autoWarning,
            payrollImpact,

            isActive

        FROM employeePointCategories

        ORDER BY id ASC
    ";

    $categoryResult =
        mysqli_query(
            $con,
            $categoryQuery
        );

    if (!$categoryResult) {

        throw new Exception(
            mysqli_error($con)
        );
    }

    while (
        $row =
            mysqli_fetch_assoc(
                $categoryResult
            )
    ) {

        $categories[] = [

            'id' =>
                (int) $row['id'],

            'categoryName' =>
                (string) $row['categoryName'],

            'categoryCode' =>
                (string) $row['categoryCode'],

            'transactionType' =>
                (string) $row['transactionType'],

            'defaultPoints' =>
                (int) $row['defaultPoints'],

            'severityLevel' =>
                (string) $row['severityLevel'],

            'autoWarning' =>
                (int) $row['autoWarning'],

            'payrollImpact' =>
                (int) $row['payrollImpact'],

            'isActive' =>
                (int) $row['isActive']
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    respond(
        true,
        'Setup loaded successfully.',
        [

            'settings' => $settings,

            'categories' => $categories
        ]
    );

} catch (\Throwable $e) {

    error_log(
        'Point setup load error: ' .
        $e->getMessage()
    );

    respond(
        false,
        'Unable to load point setup.'
    );
}