<?php

require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

$status = trim((string)($_GET['status'] ?? 'pending'));
$allowedStatuses = ['pending', 'approved', 'rejected'];

$where = '1=1';
$params = [];
$types = '';

if ($status !== '' && in_array($status, $allowedStatuses, true)) {
    $where = 'r.status = ?';
    $params[] = $status;
    $types .= 's';
}

$sql = "
    SELECT
        r.id, r.employeeId, r.assetId, r.quantity, r.purpose, r.expectedReturnDate,
        r.remarks, r.status, r.rejectionRemark, r.createdAt, r.reviewedAt,
        a.assetCode, a.assetName,
        e.fullName AS employeeName,
        rv.fullName AS reviewedByName
    FROM employeeAssetRequests r
    INNER JOIN assetMaster a ON a.id = r.assetId
    LEFT JOIN employeeusers e ON e.id = r.employeeId
    LEFT JOIN users rv ON rv.id = r.reviewedBy
    WHERE {$where}
    ORDER BY r.id DESC
";

$stmt = mysqli_prepare($con, $sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Unable to prepare query.']);
    exit;
}

if ($types !== '') {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}
mysqli_stmt_close($stmt);

echo json_encode([
    'success' => true,
    'data' => $data
]);
