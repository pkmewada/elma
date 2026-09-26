<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Crypto.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Composer\CaBundle\CaBundle -- see instagramGraphApiRequest()

function ensureInstagramSettingsTable(mysqli $con): void
{
    mysqli_query(
        $con,
        "CREATE TABLE IF NOT EXISTS instagramSettings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            metaAppId VARCHAR(191) NOT NULL DEFAULT '',
            metaAppSecret TEXT NOT NULL,
            metaConfigId VARCHAR(191) NOT NULL DEFAULT '',
            redirectUrl VARCHAR(255) NOT NULL DEFAULT '',
            webhookVerifyToken VARCHAR(191) NOT NULL DEFAULT '',
            isActive TINYINT(1) NOT NULL DEFAULT 1,
            createdBy INT UNSIGNED NOT NULL DEFAULT 0,
            createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    instagramSettingsEnsureColumn($con, 'webhookVerifyToken', "VARCHAR(191) NOT NULL DEFAULT ''");
    // Facebook Login for Business config_id — needed by api/instagram/instagramOauthStart.php
    // to build the authorize URL. Nullable-by-default (empty string) so
    // existing installs keep working with the legacy scope-based OAuth flow
    // until an operator enters a Configuration ID in the settings UI.
    instagramSettingsEnsureColumn($con, 'metaConfigId', "VARCHAR(191) NOT NULL DEFAULT '' AFTER metaAppId");
}

function instagramSettingsEnsureColumn(mysqli $con, string $column, string $definition): void
{
    $columnName = mysqli_real_escape_string($con, $column);
    $result = mysqli_query($con, "SHOW COLUMNS FROM instagramSettings LIKE '{$columnName}'");

    if ($result && mysqli_num_rows($result) > 0) {
        return;
    }

    mysqli_query($con, "ALTER TABLE instagramSettings ADD COLUMN {$column} {$definition}");
}

function ensureInstagramAccountsTable(mysqli $con): void
{
    mysqli_query(
        $con,
        "CREATE TABLE IF NOT EXISTS instagramAccounts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            createdBy INT UNSIGNED NOT NULL DEFAULT 0,
            clientId INT NULL DEFAULT NULL,
            instagramUserId VARCHAR(64) NOT NULL DEFAULT '',
            facebookPageId VARCHAR(64) NOT NULL DEFAULT '',
            username VARCHAR(180) NOT NULL DEFAULT '',
            accessToken TEXT NOT NULL,
            tokenExpiry DATETIME NULL DEFAULT NULL,
            lastAnalyticsSyncAt DATETIME NULL DEFAULT NULL,
            lastAnalyticsSyncError TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'connected',
            createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniqInstagramUserId (instagramUserId)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    instagramAccountsEnsureColumn($con, 'clientId', 'INT NULL DEFAULT NULL');
    instagramAccountsEnsureColumn($con, 'lastAnalyticsSyncAt', 'DATETIME NULL DEFAULT NULL');
    instagramAccountsEnsureColumn($con, 'lastAnalyticsSyncError', 'TEXT NULL');
}

function instagramAccountsEnsureColumn(mysqli $con, string $column, string $definition): void
{
    $columnName = mysqli_real_escape_string($con, $column);
    $result = mysqli_query($con, "SHOW COLUMNS FROM instagramAccounts LIKE '{$columnName}'");

    if ($result && mysqli_num_rows($result) > 0) {
        return;
    }

    mysqli_query($con, "ALTER TABLE instagramAccounts ADD COLUMN {$column} {$definition}");
}

function getInstagramSettingsDefaults(): array
{
    return [
        'metaAppId' => '',
        'metaConfigId' => '',
        'redirectUrl' => '',
        'webhookVerifyToken' => '',
        'hasAppSecret' => false,
    ];
}

function getInstagramSettingsRow(mysqli $con): ?array
{
    ensureInstagramSettingsTable($con);

    $stmt = mysqli_prepare(
        $con,
        "SELECT * FROM instagramSettings WHERE isActive = 1 ORDER BY id DESC LIMIT 1"
    );

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

function getInstagramSettings(mysqli $con): array
{
    $row = getInstagramSettingsRow($con);
    $defaults = getInstagramSettingsDefaults();

    if (!$row) {
        return $defaults;
    }

    return [
        'metaAppId' => (string)$row['metaAppId'],
        'metaConfigId' => (string)($row['metaConfigId'] ?? ''),
        'redirectUrl' => (string)$row['redirectUrl'],
        'webhookVerifyToken' => (string)$row['webhookVerifyToken'],
        'hasAppSecret' => trim((string)$row['metaAppSecret']) !== '',
    ];
}

function getInstagramSettingsForOAuth(mysqli $con): ?array
{
    $row = getInstagramSettingsRow($con);

    if (!$row || trim((string)$row['metaAppId']) === '' || trim((string)$row['metaAppSecret']) === '') {
        return null;
    }

    return [
        'metaAppId' => (string)$row['metaAppId'],
        'metaAppSecret' => decryptSecret((string)$row['metaAppSecret']),
        'metaConfigId' => (string)($row['metaConfigId'] ?? ''),
        'redirectUrl' => (string)$row['redirectUrl'],
        'webhookVerifyToken' => (string)$row['webhookVerifyToken'],
    ];
}

function saveInstagramSettings(mysqli $con, array $settings, int $userId): bool
{
    ensureInstagramSettingsTable($con);

    $metaAppId = trim((string)($settings['metaAppId'] ?? ''));
    $metaAppSecret = trim((string)($settings['metaAppSecret'] ?? ''));
    $metaConfigId = trim((string)($settings['metaConfigId'] ?? ''));
    $redirectUrl = trim((string)($settings['redirectUrl'] ?? ''));

    $existing = getInstagramSettingsRow($con);

    $encryptedSecret = $metaAppSecret !== ''
        ? encryptSecret($metaAppSecret)
        : (string)($existing['metaAppSecret'] ?? '');

    // A verify token is required for Meta's webhook GET handshake — generate
    // one on first save rather than leaving it blank (an empty token would
    // make verifyMetaWebhookSignature-adjacent handshake checks meaningless).
    $webhookVerifyToken = (string)($existing['webhookVerifyToken'] ?? '');

    if ($webhookVerifyToken === '') {
        $webhookVerifyToken = bin2hex(random_bytes(20));
    }

    if ($existing) {
        $stmt = mysqli_prepare(
            $con,
            "UPDATE instagramSettings
             SET metaAppId = ?, metaAppSecret = ?, metaConfigId = ?, redirectUrl = ?, webhookVerifyToken = ?
             WHERE id = ?"
        );

        if (!$stmt) {
            return false;
        }

        $existingId = (int)$existing['id'];
        mysqli_stmt_bind_param($stmt, 'sssssi', $metaAppId, $encryptedSecret, $metaConfigId, $redirectUrl, $webhookVerifyToken, $existingId);
    } else {
        $stmt = mysqli_prepare(
            $con,
            "INSERT INTO instagramSettings (metaAppId, metaAppSecret, metaConfigId, redirectUrl, webhookVerifyToken, isActive, createdBy)
             VALUES (?, ?, ?, ?, ?, 1, ?)"
        );

        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'sssssi', $metaAppId, $encryptedSecret, $metaConfigId, $redirectUrl, $webhookVerifyToken, $userId);
    }

    $saved = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $saved;
}

function getInstagramAccounts(mysqli $con, ?int $clientId = null): array
{
    ensureInstagramAccountsTable($con);

    if ($clientId !== null) {
        $stmt = mysqli_prepare(
            $con,
            "SELECT id, clientId, instagramUserId, facebookPageId, username, tokenExpiry,
                    lastAnalyticsSyncAt, lastAnalyticsSyncError, status, createdAt
             FROM instagramAccounts
             WHERE clientId = ?
             ORDER BY id DESC"
        );
        mysqli_stmt_bind_param($stmt, 'i', $clientId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query(
            $con,
            "SELECT id, clientId, instagramUserId, facebookPageId, username, tokenExpiry,
                    lastAnalyticsSyncAt, lastAnalyticsSyncError, status, createdAt
             FROM instagramAccounts
             ORDER BY id DESC"
        );
    }

    $accounts = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $accounts[] = $row;
    }

    return $accounts;
}

function saveInstagramAccountFromOAuth(mysqli $con, array $account, int $userId): int
{
    ensureInstagramAccountsTable($con);

    $clientId = (int)($account['clientId'] ?? 0);
    $instagramUserId = trim((string)($account['instagramUserId'] ?? ''));
    $facebookPageId = trim((string)($account['facebookPageId'] ?? ''));
    $username = trim((string)($account['username'] ?? ''));
    $encryptedToken = encryptSecret((string)($account['accessToken'] ?? ''));
    $tokenExpiry = $account['tokenExpiry'] ?? null;

    if ($instagramUserId === '' || $clientId <= 0) {
        return 0;
    }

    $stmt = mysqli_prepare($con, "SELECT id FROM instagramAccounts WHERE instagramUserId = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $instagramUserId);
    mysqli_stmt_execute($stmt);
    $existing = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if ($existing) {
        $existingId = (int)$existing['id'];
        $stmt = mysqli_prepare(
            $con,
            "UPDATE instagramAccounts
             SET clientId = ?, facebookPageId = ?, username = ?, accessToken = ?, tokenExpiry = ?, status = 'connected'
             WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'issssi', $clientId, $facebookPageId, $username, $encryptedToken, $tokenExpiry, $existingId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $existingId;
    }

    $stmt = mysqli_prepare(
        $con,
        "INSERT INTO instagramAccounts
            (createdBy, clientId, instagramUserId, facebookPageId, username, accessToken, tokenExpiry, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'connected')"
    );
    mysqli_stmt_bind_param($stmt, 'iisssss', $userId, $clientId, $instagramUserId, $facebookPageId, $username, $encryptedToken, $tokenExpiry);
    mysqli_stmt_execute($stmt);
    $newId = (int)mysqli_insert_id($con);
    mysqli_stmt_close($stmt);

    return $newId;
}

/**
 * Enables Meta webhook delivery for one Instagram Business Account by
 * calling ITS OWN Subscribed Apps edge (POST /{ig-user-id}/subscribed_apps)
 * — not the linked Facebook Page's edge. Confirmed by a real production call
 * during the Phase 11 webhook investigation: POST /{facebookPageId}/
 * subscribed_apps rejected "comments" with "(#100) Param subscribed_fields[0]
 * must be one of {feed, mention, name, picture, ...}" — the Page's edge only
 * accepts Page-object fields; "comments" is an Instagram-object field and
 * belongs on the IG User node's own edge instead. Host/token stay
 * graph.facebook.com + the stored Page Access Token — the same combination
 * already proven working against this exact node
 * (publishInstagramImagePost() etc. already call
 * https://graph.facebook.com/v19.0/{instagramUserId}/media with this same
 * token) — this is the "Instagram API with Facebook Login" architecture,
 * not the separate "Instagram API with Instagram Login" product (which uses
 * graph.instagram.com and a different token obtained via a different OAuth
 * flow Modlus does not implement).
 *
 * This is still a separate, required step from the App Dashboard's
 * app-level webhook subscription (App Dashboard controls what the app is
 * capable of receiving at all; this call is what actually enrolls THIS
 * specific Instagram account to have its events sent to the app). Without
 * it, Meta never delivers webhook events for the account at all, even
 * though the App Dashboard shows the field as "Subscribed".
 *
 * Never throws: called right after a successful OAuth connect, where the
 * account itself already connected successfully. A failure here must not be
 * reported as an OAuth/connection failure — only webhook delivery is
 * affected, publishing/analytics/comments-admin are untouched. Failures are
 * logged (via the existing instagramWriteApiDebugLog()/error_log()
 * convention already used by instagramGraphApiRequest()) so they're
 * developer-visible without needing a new DB column or notification system.
 * Matches the existing "log on failure, silent on success" convention (see
 * instagramGraphApiRequest()) so a log entry itself is what distinguishes
 * failure from success.
 */
function subscribeInstagramAccountWebhooks(string $instagramUserId, string $pageAccessToken): bool
{
    if ($instagramUserId === '' || $pageAccessToken === '') {
        return false;
    }

    try {
        $result = instagramGraphApiRequest(
            'https://graph.facebook.com/v19.0/' . $instagramUserId . '/subscribed_apps',
            [
                'access_token' => $pageAccessToken,
                'subscribed_fields' => 'comments',
            ],
            'POST'
        );

        if (empty($result['success'])) {
            instagramWriteApiDebugLog(
                'Instagram account webhook subscription for account ' . $instagramUserId
                . ' did not confirm success. Response: ' . json_encode($result)
            );

            return false;
        }

        return true;
    } catch (Throwable $e) {
        $message = 'Instagram account webhook subscription failed for account '
            . $instagramUserId . ': ' . $e->getMessage();
        error_log($message);
        instagramWriteApiDebugLog($message);

        return false;
    }
}

function disconnectInstagramAccount(mysqli $con, int $accountId): bool
{
    ensureInstagramAccountsTable($con);

    $stmt = mysqli_prepare(
        $con,
        "UPDATE instagramAccounts SET status = 'disconnected', accessToken = '' WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $accountId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}

/*
|--------------------------------------------------------------------------
| Phase 2: Content Publishing
|--------------------------------------------------------------------------
*/

/**
 * Phase 8 naming rename: the table was `instagramPosts` — now `socialPosts`
 * since it stores Instagram-only, Facebook-only, and Instagram+Facebook
 * rows alike (Phase 6/7). Self-heals safely on an existing production
 * database: if `socialPosts` doesn't exist yet but the old `instagramPosts`
 * does, it's renamed in place (a plain RENAME TABLE — every row, column,
 * index, default, and the primary key are preserved unchanged; nothing is
 * dropped or re-created) before the CREATE TABLE IF NOT EXISTS below ever
 * runs. See database/migrations/2026-08-28-social-posts-naming.sql for the
 * same rename as an explicit, documented migration.
 */
function ensureSocialPostsTable(mysqli $con): void
{
    $socialPostsExists = mysqli_query($con, "SHOW TABLES LIKE 'socialPosts'");

    if (!$socialPostsExists || mysqli_num_rows($socialPostsExists) === 0) {
        $legacyExists = mysqli_query($con, "SHOW TABLES LIKE 'instagramPosts'");

        if ($legacyExists && mysqli_num_rows($legacyExists) > 0) {
            mysqli_query($con, "RENAME TABLE instagramPosts TO socialPosts");
        }
    }

    mysqli_query(
        $con,
        "CREATE TABLE IF NOT EXISTS socialPosts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            createdBy INT UNSIGNED NOT NULL DEFAULT 0,
            clientId INT NULL DEFAULT NULL,
            instagramAccountId INT UNSIGNED NULL DEFAULT NULL,
            mediaType VARCHAR(20) NOT NULL DEFAULT 'image',
            mediaUrl TEXT NOT NULL,
            caption TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            scheduledAt DATETIME NULL DEFAULT NULL,
            publishedAt DATETIME NULL DEFAULT NULL,
            instagramMediaId VARCHAR(64) NOT NULL DEFAULT '',
            errorMessage TEXT NULL,
            createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idxStatusScheduledAt (status, scheduledAt)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    socialPostsEnsureColumn($con, 'clientId', 'INT NULL DEFAULT NULL');
    socialPostsEnsureColumn($con, 'instagramAccountId', 'INT UNSIGNED NULL DEFAULT NULL');
    // Phase 7: which platform(s) this post targets ('instagram', 'facebook',
    // or 'instagram,facebook') and Facebook's own independent result —
    // deliberately separate from status/instagramMediaId/errorMessage so
    // Instagram's and Facebook's outcomes can never be conflated. Default
    // 'instagram'/'not_applicable' means every pre-Phase-7 row is read
    // exactly as it always has been — zero behavior change for old posts.
    socialPostsEnsureColumn($con, 'platforms', "VARCHAR(30) NOT NULL DEFAULT 'instagram'");
    socialPostsEnsureColumn($con, 'facebookStatus', "VARCHAR(20) NOT NULL DEFAULT 'not_applicable'");
    socialPostsEnsureColumn($con, 'facebookPostId', "VARCHAR(64) NOT NULL DEFAULT ''");
    socialPostsEnsureColumn($con, 'facebookErrorMessage', 'TEXT NULL DEFAULT NULL');
}

function socialPostsEnsureColumn(mysqli $con, string $column, string $definition): void
{
    $columnName = mysqli_real_escape_string($con, $column);
    $result = mysqli_query($con, "SHOW COLUMNS FROM socialPosts LIKE '{$columnName}'");

    if ($result && mysqli_num_rows($result) > 0) {
        return;
    }

    mysqli_query($con, "ALTER TABLE socialPosts ADD COLUMN {$column} {$definition}");
}

/**
 * Loads a specific Instagram account by id — the account a given post is
 * actually tied to. This replaces the old "always use whichever account
 * connected most recently" lookup now that accounts are client-scoped:
 * a post must always publish through its own instagramAccountId, never
 * an arbitrary "primary" account.
 */
function getInstagramAccountById(mysqli $con, int $accountId): ?array
{
    ensureInstagramAccountsTable($con);

    $stmt = mysqli_prepare(
        $con,
        "SELECT * FROM instagramAccounts
         WHERE id = ? AND status = 'connected' AND (tokenExpiry IS NULL OR tokenExpiry > NOW())
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'i', $accountId);
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
        'facebookPageId' => (string)$row['facebookPageId'],
        'username' => (string)$row['username'],
        'accessToken' => decryptSecret((string)$row['accessToken']),
    ];
}

/**
 * Confirms an Instagram account belongs to a given client — the core
 * guarantee that prevents cross-client publishing. Used both when saving a
 * post (client picked an account that isn't theirs) and defensively.
 */
function instagramAccountBelongsToClient(mysqli $con, int $accountId, int $clientId): bool
{
    ensureInstagramAccountsTable($con);

    $stmt = mysqli_prepare(
        $con,
        "SELECT id FROM instagramAccounts WHERE id = ? AND clientId = ? LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $accountId, $clientId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    return (bool)$row;
}

/**
 * Confirms a clientId corresponds to a real, active clientMaster row —
 * reuses the same clientMaster + leads join already established by
 * DeliverableEngine::getClients() / calendarEngine.php's getClients().
 */
function instagramClientExists(mysqli $con, int $clientId): bool
{
    $stmt = mysqli_prepare($con, "SELECT id FROM clientMaster WHERE id = ? AND status = 'active' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $clientId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    return (bool)$row;
}

/**
 * Human-readable client label for audit log / redirect messages —
 * "Full Name (CLIENT-CODE)", matching the display format already used by
 * the existing client selector dropdowns (client-deliverable.js, calendar.php).
 */
function getInstagramClientLabel(mysqli $con, ?int $clientId): string
{
    if ($clientId === null || $clientId <= 0) {
        return 'Unassigned';
    }

    $stmt = mysqli_prepare(
        $con,
        "SELECT cm.clientCode, l.fullName
         FROM clientMaster cm
         INNER JOIN leads l ON l.id = cm.leadId
         WHERE cm.id = ?
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'i', $clientId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if (!$row) {
        return 'Client #' . $clientId;
    }

    return (string)$row['fullName'] . ' (' . (string)$row['clientCode'] . ')';
}

/*
|--------------------------------------------------------------------------
| Media Upload
|--------------------------------------------------------------------------
| Mirrors the finfo-validated, collision-safe filename pattern used by
| LeadEngine::uploadFile() (includes/leadEngine.php) — real content-type
| detection rather than trusting the extension, unique filenames.
*/

function saveInstagramMediaFile(array $file, string $mediaCategory = 'image'): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['success' => false, 'path' => '', 'error' => 'File upload failed.'];
    }

    // Meta's Content Publishing API only accepts JPEG for image_url-based
    // posts (image and carousel) — PNG uploads would pass our own
    // validation but fail silently at Meta's end.
    $isVideo = $mediaCategory === 'video';
    $allowedTypes = $isVideo
        ? ['video/mp4' => 'mp4', 'video/quicktime' => 'mov']
        : ['image/jpeg' => 'jpg'];
    $maxBytes = $isVideo ? 100 * 1024 * 1024 : 8 * 1024 * 1024;

    if ((int)($file['size'] ?? 0) > $maxBytes) {
        return [
            'success' => false,
            'path' => '',
            'error' => $isVideo ? 'Video must be 100 MB or smaller.' : 'Image must be 8 MB or smaller.',
        ];
    }

    $tmpName = (string)($file['tmp_name'] ?? '');

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return ['success' => false, 'path' => '', 'error' => 'Invalid upload.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $tmpName) : false;

    if ($finfo) {
        finfo_close($finfo);
    }

    if ($mimeType === false || !isset($allowedTypes[$mimeType])) {
        return [
            'success' => false,
            'path' => '',
            'error' => $isVideo ? 'Video must be MP4 or MOV.' : 'Image must be a JPEG (.jpg) file.',
        ];
    }

    $uploadDir = dirname(__DIR__) . '/uploads/instagram-posts/';

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        return ['success' => false, 'path' => '', 'error' => 'Unable to create upload directory.'];
    }

    $filename = uniqid('ig_', true) . '_' . time() . '.' . $allowedTypes[$mimeType];
    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($tmpName, $destination)) {
        return ['success' => false, 'path' => '', 'error' => 'Unable to save uploaded file.'];
    }

    return ['success' => true, 'path' => 'uploads/instagram-posts/' . $filename, 'error' => ''];
}

function saveInstagramMediaFiles(array $filesField, string $mediaCategory = 'image', int $maxFiles = 10): array
{
    $paths = [];
    $errors = [];

    if (!isset($filesField['name']) || !is_array($filesField['name'])) {
        return ['paths' => $paths, 'errors' => ['No files were uploaded.']];
    }

    $count = min(count($filesField['name']), $maxFiles);

    for ($i = 0; $i < $count; $i++) {
        if (($filesField['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $singleFile = [
            'name' => $filesField['name'][$i],
            'type' => $filesField['type'][$i],
            'tmp_name' => $filesField['tmp_name'][$i],
            'error' => $filesField['error'][$i],
            'size' => $filesField['size'][$i],
        ];

        $result = saveInstagramMediaFile($singleFile, $mediaCategory);

        if ($result['success']) {
            $paths[] = $result['path'];
        } else {
            $errors[] = $result['error'];
        }
    }

    return ['paths' => $paths, 'errors' => $errors];
}

/*
|--------------------------------------------------------------------------
| Post CRUD
|--------------------------------------------------------------------------
*/

function encodeSocialPostMediaPaths(array $paths): string
{
    return (string)json_encode(array_values($paths));
}

function decodeSocialPostMediaPaths(?string $mediaUrl): array
{
    $decoded = json_decode((string)$mediaUrl, true);

    return is_array($decoded) ? $decoded : array_values(array_filter([(string)$mediaUrl]));
}

function socialPostMediaAbsoluteUrls(array $relativePaths): array
{
    return array_map(
        static fn(string $path): string => BASE_URL . '/' . ltrim($path, '/'),
        $relativePaths
    );
}

function getSocialPosts(mysqli $con, string $status = '', ?int $clientId = null): array
{
    ensureSocialPostsTable($con);

    $where = [];
    $types = '';
    $params = [];

    if ($status !== '') {
        $where[] = 'status = ?';
        $types .= 's';
        $params[] = $status;
    }

    if ($clientId !== null) {
        $where[] = 'clientId = ?';
        $types .= 'i';
        $params[] = $clientId;
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $stmt = mysqli_prepare(
        $con,
        "SELECT * FROM socialPosts {$whereSql} ORDER BY COALESCE(scheduledAt, createdAt) DESC, id DESC"
    );

    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $posts = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $row['mediaUrls'] = socialPostMediaAbsoluteUrls(decodeSocialPostMediaPaths($row['mediaUrl']));
        $posts[] = $row;
    }

    mysqli_stmt_close($stmt);

    return $posts;
}

function getSocialPostById(mysqli $con, int $id): ?array
{
    ensureSocialPostsTable($con);

    $stmt = mysqli_prepare($con, "SELECT * FROM socialPosts WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if (!$row) {
        return null;
    }

    $row['mediaUrls'] = socialPostMediaAbsoluteUrls(decodeSocialPostMediaPaths($row['mediaUrl']));

    return $row;
}

function saveSocialPost(mysqli $con, array $data, int $userId): int
{
    ensureSocialPostsTable($con);

    $clientId = (int)($data['clientId'] ?? 0);
    $instagramAccountId = (int)($data['instagramAccountId'] ?? 0);
    $mediaType = (string)($data['mediaType'] ?? 'image');
    $mediaPathsJson = encodeSocialPostMediaPaths($data['mediaPaths'] ?? []);
    $caption = (string)($data['caption'] ?? '');
    $status = (string)($data['status'] ?? 'draft');
    $scheduledAt = $data['scheduledAt'] ?? null;
    $postId = (int)($data['id'] ?? 0);

    // Phase 7: 'instagram' (the only value that has ever existed) is the
    // default whenever a caller doesn't specify platforms — every existing
    // caller/behavior is unaffected.
    $platformsInput = (array)($data['platforms'] ?? ['instagram']);
    $platforms = array_values(array_unique(array_intersect(
        array_map('strtolower', array_map('trim', $platformsInput)),
        ['instagram', 'facebook']
    )));

    if (empty($platforms)) {
        $platforms = ['instagram'];
    }

    $platformsValue = implode(',', $platforms);
    $facebookStatus = in_array('facebook', $platforms, true) ? 'pending' : 'not_applicable';

    if ($postId > 0) {
        $stmt = mysqli_prepare(
            $con,
            "UPDATE socialPosts
             SET clientId = ?, instagramAccountId = ?, mediaType = ?, mediaUrl = ?, caption = ?, status = ?, scheduledAt = ?,
                 errorMessage = '', platforms = ?, facebookStatus = ?, facebookPostId = '', facebookErrorMessage = NULL
             WHERE id = ?"
        );
        mysqli_stmt_bind_param(
            $stmt,
            'iisssssssi',
            $clientId,
            $instagramAccountId,
            $mediaType,
            $mediaPathsJson,
            $caption,
            $status,
            $scheduledAt,
            $platformsValue,
            $facebookStatus,
            $postId
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $postId;
    }

    $stmt = mysqli_prepare(
        $con,
        "INSERT INTO socialPosts
            (createdBy, clientId, instagramAccountId, mediaType, mediaUrl, caption, status, scheduledAt, platforms, facebookStatus)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param(
        $stmt,
        'iiisssssss',
        $userId,
        $clientId,
        $instagramAccountId,
        $mediaType,
        $mediaPathsJson,
        $caption,
        $status,
        $scheduledAt,
        $platformsValue,
        $facebookStatus
    );
    mysqli_stmt_execute($stmt);
    $newId = (int)mysqli_insert_id($con);
    mysqli_stmt_close($stmt);

    return $newId;
}

function deleteSocialPostRecord(mysqli $con, int $id): bool
{
    $post = getSocialPostById($con, $id);

    if (!$post) {
        return false;
    }

    $stmt = mysqli_prepare($con, "DELETE FROM socialPosts WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        foreach (decodeSocialPostMediaPaths($post['mediaUrl']) as $relativePath) {
            $fullPath = dirname(__DIR__) . '/' . ltrim((string)$relativePath, '/');

            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
        }
    }

    return $ok;
}

function getDueSocialPosts(mysqli $con, int $limit = 20): array
{
    ensureSocialPostsTable($con);

    $stmt = mysqli_prepare(
        $con,
        "SELECT * FROM socialPosts
         WHERE status = 'scheduled' AND scheduledAt <= NOW()
         ORDER BY scheduledAt ASC
         LIMIT ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $posts = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $posts[] = $row;
    }

    mysqli_stmt_close($stmt);

    return $posts;
}

function getSocialPostsByStatus(mysqli $con, string $status, int $limit = 20, ?string $mediaType = null): array
{
    ensureSocialPostsTable($con);

    if ($mediaType !== null) {
        $stmt = mysqli_prepare(
            $con,
            "SELECT * FROM socialPosts WHERE status = ? AND mediaType = ? ORDER BY updatedAt ASC LIMIT ?"
        );
        mysqli_stmt_bind_param($stmt, 'ssi', $status, $mediaType, $limit);
    } else {
        $stmt = mysqli_prepare(
            $con,
            "SELECT * FROM socialPosts WHERE status = ? ORDER BY updatedAt ASC LIMIT ?"
        );
        mysqli_stmt_bind_param($stmt, 'si', $status, $limit);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $posts = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $posts[] = $row;
    }

    mysqli_stmt_close($stmt);

    return $posts;
}

/**
 * Phase 7 recovery scan: image/carousel posts stuck in 'publishing' across
 * cron runs. Reels are deliberately excluded — they legitimately sit in
 * 'publishing' while Meta processes them asynchronously (see
 * getSocialPostsByStatus($con, 'publishing', 20, 'reel') in the
 * scheduler's existing Phase A) — an image/carousel post has no such
 * legitimate async state, so finding one here always means the previous
 * cron run's process died mid-publish, never a normal in-progress state.
 */
function getStuckSocialPosts(mysqli $con, int $limit = 20): array
{
    ensureSocialPostsTable($con);

    $stmt = mysqli_prepare(
        $con,
        "SELECT * FROM socialPosts
         WHERE status = 'publishing' AND mediaType IN ('image', 'carousel')
         ORDER BY updatedAt ASC
         LIMIT ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $posts = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $posts[] = $row;
    }

    mysqli_stmt_close($stmt);

    return $posts;
}

/**
 * Pure decision function — no DB access, no Meta call. Given a post row
 * (fresh or recovered), decides which requested platforms still need a
 * publish attempt versus which are already recorded-done. "Done" is
 * determined solely from a persisted result (instagramMediaId /
 * facebookPostId), never from the row's overall `status` — this is what
 * makes recovery safe: a platform whose success was already written to the
 * database is never re-attempted, regardless of why the row was revisited.
 */
function socialScheduledRecoveryPlan(array $post): array
{
    $platforms = array_values(array_filter(array_map('trim', explode(',', (string)($post['platforms'] ?? 'instagram')))));

    if (empty($platforms)) {
        $platforms = ['instagram'];
    }

    $instagramSelected = in_array('instagram', $platforms, true);
    $facebookSelected = in_array('facebook', $platforms, true);

    $instagramDone = $instagramSelected && trim((string)($post['instagramMediaId'] ?? '')) !== '';
    $facebookDone = $facebookSelected && trim((string)($post['facebookPostId'] ?? '')) !== '';

    return [
        'platforms' => $platforms,
        'instagramSelected' => $instagramSelected,
        'facebookSelected' => $facebookSelected,
        'instagramDone' => $instagramDone,
        'facebookDone' => $facebookDone,
        'instagramNeeded' => $instagramSelected && !$instagramDone,
        'facebookNeeded' => $facebookSelected && !$facebookDone,
        'alreadyComplete' => ($instagramSelected === $instagramDone) && ($facebookSelected === $facebookDone),
    ];
}

function markFacebookScheduledPublished(mysqli $con, int $id, string $postId): void
{
    $stmt = mysqli_prepare(
        $con,
        "UPDATE socialPosts SET facebookStatus = 'published', facebookPostId = ?, facebookErrorMessage = NULL WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'si', $postId, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function markFacebookScheduledFailed(mysqli $con, int $id, string $message): void
{
    $stmt = mysqli_prepare(
        $con,
        "UPDATE socialPosts SET facebookStatus = 'failed', facebookErrorMessage = ? WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'si', $message, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function setSocialPostOverallStatus(mysqli $con, int $id, string $status): void
{
    $stmt = mysqli_prepare($con, "UPDATE socialPosts SET status = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $status, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Records Instagram's own OWN result fields (instagramMediaId/publishedAt/
 * errorMessage) for a multi-platform scheduled post — deliberately does
 * NOT touch `status`, unlike markSocialPostPublished()/
 * markSocialPostFailed() (which remain unchanged and are still used
 * as-is by the legacy single-platform scheduler path and Reel/carousel
 * handling). `status` for a multi-platform post is only ever decided once,
 * explicitly, by finalizeSocialScheduledPost() below — never as a side
 * effect of recording one platform's own result — so a still-unresolved
 * sibling platform can never be short-circuited by this one's outcome.
 */
function recordInstagramPlatformResult(mysqli $con, int $id, bool $success, string $mediaId, string $errorMessage): void
{
    if ($success) {
        $stmt = mysqli_prepare(
            $con,
            "UPDATE socialPosts SET instagramMediaId = ?, publishedAt = NOW(), errorMessage = '' WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'si', $mediaId, $id);
    } else {
        $stmt = mysqli_prepare($con, "UPDATE socialPosts SET errorMessage = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $errorMessage, $id);
    }

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * publishSocialPost() returns an EMPTY 'platforms' array when its own
 * top-level validation rejects the whole request before dispatching to any
 * individual platform (e.g. missing Facebook Page, invalid account) — a
 * fundamentally different situation from a platform simply not being
 * attempted because recovery already found it done.
 * finalizeSocialScheduledPost() must never confuse the two (an empty
 * 'platforms' array would otherwise be silently read as "trust the
 * existing recorded state", hiding a real, actionable failure). This
 * normalizes a validation-level rejection into an explicit failure entry
 * for every platform that was actually intended to be attempted this run.
 */
function normalizeSocialEngineResult(array $engineResult, array $platformsAttempted): array
{
    if (!empty($engineResult['platforms'])) {
        return $engineResult['platforms'];
    }

    $normalized = [];

    foreach ($platformsAttempted as $platform) {
        $normalized[$platform] = [
            'success' => false,
            'postId' => null,
            'message' => (string)($engineResult['message'] ?? 'Publishing failed.'),
        ];
    }

    return $normalized;
}

/**
 * Records a fresh publishSocialPost() attempt's results onto a scheduled
 * post row, then computes the row's overall status. $engineResultPlatforms
 * is $result['platforms'] — it may be MISSING a platform's key entirely
 * when that platform wasn't attempted this run (already done, per
 * socialScheduledRecoveryPlan()); in that case its already-recorded state
 * is trusted instead of being treated as a failure.
 *
 * Two-phase by design: phase 1 resolves each platform's own outcome and
 * writes only that platform's own fields (never `status`); phase 2 decides
 * `status` exactly once, from both platforms together. Splitting these is
 * what makes a still-unresolved sibling platform safe — recording one
 * platform's success can never prematurely flip the shared `status` to a
 * terminal value while the other platform hasn't been decided yet.
 *
 * A platform result carrying 'transient' => true (see
 * SocialPostEngine.php's InstagramTransientApiException handling) is a
 * network-level blip, not Meta rejecting the post — its state is left
 * completely untouched (not marked failed) so the next scheduler run's
 * recovery scan retries exactly that platform. When any requested platform
 * is left unresolved this way, `status` is deliberately never written in
 * this call — it stays 'publishing' so getStuckSocialPosts() picks the row
 * back up, the same recovery path a crash uses.
 */
function finalizeSocialScheduledPost(mysqli $con, array $post, array $engineResultPlatforms): void
{
    $postId = (int)$post['id'];
    $plan = socialScheduledRecoveryPlan($post);

    $instagramOutcome = null;
    $instagramUnresolved = false;

    if ($plan['instagramSelected']) {
        if (isset($engineResultPlatforms['instagram'])) {
            $platformResult = $engineResultPlatforms['instagram'];

            if (!empty($platformResult['transient'])) {
                $instagramUnresolved = true;
            } else {
                $instagramOutcome = !empty($platformResult['success']);
                recordInstagramPlatformResult(
                    $con,
                    $postId,
                    $instagramOutcome,
                    (string)($platformResult['postId'] ?? ''),
                    (string)($platformResult['message'] ?? 'Instagram publishing failed.')
                );
            }
        } else {
            // Not attempted this run — recovery already found it done;
            // nothing new to write for it.
            $instagramOutcome = $plan['instagramDone'];
        }
    }

    $facebookOutcome = null;
    $facebookUnresolved = false;

    if ($plan['facebookSelected']) {
        if (isset($engineResultPlatforms['facebook'])) {
            $platformResult = $engineResultPlatforms['facebook'];

            if (!empty($platformResult['transient'])) {
                $facebookUnresolved = true;
            } else {
                $facebookOutcome = !empty($platformResult['success']);

                if ($facebookOutcome) {
                    markFacebookScheduledPublished($con, $postId, (string)($platformResult['postId'] ?? ''));
                } else {
                    markFacebookScheduledFailed($con, $postId, (string)($platformResult['message'] ?? 'Facebook publishing failed.'));
                }
            }
        } else {
            $facebookOutcome = $plan['facebookDone'];
        }
    }

    if ($instagramUnresolved || $facebookUnresolved) {
        // Leave `status` completely untouched here (still 'publishing').
        // Any platform result recorded above (e.g. Instagram just
        // succeeded while Facebook is the one still unresolved) is already
        // safely persisted via recordInstagramPlatformResult()/
        // markFacebookScheduledPublished() without needing `status` to move.
        return;
    }

    $outcomes = array_values(array_filter([$instagramOutcome, $facebookOutcome], static fn($v) => $v !== null));

    if (empty($outcomes)) {
        return;
    }

    $successCount = count(array_filter($outcomes, static fn($v) => $v === true));
    $totalCount = count($outcomes);

    if ($successCount === $totalCount) {
        setSocialPostOverallStatus($con, $postId, 'published');
    } elseif ($successCount === 0) {
        setSocialPostOverallStatus($con, $postId, 'failed');
    } else {
        setSocialPostOverallStatus($con, $postId, 'partial');
    }
}

function markSocialPostPublishing(mysqli $con, int $id): void
{
    $stmt = mysqli_prepare($con, "UPDATE socialPosts SET status = 'publishing' WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function updateInstagramPostContainerId(mysqli $con, int $id, string $containerId): void
{
    $stmt = mysqli_prepare($con, "UPDATE socialPosts SET instagramMediaId = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $containerId, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function markSocialPostPublished(mysqli $con, int $id, string $mediaId): void
{
    $stmt = mysqli_prepare(
        $con,
        "UPDATE socialPosts
         SET status = 'published', instagramMediaId = ?, publishedAt = NOW(), errorMessage = ''
         WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'si', $mediaId, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function markSocialPostFailed(mysqli $con, int $id, string $message): void
{
    $stmt = mysqli_prepare(
        $con,
        "UPDATE socialPosts SET status = 'failed', errorMessage = ? WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'si', $message, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Requeues a post that was mid-publish when a transient (network-level)
 * error occurred, so the next scheduler run retries it instead of it being
 * stuck in 'publishing' or wrongly marked 'failed' for a blip that wasn't
 * Meta rejecting the post.
 */
function revertSocialPostToScheduled(mysqli $con, int $id): void
{
    $stmt = mysqli_prepare($con, "UPDATE socialPosts SET status = 'scheduled' WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/*
|--------------------------------------------------------------------------
| Meta Graph API Publishing
|--------------------------------------------------------------------------
| All requests route through the single instagramGraphApiRequest() curl
| wrapper defined below — no separate curl implementations per media type.
*/

function publishInstagramContainer(array $account, string $containerId): string
{
    $publish = instagramGraphApiRequest(
        'https://graph.facebook.com/v19.0/' . $account['instagramUserId'] . '/media_publish',
        [
            'creation_id' => $containerId,
            'access_token' => $account['accessToken'],
        ]
    );

    if (empty($publish['id'])) {
        throw new RuntimeException('Meta did not return a published media id.');
    }

    return (string)$publish['id'];
}

function getInstagramContainerStatus(array $account, string $containerId): string
{
    $status = instagramGraphApiRequest(
        'https://graph.facebook.com/v19.0/' . $containerId,
        [
            'fields' => 'status_code',
            'access_token' => $account['accessToken'],
        ],
        'GET'
    );

    return (string)($status['status_code'] ?? 'UNKNOWN');
}

/*
|--------------------------------------------------------------------------
| Production bug fix (Phase 7.x): Instagram image container readiness
|--------------------------------------------------------------------------
| Production evidence: a scheduled Instagram+Facebook post had Facebook
| succeed while Instagram's media_publish call failed with HTTP 400,
| error_subcode 2207027 ("The media is not ready to be published. Please
| wait a moment.") — Meta had accepted the container create call but
| hadn't finished processing it yet when media_publish was called
| immediately afterward. Fixed by polling the EXISTING
| getInstagramContainerStatus() (already used by the Reel finalization
| phase — not duplicated) between container creation and media_publish,
| bounded so a container that never becomes ready doesn't hang the
| publish call indefinitely.
*/
const INSTAGRAM_CONTAINER_POLL_INTERVAL_SECONDS = 2;
const INSTAGRAM_CONTAINER_POLL_MAX_SECONDS = 30;

/**
 * Blocks (bounded) until an Instagram media container reports FINISHED, or
 * throws. Never busy-loops — each iteration sleeps
 * INSTAGRAM_CONTAINER_POLL_INTERVAL_SECONDS; the whole wait is capped at
 * INSTAGRAM_CONTAINER_POLL_MAX_SECONDS.
 *
 * - FINISHED                  -> returns normally, caller proceeds to media_publish.
 * - ERROR / EXPIRED           -> throws RuntimeException (permanent — Meta
 *                                could not process the container at all).
 * - IN_PROGRESS/anything else -> keeps polling until the deadline.
 * - deadline reached without FINISHED/ERROR -> throws
 *   InstagramTransientApiException — the SAME exception class every other
 *   network-level/retryable failure in this module already uses, so both
 *   existing callers (SocialPostEngine.php's per-platform catch and the
 *   scheduler's legacy try/catch) already handle it correctly as
 *   retryable without any further changes.
 *
 * Logs each poll attempt (creation_id + status) via the existing
 * instagramWriteApiDebugLog() diagnostic log — never the access token,
 * which this function never even receives outside $account['accessToken']
 * passed straight through to getInstagramContainerStatus().
 */
function waitForInstagramContainerReady(array $account, string $containerId): void
{
    $deadline = time() + INSTAGRAM_CONTAINER_POLL_MAX_SECONDS;

    while (true) {
        $statusCode = getInstagramContainerStatus($account, $containerId);

        instagramWriteApiDebugLog(
            'Instagram container readiness check' . "\n"
            . 'creation_id: ' . $containerId . "\n"
            . 'status_code: ' . $statusCode
        );

        if ($statusCode === 'FINISHED') {
            return;
        }

        if ($statusCode === 'ERROR' || $statusCode === 'EXPIRED') {
            throw new RuntimeException('Meta reported the Instagram media container could not be processed (status: ' . $statusCode . ').');
        }

        if (time() >= $deadline) {
            throw new InstagramTransientApiException(
                'Instagram media container was not ready within ' . INSTAGRAM_CONTAINER_POLL_MAX_SECONDS . ' seconds (last status: ' . $statusCode . ').'
            );
        }

        sleep(INSTAGRAM_CONTAINER_POLL_INTERVAL_SECONDS);
    }
}

function publishInstagramImagePost(array $account, string $mediaUrl, string $caption): array
{
    $container = instagramGraphApiRequest(
        'https://graph.facebook.com/v19.0/' . $account['instagramUserId'] . '/media',
        [
            'image_url' => $mediaUrl,
            'caption' => $caption,
            'access_token' => $account['accessToken'],
        ]
    );

    if (empty($container['id'])) {
        throw new RuntimeException('Meta did not return a media container id.');
    }

    $containerId = (string)$container['id'];
    waitForInstagramContainerReady($account, $containerId);

    return ['instagramMediaId' => publishInstagramContainer($account, $containerId)];
}

function publishInstagramCarouselPost(array $account, array $mediaUrls, string $caption): array
{
    if (count($mediaUrls) < 2) {
        throw new RuntimeException('A carousel post needs at least 2 images.');
    }

    $childIds = [];

    foreach ($mediaUrls as $url) {
        $item = instagramGraphApiRequest(
            'https://graph.facebook.com/v19.0/' . $account['instagramUserId'] . '/media',
            [
                'image_url' => $url,
                'is_carousel_item' => 'true',
                'access_token' => $account['accessToken'],
            ]
        );

        if (empty($item['id'])) {
            throw new RuntimeException('Meta did not return a carousel item container id.');
        }

        $childIds[] = $item['id'];
    }

    $parent = instagramGraphApiRequest(
        'https://graph.facebook.com/v19.0/' . $account['instagramUserId'] . '/media',
        [
            'media_type' => 'CAROUSEL',
            'caption' => $caption,
            'children' => implode(',', $childIds),
            'access_token' => $account['accessToken'],
        ]
    );

    if (empty($parent['id'])) {
        throw new RuntimeException('Meta did not return a carousel container id.');
    }

    return ['instagramMediaId' => publishInstagramContainer($account, (string)$parent['id'])];
}

/**
 * Reels are processed asynchronously by Meta. This starts the container and
 * publishes immediately if it's already FINISHED; otherwise it returns a
 * 'pending' status with the container id so the caller (the cron scheduler)
 * can finalize it on a later run via getInstagramContainerStatus() +
 * publishInstagramContainer() — no blocking sleep loops.
 */
function publishInstagramVideoPost(array $account, string $videoUrl, string $caption): array
{
    $container = instagramGraphApiRequest(
        'https://graph.facebook.com/v19.0/' . $account['instagramUserId'] . '/media',
        [
            'video_url' => $videoUrl,
            'caption' => $caption,
            'media_type' => 'REELS',
            'access_token' => $account['accessToken'],
        ]
    );

    if (empty($container['id'])) {
        throw new RuntimeException('Meta did not return a video container id.');
    }

    $containerId = (string)$container['id'];
    $statusCode = getInstagramContainerStatus($account, $containerId);

    if ($statusCode === 'FINISHED') {
        return ['status' => 'published', 'instagramMediaId' => publishInstagramContainer($account, $containerId)];
    }

    if ($statusCode === 'ERROR' || $statusCode === 'EXPIRED') {
        throw new RuntimeException('Meta reported video processing status: ' . $statusCode);
    }

    return ['status' => 'pending', 'instagramMediaId' => $containerId];
}

function instagramGraphApiRequest(string $url, array $params, string $method = 'POST'): array
{
    $ch = curl_init();

    if (strtoupper($method) === 'GET') {
        curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
    } else {
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    // Explicit CA bundle, not cURL's auto-discovered default (same root
    // cause/fix as AnthropicCaptionGenerator::buildClient() — some
    // environments have no curl.cainfo configured, so every outbound HTTPS
    // call fails TLS verification with "unable to get local issuer
    // certificate" before ever reaching Meta). Safe on a properly
    // configured server too — it just points at a valid bundle.
    if (class_exists(\Composer\CaBundle\CaBundle::class)) {
        curl_setopt($ch, CURLOPT_CAINFO, \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath());
    }

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        // Diagnostic-only (temporary): mirrors into logs/instagram-api.log
        // because error_log()'s destination isn't reachable on this
        // Hostinger deployment. See instagramWriteApiDebugLog().
        instagramWriteApiDebugLog(
            'Instagram Graph API network error' . "\n"
            . 'URL: ' . $url . "\n"
            . 'Method: ' . strtoupper($method) . "\n"
            . 'HTTP Status: ' . $httpStatus . "\n"
            . 'Params: ' . json_encode(instagramSanitizeParamsForLog($params)) . "\n"
            . 'cURL error: ' . $curlError
        );

        // Network-level failure (DNS, timeout, connection reset) — treated as
        // transient so callers can retry rather than permanently failing a post.
        throw new InstagramTransientApiException('Meta API request failed: ' . $curlError);
    }

    $decoded = json_decode((string)$response, true);

    if (!is_array($decoded)) {
        // Response body only (no request params echoed back by Meta here),
        // so nothing credential-bearing to redact from it.
        instagramWriteApiDebugLog(
            'Instagram Graph API invalid JSON response' . "\n"
            . 'URL: ' . $url . "\n"
            . 'Method: ' . strtoupper($method) . "\n"
            . 'HTTP Status: ' . $httpStatus . "\n"
            . 'Params: ' . json_encode(instagramSanitizeParamsForLog($params)) . "\n"
            . 'Response body: ' . (string)$response
        );

        throw new RuntimeException('Meta API returned an invalid response.');
    }

    if (isset($decoded['error'])) {
        $errorInfo = is_array($decoded['error']) ? $decoded['error'] : [];
        $message = (string)($errorInfo['message'] ?? '');
        $code = (int)($errorInfo['code'] ?? 0);

        // Diagnostic-only: log the complete Meta error response server-side
        // so a rejected media publish (e.g. "Only photo or video can be
        // accepted as media type") can be root-caused from the exact
        // message/code/subcode/fbtrace_id Meta sent, not just the short
        // message string the thrown exception carries. image_url/video_url
        // are intentionally left unredacted — that's exactly what's being
        // debugged; only credential-bearing params are redacted.
        $diagnosticEntry = 'Instagram Graph API error' . "\n"
            . 'URL: ' . $url . "\n"
            . 'Method: ' . strtoupper($method) . "\n"
            . 'HTTP Status: ' . $httpStatus . "\n"
            . 'Params: ' . json_encode(instagramSanitizeParamsForLog($params)) . "\n"
            . 'Meta error: ' . json_encode($errorInfo);

        error_log($diagnosticEntry);
        instagramWriteApiDebugLog($diagnosticEntry);

        throw new RuntimeException($message !== '' ? $message : 'Unknown Meta API error.', $code);
    }

    return $decoded;
}

/**
 * Diagnostic-only (temporary): redacts credential-bearing keys from a Graph
 * API params array before it's written to logs/instagram-api.log. Shared by
 * all three failure branches in instagramGraphApiRequest() below.
 */
function instagramSanitizeParamsForLog(array $params): array
{
    $sensitiveKeys = ['access_token', 'client_secret', 'code', 'fb_exchange_token'];
    $sanitized = $params;

    foreach ($sensitiveKeys as $sensitiveKey) {
        if (array_key_exists($sensitiveKey, $sanitized)) {
            $sanitized[$sensitiveKey] = '[REDACTED]';
        }
    }

    return $sanitized;
}

/**
 * Diagnostic-only (temporary): appends one entry to logs/instagram-api.log,
 * used only by instagramGraphApiRequest()'s failure branches so the exact
 * Meta API error can be inspected on hosts (e.g. Hostinger) where
 * error_log()'s destination isn't reachable. Never called on success.
 */
function instagramWriteApiDebugLog(string $entry): void
{
    $logDir = dirname(__DIR__) . '/logs';

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    if (!is_dir($logDir)) {
        return;
    }

    $logFile = $logDir . '/instagram-api.log';
    $line = '[' . date('Y-m-d H:i:s') . ']' . "\n" . $entry . "\n\n";

    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Thrown for network-level failures talking to the Meta API (as opposed to
 * an error Meta itself returned). Callers should retry rather than fail.
 */
class InstagramTransientApiException extends RuntimeException
{
}

/**
 * Code 190 is Meta's standard "invalid/expired OAuth access token" error.
 */
function isInstagramAuthError(Throwable $e): bool
{
    return (int)$e->getCode() === 190;
}
