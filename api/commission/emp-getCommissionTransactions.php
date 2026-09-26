<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../includes/emp-auth.php';

require_once __DIR__ . '/../../includes/db.php';

require_once __DIR__ . '/../../includes/employeeInfoEngine.php';

header('Content-Type: application/json');

/*
|--------------------------------------------------------------------------
| Response Helper
|--------------------------------------------------------------------------
*/

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
    | Engine
    |--------------------------------------------------------------------------
    */

    $employeeEngine =
        new EmployeeInfoEngine($con);

    /*
    |--------------------------------------------------------------------------
    | Logged Employee
    |--------------------------------------------------------------------------
    */

    $currentEmployee =
        $employeeEngine->getCurrentEmployee();

    if (!$currentEmployee) {

        respond(
            false,
            'Employee session expired'
        );
    }

    $employeeId =
        (int)$currentEmployee['id'];

    /*
    |--------------------------------------------------------------------------
    | Fetch Transactions
    |--------------------------------------------------------------------------
    */

    $transactions =
        $employeeEngine->getEmployeeCommissionTransactions(
            $employeeId
        );

    /*
    |--------------------------------------------------------------------------
    | Summary Calculation
    |--------------------------------------------------------------------------
    */

    $summary = [

        'pending' => 0,

        'approved' => 0,

        'synced' => 0,

        'paid' => 0
    ];

    /*
    |--------------------------------------------------------------------------
    | Format Transactions
    |--------------------------------------------------------------------------
    */

    $formatted = [];

    foreach ($transactions as $row) {

        $amount =
            (float)($row['amount'] ?? 0);

        $approvalStatus =
            strtolower(
                $row['approvalStatus'] ?? ''
            );

        $payrollStatus =
            strtolower(
                $row['payrollStatus'] ?? ''
            );

        /*
        |--------------------------------------------------------------------------
        | Summary
        |--------------------------------------------------------------------------
        */

        if ($approvalStatus === 'pending') {

            $summary['pending'] += $amount;
        }

        if ($approvalStatus === 'approved') {

            $summary['approved'] += $amount;
        }

        if ($payrollStatus === 'synced') {

            $summary['synced'] += $amount;
        }

        if ($payrollStatus === 'paid') {

            $summary['paid'] += $amount;
        }

        /*
        |--------------------------------------------------------------------------
        | Row
        |--------------------------------------------------------------------------
        */

        $formatted[] = [

            'id' =>
                (int)$row['id'],

            'transactionCode' =>
                $row['transactionCode'] ?? '',

            'employeeName' =>
                $row['employeeName'] ?? '',

            'categoryName' =>
                $row['categoryName'] ?? '',

            'categoryCode' =>
                $row['categoryCode'] ?? '',

            'categoryType' =>
                $row['categoryType'] ?? '',

            'amount' =>
                round($amount, 2),

            'remarks' =>
                $row['remarks'] ?? '',

            'effectiveMonth' =>
                $row['effectiveMonth'] ?? '',

            'approvalStatus' =>
                $row['approvalStatus'] ?? 'Pending',

            'payrollStatus' =>
                $row['payrollStatus'] ?? 'Pending',

            'createdAt' =>

                !empty($row['createdAt'])

                    ? date(
                        'd M Y',
                        strtotime($row['createdAt'])
                    )

                    : '--'
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    respond(

        true,

        'Commission transactions loaded successfully',

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