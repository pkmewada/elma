<?php
/*
|--------------------------------------------------------------------------
| Outbound WhatsApp message from a logged-in CRM user
|--------------------------------------------------------------------------
| Normal CRM session + CSRF (this is a user-initiated action, unlike the
| provider webhook). POST {conversationId, messageType: text|template|
| image|document, message?, templateName?, templateLanguage?,
| templateVariables[]?, media (file)?}.
*/
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/whatsappAccess.php';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    whatsappJsonExit(405, 'Method not allowed.');
}

if (isRequestBodyTooLarge()) {
    whatsappJsonExit(413, 'Upload is too large. Maximum file size is ' . formatPrivateFileLimit() . '.');
}

requireApiActionPermission([whatsappCallerRoute()], 'send_whatsapp_message');

$conversationId = (int)($_POST['conversationId'] ?? 0);
$conversation = requireConversationAccess($con, $conversationId);

$setting = getIntegrationSetting($con, 'whatsapp');

if (!$setting || !$setting['isEnabled']) {
    whatsappJsonExit(503, 'WhatsApp integration is not enabled.');
}

$secrets = getIntegrationSecrets($con, 'whatsapp');
$config = getIntegrationConfig($con, 'whatsapp');

if (empty($secrets['accessToken']) || empty($config['phoneNumberId'])) {
    whatsappJsonExit(503, 'WhatsApp integration is not fully configured.');
}

$messageType = (string)($_POST['messageType'] ?? 'text');
$windowOpen = isConversationWindowOpen(getLastInboundAt($con, $conversationId));

if (in_array($messageType, ['text', 'image', 'document'], true) && !$windowOpen) {
    whatsappJsonExit(422, 'Conversation window closed — send an approved template instead.');
}

$actor = getCurrentActor();
$messageText = null;
$mediaId = null;
$mediaPath = null;
$sendResult = null;

if ($messageType === 'text') {
    $messageText = trim((string)($_POST['message'] ?? ''));

    if ($messageText === '') {
        whatsappJsonExit(422, 'Message cannot be empty.');
    }

    if (mb_strlen($messageText) > 4096) {
        whatsappJsonExit(422, 'Message is too long (4096 character limit).');
    }
} elseif ($messageType === 'template') {
    $templateName = trim((string)($_POST['templateName'] ?? ''));
    $templateLanguage = trim((string)($_POST['templateLanguage'] ?? '')) ?: 'en_US';
    $variables = array_values(array_filter((array)($_POST['templateVariables'] ?? []), static fn($v) => trim((string)$v) !== ''));

    if ($templateName === '') {
        whatsappJsonExit(422, 'Select a template.');
    }

    $messageText = 'Template: ' . $templateName . ' (' . $templateLanguage . ')';
} elseif (in_array($messageType, ['image', 'document'], true)) {
    if (empty($_FILES['media'])) {
        whatsappJsonExit(422, 'Select a file to send.');
    }

    $allowed = $messageType === 'image' ? PRIVATE_FILE_TYPES_IMAGE : PRIVATE_FILE_TYPES_DOCUMENT;
    $stored = storePrivateFile($_FILES['media'], WHATSAPP_MEDIA_SUBDIR . '/' . $conversationId, $allowed, static function (int $code, string $msg) {
        whatsappJsonExit($code, $msg);
    });
    $mediaPath = $stored['fileName'];
    $messageText = cleanOriginalFileName((string)($_FILES['media']['name'] ?? ''));
} else {
    whatsappJsonExit(422, 'Unsupported message type.');
}

