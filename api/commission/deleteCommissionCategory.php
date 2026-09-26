<?php

header('Content-Type: application/json');

include __DIR__ . '/../../includes/db.php';
include __DIR__ . '/../../includes/auth.php';
include __DIR__ . '/../../includes/commissionBonusEngine.php';

try {

    $id =
        intval($_POST['id'] ?? 0);

    if ($id <= 0) {

        throw new Exception(
            'Invalid category ID.'
        );
    }

    $deleted =
        CommissionBonusEngine::deleteCategory(
            $con,
            $id,
            $_SESSION['user_id'] ?? 0
        );

    if (!$deleted) {

        throw new Exception(
            'Unable to delete category.'
        );
    }

    echo json_encode([

        'success' => true,

        'message' =>
            'Category deleted successfully.'
    ]);

} catch (Exception $e) {

    echo json_encode([

        'success' => false,

        'message' => $e->getMessage()
    ]);
}