<?php

require_once __DIR__ . '/../../includes/db.php';

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
$rejectionRemark = trim((string)($_POST['rejectionRemark'] ?? ''));

if ($requestId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$stmt = mysqli_prepare($con, "SELECT status FROM employeeAssetRequests WHERE id = ? LIMIT 1");
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

$remarkValue = $rejectionRemark !== '' ? $rejectionRemark : null;

// Rejecting never touches assetMaster/assetAssignment -- the asset stays
// exactly as it was, only the request row is marked and kept for history.
$updateStmt = mysqli_prepare($con, "
    UPDATE employeeAssetRequests
    SET status = 'rejected', reviewedBy = ?, reviewedAt = NOW(), rejectionRemark = ?
    WHERE id = ? AND status = 'pending'
");
mysqli_stmt_bind_param($updateStmt, 'isi', $adminUserId, $remarkValue, $requestId);
$updated = mysqli_stmt_execute($updateStmt);
$affected = mysqli_stmt_affected_rows($updateStmt);
mysqli_stmt_close($updateStmt);

if (!$updated || $affected === 0) {
    echo json_encode(['success' => false, 'message' => 'Failed to reject request.']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Request rejected successfully.'
]);
