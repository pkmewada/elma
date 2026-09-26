<?php

/*
|--------------------------------------------------------------------------
| Meta Instagram Webhook Receiver
|--------------------------------------------------------------------------
| Deliberately does NOT include includes/auth.php — Meta is the caller, not
| a logged-in Modlus user. Security comes entirely from:
|   - GET:  hub_verify_token must match instagramSettings.webhookVerifyToken
|   - POST: X-Hub-Signature-256 HMAC must match, keyed by the Meta App Secret
| See docs/instagram-automation-flow.md §14.
*/

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/InstagramAutomation.php';
require_once __DIR__ . '/../../includes/InstagramComments.php';
require_once __DIR__ . '/../../includes/InstagramWebhooks.php';

function instagramWebhookLog(string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents(__DIR__ . '/../../cron/instagramWebhook.log', $line, FILE_APPEND);
}

/*
|--------------------------------------------------------------------------
| GET — Meta's subscription verification handshake
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = (string)($_GET['hub_mode'] ?? '');
    $verifyToken = (string)($_GET['hub_verify_token'] ?? '');
    $challenge = (string)($_GET['hub_challenge'] ?? '');

    $settings = getInstagramSettingsForOAuth($con);
    $expectedToken = $settings['webhookVerifyToken'] ?? '';

    if ($mode === 'subscribe' && $expectedToken !== '' && hash_equals($expectedToken, $verifyToken)) {
        header('Content-Type: text/plain');
        echo $challenge;
        exit;
    }

    http_response_code(403);
    exit('Verification failed.');
}

/*
|--------------------------------------------------------------------------
| POST — actual webhook event delivery
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$rawBody = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? null;

$settings = getInstagramSettingsForOAuth($con);
$appSecret = $settings['metaAppSecret'] ?? '';

if (!verifyMetaWebhookSignature($rawBody, $signatureHeader, $appSecret)) {
    instagramWebhookLog('Rejected webhook delivery: invalid or missing signature.');
    http_response_code(403);
    exit('Invalid signature.');
}

// Meta expects a fast 200 regardless of internal processing outcome — a
// non-200 response triggers retries and can eventually suspend the
// subscription. Every failure below is caught and recorded, never thrown
// back to the HTTP response.
header('Content-Type: application/json; charset=UTF-8');
echo json_encode(['success' => true]);

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

$decoded = json_decode($rawBody, true);

if (!is_array($decoded) || empty($decoded['entry']) || !is_array($decoded['entry'])) {
    instagramWebhookLog('Received webhook with no parseable entries.');
    exit;
}

// Meta can batch multiple entries (and multiple changes per entry) into a
// single delivery — every entry/change is isolated in its own try/catch so
// one malformed or DB-hiccuping item can't abort the rest of the batch.
foreach ($decoded['entry'] as $entry) {
    try {
        $instagramUserId = (string)($entry['id'] ?? '');
        $account = resolveInstagramAccountFromWebhookEntry($con, $instagramUserId);
        $clientId = $account['clientId'] ?? null;
        $accountId = $account['id'] ?? null;

        foreach (($entry['changes'] ?? []) as $change) {
            $eventId = null;

            try {
                $eventType = (string)($change['field'] ?? 'unknown');
                $eventPayload = json_encode(['entryId' => $instagramUserId, 'change' => $change]);
                $eventId = storeInstagramWebhookEvent($con, $clientId, $accountId, $eventType, (string)$eventPayload);

                if (!$account) {
                    markInstagramWebhookEventFailed($con, $eventId, 'Unknown Instagram account: ' . $instagramUserId);
                    instagramWebhookLog('Event #' . $eventId . ' failed: unknown Instagram account ' . $instagramUserId);
                    continue;
                }

                if ($account['status'] !== 'connected') {
                    markInstagramWebhookEventFailed($con, $eventId, 'Instagram account is disconnected.');
                    instagramWebhookLog('Event #' . $eventId . ' skipped: account #' . $accountId . ' is disconnected.');
                    continue;
                }

                if ($eventType === 'comments') {
                    processInstagramCommentWebhookChange($con, (int)$clientId, (int)$accountId, $change);
                }

                markInstagramWebhookEventProcessed($con, $eventId);
                instagramWebhookLog('Event #' . $eventId . ' processed (' . $eventType . ', account #' . $accountId . ').');
            } catch (Throwable $e) {
                if ($eventId !== null && $eventId > 0) {
                    markInstagramWebhookEventFailed($con, $eventId, $e->getMessage());
                }
                instagramWebhookLog('Webhook change failed: ' . $e->getMessage());
            }
        }
    } catch (Throwable $e) {
        instagramWebhookLog('Webhook entry failed before it could be stored: ' . $e->getMessage());
    }
}

function processInstagramCommentWebhookChange(mysqli $con, int $clientId, int $accountId, array $change): void
{
    $value = $change['value'] ?? [];
    $commentId = (string)($value['id'] ?? '');

    if ($commentId === '') {
        throw new RuntimeException('Webhook comment change had no comment id.');
    }

    upsertInstagramCommentFromWebhook($con, [
        'clientId' => $clientId,
        'instagramAccountId' => $accountId,
        'instagramMediaId' => (string)($value['media']['id'] ?? ''),
        'instagramCommentId' => $commentId,
        'parentCommentId' => isset($value['parent_id']) ? (string)$value['parent_id'] : null,
        'username' => (string)($value['from']['username'] ?? ''),
        'commentText' => (string)($value['text'] ?? ''),
        'commentedAt' => isset($value['time']) ? date('Y-m-d H:i:s', (int)$value['time']) : date('Y-m-d H:i:s'),
    ]);
}
