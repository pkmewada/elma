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
    | Get Categories
    |--------------------------------------------------------------------------
    */

    $categories =
        CommissionBonusEngine::getCategories($con);

    /*
    |--------------------------------------------------------------------------
    | Format Response
    |--------------------------------------------------------------------------
    */

    $formatted = [];

    foreach ($categories as $row) {

        $formatted[] = [

            'id' =>
                (int)$row['id'],
        
            'categoryName' =>
                $row['categoryName'],
        
            'categoryCode' =>
                $row['categoryCode'],
        
            'categoryType' =>
                $row['categoryType'],
        
            'defaultAmount' =>
                (float)$row['defaultAmount'],
        
            'commissionPercentage' =>
                isset($row['commissionPercentage'])
                    ? (float)$row['commissionPercentage']
                    : 0,
        
            'taxable' =>
                (int)$row['taxable'],
        
            'payrollApplicable' =>
                (int)$row['payrollApplicable'],
        
            'requiresApproval' =>
                (int)$row['requiresApproval']
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Success Response
    |--------------------------------------------------------------------------
    */

    respond(
        true,
        'Categories loaded successfully',
        [
            'categories' => $formatted
        ]
    );

} catch (Exception $e) {

    respond(
        false,
        $e->getMessage()
    );
}