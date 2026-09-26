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

$companyId = (int) ($_SESSION['companyId'] ?? $_SESSION['userId'] ?? 0);

if ($companyId <= 0) {
    respond(false, 'Invalid company context');
}

$sql = "
    SELECT 
        ot.id,
        ot.employeeId,
        e.fullName AS employeeName,
        ot.date,
        ot.startTime,
        ot.endTime,
        ot.totalHours,
        ot.calculatedOtHours AS otHours,
        ot.status
    FROM overtimeRequests ot
    INNER JOIN employeeusers e ON e.id = ot.employeeId
    ORDER BY ot.id DESC
";

$stmt = mysqli_prepare($con, $sql);

if (!$stmt) {
    respond(false, 'Query preparation failed');
}
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$data = [];

while ($row = mysqli_fetch_assoc($result)) {

    // format values
    $row['employeeName'] = $row['employeeName'] ?: 'Unknown';
    $row['totalHours'] = number_format((float)$row['totalHours'], 2);
    $row['otHours'] = number_format((float)$row['otHours'], 2);

    $data[] = $row;
}

mysqli_stmt_close($stmt);

respond(true, 'Overtime fetched successfully', $data);