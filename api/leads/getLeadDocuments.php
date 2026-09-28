<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadAccess.php';

// View permission + lead in the caller's scope. URLs point at the
// authorised download endpoint, never at a storage path.
requireLeadPermission('canView');

$leadId = (int)($_GET['leadId'] ?? 0);
requireLeadAccess($con, $leadId);

$author = actorNameSql('ld.uploadedByType', 'ua', 'ea');
$stmt = mysqli_prepare(
    $con,
    "SELECT ld.id, ld.originalFileName, ld.createdAt, {$author} AS employeeName
     FROM leadDocuments ld
     LEFT JOIN users ua ON ua.id = ld.uploadedByCandidateId AND ld.uploadedByType = 'admin'
     LEFT JOIN employeeusers ea ON ea.id = ld.uploadedByCandidateId AND ld.uploadedByType = 'employee'
     WHERE ld.leadId = ?
     ORDER BY ld.id DESC"
);

if (!$stmt) {
    leadJsonExit(500, 'Unable to load documents.');
}

mysqli_stmt_bind_param($stmt, 'i', $leadId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $documentUrl = BASE_URL . '/api/leads/downloadLeadDocument.php?id=' . (int)$row['id'];
    $data[] = [
        'id' => (int)$row['id'],
        'fileName' => $row['originalFileName'],
        'employeeName' => $row['employeeName'],
        'uploadedAt' => date('d M Y h:i A', strtotime($row['createdAt'])),
        'viewUrl' => $documentUrl,
        'downloadUrl' => $documentUrl . '&download=1',
    ];
}
mysqli_stmt_close($stmt);

echo json_encode(['success' => true, 'data' => $data]);