// Insert as 'pending' before any Meta API call, so a failure at ANY later
// step (media upload included) still leaves a visible, retryable 'failed'
// row instead of the message silently vanishing (Section 33: keep failed
// message, allow retry).
$insertStmt = mysqli_prepare($con, "
    INSERT INTO whatsappMessages (conversationId, direction, messageType, messageText, mediaId, mediaPath, status, sentByType, sentById, sentAt)
    VALUES (?, 'outbound', ?, ?, ?, ?, 'pending', ?, ?, NOW())
");
mysqli_stmt_bind_param($insertStmt, 'isssssi', $conversationId, $messageType, $messageText, $mediaId, $mediaPath, $actor['type'], $actor['id']);
mysqli_stmt_execute($insertStmt);
$messageId = mysqli_insert_id($con);
mysqli_stmt_close($insertStmt);

function markWhatsappMessageFailed(mysqli $con, int $messageId, string $errorMessage): void
{
    $errorMessage = mb_substr($errorMessage, 0, 255);
    $stmt = mysqli_prepare($con, "UPDATE whatsappMessages SET status = 'failed', errorMessage = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $errorMessage, $messageId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

if (in_array($messageType, ['image', 'document'], true)) {
    $uploadedMediaId = uploadWhatsappMedia($secrets, $config, getPrivateStorageRoot() . '/' . WHATSAPP_MEDIA_SUBDIR . '/' . $conversationId . '/' . $mediaPath, $stored['mimeType']);

    if (!$uploadedMediaId) {
        markWhatsappMessageFailed($con, $messageId, 'Could not upload media to WhatsApp.');
        logIntegrationEvent($con, 'whatsapp', null, 'message_sent', $conversation['leadId'] ?: null, 'error', 'Media upload failed', ['type' => $messageType]);
        whatsappJsonExit(502, 'Message could not be sent. It has been kept as failed and can be retried.', ['id' => $messageId, 'status' => 'failed']);
    }

    $mediaId = $uploadedMediaId;
}

if ($messageType === 'text') {
    $sendResult = sendWhatsappText($secrets, $config, $conversation['waId'], $messageText);
} elseif ($messageType === 'template') {
    $sendResult = sendWhatsappTemplate($secrets, $config, $conversation['waId'], $templateName, $templateLanguage, $variables);
} else {
    $sendResult = sendWhatsappMedia($secrets, $config, $conversation['waId'], $messageType, $mediaId);
}

$metaMessageId = $sendResult['body']['messages'][0]['id'] ?? null;

if ($sendResult['ok'] && $metaMessageId) {
    $updateStmt = mysqli_prepare($con, "UPDATE whatsappMessages SET status = 'sent', metaMessageId = ? WHERE id = ?");
    mysqli_stmt_bind_param($updateStmt, 'si', $metaMessageId, $messageId);
    mysqli_stmt_execute($updateStmt);
    mysqli_stmt_close($updateStmt);

    $preview = $messageType === 'text' ? mb_substr($messageText, 0, 250) : ('[' . ucfirst($messageType) . '] ' . mb_substr((string)$messageText, 0, 200));
    $convUpdate = mysqli_prepare($con, 'UPDATE whatsappConversations SET lastMessageAt = NOW(), lastMessagePreview = ? WHERE id = ?');
    mysqli_stmt_bind_param($convUpdate, 'si', $preview, $conversationId);
    mysqli_stmt_execute($convUpdate);
    mysqli_stmt_close($convUpdate);

    if ($conversation['leadId']) {
        saveActivityLog($con, 'Lead', (int)$conversation['leadId'], 'WHATSAPP_SENT', 'WhatsApp message sent : ' . ($conversation['leadFullName'] ?: $conversation['waId']));
    }

    logIntegrationEvent($con, 'whatsapp', $metaMessageId, 'message_sent', $conversation['leadId'] ?: null, 'created', 'Message sent', ['type' => $messageType]);

    echo json_encode(['success' => true, 'message' => 'Message sent.', 'data' => ['id' => $messageId, 'status' => 'sent', 'metaMessageId' => $metaMessageId]]);
} else {
    // Never expose the raw Meta response to the browser; keep a safe reason for admins in the log.
    $errorTitle = mb_substr((string)($sendResult['body']['error']['message'] ?? 'Provider send failed'), 0, 255);
    markWhatsappMessageFailed($con, $messageId, $errorTitle);
    logIntegrationEvent($con, 'whatsapp', null, 'message_sent', $conversation['leadId'] ?: null, 'error', $errorTitle, ['type' => $messageType]);

    whatsappJsonExit(502, 'Message could not be sent. It has been kept as failed and can be retried.', ['id' => $messageId, 'status' => 'failed']);
}
