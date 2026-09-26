<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

include __DIR__ . '/../../includes/db.php';
include __DIR__ . '/../../includes/auth.php';
include __DIR__ . '/../../includes/commissionBonusEngine.php';

try {

    /*
    |--------------------------------------------------------------------------
    | Get Settings
    */

    $settings =
        CommissionBonusEngine::getSettings($con);

    /*
    |--------------------------------------------------------------------------
    | Get Categories
    |--------------------------------------------------------------------------
    */

    $categories =
        CommissionBonusEngine::getCategories($con);

    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        'success' => true,

        'data' => [

            'settings' => $settings,

            'categories' => $categories
        ]
    ]);

} catch (Exception $e) {

    echo json_encode([

        'success' => false,

        'message' => $e->getMessage()
    ]);
}