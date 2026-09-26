<?php
require_once __DIR__ . '/../../includes/db.php';

$result = mysqli_query($con, "
    SELECT id, fullName, email, phoneNumber, currentLocation, appliedRole,
           experienceYears, expectedSalary, status, createdAt, employeeName
    FROM candidateRecord
    WHERE status = 'walk_in'
    ORDER BY id DESC
");

$data = [];

while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

echo json_encode([
    'success' => true,
    'data' => $data
]);
