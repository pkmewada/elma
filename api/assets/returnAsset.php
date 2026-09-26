<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../app/models/AssetModel.php';
require_once __DIR__ . '/../../app/models/AssetAssignmentModel.php';

header('Content-Type: application/json');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Admin/HR action only -- an employee must never be able to change an
// assignment's status directly.
if (empty($_SESSION['userId'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

try {

    $assetId = (int)$_POST['assetId'];
    $condition = $_POST['conditionStatus'] ?? 'good';
    $remarks = $_POST['remarks'] ?? '';

    if (!$assetId) {
        throw new Exception('Invalid asset');
    }

    $assetModel = new AssetModel($con);
    $assignmentModel = new AssetAssignmentModel($con);

    // UPDATE ASSIGNMENT (MARK RETURNED / CLOSE ENTRY)
    $assignmentModel->returnAsset($assetId, $condition, $remarks);

    // UPDATE ASSET STATUS + CONDITION
    $assetModel->updateStatus($assetId, 'available');
    $assetModel->updateConditionStatus($assetId, $condition);

    echo json_encode([
        'success' => true,
        'message' => 'Asset returned successfully'
    ]);

} catch (Throwable $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}