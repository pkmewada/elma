<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/integrationAccess.php';

requireApiPermission(['/integrations'], 'canView');

$provider = trim((string)($_GET['provider'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$limit = max(1, min(200, (int)($_GET['limit'] ?? 100)));

$where = [];
$types = '';
$params = [];

if (isset(INTEGRATION_PROVIDERS[$provider])) {
    $where[] = 'l.provider = ?';
    $types .= 's';
    $params[] = $provider;
}

if (in_array($status, ['received', 'duplicate', 'rejected', 'created', 'error'], true)) {
    $where[] = 'l.status = ?';
    $types .= 's';
    $params[] = $status;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
    SELECT l.id, l.provider, l.externalId, l.eventType, l.leadId, l.status, l.message, l.receivedAt, l.processedAt,
           ld.fullName AS leadName
    FROM integrationLogs l
    LEFT JOIN leads ld ON ld.id = l.leadId
    {$whereSql}
    ORDER BY l.receivedAt DESC
    LIMIT {$limit}
";

$stmt = mysqli_prepare($con, $sql);

if ($types !== '') {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$rows = [];

while ($row = mysqli_fetch_assoc($result)) {
    $rows[] = $row;
}

mysqli_stmt_close($stmt);

echo json_encode(['success' => true, 'data' => $rows]);
