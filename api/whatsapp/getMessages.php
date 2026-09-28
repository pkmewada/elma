<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/whatsappAccess.php';

requireApiPermission([whatsappCallerRoute()], 'canView');

$conversationId = (int)($_GET['conversationId'] ?? 0);
$leadId = (int)($_GET['leadId'] ?? 0);

if ($conversationId <= 0 && $leadId > 0) {
    // Lead page's WhatsApp action: open (or create) that lead's conversation
    // rather than requiring the chat list to already have it (Section 22).
    $lead = requireLeadAccess($con, $leadId);
    $waId = normalizeWaId((string)$lead['countryCode'] . (string)$lead['phone']);

    if ($waId === '') {
        whatsappJsonExit(422, 'This lead has no phone number to message.');
    }

    $conversation = findOrCreateConversation($con, $waId, $waId, $lead['fullName']);

    if (!$conversation['leadId']) {
        $stmt = mysqli_prepare($con, 'UPDATE whatsappConversations SET leadId = ? WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'ii', $leadId, $conversation['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    $conversation = requireConversationAccess($con, (int)$conversation['id']);
    $conversationId = (int)$conversation['id'];
} else {
    $conversation = requireConversationAccess($con, $conversationId);
}

$stmt = mysqli_prepare($con, '
    SELECT id, metaMessageId, direction, messageType, messageText, mediaId, mediaPath, status,
           errorMessage, sentByType, sentById, sentAt, deliveredAt, readAt
    FROM whatsappMessages
    WHERE conversationId = ?
    ORDER BY sentAt ASC, id ASC
    LIMIT 200
');
mysqli_stmt_bind_param($stmt, 'i', $conversationId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$messages = [];

while ($row = mysqli_fetch_assoc($result)) {
    $messages[] = $row;
}

mysqli_stmt_close($stmt);

$lastInboundAt = getLastInboundAt($con, $conversationId);
$windowOpen = isConversationWindowOpen($lastInboundAt);
$config = getIntegrationConfig($con, 'whatsapp');
$setting = getIntegrationSetting($con, 'whatsapp');

echo json_encode([
    'success' => true,
    'data' => [
        'conversation' => [
            'id' => (int)$conversation['id'],
            'waId' => $conversation['waId'],
            'phoneNumber' => $conversation['phoneNumber'],
            'customerName' => $conversation['customerName'],
            'leadId' => $conversation['leadId'] ? (int)$conversation['leadId'] : null,
            'leadFullName' => $conversation['leadFullName'] ?? null,
            'leadStatus' => $conversation['leadStatus'] ?? null,
            'projectName' => $conversation['projectName'] ?? null,
            'assignedToName' => $conversation['assignedToName'] ?? null,
        ],
        'messages' => $messages,
        'windowOpen' => $windowOpen,
        'windowOpenUntil' => $lastInboundAt ? date('c', strtotime($lastInboundAt) + WHATSAPP_WINDOW_HOURS * 3600) : null,
        'integrationEnabled' => (bool)($setting['isEnabled'] ?? false),
        'templates' => is_array($config['templates'] ?? null) ? $config['templates'] : [],
    ],
]);
