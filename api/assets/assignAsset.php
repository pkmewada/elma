<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../app/models/AssetModel.php';
require_once __DIR__ . '/../../app/models/AssetAssignmentModel.php';

header('Content-Type: application/json');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Direct assignment is an Admin/HR action only -- an employee must go
// through the request -> approval flow (emp-requestAsset.php +
// approveAssetRequest.php), never straight to this endpoint.
if (empty($_SESSION['userId'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

try {

    $assetId = (int)$_POST['assetId'];
    $employeeId = (int)$_POST['employeeId'];

    if (!$assetId || !$employeeId) {
        throw new Exception('Invalid data');
    }

    $details = [
        'quantity' => (int)($_POST['quantity'] ?? 1),
        'purpose' => trim((string)($_POST['purpose'] ?? '')),
        'issueRemarks' => trim((string)($_POST['issueRemarks'] ?? '')),
        'expectedReturnDate' => trim((string)($_POST['expectedReturnDate'] ?? '')),
    ];

    $assignmentModel = new AssetAssignmentModel($con);
    $assetModel = new AssetModel($con);

    // ASSIGN ENTRY
    $assignmentModel->assignAsset($assetId, $employeeId, $details);

    // UPDATE STATUS
    $assetModel->updateStatus($assetId, 'assigned');

    echo json_encode([
        'success' => true,
        'message' => 'Asset assigned successfully'
    ]);

} catch (Throwable $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}