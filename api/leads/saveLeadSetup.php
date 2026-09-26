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
    string $message
): void {

    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);

    exit;
}

try {

    $payload = json_decode(
        file_get_contents('php://input'),
        true
    );

    $leadCategories =
        $payload['leadCategories']
        ?? [];

    $leadPlans =
        $payload['leadPlans']
        ?? [];

    mysqli_begin_transaction($con);

    /*
    |--------------------------------------------------------------------------
    | Existing Category IDs
    |--------------------------------------------------------------------------
    */
    $existingCategoryIds = [];

    $q = mysqli_query(
        $con,
        "
        SELECT id
        FROM leadCategories
        "
    );

    while ($row = mysqli_fetch_assoc($q)) {

        $existingCategoryIds[] =
            (int)$row['id'];
    }

    $receivedCategoryIds = [];

    /*
    |--------------------------------------------------------------------------
    | Save Categories
    |--------------------------------------------------------------------------
    */
    foreach (
        $leadCategories
        as $category
    ) {

        $id =
            $category['id']
            ?? '';

        $categoryName =
            trim(
                $category['categoryName']
                ?? ''
            );

        $categoryCode =
            trim(
                $category['categoryCode']
                ?? ''
            );

        $status =
            trim(
                $category['status']
                ?? 'Active'
            );

        if (
            $categoryName === ''
            ||
            $categoryCode === ''
        ) {
            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | Existing Category
        |--------------------------------------------------------------------------
        */
        if (
            is_numeric($id)
        ) {

            $receivedCategoryIds[] =
                (int)$id;

            $stmt =
                mysqli_prepare(
                    $con,
                    "
                    UPDATE leadCategories
                    SET
                        categoryName = ?,
                        categoryCode = ?,
                        status = ?
                    WHERE id = ?
                    "
                );

            mysqli_stmt_bind_param(
                $stmt,
                "sssi",
                $categoryName,
                $categoryCode,
                $status,
                $id
            );

            mysqli_stmt_execute(
                $stmt
            );

            mysqli_stmt_close(
                $stmt
            );

        } else {

            /*
            |--------------------------------------------------------------------------
            | New Category
            |--------------------------------------------------------------------------
            */
            $stmt =
                mysqli_prepare(
                    $con,
                    "
                    INSERT INTO
                    leadCategories
                    (
                        categoryName,
                        categoryCode,
                        status
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?
                    )
                    "
                );

            mysqli_stmt_bind_param(
                $stmt,
                "sss",
                $categoryName,
                $categoryCode,
                $status
            );

            mysqli_stmt_execute(
                $stmt
            );

            $newId =
                mysqli_insert_id(
                    $con
                );

            $receivedCategoryIds[] =
                $newId;

            /*
            |--------------------------------------------------------------------------
            | Update Temporary IDs
            |--------------------------------------------------------------------------
            */
            foreach (
                $leadPlans
                as &$plan
            ) {

                if (
                    (string)$plan['categoryId']
                    ===
                    (string)$id
                ) {

                    $plan['categoryId'] =
                        $newId;
                }
            }

            mysqli_stmt_close(
                $stmt
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Removed Categories
    |--------------------------------------------------------------------------
    */
    foreach (
        $existingCategoryIds
        as $existingId
    ) {

        if (
            !in_array(
                $existingId,
                $receivedCategoryIds
            )
        ) {

            mysqli_query(
                $con,
                "
                DELETE FROM leadCategories
                WHERE id = {$existingId}
                "
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Existing Plans
    |--------------------------------------------------------------------------
    */
    $existingPlanIds = [];

    $q = mysqli_query(
        $con,
        "
        SELECT id
        FROM leadPlans
        "
    );

    while ($row = mysqli_fetch_assoc($q)) {

        $existingPlanIds[] =
            (int)$row['id'];
    }

    $receivedPlanIds = [];

    /*
    |--------------------------------------------------------------------------
    | Save Plans
    |--------------------------------------------------------------------------
    */
    foreach (
        $leadPlans
        as $plan
    ) {

        $id =
            $plan['id']
            ?? '';

        $categoryId =
            (int)(
                $plan['categoryId']
                ?? 0
            );

        $planName =
            trim(
                $plan['planName']
                ?? ''
            );

        $planCode =
            trim(
                $plan['planCode']
                ?? ''
            );

        $status =
            trim(
                $plan['status']
                ?? 'Active'
            );

        if (
            $categoryId <= 0
            ||
            $planName === ''
            ||
            $planCode === ''
        ) {
            continue;
        }

        if (
            is_numeric($id)
        ) {

            $receivedPlanIds[] =
                (int)$id;

            $stmt =
                mysqli_prepare(
                    $con,
                    "
                    UPDATE leadPlans
                    SET
                        categoryId = ?,
                        planName = ?,
                        planCode = ?,
                        status = ?
                    WHERE id = ?
                    "
                );

            mysqli_stmt_bind_param(
                $stmt,
                "isssi",
                $categoryId,
                $planName,
                $planCode,
                $status,
                $id
            );

            mysqli_stmt_execute(
                $stmt
            );

            mysqli_stmt_close(
                $stmt
            );

        } else {

            $stmt =
                mysqli_prepare(
                    $con,
                    "
                    INSERT INTO
                    leadPlans
                    (
                        categoryId,
                        planName,
                        planCode,
                        status
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?
                    )
                    "
                );

            mysqli_stmt_bind_param(
                $stmt,
                "isss",
                $categoryId,
                $planName,
                $planCode,
                $status
            );

            mysqli_stmt_execute(
                $stmt
            );

            $receivedPlanIds[] =
                mysqli_insert_id(
                    $con
                );

            mysqli_stmt_close(
                $stmt
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Removed Plans
    |--------------------------------------------------------------------------
    */
    foreach (
        $existingPlanIds
        as $existingId
    ) {

        if (
            !in_array(
                $existingId,
                $receivedPlanIds
            )
        ) {

            mysqli_query(
                $con,
                "
                DELETE FROM leadPlans
                WHERE id = {$existingId}
                "
            );
        }
    }

    mysqli_commit($con);

    respond(
        true,
        'Lead setup saved successfully.'
    );

} catch (Throwable $e) {

    mysqli_rollback($con);

    respond(
        false,
        $e->getMessage()
    );
}
?>
