<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/InstagramAutomation.php';

/*
|--------------------------------------------------------------------------
| Phase 3: Comments
|--------------------------------------------------------------------------
| clientId + instagramAccountId are always resolved before a row is written
| (from the account the comment's webhook event was resolved against — see
| InstagramWebhooks.php). postId is nullable: a comment can be on an
| Instagram post published outside Modlus, in which case instagramMediaId
| (Meta's own post id, always present) is the only reliable reference.
*/

function ensureInstagramCommentsTable(mysqli $con): void
{
    mysqli_query(
        $con,
        "CREATE TABLE IF NOT EXISTS instagramComments (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            clientId INT NOT NULL,
            instagramAccountId INT UNSIGNED NOT NULL,
            postId INT UNSIGNED NULL DEFAULT NULL,
            instagramMediaId VARCHAR(64) NOT NULL DEFAULT '',
            instagramCommentId VARCHAR(64) NOT NULL,
            parentCommentId VARCHAR(64) NULL DEFAULT NULL,
            username VARCHAR(180) NOT NULL DEFAULT '',
            commentText TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'visible',
            repliedAt DATETIME NULL DEFAULT NULL,
            hiddenAt DATETIME NULL DEFAULT NULL,
            commentedAt DATETIME NULL DEFAULT NULL,
            createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniqInstagramCommentId (instagramCommentId),
            KEY idxCommentsClientId (clientId),
            KEY idxCommentsAccountId (instagramAccountId),
            KEY idxCommentsPostId (postId)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

/**
 * Best-effort match of a comment's Instagram media id against a locally
 * tracked post for the same account. No match (post published outside
 * Modlus, or before this account was connected) is expected and fine —
 * the comment is still stored with postId = NULL.
 */
function resolveInstagramPostIdByMediaId(mysqli $con, int $accountId, string $mediaId): ?int
{
    if ($mediaId === '') {
        return null;
    }

    $stmt = mysqli_prepare(
        $con,
        "SELECT id FROM socialPosts WHERE instagramAccountId = ? AND instagramMediaId = ? LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'is', $accountId, $mediaId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    return $row ? (int)$row['id'] : null;
}

/**
 * Upserts by instagramCommentId (Meta's own id — the natural dedupe key for
 * webhook deliveries, which Meta may redeliver). clientId/instagramAccountId
 * must already be resolved by the caller (InstagramWebhooks.php) — this
 * function does not look them up itself.
 */
function upsertInstagramCommentFromWebhook(mysqli $con, array $data): int
{
    ensureInstagramCommentsTable($con);

    $clientId = (int)($data['clientId'] ?? 0);
    $accountId = (int)($data['instagramAccountId'] ?? 0);
    $instagramCommentId = trim((string)($data['instagramCommentId'] ?? ''));
    $instagramMediaId = trim((string)($data['instagramMediaId'] ?? ''));
    $parentCommentId = $data['parentCommentId'] ?? null;
    $username = trim((string)($data['username'] ?? ''));
    $commentText = (string)($data['commentText'] ?? '');
    $commentedAt = $data['commentedAt'] ?? null;

    if ($clientId <= 0 || $accountId <= 0 || $instagramCommentId === '') {
        return 0;
    }

    $postId = resolveInstagramPostIdByMediaId($con, $accountId, $instagramMediaId);

    $stmt = mysqli_prepare($con, "SELECT id FROM instagramComments WHERE instagramCommentId = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $instagramCommentId);
    mysqli_stmt_execute($stmt);
    $existing = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if ($existing) {
        $existingId = (int)$existing['id'];
        $stmt = mysqli_prepare(
            $con,
            "UPDATE instagramComments SET postId = ?, commentText = ? WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'isi', $postId, $commentText, $existingId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $existingId;
    }

    $stmt = mysqli_prepare(
        $con,
        "INSERT INTO instagramComments
            (clientId, instagramAccountId, postId, instagramMediaId, instagramCommentId, parentCommentId, username, commentText, commentedAt)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param(
        $stmt,
        'iiissssss',
        $clientId,
        $accountId,
        $postId,
        $instagramMediaId,
        $instagramCommentId,
        $parentCommentId,
        $username,
        $commentText,
        $commentedAt
    );
    mysqli_stmt_execute($stmt);
    $newId = (int)mysqli_insert_id($con);
    mysqli_stmt_close($stmt);

    return $newId;
}

function getInstagramComments(mysqli $con, int $clientId, ?int $accountId = null, ?int $postId = null): array
{
    ensureInstagramCommentsTable($con);

    $where = ['clientId = ?'];
    $types = 'i';
    $params = [$clientId];

    if ($accountId !== null) {
        $where[] = 'instagramAccountId = ?';
        $types .= 'i';
        $params[] = $accountId;
    }

    if ($postId !== null) {
        $where[] = 'postId = ?';
        $types .= 'i';
        $params[] = $postId;
    }

    $whereSql = implode(' AND ', $where);
    $stmt = mysqli_prepare(
        $con,
        "SELECT * FROM instagramComments WHERE {$whereSql} ORDER BY commentedAt DESC, id DESC"
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

function getInstagramCommentById(mysqli $con, int $id): ?array
{
    ensureInstagramCommentsTable($con);

    $stmt = mysqli_prepare($con, "SELECT * FROM instagramComments WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

function markInstagramCommentReplied(mysqli $con, int $id): void
{
    $stmt = mysqli_prepare($con, "UPDATE instagramComments SET status = 'replied', repliedAt = NOW() WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function markInstagramCommentHidden(mysqli $con, int $id, bool $hidden): void
{
    if ($hidden) {
        $stmt = mysqli_prepare($con, "UPDATE instagramComments SET status = 'hidden', hiddenAt = NOW() WHERE id = ?");
    } else {
        $stmt = mysqli_prepare($con, "UPDATE instagramComments SET status = 'visible', hiddenAt = NULL WHERE id = ?");
    }

    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/*
|--------------------------------------------------------------------------
| Meta Graph API — real comment actions
|--------------------------------------------------------------------------
| Routed through the existing instagramGraphApiRequest() wrapper — no
| separate curl implementation.
*/

function replyToInstagramComment(array $account, string $instagramCommentId, string $message): string
{
    $reply = instagramGraphApiRequest(
        'https://graph.facebook.com/v19.0/' . $instagramCommentId . '/replies',
        [
            'message' => $message,
            'access_token' => $account['accessToken'],
        ]
    );

    if (empty($reply['id'])) {
        throw new RuntimeException('Meta did not return a reply id.');
    }

    return (string)$reply['id'];
}

function hideInstagramComment(array $account, string $instagramCommentId, bool $hide): bool
{
    $result = instagramGraphApiRequest(
        'https://graph.facebook.com/v19.0/' . $instagramCommentId,
        [
            'hide' => $hide ? 'true' : 'false',
            'access_token' => $account['accessToken'],
        ]
    );

    return !empty($result['success']);
}
