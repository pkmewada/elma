<?php
/*
|--------------------------------------------------------------------------
| WhatsApp Cloud API webhook -> CRM conversation/message
|--------------------------------------------------------------------------
| PUBLIC endpoint (exempted from session auth in api-gateway.php). Uses the
| exact same Meta webhook mechanism already implemented for Lead Ads in
| api/integrations/meta-webhook.php: GET is the one-time subscribe
| handshake (hub.mode/hub.verify_token/hub.challenge), POST carries the
| actual events and must be signed with X-Hub-Signature-256 (HMAC-SHA256 of
| the raw body with the App Secret) -- reuses verifyMetaChallenge()/
| verifyMetaSignature() from includes/integrationAccess.php as-is.
|
| Two independent event shapes can appear per Meta's docs
| (https://developers.facebook.com/docs/whatsapp/cloud-api):
|   value.messages[]  -- an inbound customer message (dedup by message id)
|   value.statuses[]  -- a status update for OUR outbound message (sent/
|                        delivered/read/failed), matched by metaMessageId.
| Always responds 200 (Meta only needs the 200; per-item failures are
| logged, not surfaced in the HTTP response), same pattern as meta-webhook.php.
*/
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/whatsappAccess.php';

const PROVIDER = 'whatsapp';

$secrets = getIntegrationSecrets($con, PROVIDER);
$setting = getIntegrationSetting($con, PROVIDER);

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
    $mode = (string)($_GET['hub_mode'] ?? '');
    $token = (string)($_GET['hub_verify_token'] ?? '');
    $challenge = (string)($_GET['hub_challenge'] ?? '');

    if (verifyMetaChallenge((string)($secrets['verifyToken'] ?? ''), $mode, $token)) {
        header('Content-Type: text/plain');
        echo $challenge;
        exit;
    }

    logIntegrationEvent($con, PROVIDER, null, 'webhook_verify', null, 'error', 'Verification token mismatch');
    http_response_code(403);
    exit('Verification failed.');
}

if (!$setting || !$setting['isEnabled']) {
    whatsappJsonExit(503, 'WhatsApp integration is not enabled.');
}

if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 2 * 1024 * 1024) {
    whatsappJsonExit(413, 'Request too large.');
}

$rawBody = file_get_contents('php://input');

// Unconditional receipt log -- BEFORE the signature check -- so "the webhook
// was never reached" is distinguishable from "it was reached and rejected".
// Safe fields only: sizes/booleans/counts, never the signature header value
// or the raw payload itself.
$payloadPreview = json_decode($rawBody, true);
logIntegrationEvent($con, PROVIDER, null, 'webhook_received', null, 'received', 'Webhook POST received', [
    'bytes' => strlen($rawBody),
    'hasSignatureHeader' => isset($_SERVER['HTTP_X_HUB_SIGNATURE_256']),
    'hasEntry' => isset($payloadPreview['entry']),
    'entryCount' => is_array($payloadPreview['entry'] ?? null) ? count($payloadPreview['entry']) : 0,
]);

if (!verifyMetaSignature($rawBody, (string)($secrets['appSecret'] ?? ''), $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? null)) {
    logIntegrationEvent($con, PROVIDER, null, 'webhook_event', null, 'error', 'Invalid or missing signature');
    whatsappJsonExit(401, 'Invalid signature.');
}

$config = getIntegrationConfig($con, PROVIDER);
$payload = $payloadPreview;
$entries = is_array($payload['entry'] ?? null) ? $payload['entry'] : [];

