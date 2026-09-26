<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/emp-auth.php';
require_once __DIR__ . '/../../includes/AttendanceEngine.php';

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
$employeeId =
    (int)(
        $_SESSION['candidateId'] ?? 0
    );

if ($employeeId <= 0) {

    respond([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
}

// =============================
// LOAD ENGINE
// =============================
$attendanceEngine =
    new AttendanceEngine($con);

// =============================
// PUNCH OUT
// =============================
$response =
    $attendanceEngine->punchOut(
        $employeeId
    );

// =============================
// RESPONSE
// =============================
respond($response);