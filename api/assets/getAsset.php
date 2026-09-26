<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../app/models/AssetModel.php';

header('Content-Type: application/json');

try {

    $assetModel = new AssetModel($con);

    $result = $assetModel->getAllAssets();

    $data = [];

    if ($result && mysqli_num_rows($result) > 0) {

        while ($row = mysqli_fetch_assoc($result)) {

            $data[] = [
                'id' => (int)$row['id'],
                'assetCode' => $row['assetCode'],
                'assetName' => $row['assetName'],
                'categoryName' => $row['categoryName'] ?? '',
                'status' => $row['status'],
                'assignedTo' => $row['assignedTo'] ?? '',
                'assignedEmployeeId' => isset($row['assignedEmployeeId']) ? (int)$row['assignedEmployeeId'] : null,
                'conditionStatus' => $row['conditionStatus']
            ];
        }
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