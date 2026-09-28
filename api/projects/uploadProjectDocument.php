<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/projectAccess.php';

/*
| POST multipart {projectId, documentType, document} + CSRF (gateway) +
| canEdit on /projects. Images: JPG/PNG/WEBP; other types: PDF or image.
| Real MIME via finfo, generated name, private storage, size limit.
*/
requireProjectPost();
requireProjectManage('canEdit');

$projectId = (int)($_POST['projectId'] ?? 0);
$project = requireProject($con, $projectId);
$documentType = (string)($_POST['documentType'] ?? '');

if (!isset(PROJECT_DOCUMENT_TYPES[$documentType])) {
    projectJsonExit(422, 'Select a valid document type.');
}

if (empty($_FILES['document'])) {
    projectJsonExit(422, 'Please select a file.');
}

$allowedTypes = $documentType === 'image' ? PRIVATE_FILE_TYPES_IMAGE : PRIVATE_FILE_TYPES_DOCUMENT;
$stored = storePrivateFile($_FILES['document'], projectDocumentDirectory($projectId), $allowedTypes, 'projectJsonExit');
$originalFileName = cleanOriginalFileName((string)($_FILES['document']['name'] ?? ''));
$actor = getCurrentActor();

$stmt = mysqli_prepare(
    $con,
    'INSERT INTO projectDocuments (projectId, documentType, fileName, originalFileName, mimeType, fileSize, uploadedByType, uploadedById)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
mysqli_stmt_bind_param($stmt, 'issssisi', $projectId, $documentType, $stored['fileName'], $originalFileName, $stored['mimeType'], $stored['size'], $actor['type'], $actor['id']);

if (!mysqli_stmt_execute($stmt)) {
    deletePrivateFile(projectDocumentDirectory($projectId), $stored['fileName']);
    projectJsonExit(500, 'Upload failed.');
}
$documentId = (int)mysqli_insert_id($con);
mysqli_stmt_close($stmt);

saveActivityLog($con, 'Project', $projectId, 'DOCUMENT',
    PROJECT_DOCUMENT_TYPES[$documentType] . ' uploaded : ' . $project['projectName'], null,
    ['documentId' => $documentId, 'originalFileName' => $originalFileName]);

echo json_encode(['success' => true, 'message' => PROJECT_DOCUMENT_TYPES[$documentType] . ' uploaded.', 'data' => ['id' => $documentId]]);
