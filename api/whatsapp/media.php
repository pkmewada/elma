<?php
/*
|--------------------------------------------------------------------------
| Serve a WhatsApp media file (image/document) to an authorized CRM user
|--------------------------------------------------------------------------
| Same private-file pattern as lead/project documents: session + conversation
| access re-checked here (never trust the file name alone), streamed from
| storage/whatsapp-media/<conversationId>/ with its real MIME type re-verified.
*/
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/whatsappAccess.php';

requireApiPermission([whatsappCallerRoute()], 'canView');

$conversationId = (int)($_GET['conversationId'] ?? 0);
$conversation = requireConversationAccess($con, $conversationId);
$fileName = basename((string)($_GET['file'] ?? ''));

if ($fileName === '') {
    whatsappJsonExit(404, 'File not found.');
}

$allowed = PRIVATE_FILE_TYPES_DOCUMENT; // superset of image + pdf types used for WhatsApp media

streamPrivateFile(
    WHATSAPP_MEDIA_SUBDIR . '/' . $conversationId,
    $fileName,
    $fileName,
    false,
    $allowed,
    static function (int $code, string $message) {
        whatsappJsonExit($code, $message);
    }
);
