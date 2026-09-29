<?php
/*
|--------------------------------------------------------------------------
| WhatsApp CRM Chat: conversation/message access, Meta Cloud API calls
|--------------------------------------------------------------------------
| Reuses includes/integrationAccess.php for settings/secrets/logging
| (provider = 'whatsapp' on the same integrationSettings/integrationLogs
| tables -- no second config system) and includes/leadAccess.php for scope
| (getLeadScopeEmployeeId()/canAccessAllLeads() -- no second permission
| model). Conversation ownership: a linked conversation's owner is its
| lead's assignedToId; an unlinked conversation's owner is its own
| assignedToId (or nobody, in which case only full-scope callers see it).
|
| Endpoint: POST https://graph.facebook.com/{version}/{phone_number_id}/...
| (Bearer access token), per Meta's WhatsApp Cloud API docs, verified
| against https://developers.facebook.com/docs/whatsapp/cloud-api during
| Phase 6.5 implementation. Webhook verification/signature is the same
| Meta mechanism already implemented for Lead Ads (verifyMetaChallenge()/
| verifyMetaSignature() in integrationAccess.php) -- reused as-is.
*/
require_once __DIR__ . '/integrationAccess.php';
require_once __DIR__ . '/leadAccess.php';
require_once __DIR__ . '/privateFiles.php';

const WHATSAPP_WINDOW_HOURS = 24;
const WHATSAPP_DEFAULT_API_VERSION = 'v21.0';
const WHATSAPP_MEDIA_SUBDIR = 'whatsapp-media';

function whatsappJsonExit(int $statusCode, string $message, array $data = []): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message, 'data' => $data]);
    exit;
}

/** The route the current caller's WhatsApp page/APIs are gated by. */
function whatsappCallerRoute(): string
{
    return getLoggedInUserType() === 'employee' ? '/emp-whatsapp' : '/whatsapp';
}

/** Meta wa_id: digits only (country code + number). Never assume +91. */
function normalizeWaId(string $raw): string
{
    return preg_replace('/\D/', '', $raw) ?? '';
}

/**
 * Best-effort match of an inbound wa_id to an existing lead by phone,
 * comparing digits-only so stored formatting (+91, spaces) doesn't matter.
 * Not a new dedup rule -- same "compare normalized phone" idea already used
 * for manual leads, just applied one-directionally here (wa_id -> lead).
 */
function findLeadByWaId(mysqli $con, string $waId): ?array
{
    $suffix = substr($waId, -10);

    if ($suffix === '') {
        return null;
    }

    $stmt = mysqli_prepare($con, "SELECT id, fullName, assignedToId, phone, countryCode FROM leads WHERE phone LIKE ? ORDER BY createdAt DESC LIMIT 25");
    $like = '%' . $suffix;
    mysqli_stmt_bind_param($stmt, 's', $like);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $candidate = preg_replace('/\D/', '', ($row['countryCode'] ?? '') . $row['phone']) ?? '';
        $candidateNoCode = preg_replace('/\D/', '', (string)$row['phone']) ?? '';

        if ($candidate !== '' && ($candidate === $waId || (strlen($candidateNoCode) >= 8 && str_ends_with($waId, $candidateNoCode)))) {
            mysqli_stmt_close($stmt);

            return $row;
        }
    }

    mysqli_stmt_close($stmt);

    return null;
}

/**
 * Best-effort split of a wa_id into the CRM's stored (countryCode, phone)
 * shape, only for the pattern the CRM's other channels already default to
 * (91 + 10 digits) -- so dedup-by-phone still matches existing +91 leads.
 * Any other international number is kept as-is (never guessed/reformatted),
 * per Section 30: this CRM may receive international customers.
 */
function splitWaIdForLead(string $waId): array
{
    if (preg_match('/^91(\d{10})$/', $waId, $matches)) {
        return ['countryCode' => '+91', 'phone' => $matches[1]];
    }

    return ['countryCode' => '', 'phone' => $waId];
}

