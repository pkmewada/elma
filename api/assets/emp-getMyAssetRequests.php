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

$employeeId = (int)($_SESSION['candidateId'] ?? 0);

if ($employeeId <= 0) {
    respond(false, 'Invalid employee session.');
}

$stmt = mysqli_prepare($con, "
    SELECT
        r.id, r.assetId, r.quantity, r.purpose, r.expectedReturnDate, r.remarks,
        r.status, r.rejectionRemark, r.createdAt,
        a.assetCode, a.assetName
    FROM employeeAssetRequests r
    INNER JOIN assetMaster a ON a.id = r.assetId
    WHERE r.employeeId = ?
    ORDER BY r.id DESC
");

if (!$stmt) {
    respond(false, 'Unable to prepare query.');
}

mysqli_stmt_bind_param($stmt, 'i', $employeeId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}
mysqli_stmt_close($stmt);

respond(true, 'Requests fetched successfully.', $data);
