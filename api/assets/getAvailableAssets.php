<?php
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

$sql = mysqli_query($con, "
    SELECT id, assetCode, assetName 
    FROM assetMaster
    WHERE status = 'available'
");

$data = [];

while($row = mysqli_fetch_assoc($sql)){
    $data[] = $row;
}

echo json_encode([
    'success' => true,
    'data' => $data
]);