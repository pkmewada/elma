<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadAccess.php';

/*
|--------------------------------------------------------------------------
| Upload a lead document (PDF)
|--------------------------------------------------------------------------
| POST + CSRF (gateway) + edit permission + lead in the caller's scope.
| Validated by real MIME type, stored privately under a generated name,
| served only via api/leads/downloadLeadDocument.php.
*/
requireLeadPost();
requireLeadPermission('canEdit');

$leadId = (int)($_POST['leadId'] ?? 0);
$lead = requireLeadAccess($con, $leadId);

if (empty($_FILES['document'])) {
    leadJsonExit(422, 'Please select a PDF.');
}

$file = $_FILES['document'];
$fileName = storeLeadFile($file, $leadId);
$originalFileName = cleanOriginalFileName((string)($file['name'] ?? ''));
$actor = getCurrentActor();

$stmt = mysqli_prepare(
    $con,
    'INSERT INTO leadDocuments (leadId, fileName, originalFileName, uploadedByCandidateId, uploadedByType) VALUES (?, ?, ?, ?, ?)'
);
mysqli_stmt_bind_param($stmt, 'issis', $leadId, $fileName, $originalFileName, $actor['id'], $actor['type']);
$uploaded = mysqli_stmt_execute($stmt);
$documentId = (int)mysqli_insert_id($con);
mysqli_stmt_close($stmt);

if (!$uploaded) {
    deletePrivateFile('lead-documents/' . $leadId, $fileName);
    leadJsonExit(500, 'Upload failed.');
}

saveActivityLog($con, 'Lead', $leadId, 'DOCUMENT', 'Document uploaded : ' . ($lead['fullName'] ?? ''), null, [
    'documentId' => $documentId,
    'originalFileName' => $originalFileName,
]);

echo json_encode([
    'success' => true,
    'message' => 'Document uploaded successfully.',
]);
