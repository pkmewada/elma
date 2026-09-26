<?php 
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/permission-helper.php';
header('Content-Type: application/json');

requireApiPermission('/onboarding-queue', 'canView');

$data = [];

$sql = "
SELECT
id,
fullName,
phoneNumber,
email,
appliedRole,
joiningDate,
joiningStatus
FROM candidateRecord
WHERE status='convert'
AND reviewStatus='verified'
ORDER BY joiningDate ASC
";

$result = mysqli_query($con, $sql);

while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

echo json_encode([
    'success' => true,
    'data' => $data
]);