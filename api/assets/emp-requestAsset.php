<?php

require_once __DIR__ . '/../../includes/emp-auth.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

function respond(bool $success, string $message, array $data = []): void
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.');
}

// Employee identity from session only -- the employee can never request
// on behalf of another employeeId passed from the frontend.
$employeeId = (int)($_SESSION['candidateId'] ?? 0);

if ($employeeId <= 0) {
    respond(false, 'Invalid employee session.');
}

$assetId = (int)($_POST['assetId'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 1);
$purpose = trim((string)($_POST['purpose'] ?? ''));
$expectedReturnDate = trim((string)($_POST['expectedReturnDate'] ?? ''));
$remarks = trim((string)($_POST['remarks'] ?? ''));

if ($assetId <= 0) {
    respond(false, 'Please select an asset.');
}

if ($quantity < 1) {
    $quantity = 1;
}

if ($expectedReturnDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expectedReturnDate)) {
    respond(false, 'Invalid expected return date.');
}

// Server-side availability check -- the employee must not be able to
// request an asset that is not currently available, regardless of what
// the frontend showed them (existing asset rules: assetMaster.status).
$stmt = mysqli_prepare($con, "SELECT status FROM assetMaster WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $assetId);
mysqli_stmt_execute($stmt);
$asset = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$asset) {
    respond(false, 'Asset not found.');
}

if ($asset['status'] !== 'available') {
    respond(false, 'This asset is not currently available.');
}

// One pending request per asset per employee at a time -- avoids piling
// up duplicate requests for the same item while one is still open.
$dupStmt = mysqli_prepare($con, "
    SELECT id FROM employeeAssetRequests
    WHERE employeeId = ? AND assetId = ? AND status = 'pending'
    LIMIT 1
");
mysqli_stmt_bind_param($dupStmt, 'ii', $employeeId, $assetId);
mysqli_stmt_execute($dupStmt);
$duplicate = mysqli_fetch_assoc(mysqli_stmt_get_result($dupStmt));
mysqli_stmt_close($dupStmt);

if ($duplicate) {
    respond(false, 'You already have a pending request for this asset.');
}

$stmt = mysqli_prepare($con, "
    INSERT INTO employeeAssetRequests
    (employeeId, assetId, quantity, purpose, expectedReturnDate, remarks, status)
    VALUES (?, ?, ?, ?, ?, ?, 'pending')
");

$purposeValue = $purpose !== '' ? $purpose : null;
$expectedReturnDateValue = $expectedReturnDate !== '' ? $expectedReturnDate : null;
$remarksValue = $remarks !== '' ? $remarks : null;

mysqli_stmt_bind_param(
    $stmt,
    'iiisss',
    $employeeId,
    $assetId,
    $quantity,
    $purposeValue,
    $expectedReturnDateValue,
    $remarksValue
);

if (!mysqli_stmt_execute($stmt)) {
    $error = mysqli_error($con);
    mysqli_stmt_close($stmt);
    respond(false, 'Failed to create request: ' . $error);
}

mysqli_stmt_close($stmt);

respond(true, 'Asset request submitted successfully.');
