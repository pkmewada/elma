<?php

date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

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

/*
|--------------------------------------------------------------------------
| Request Validation
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    respond(false, 'Invalid request method.');
}

/*
|--------------------------------------------------------------------------
| Inputs
|--------------------------------------------------------------------------
*/

$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {

    respond(false, 'Invalid category ID.');
}

/*
|--------------------------------------------------------------------------
| Delete Category
|--------------------------------------------------------------------------
*/

$query = "
    UPDATE employeePointCategories
    SET

        isActive = 0,
        updatedAt = NOW()

    WHERE id = ?
    LIMIT 1
";

$stmt =
    mysqli_prepare(
        $con,
        $query
    );

if (!$stmt) {

    respond(
        false,
        'Unable to prepare delete query.'
    );
}

mysqli_stmt_bind_param(
    $stmt,
    'i',
    $id
);

$executed =
    mysqli_stmt_execute($stmt);

mysqli_stmt_close($stmt);

if (!$executed) {

    respond(
        false,
        'Unable to delete category.'
    );
}

/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

respond(
    true,
    'Category deleted successfully.'
);