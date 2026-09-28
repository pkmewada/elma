<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/integrationAccess.php';

requireApiPermission(['/integrations'], 'canView');

$provider = trim((string)($_GET['provider'] ?? ''));

if (!in_array($provider, ['meta', 'google'], true)) {
    integrationJsonExit(422, 'Provide provider=meta or provider=google.');
}

$stmt = mysqli_prepare($con, "
    SELECT m.id, m.provider, m.externalFormId, m.externalPageId, m.formLabel, m.projectId, m.defaultAssigneeId, m.isActive,
           p.projectName, e.fullName AS assigneeName
    FROM integrationFormMappings m
    LEFT JOIN projects p ON p.id = m.projectId
    LEFT JOIN employeeusers e ON e.id = m.defaultAssigneeId
    WHERE m.provider = ?
    ORDER BY m.createdAt DESC
");
mysqli_stmt_bind_param($stmt, 's', $provider);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$rows = [];

while ($row = mysqli_fetch_assoc($result)) {
    $rows[] = $row;
}

mysqli_stmt_close($stmt);

echo json_encode(['success' => true, 'data' => $rows]);
