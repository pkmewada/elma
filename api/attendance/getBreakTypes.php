<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/emp-auth.php';

header('Content-Type: application/json');

// =============================
// RESPONSE HELPER
// =============================
function respond($data)
{
    echo json_encode($data);
    exit;
}

// =============================
// VALIDATE SESSION
// =============================
$employeeId = (int)($_SESSION['candidateId'] ?? 0);

if ($employeeId <= 0) {

    respond([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
}

// =============================
// GET BREAK TYPES
// =============================
$breakTypes = [];

$stmt = mysqli_prepare(
    $con,
    "SELECT
        id,
        breakName,
        breakCode,
        allowedMinutes,
        isPaid,
        allowMultipleTimes
     FROM attendanceBreakTypes
     WHERE isActive = 1
     ORDER BY breakName ASC"
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {

    $breakTypes[] = $row;
}

mysqli_stmt_close($stmt);

// =============================
// RESPONSE
// =============================
respond([
    'success' => true,
    'data' => $breakTypes
]);