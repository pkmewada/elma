<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$sql = mysqli_query($con, "
    SELECT COUNT(*) AS total
    FROM eventHolidayMaster
    WHERE eventDate >= CURDATE()
    AND status = 'active'
");

$row = mysqli_fetch_assoc($sql);

echo json_encode([
    'success' => true,
    'total'   => (int)$row['total']
]);