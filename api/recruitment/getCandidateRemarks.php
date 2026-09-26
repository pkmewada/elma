<?php
require_once '../../includes/db.php';

$candidateId = (int) $_GET['candidateId'];

$result = mysqli_query($con, "
    SELECT remark, followUpType, followUpDateTime, createdAt
    FROM candidateRemarks
    WHERE candidateId = $candidateId
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