/** Finds the conversation for a wa_id, or creates an unlinked/auto-linked one. */
function findOrCreateConversation(mysqli $con, string $waId, string $phoneNumber, ?string $customerName): array
{
    $stmt = mysqli_prepare($con, 'SELECT * FROM whatsappConversations WHERE waId = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $waId);
    mysqli_stmt_execute($stmt);
    $conversation = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($conversation) {
        if ($customerName && empty($conversation['customerName'])) {
            $stmt = mysqli_prepare($con, 'UPDATE whatsappConversations SET customerName = ? WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'si', $customerName, $conversation['id']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $conversation['customerName'] = $customerName;
        }

        return $conversation;
    }

    $lead = findLeadByWaId($con, $waId);
    $leadId = $lead['id'] ?? null;

    $stmt = mysqli_prepare($con, 'INSERT INTO whatsappConversations (leadId, waId, phoneNumber, customerName) VALUES (?, ?, ?, ?)');
    mysqli_stmt_bind_param($stmt, 'isss', $leadId, $waId, $phoneNumber, $customerName);
    mysqli_stmt_execute($stmt);
    $id = mysqli_insert_id($con);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($con, 'SELECT * FROM whatsappConversations WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $conversation = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return $conversation;
}

/**
 * Loads a conversation and aborts 404 when missing or outside the caller's
 * scope. Full-scope callers (admin / view-all-leads) see everything; a
 * restricted employee only sees conversations owned by them, via their
 * linked lead's assignedToId or the conversation's own assignedToId.
 */
function requireConversationAccess(mysqli $con, int $conversationId): array
{
    if ($conversationId <= 0) {
        whatsappJsonExit(422, 'Invalid conversation ID.');
    }

    $stmt = mysqli_prepare($con, '
        SELECT c.*, l.fullName AS leadFullName, l.status AS leadStatus, l.assignedToId AS leadAssignedToId,
               l.projectId, p.projectName, eu.fullName AS assignedToName
        FROM whatsappConversations c
        LEFT JOIN leads l ON l.id = c.leadId
        LEFT JOIN projects p ON p.id = l.projectId
        LEFT JOIN employeeusers eu ON eu.id = COALESCE(l.assignedToId, c.assignedToId)
        WHERE c.id = ? LIMIT 1
    ');
    mysqli_stmt_bind_param($stmt, 'i', $conversationId);
    mysqli_stmt_execute($stmt);
    $conversation = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$conversation) {
        whatsappJsonExit(404, 'Conversation not found.');
    }

    $scope = getLeadScopeEmployeeId();

    if ($scope !== 0) {
        $ownerId = $conversation['leadId'] ? (int)$conversation['leadAssignedToId'] : (int)$conversation['assignedToId'];

        if ($ownerId !== $scope) {
            whatsappJsonExit(404, 'Conversation not found.');
        }
    }

    return $conversation;
}

/** Timestamp of the customer's last inbound message, or null if none yet. */
function getLastInboundAt(mysqli $con, int $conversationId): ?string
{
    $stmt = mysqli_prepare($con, "SELECT MAX(sentAt) FROM whatsappMessages WHERE conversationId = ? AND direction = 'inbound'");
    mysqli_stmt_bind_param($stmt, 'i', $conversationId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $lastInboundAt);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    return $lastInboundAt;
}

/**
 * Meta's 24-hour customer service window: free-form replies are only
 * allowed within 24h of the customer's last message; outside it, only an
 * approved template may be sent. https://developers.facebook.com/docs/whatsapp/cloud-api
 */
function isConversationWindowOpen(?string $lastInboundAt): bool
{
    return $lastInboundAt !== null && (time() - strtotime($lastInboundAt)) < WHATSAPP_WINDOW_HOURS * 3600;
}

function whatsappApiVersion(array $config): string
{
    $version = trim((string)($config['apiVersion'] ?? ''));

    return $version !== '' ? $version : WHATSAPP_DEFAULT_API_VERSION;
}

function whatsappGraphRequest(string $method, string $path, array $secrets, array $config, ?array $jsonBody = null, ?array $multipart = null): array
{
    $url = 'https://graph.facebook.com/' . whatsappApiVersion($config) . '/' . ltrim($path, '/');
    $headers = ['Authorization: Bearer ' . (string)($secrets['accessToken'] ?? '')];

    $ch = curl_init($url);
    $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => true];

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;

        if ($multipart !== null) {
            $options[CURLOPT_POSTFIELDS] = $multipart;
        } else {
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_POSTFIELDS] = json_encode($jsonBody ?? []);
        }
    }

    $options[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = $response !== false ? json_decode($response, true) : null;

    return ['ok' => $httpCode >= 200 && $httpCode < 300, 'httpCode' => $httpCode, 'body' => is_array($decoded) ? $decoded : []];
}

function sendWhatsappText(array $secrets, array $config, string $toWaId, string $text): array
{
    $phoneNumberId = (string)($config['phoneNumberId'] ?? '');

    return whatsappGraphRequest('POST', "{$phoneNumberId}/messages", $secrets, $config, [
        'messaging_product' => 'whatsapp', 'to' => $toWaId, 'type' => 'text', 'text' => ['body' => $text],
    ]);
}

function sendWhatsappTemplate(array $secrets, array $config, string $toWaId, string $templateName, string $language, array $variables): array
{
    $components = [];

    if ($variables) {
        $components[] = ['type' => 'body', 'parameters' => array_map(static fn($v) => ['type' => 'text', 'text' => (string)$v], $variables)];
    }

    $phoneNumberId = (string)($config['phoneNumberId'] ?? '');

    return whatsappGraphRequest('POST', "{$phoneNumberId}/messages", $secrets, $config, [
        'messaging_product' => 'whatsapp', 'to' => $toWaId, 'type' => 'template',
        'template' => array_filter([
            'name' => $templateName,
            'language' => ['code' => $language],
            'components' => $components ?: null,
        ], static fn($v) => $v !== null),
    ]);
}

function sendWhatsappMedia(array $secrets, array $config, string $toWaId, string $mediaType, string $mediaId): array
{
    $phoneNumberId = (string)($config['phoneNumberId'] ?? '');

    return whatsappGraphRequest('POST', "{$phoneNumberId}/messages", $secrets, $config, [
        'messaging_product' => 'whatsapp', 'to' => $toWaId, 'type' => $mediaType, $mediaType => ['id' => $mediaId],
    ]);
}

/** Uploads a local file to Meta's media endpoint; returns the media id or null. */
function uploadWhatsappMedia(array $secrets, array $config, string $filePath, string $mimeType): ?string
{
    $phoneNumberId = (string)($config['phoneNumberId'] ?? '');
    $result = whatsappGraphRequest('POST', "{$phoneNumberId}/media", $secrets, $config, null, [
        'messaging_product' => 'whatsapp',
        'type' => $mimeType,
        'file' => new CURLFile($filePath, $mimeType),
    ]);

    return $result['ok'] ? ($result['body']['id'] ?? null) : null;
}

/** Downloads inbound media (two-step: fetch URL, then fetch the bytes) or null on any failure. */
function downloadWhatsappMediaBinary(array $secrets, array $config, string $mediaId): ?array
{
    $meta = whatsappGraphRequest('GET', $mediaId, $secrets, $config);

    if (!$meta['ok'] || empty($meta['body']['url'])) {
        return null;
    }

    $ch = curl_init($meta['body']['url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . (string)($secrets['accessToken'] ?? '')],
        CURLOPT_TIMEOUT => 30,
    ]);
    $binary = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || $binary === false) {
        return null;
    }

    return ['binary' => $binary, 'mimeType' => (string)($meta['body']['mime_type'] ?? 'application/octet-stream')];
}

