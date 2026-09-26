<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

function response($success, $message, $data = [])
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    response(false, 'Invalid ID.');
}

$stmt = mysqli_prepare($con,"
    SELECT *
    FROM eventHolidayMaster
    WHERE id=?
    LIMIT 1
");

mysqli_stmt_bind_param($stmt,'i',$id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

if (!$row) {
    response(false,'Record not found.');
}

response(true,'Success',$row);