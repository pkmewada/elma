<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/whatsappAccess.php';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    whatsappJsonExit(405, 'Method not allowed.');
}

requireApiPermission([whatsappCallerRoute()], 'canView');

$conversationId = (int)($_POST['conversationId'] ?? 0);
$conversation = requireConversationAccess($con, $conversationId);

$stmt = mysqli_prepare($con, 'UPDATE whatsappConversations SET unreadCount = 0 WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $conversationId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// Best-effort: tell Meta the latest inbound message was read (section 20 --
// not required for the CRM's own unread badge, which is already cleared
// above regardless of whether this call succeeds).
$setting = getIntegrationSetting($con, 'whatsapp');

if ($setting && $setting['isEnabled']) {
    $stmt = mysqli_prepare($con, "SELECT metaMessageId FROM whatsappMessages WHERE conversationId = ? AND direction = 'inbound' AND metaMessageId IS NOT NULL ORDER BY sentAt DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $conversationId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $lastInboundMessageId);
    $found = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if ($found && $lastInboundMessageId) {
        $secrets = getIntegrationSecrets($con, 'whatsapp');
        $config = getIntegrationConfig($con, 'whatsapp');
        whatsappGraphRequest('POST', ($config['phoneNumberId'] ?? '') . '/messages', $secrets, $config, [
            'messaging_product' => 'whatsapp', 'status' => 'read', 'message_id' => $lastInboundMessageId,
        ]);
    }
}

echo json_encode(['success' => true, 'message' => 'Marked as read.', 'data' => []]);
