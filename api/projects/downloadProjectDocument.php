<?php
/*
| GET ?id={projectDocuments.id}[&download=1]
| Login (gateway) + project view permission; employees only for active
| projects. File resolved from the DB, streamed from private storage.
*/
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/projectAccess.php';

requireProjectView();

$id = (int)($_GET['id'] ?? 0);

$stmt = mysqli_prepare($con, 'SELECT projectId, fileName, originalFileName FROM projectDocuments WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$document = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$document) {
    projectJsonExit(404, 'Document not found.');
}

requireProject($con, (int)$document['projectId']);

streamPrivateFile(
    projectDocumentDirectory((int)$document['projectId']),
    (string)$document['fileName'],
    (string)$document['originalFileName'],
    !empty($_GET['download']),
    PRIVATE_FILE_TYPES_DOCUMENT,
    'projectJsonExit'
);
