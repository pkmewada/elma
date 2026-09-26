<?php

header('Content-Type: application/json');

include __DIR__ . '/../../includes/db.php';
include __DIR__ . '/../../includes/auth.php';
include __DIR__ . '/../../includes/commissionBonusEngine.php';

try {

    /*
    |--------------------------------------------------------------------------
    | Parse Payload
    |--------------------------------------------------------------------------
    */

    $payload =
        json_decode(
            file_get_contents("php://input"),
            true
        );

    if (!$payload) {

        throw new Exception(
            'Invalid request payload.'
        );
    }

    $settings =
        $payload['settings'] ?? [];

    $categories =
        $payload['categories'] ?? [];

    /*
    |--------------------------------------------------------------------------
    | Save Settings
    |--------------------------------------------------------------------------
    */

    $settings['createdBy'] =
        $_SESSION['user_id'] ?? 0;

    $settings['updatedBy'] =
        $_SESSION['user_id'] ?? 0;

    $saveSettings =
        CommissionBonusEngine::saveSettings(
            $con,
            $settings
        );

    if (!$saveSettings) {

        throw new Exception(
            'Unable to save settings.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Save Categories
    |--------------------------------------------------------------------------
    */

    foreach ($categories as $category) {

        /*
        |--------------------------------------------------------------------------
        | Skip Inactive
        |--------------------------------------------------------------------------
        */

        if (
            isset($category['isActive']) &&
            intval($category['isActive']) !== 1
        ) {

            /*
            |--------------------------------------------------------------------------
            | Existing DB Record
            |--------------------------------------------------------------------------
            */

            if (!empty($category['id'])) {

                CommissionBonusEngine::deleteCategory(
                    $con,
                    intval($category['id']),
                    $_SESSION['user_id'] ?? 0
                );
            }

            continue;
        }
        
        /*
        |--------------------------------------------------------------------------
        | Backward Compatibility Migration
        |--------------------------------------------------------------------------
        */
        
        if (
            strtolower($category['categoryType']) === 'commission'
        ) {
        
            /*
            |--------------------------------------------------------------------------
            | Old Records Support
            |--------------------------------------------------------------------------
            */
        
            if (
                (
                    !isset($category['commissionPercentage']) ||
                    $category['commissionPercentage'] === null ||
                    $category['commissionPercentage'] === ''
                )
                &&
                isset($category['defaultAmount'])
            ) {
        
                $category['commissionPercentage'] =
                    floatval($category['defaultAmount']);
        
                $category['defaultAmount'] = 0;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */
        
        $errors =
            CommissionBonusEngine::validateCategory(
                $category
            );

        if (!empty($errors)) {

            throw new Exception(
                implode(', ', $errors)
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Prepare Data
        |--------------------------------------------------------------------------
        */

        $category['createdBy'] =
            $_SESSION['user_id'] ?? 0;

        $category['updatedBy'] =
            $_SESSION['user_id'] ?? 0;

        /*
        |--------------------------------------------------------------------------
        | Update Existing
        |--------------------------------------------------------------------------
        */

        if (!empty($category['id'])) {

            $updated =
                CommissionBonusEngine::updateCategory(
                    $con,
                    intval($category['id']),
                    $category
                );

            if (!$updated) {

                throw new Exception(
                    'Unable to update category.'
                );
            }

        } else {

            /*
            |--------------------------------------------------------------------------
            | Insert New
            |--------------------------------------------------------------------------
            */

            $saved =
                CommissionBonusEngine::saveCategory(
                    $con,
                    $category
                );

            if (!$saved) {

                throw new Exception(
                    'Unable to save category.'
                );
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Success Response
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        'success' => true,

        'message' =>
            'Commission setup saved successfully.'
    ]);

} catch (Exception $e) {

    echo json_encode([

        'success' => false,

        'message' => $e->getMessage()
    ]);
}