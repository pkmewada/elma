<?php
/*
|--------------------------------------------------------------------------
| Outbound WhatsApp message from a logged-in CRM user
|--------------------------------------------------------------------------
| Normal CRM session + CSRF (this is a user-initiated action, unlike the
| provider webhook). POST {conversationId, messageType: text|image|document,
| message?, media (file)?}. Template messages go through the dedicated
| api/whatsapp/send-template.php endpoint instead (it validates the
| template name/language/variable count against the admin-approved list
| before ever calling Meta -- this endpoint intentionally does not accept
| messageType=template any more).
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

if ($messageType === 'template') {
    whatsappJsonExit(422, 'Send template messages through the template endpoint instead.');
}

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
// message, allow retry). Shared with send-template.php.
$messageId = insertPendingWhatsappMessage($con, $conversationId, $messageType, $messageText, $mediaId, $mediaPath, $actor);

if (in_array($messageType, ['image', 'document'], true)) {
    $upload = uploadWhatsappMedia($secrets, $config, getPrivateStorageRoot() . '/' . WHATSAPP_MEDIA_SUBDIR . '/' . $conversationId . '/' . $mediaPath, $stored['mimeType']);

    if (!$upload['mediaId']) {
        markWhatsappMessageFailed($con, $messageId, $upload['error']);
        logIntegrationEvent($con, 'whatsapp', null, 'message_sent', $conversation['leadId'] ?: null, 'error', $upload['error'], [
            'type' => $messageType,
            'recipient' => maskWhatsappNumber((string)$conversation['waId']),
        ]);
        whatsappJsonExit(502, 'Message could not be sent. It has been kept as failed and can be retried.', ['id' => $messageId, 'status' => 'failed']);
    }

    $mediaId = $upload['mediaId'];
}

$sendResult = $messageType === 'text'
    ? sendWhatsappText($secrets, $config, $conversation['waId'], $messageText)
    : sendWhatsappMedia($secrets, $config, $conversation['waId'], $messageType, $mediaId);

finalizeWhatsappSend($con, $conversation, $messageId, $sendResult, $messageType, $messageText);
