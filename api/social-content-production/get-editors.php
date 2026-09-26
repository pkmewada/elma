<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!isset($_SESSION['userId'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $sql = "SELECT id, fullName FROM employeeusers
            WHERE employmentStatus = 'Active' AND (designationName = 'Video Editor' OR designationName = 'Graphic Executive')
            ORDER BY fullName ASC";
    $result = mysqli_query($con, $sql);

    $editors = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $editors[] = ['id' => (int)$row['id'], 'fullName' => $row['fullName']];
    }

    echo json_encode(['success' => true, 'data' => $editors]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
