<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json; charset=UTF-8');

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
        'data'    => $data
    ]);

    exit;
}

try {

    /*
    |--------------------------------------------------------------------------
    | Lead Categories
    |--------------------------------------------------------------------------
    */
    $leadCategories = [];

    $categoryQuery = mysqli_query(
        $con,
        "
        SELECT
            id,
            categoryName,
            categoryCode,
            status
        FROM leadCategories
        ORDER BY categoryName ASC
        "
    );

    while (
        $row = mysqli_fetch_assoc(
            $categoryQuery
        )
    ) {

        $leadCategories[] = [

            'id' => (int)$row['id'],

            'categoryName' =>
                $row['categoryName'],

            'categoryCode' =>
                $row['categoryCode'],

            'status' =>
                $row['status']
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Lead Plans
    |--------------------------------------------------------------------------
    */
    $leadPlans = [];

    $planQuery = mysqli_query(
        $con,
        "
        SELECT
            lp.id,
            lp.categoryId,
            lp.planName,
            lp.planCode,
            lp.status,

            lc.categoryName

        FROM leadPlans lp

        INNER JOIN leadCategories lc
            ON lc.id = lp.categoryId

        ORDER BY
            lc.categoryName ASC,
            lp.planName ASC
        "
    );

    while (
        $row = mysqli_fetch_assoc(
            $planQuery
        )
    ) {

        $leadPlans[] = [

            'id' => (int)$row['id'],

            'categoryId' =>
                (int)$row['categoryId'],

            'categoryName' =>
                $row['categoryName'],

            'planName' =>
                $row['planName'],

            'planCode' =>
                $row['planCode'],

            'status' =>
                $row['status']
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Success Response
    |--------------------------------------------------------------------------
    */
    respond(
        true,
        'Lead setup loaded successfully.',
        [

            'leadCategories' =>
                $leadCategories,

            'leadPlans' =>
                $leadPlans
        ]
    );

} catch (Throwable $e) {

    respond(
        false,
        $e->getMessage()
    );
}
?>
