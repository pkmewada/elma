<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

$stmt = mysqli_prepare(
    $con,
    "SELECT id, fullName
     FROM employeeusers
     WHERE employmentStatus = 'Active'
     ORDER BY fullName ASC"
);

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'data' => [],
        'message' => 'Failed to load employees'
    ]);
    exit;
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$data = [];

while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

mysqli_stmt_close($stmt);

echo json_encode([
    'success' => true,
    'data' => $data
]);
