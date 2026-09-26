<?php

header('Content-Type: application/json');

// require_once __DIR__ . '/../../includes/emp-auth.php';
require_once __DIR__ . '/../../includes/db.php';

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
    | Lead Categories
    |--------------------------------------------------------------------------
    */
    $categories = [];

    $categoryQuery = mysqli_query(
        $con,
        "
        SELECT
            id,
            categoryName,
            categoryCode
        FROM leadCategories
        WHERE status = 'Active'
        ORDER BY categoryName ASC
        "
    );

    while (
        $row = mysqli_fetch_assoc(
            $categoryQuery
        )
    ) {

        $categories[] = [

            'id' =>
                (int)$row['id'],

            'categoryName' =>
                $row['categoryName'],

            'categoryCode' =>
                $row['categoryCode']
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Lead Plans
    |--------------------------------------------------------------------------
    */
    $plans = [];

    $planQuery = mysqli_query(
        $con,
        "
        SELECT
            id,
            categoryId,
            planName,
            planCode
        FROM leadPlans
        WHERE status = 'Active'
        ORDER BY planName ASC
        "
    );

    while (
        $row = mysqli_fetch_assoc(
            $planQuery
        )
    ) {

        $plans[] = [

            'id' =>
                (int)$row['id'],

            'categoryId' =>
                (int)$row['categoryId'],

            'planName' =>
                $row['planName'],

            'planCode' =>
                $row['planCode']
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Success Response
    |--------------------------------------------------------------------------
    */
    respond(
        true,
        'Lead master data loaded successfully.',
        [

            'categories' =>
                $categories,

            'plans' =>
                $plans
        ]
    );

} catch (Throwable $e) {

    respond(
        false,
        $e->getMessage()
    );
}
?>