/** Validates+stores a downloaded media binary the same way storePrivateFile() validates an upload. */
function storeWhatsappMediaBinary(string $binary, int $conversationId, array $allowedTypes): ?array
{
    $tmpFile = tempnam(sys_get_temp_dir(), 'wa');
    file_put_contents($tmpFile, $binary);
    $mimeType = (string)(new finfo(FILEINFO_MIME_TYPE))->file($tmpFile);

    if (!isset($allowedTypes[$mimeType])) {
        @unlink($tmpFile);

        return null;
    }

    $directory = getPrivateStorageRoot() . '/' . WHATSAPP_MEDIA_SUBDIR . '/' . $conversationId;

    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        @unlink($tmpFile);

        return null;
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];

    if (!rename($tmpFile, $directory . '/' . $fileName)) {
        @unlink($tmpFile);

        return null;
    }

    return ['fileName' => $fileName, 'mimeType' => $mimeType];
}

/** Status rank so an out-of-order/duplicate webhook can never downgrade a message's recorded status. */
function whatsappStatusRank(string $status): int
{
    return ['pending' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 9][$status] ?? -1;
}

/*
|--------------------------------------------------------------------------
| Shared outbound-send bookkeeping (used by both send-message.php and
| send-template.php so the pending/sent/failed lifecycle, activity logging
| and integration logging stay in exactly one place).
|--------------------------------------------------------------------------
*/

