<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

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

    $employees = [];

    $stmt = mysqli_prepare(
        $con,
        "SELECT
            id,
            fullName
         FROM employeeusers
         WHERE employmentStatus = 'Active'
         ORDER BY fullName ASC"
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {

        $employees[] = [
            'id' => (int)$row['id'],
            'fullName' => $row['fullName']
        ];
    }

    mysqli_stmt_close($stmt);

    respond(
        true,
        'Employees loaded successfully',
        $employees
    );

} catch (Exception $e) {

    respond(false, $e->getMessage());
}