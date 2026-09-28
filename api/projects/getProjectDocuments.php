<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/projectAccess.php';

// Documents of one project (view permission; employees: active projects only).
requireProjectView();

$projectId = (int)($_GET['projectId'] ?? 0);
requireProject($con, $projectId);

$author = actorNameSql('d.uploadedByType', 'ua', 'ea');
$stmt = mysqli_prepare(
    $con,
    "SELECT d.id, d.documentType, d.originalFileName, d.mimeType, d.fileSize, d.createdAt, {$author} AS uploadedBy
     FROM projectDocuments d
     LEFT JOIN users ua ON ua.id = d.uploadedById AND d.uploadedByType = 'admin'
     LEFT JOIN employeeusers ea ON ea.id = d.uploadedById AND d.uploadedByType = 'employee'
     WHERE d.projectId = ?
     ORDER BY FIELD(d.documentType, 'image', 'brochure', 'floor_plan', 'price_list', 'other'), d.id DESC"
);
mysqli_stmt_bind_param($stmt, 'i', $projectId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $url = BASE_URL . '/api/projects/downloadProjectDocument.php?id=' . (int)$row['id'];
    $data[] = [
        'id' => (int)$row['id'],
        'documentType' => $row['documentType'],
        'documentTypeLabel' => PROJECT_DOCUMENT_TYPES[$row['documentType']] ?? $row['documentType'],
        'fileName' => $row['originalFileName'],
        'isImage' => strpos((string)$row['mimeType'], 'image/') === 0,
        'fileSizeKb' => (int)ceil((int)$row['fileSize'] / 1024),
        'uploadedBy' => $row['uploadedBy'],
        'uploadedAt' => date('d M Y h:i A', strtotime($row['createdAt'])),
        'viewUrl' => $url,
        'downloadUrl' => $url . '&download=1',
    ];
}
mysqli_stmt_close($stmt);

echo json_encode(['success' => true, 'data' => $data]);