/** Inserts the outbound message row as 'pending' BEFORE any Meta API call, so a failure at any later step still leaves a visible, retryable row. */
function insertPendingWhatsappMessage(mysqli $con, int $conversationId, string $messageType, ?string $messageText, ?string $mediaId, ?string $mediaPath, array $actor): int
{
    $stmt = mysqli_prepare($con, "
        INSERT INTO whatsappMessages (conversationId, direction, messageType, messageText, mediaId, mediaPath, status, sentByType, sentById, sentAt)
        VALUES (?, 'outbound', ?, ?, ?, ?, 'pending', ?, ?, NOW())
    ");
    mysqli_stmt_bind_param($stmt, 'isssssi', $conversationId, $messageType, $messageText, $mediaId, $mediaPath, $actor['type'], $actor['id']);
    mysqli_stmt_execute($stmt);
    $messageId = mysqli_insert_id($con);
    mysqli_stmt_close($stmt);

    return $messageId;
}

function markWhatsappMessageFailed(mysqli $con, int $messageId, string $errorMessage): void
{
    $errorMessage = mb_substr($errorMessage, 0, 255);
    $stmt = mysqli_prepare($con, "UPDATE whatsappMessages SET status = 'failed', errorMessage = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $errorMessage, $messageId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/** Records a Meta send result against a pending message row and exits with the same JSON shape either way -- never returns. */
function finalizeWhatsappSend(mysqli $con, array $conversation, int $messageId, array $sendResult, string $messageType, ?string $messageText): void
{
    $conversationId = (int)$conversation['id'];
    $metaMessageId = $sendResult['body']['messages'][0]['id'] ?? null;

    if ($sendResult['ok'] && $metaMessageId) {
        $updateStmt = mysqli_prepare($con, "UPDATE whatsappMessages SET status = 'sent', metaMessageId = ? WHERE id = ?");
        mysqli_stmt_bind_param($updateStmt, 'si', $metaMessageId, $messageId);
        mysqli_stmt_execute($updateStmt);
        mysqli_stmt_close($updateStmt);

        $preview = $messageType === 'text' ? mb_substr((string)$messageText, 0, 250) : ('[' . ucfirst($messageType) . '] ' . mb_substr((string)$messageText, 0, 200));
        $convUpdate = mysqli_prepare($con, 'UPDATE whatsappConversations SET lastMessageAt = NOW(), lastMessagePreview = ? WHERE id = ?');
        mysqli_stmt_bind_param($convUpdate, 'si', $preview, $conversationId);
        mysqli_stmt_execute($convUpdate);
        mysqli_stmt_close($convUpdate);

        if ($conversation['leadId']) {
            saveActivityLog($con, 'Lead', (int)$conversation['leadId'], 'WHATSAPP_SENT', 'WhatsApp message sent : ' . ($conversation['leadFullName'] ?: $conversation['waId']));
        }

        logIntegrationEvent($con, 'whatsapp', $metaMessageId, 'message_sent', $conversation['leadId'] ?: null, 'created', 'Message sent', ['type' => $messageType]);

        echo json_encode(['success' => true, 'message' => 'Message sent.', 'data' => ['id' => $messageId, 'status' => 'sent', 'metaMessageId' => $metaMessageId]]);
        exit;
    }

    // Never expose the raw Meta response to the browser; keep a safe reason for admins in the log.
    $errorTitle = mb_substr((string)($sendResult['body']['error']['message'] ?? 'Provider send failed'), 0, 255);
    markWhatsappMessageFailed($con, $messageId, $errorTitle);
    logIntegrationEvent($con, 'whatsapp', null, 'message_sent', $conversation['leadId'] ?: null, 'error', $errorTitle, ['type' => $messageType]);

    whatsappJsonExit(502, 'Message could not be sent. It has been kept as failed and can be retried.', ['id' => $messageId, 'status' => 'failed']);
}

/**
 * Matches a requested template name/language against the admin-approved
 * list (Integrations -> WhatsApp Cloud API -> Approved Templates,
 * config.templates). Meta is the source of truth for whether a template is
 * actually approved -- this only stops a request for a name/language/
 * variable-count the admin never configured from reaching the Graph API,
 * catching typos and mismatched variable counts before they burn a send.
 */
function findApprovedWhatsappTemplate(array $config, string $name, string $language): ?array
{
    if ($name === '') {
        return null;
    }

    foreach ((array)($config['templates'] ?? []) as $template) {
        if (!is_array($template) || ($template['name'] ?? '') !== $name) {
            continue;
        }

        if ($language !== '' && ($template['language'] ?? '') !== $language) {
            continue;
        }

        return $template;
    }

    return null;
}
