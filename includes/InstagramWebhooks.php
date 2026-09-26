<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/InstagramAutomation.php';

/*
|--------------------------------------------------------------------------
| Phase 3: Webhooks
|--------------------------------------------------------------------------
| clientId/instagramAccountId are the one deliberate exception to "always
| required" in this module — a webhook can arrive for an account Modlus has
| since disconnected or never had. They're resolved here on a best-effort
| basis and stored NULL when unresolvable, so the event is never silently
| dropped (this table's whole purpose is safe debugging of what Meta sent).
*/

function ensureInstagramWebhookEventsTable(mysqli $con): void
{
    mysqli_query(
        $con,
        "CREATE TABLE IF NOT EXISTS instagramWebhookEvents (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            clientId INT NULL DEFAULT NULL,
            instagramAccountId INT UNSIGNED NULL DEFAULT NULL,
            eventType VARCHAR(50) NOT NULL DEFAULT '',
            payload LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'received',
            errorMessage TEXT NULL,
            createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            processedAt DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idxWebhookClientId (clientId),
            KEY idxWebhookAccountId (instagramAccountId),
            KEY idxWebhookStatus (status),
            KEY idxWebhookEventType (eventType)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function storeInstagramWebhookEvent(mysqli $con, ?int $clientId, ?int $accountId, string $eventType, string $payload): int
{
    ensureInstagramWebhookEventsTable($con);

    $stmt = mysqli_prepare(
        $con,
        "INSERT INTO instagramWebhookEvents (clientId, instagramAccountId, eventType, payload, status)
         VALUES (?, ?, ?, ?, 'received')"
    );
    mysqli_stmt_bind_param($stmt, 'iiss', $clientId, $accountId, $eventType, $payload);
    mysqli_stmt_execute($stmt);
    $newId = (int)mysqli_insert_id($con);
    mysqli_stmt_close($stmt);

    return $newId;
}

function markInstagramWebhookEventProcessed(mysqli $con, int $id): void
{
    $stmt = mysqli_prepare(
        $con,
        "UPDATE instagramWebhookEvents SET status = 'processed', processedAt = NOW(), errorMessage = NULL WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function markInstagramWebhookEventFailed(mysqli $con, int $id, string $message): void
{
    $stmt = mysqli_prepare(
        $con,
        "UPDATE instagramWebhookEvents SET status = 'failed', processedAt = NOW(), errorMessage = ? WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'si', $message, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Admin-facing read of recent webhook events (Task 5 — failed-event
 * visibility). $status = 'failed' is the primary use case; null returns
 * everything for the given client/account scope.
 */
function getInstagramWebhookEvents(mysqli $con, ?int $clientId = null, ?int $accountId = null, ?string $status = null, int $limit = 20): array
{
    ensureInstagramWebhookEventsTable($con);

    $where = [];
    $types = '';
    $params = [];

    if ($clientId !== null) {
        $where[] = 'clientId = ?';
        $types .= 'i';
        $params[] = $clientId;
    }

    if ($accountId !== null) {
        $where[] = 'instagramAccountId = ?';
        $types .= 'i';
        $params[] = $accountId;
    }

    if ($status !== null) {
        $where[] = 'status = ?';
        $types .= 's';
        $params[] = $status;
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $types .= 'i';
    $params[] = $limit;

    $stmt = mysqli_prepare(
        $con,
        "SELECT id, clientId, instagramAccountId, eventType, status, errorMessage, createdAt, processedAt
         FROM instagramWebhookEvents
         {$whereSql}
         ORDER BY id DESC
         LIMIT ?"
    );
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * Resolves a Modlus instagramAccounts row from the Instagram Business
 * Account id Meta's webhook payload identifies ("entry[].id"). Intentionally
 * NOT filtered by status='connected' — a webhook for a disconnected account
 * should still resolve its clientId for debugging visibility, even though
 * such an event won't be further processed (see api/instagram/instagramWebhook.php).
 */
function resolveInstagramAccountFromWebhookEntry(mysqli $con, string $instagramUserId): ?array
{
    if ($instagramUserId === '') {
        return null;
    }

    ensureInstagramAccountsTable($con);

    $stmt = mysqli_prepare($con, "SELECT * FROM instagramAccounts WHERE instagramUserId = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $instagramUserId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if (!$row) {
        return null;
    }

    return [
        'id' => (int)$row['id'],
        'clientId' => $row['clientId'] !== null ? (int)$row['clientId'] : null,
        'instagramUserId' => (string)$row['instagramUserId'],
        'username' => (string)$row['username'],
        'status' => (string)$row['status'],
        'accessToken' => decryptSecret((string)$row['accessToken']),
    ];
}

/**
 * Meta's X-Hub-Signature-256 verification: HMAC-SHA256 of the raw request
 * body, keyed by the Meta App Secret, compared with hash_equals() (constant
 * time — this is a security boundary, not a convenience check).
 */
function verifyMetaWebhookSignature(string $rawBody, ?string $signatureHeader, string $appSecret): bool
{
    if ($appSecret === '' || $signatureHeader === null || $signatureHeader === '') {
        return false;
    }

    if (strpos($signatureHeader, 'sha256=') !== 0) {
        return false;
    }

    $providedSignature = substr($signatureHeader, strlen('sha256='));
    $expectedSignature = hash_hmac('sha256', $rawBody, $appSecret);

    return hash_equals($expectedSignature, $providedSignature);
}
