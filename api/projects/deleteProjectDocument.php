<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/projectAccess.php';

// POST {id} + CSRF (gateway) + canDelete on /projects.
requireProjectPost();
requireProjectManage('canDelete');

$id = (int)($_POST['id'] ?? 0);

$stmt = mysqli_prepare($con, 'SELECT id, projectId, documentType, fileName, originalFileName FROM projectDocuments WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$document = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$document) {
    projectJsonExit(404, 'Document not found.');
}

$project = requireProject($con, (int)$document['projectId']);

$stmt = mysqli_prepare($con, 'DELETE FROM projectDocuments WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

deletePrivateFile(projectDocumentDirectory((int)$document['projectId']), $document['fileName']);

saveActivityLog($con, 'Project', (int)$document['projectId'], 'DOCUMENT_DELETE',
    (PROJECT_DOCUMENT_TYPES[$document['documentType']] ?? 'Document') . ' deleted : ' . $project['projectName'],
    ['documentId' => $id, 'originalFileName' => $document['originalFileName']], null);

echo json_encode(['success' => true, 'message' => 'Document deleted.', 'data' => []]);