try {

foreach ($entries as $entry) {
    $changes = is_array($entry['changes'] ?? null) ? $entry['changes'] : [];

    foreach ($changes as $change) {
        $value = $change['value'] ?? [];
        $contacts = is_array($value['contacts'] ?? null) ? $value['contacts'] : [];
        $messages = is_array($value['messages'] ?? null) ? $value['messages'] : [];
        $statuses = is_array($value['statuses'] ?? null) ? $value['statuses'] : [];

        // Neither shape this parser understands -- most likely either a
        // different webhook "field" landed on this URL (e.g. Meta's
        // Lead Ads 'leadgen' field pointed at the WhatsApp callback URL by
        // mistake, or vice versa) or Meta added a new value shape. Logging
        // the field name + value's top-level keys (never their content) is
        // enough to tell which, without needing to dump the raw payload.
        if (!$messages && !$statuses) {
            logIntegrationEvent($con, PROVIDER, null, 'webhook_event', null, 'rejected', 'Unrecognized change value shape', [
                'field' => $change['field'] ?? null,
                'valueKeys' => is_array($value) ? array_keys($value) : [],
            ]);
        }

        // ---------------- Inbound messages ----------------
        foreach ($messages as $message) {
            $metaMessageId = (string)($message['id'] ?? '');
            $waId = normalizeWaId((string)($message['from'] ?? ''));

            if ($waId === '' || $metaMessageId === '') {
                logIntegrationEvent($con, PROVIDER, $metaMessageId ?: null, 'message_received', null, 'rejected', 'Missing from/id');
                continue;
            }

            $existsStmt = mysqli_prepare($con, 'SELECT id FROM whatsappMessages WHERE metaMessageId = ? LIMIT 1');
            mysqli_stmt_bind_param($existsStmt, 's', $metaMessageId);
            mysqli_stmt_execute($existsStmt);
            $already = mysqli_fetch_assoc(mysqli_stmt_get_result($existsStmt));
            mysqli_stmt_close($existsStmt);

            if ($already) {
                logIntegrationEvent($con, PROVIDER, $metaMessageId, 'message_received', null, 'duplicate', 'Duplicate Meta message id');
                continue;
            }

            $contactName = null;
            foreach ($contacts as $contact) {
                if (normalizeWaId((string)($contact['wa_id'] ?? '')) === $waId) {
                    $contactName = (string)($contact['profile']['name'] ?? '') ?: null;
                    break;
                }
            }

            $conversation = findOrCreateConversation($con, $waId, (string)($message['from'] ?? $waId), $contactName);
            $type = (string)($message['type'] ?? 'text');
            $messageText = null;
            $mediaId = null;
            $mediaPath = null;

            if ($type === 'text') {
                $messageText = (string)($message['text']['body'] ?? '');
            } elseif (in_array($type, ['image', 'document'], true)) {
                $mediaId = (string)($message[$type]['id'] ?? '');
                $messageText = (string)($message[$type]['caption'] ?? '');

                if ($mediaId !== '') {
                    $allowed = $type === 'image' ? PRIVATE_FILE_TYPES_IMAGE : PRIVATE_FILE_TYPES_DOCUMENT;
                    $downloaded = downloadWhatsappMediaBinary($secrets, $config, $mediaId);
                    $stored = $downloaded ? storeWhatsappMediaBinary($downloaded['binary'], (int)$conversation['id'], $allowed) : null;
                    $mediaPath = $stored ? $stored['fileName'] : null;
                }
            } else {
                // Out of scope for this phase (audio/video/sticker/location/interactive/etc.) --
                // recorded as metadata only so it never breaks lead/chat flow, never discarded silently.
                $messageText = '[' . ucfirst($type) . ' message]';
            }

            $sentAt = date('Y-m-d H:i:s', (int)($message['timestamp'] ?? time()));
            $preview = $messageText !== '' ? mb_substr($messageText, 0, 250) : ('[' . ucfirst($type) . ']');

            $insertStmt = mysqli_prepare($con, "
                INSERT INTO whatsappMessages (conversationId, metaMessageId, direction, messageType, messageText, mediaId, mediaPath, status, sentAt)
                VALUES (?, ?, 'inbound', ?, ?, ?, ?, 'received', ?)
            ");
            mysqli_stmt_bind_param($insertStmt, 'issssss', $conversation['id'], $metaMessageId, $type, $messageText, $mediaId, $mediaPath, $sentAt);
            $inserted = mysqli_stmt_execute($insertStmt);
            $insertError = $inserted ? '' : mysqli_error($con);
            mysqli_stmt_close($insertStmt);

            // db.php runs with mysqli_report(MYSQLI_REPORT_OFF) -- a failed INSERT
            // here would otherwise be completely silent (no exception, no warning)
            // and the message would just vanish with no trace anywhere.
            if (!$inserted) {
                logIntegrationEvent($con, PROVIDER, $metaMessageId, 'message_received', $conversation['leadId'] ?: null, 'error', 'DB insert failed: ' . mb_substr($insertError, 0, 200), ['from' => $waId, 'type' => $type]);
                continue;
            }

            $updateStmt = mysqli_prepare($con, 'UPDATE whatsappConversations SET lastMessageAt = ?, lastMessagePreview = ?, unreadCount = unreadCount + 1 WHERE id = ?');
            mysqli_stmt_bind_param($updateStmt, 'ssi', $sentAt, $preview, $conversation['id']);
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);

            if ($conversation['leadId']) {
                saveActivityLog($con, 'Lead', (int)$conversation['leadId'], 'WHATSAPP_RECEIVED', 'WhatsApp message received : ' . ($conversation['customerName'] ?: $waId));
            }

            logIntegrationEvent($con, PROVIDER, $metaMessageId, 'message_received', $conversation['leadId'] ?: null, 'created', 'Message stored', ['from' => $waId, 'type' => $type]);
        }

        // ---------------- Status updates for our outbound messages ----------------
        foreach ($statuses as $status) {
            $metaMessageId = (string)($status['id'] ?? '');
            $newStatus = (string)($status['status'] ?? '');

            if ($metaMessageId === '' || whatsappStatusRank($newStatus) < 0) {
                continue;
            }

            $rowStmt = mysqli_prepare($con, 'SELECT id, status FROM whatsappMessages WHERE metaMessageId = ? LIMIT 1');
            mysqli_stmt_bind_param($rowStmt, 's', $metaMessageId);
            mysqli_stmt_execute($rowStmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($rowStmt));
            mysqli_stmt_close($rowStmt);

            if (!$row) {
                continue; // status for a message we don't have (e.g. sent from a different system) -- nothing to update
            }

            // Never let an out-of-order/duplicate webhook downgrade an already-more-advanced status.
            if (whatsappStatusRank($newStatus) <= whatsappStatusRank((string)$row['status']) && $newStatus !== 'failed') {
                continue;
            }

            $timestamp = date('Y-m-d H:i:s', (int)($status['timestamp'] ?? time()));
            $errorCode = null;
            $errorMessage = null;

            if ($newStatus === 'failed' && !empty($status['errors'][0])) {
                $errorCode = mb_substr((string)($status['errors'][0]['code'] ?? ''), 0, 50);
                $errorMessage = mb_substr((string)($status['errors'][0]['title'] ?? 'Delivery failed'), 0, 255);
            }

            $sets = ['status = ?'];
            $types = 's';
            $params = [$newStatus];

            if ($newStatus === 'delivered') {
                $sets[] = 'deliveredAt = ?'; $types .= 's'; $params[] = $timestamp;
            } elseif ($newStatus === 'read') {
                $sets[] = 'readAt = ?'; $types .= 's'; $params[] = $timestamp;
            } elseif ($newStatus === 'failed') {
                $sets[] = 'errorCode = ?'; $types .= 's'; $params[] = $errorCode;
                $sets[] = 'errorMessage = ?'; $types .= 's'; $params[] = $errorMessage;
            }

            $types .= 's';
            $params[] = $metaMessageId;
            $updateStmt = mysqli_prepare($con, 'UPDATE whatsappMessages SET ' . implode(', ', $sets) . ' WHERE metaMessageId = ?');
            mysqli_stmt_bind_param($updateStmt, $types, ...$params);
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);

            if ($newStatus === 'failed') {
                logIntegrationEvent($con, PROVIDER, $metaMessageId, 'message_status', null, 'error', $errorMessage ?: 'Delivery failed', ['status' => $newStatus]);
            }
        }
    }
}

} catch (Throwable $e) {
    // Without this, an uncaught error anywhere in the loop above (e.g. a
    // missing dependency, a schema mismatch) would 500 with no body and,
    // critically, no integrationLogs row at all -- indistinguishable from
    // the webhook never having been reached in the first place.
    error_log('whatsapp-webhook failed: ' . $e->getMessage());
    logIntegrationEvent($con, PROVIDER, null, 'webhook_event', null, 'error', 'Unhandled error: ' . mb_substr($e->getMessage(), 0, 200));
}

echo json_encode(['success' => true]);
