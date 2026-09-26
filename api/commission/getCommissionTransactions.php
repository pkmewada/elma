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
    | Employee Filter
    |--------------------------------------------------------------------------
    */

    $employeeId =
        intval($_GET['employeeId'] ?? 0);

    /*
    |--------------------------------------------------------------------------
    | Get Transactions
    |--------------------------------------------------------------------------
    */

    $transactions =
        CommissionBonusEngine::getTransactions(
            $con,
            $employeeId
        );

    /*
    |--------------------------------------------------------------------------
    | Calculate Summary
    |--------------------------------------------------------------------------
    */

    $summary =
        CommissionBonusEngine::calculateSummary(
            $transactions
        );

    /*
    |--------------------------------------------------------------------------
    | Format Transactions
    |--------------------------------------------------------------------------
    */

    $formatted = [];

    foreach ($transactions as $row) {

        $formatted[] = [

            'id' =>
                (int)$row['id'],

            'transactionCode' =>
                $row['transactionCode'],

            'employeeId' =>
                (int)$row['employeeId'],

            'employeeName' =>
                $row['employeeName'],

            'categoryId' =>
                (int)$row['categoryId'],

            'categoryName' =>
                $row['categoryName'],

            'categoryCode' =>
                $row['categoryCode'],

            'categoryType' =>
                $row['categoryType'],

            'amount' =>
                (float)$row['amount'],

            'remarks' =>
                $row['remarks'],

            'attachment' =>
                $row['attachment'],

            'effectiveMonth' =>
                $row['effectiveMonth'],

            'approvalStatus' =>
                $row['approvalStatus'],

            'payrollStatus' =>
                $row['payrollStatus'],

            'createdAt' =>
                date(
                    'd M Y',
                    strtotime($row['createdAt'])
                )
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Success Response
    |--------------------------------------------------------------------------
    */

    respond(
        true,
        'Transactions loaded successfully',
        [

            'summary' => [

                'pending' =>
                    round(
                        $summary['pending'],
                        2
                    ),

                'approved' =>
                    round(
                        $summary['approved'],
                        2
                    ),

                'synced' =>
                    round(
                        $summary['synced'],
                        2
                    ),

                'paid' =>
                    round(
                        $summary['paid'],
                        2
                    )
            ],

            'transactions' =>
                $formatted
        ]
    );

} catch (Exception $e) {

    respond(
        false,
        $e->getMessage()
    );
}