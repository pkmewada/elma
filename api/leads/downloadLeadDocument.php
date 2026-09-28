<?php
/*
|--------------------------------------------------------------------------
| Stream a private lead document
|--------------------------------------------------------------------------
| GET ?id={leadDocuments.id}[&download=1]
| Login (gateway) + view permission + the document's lead in the caller's
| scope. The stored name comes from the DB only (never from the request).
*/
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadAccess.php';

requireLeadPermission('canView');

$documentId = (int)($_GET['id'] ?? 0);

if ($documentId <= 0) {
    leadJsonExit(422, 'Invalid document.');
}

$stmt = mysqli_prepare($con, 'SELECT id, leadId, fileName, originalFileName FROM leadDocuments WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $documentId);
mysqli_stmt_execute($stmt);
$document = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$document) {
    leadJsonExit(404, 'Document not found.');
}

requireLeadAccess($con, (int)$document['leadId']);

streamPrivateFile(
    'lead-documents/' . (int)$document['leadId'],
    (string)$document['fileName'],
    (string)$document['originalFileName'],
    !empty($_GET['download']),
    PRIVATE_FILE_TYPES_PDF,
    'leadJsonExit'
);
