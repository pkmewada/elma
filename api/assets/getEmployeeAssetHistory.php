<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../app/models/AssetAssignmentModel.php';

header('Content-Type: application/json');

try {

    $employeeId = (int)($_GET['employeeId'] ?? 0);

    if (!$employeeId) {
        throw new Exception('Invalid employee');
    }

    $assignmentModel = new AssetAssignmentModel($con);
    $result = $assignmentModel->getHistoryByEmployee($employeeId);

    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }

    echo json_encode([
        'success' => true,
        'data' => $data
    ]);

} catch (Throwable $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
