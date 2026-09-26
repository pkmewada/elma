<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/employeePointEngine.php';

header('Content-Type: application/json');

function respond($success, $message = '', $data = [])
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

try {

    $companyId = $_SESSION['companyId'] ?? 0;

    if (!$companyId) {
        respond(false, 'Unauthorized access');
    }

    $pointEngine = new EmployeePointEngine($con, $companyId);

    $categories = $pointEngine->getCategories();

    respond(
        true,
        'Categories loaded successfully',
        [
            'categories' => $categories
        ]
    );

} catch (Exception $e) {

    respond(false, $e->getMessage());
}