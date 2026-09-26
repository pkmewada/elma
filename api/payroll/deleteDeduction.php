<?php

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
    string $message
): void {

    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Validate Request
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    respond(false, 'Invalid request method.');
}

$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {

    respond(false, 'Invalid deduction ID.');
}

/*
|--------------------------------------------------------------------------
| Delete Deduction
|--------------------------------------------------------------------------
*/

$query = "
    DELETE FROM employeeDeductions
    WHERE id = ?
";

$stmt = mysqli_prepare($con, $query);

if (!$stmt) {

    respond(false, 'Unable to prepare delete query.');
}

mysqli_stmt_bind_param($stmt, 'i', $id);

$executed = mysqli_stmt_execute($stmt);

mysqli_stmt_close($stmt);

if (!$executed) {

    respond(false, 'Unable to delete deduction.');
}

/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

respond(true, 'Deduction deleted successfully.');