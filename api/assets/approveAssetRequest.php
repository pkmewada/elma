<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../app/models/AssetAssignmentModel.php';

header('Content-Type: application/json');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['userId'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$adminUserId = (int)$_SESSION['userId'];
$requestId = (int)($_POST['id'] ?? 0);

if ($requestId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$stmt = mysqli_prepare($con, "SELECT * FROM employeeAssetRequests WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $requestId);
mysqli_stmt_execute($stmt);
$assetRequest = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$assetRequest) {
    echo json_encode(['success' => false, 'message' => 'Request not found.']);
    exit;
}

if ($assetRequest['status'] !== 'pending') {
    echo json_encode(['success' => false, 'message' => 'This request has already been reviewed.']);
    exit;
}

// Re-check availability at approval time -- never trust that it's still
// available just because the request was created while it was. If it was
// assigned elsewhere in the meantime, fail safely without touching the
// request or creating a duplicate assignment.
$assetStmt = mysqli_prepare($con, "SELECT status FROM assetMaster WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($assetStmt, 'i', $assetRequest['assetId']);
mysqli_stmt_execute($assetStmt);
$asset = mysqli_fetch_assoc(mysqli_stmt_get_result($assetStmt));
mysqli_stmt_close($assetStmt);

if (!$asset) {
    echo json_encode(['success' => false, 'message' => 'Asset no longer exists.']);
    exit;
}

if ($asset['status'] !== 'available') {
    echo json_encode(['success' => false, 'message' => 'This asset is no longer available. Reject the request or ask the employee to request a different asset.']);
    exit;
}

$assignmentModel = new AssetAssignmentModel($con);

// assignAsset() itself re-checks isAlreadyAssigned() right before the
// insert -- a second safety net against the same race condition. It
// returns the new assetAssignment.id on success, or false.
$assignmentId = $assignmentModel->assignAsset(
    (int)$assetRequest['assetId'],
    (int)$assetRequest['employeeId'],
    [
        'quantity' => (int)$assetRequest['quantity'],
        'purpose' => $assetRequest['purpose'],
        'issueRemarks' => $assetRequest['remarks'],
        'expectedReturnDate' => $assetRequest['expectedReturnDate'],
    ]
);

if (!$assignmentId) {
    echo json_encode(['success' => false, 'message' => 'Asset was assigned to someone else just now. Please reject this request or pick another asset.']);
    exit;
}

$updateStmt = mysqli_prepare($con, "
    UPDATE employeeAssetRequests
    SET status = 'approved', reviewedBy = ?, reviewedAt = NOW(), assignmentId = ?
    WHERE id = ? AND status = 'pending'
");
mysqli_stmt_bind_param($updateStmt, 'iii', $adminUserId, $assignmentId, $requestId);
$updated = mysqli_stmt_execute($updateStmt);
mysqli_stmt_close($updateStmt);

if (!$updated) {
    echo json_encode(['success' => false, 'message' => 'Asset was assigned, but the request record could not be updated. Check Asset History.']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Request approved and asset assigned successfully.'
]);
