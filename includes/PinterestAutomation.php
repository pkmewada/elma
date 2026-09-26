<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Crypto.php';
// instagramClientExists() and getInstagramClientLabel() are generic
// clientMaster lookups (no Instagram-specific behavior) already reused
// as-is by LinkedInAutomation.php — reused here too rather than
// duplicating them under a Pinterest-specific name.
require_once __DIR__ . '/InstagramAutomation.php';

/*
|--------------------------------------------------------------------------
| Phase 13: Pinterest Integration Foundation
|--------------------------------------------------------------------------
| Mirrors the LinkedIn foundation module's conventions (settings table,
| encrypted-secret storage, client-scoped account table, a dedicated
| per-platform HTTP transport) — see
| docs/INSTAGRAM_AUTOMATION_PRODUCTION_STATE.md §25/§26. Pinterest is a
| wholly separate vendor from both Meta and LinkedIn: the token endpoint
| authenticates the app via HTTP Basic Auth (base64 client_id:client_secret)
| rather than LinkedIn's POST-body client_secret or Meta's query-string
| access_token, and Pinterest access tokens genuinely expire (30 days) with
| a real, rotating refresh_token — unlike LinkedIn's foundation-phase token,
| which had no refresh flow implemented. Pinterest gets its own transport
| function here rather than reusing linkedinApiRequest() or
| instagramGraphApiRequest() for that reason.
|
| Foundation phase only: connect, identify user, discover boards, select
| one, store it against a Modlus client, and refresh tokens. No publishing.
*/

// Pinterest's stable major API version path segment. Not centralized into
// a constant used elsewhere on purpose — same rationale as the hardcoded
// Meta Graph API version and LinkedIn's LINKEDIN_API_VERSION: changing it
// is a deliberate, explicit action, not something to abstract away.
const PINTEREST_API_VERSION = 'v5';
const PINTEREST_API_BASE = 'https://api.pinterest.com/v5';
const PINTEREST_OAUTH_AUTHORIZE_URL = 'https://www.pinterest.com/oauth/';
const PINTEREST_OAUTH_TOKEN_URL = 'https://api.pinterest.com/v5/oauth/token';

/*
|--------------------------------------------------------------------------
| Settings (Pinterest App Client ID / Client Secret) — one active row,
| platform-wide, same shape as linkedinSettings/instagramSettings.
|--------------------------------------------------------------------------
*/

function ensurePinterestSettingsTable(mysqli $con): void
{
    mysqli_query(
        $con,
        "CREATE TABLE IF NOT EXISTS pinterestSettings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            pinterestClientId VARCHAR(191) NOT NULL DEFAULT '',
            pinterestClientSecret TEXT NOT NULL,
            redirectUrl VARCHAR(255) NOT NULL DEFAULT '',
            createdBy INT UNSIGNED NOT NULL DEFAULT 0,
            createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function getPinterestSettingsRow(mysqli $con): ?array
{
    ensurePinterestSettingsTable($con);

    $result = mysqli_query($con, "SELECT * FROM pinterestSettings ORDER BY id DESC LIMIT 1");

    return $result ? (mysqli_fetch_assoc($result) ?: null) : null;
}

function getPinterestSettings(mysqli $con): array
{
    $row = getPinterestSettingsRow($con);

    if (!$row) {
        return ['pinterestClientId' => '', 'redirectUrl' => '', 'hasClientSecret' => false];
    }

    return [
        'pinterestClientId' => (string)$row['pinterestClientId'],
        'redirectUrl' => (string)$row['redirectUrl'],
        'hasClientSecret' => trim((string)$row['pinterestClientSecret']) !== '',
    ];
}

function getPinterestSettingsForOAuth(mysqli $con): ?array
{
    $row = getPinterestSettingsRow($con);

    if (!$row || trim((string)$row['pinterestClientId']) === '' || trim((string)$row['pinterestClientSecret']) === '') {
        return null;
    }

    return [
        'pinterestClientId' => (string)$row['pinterestClientId'],
        'pinterestClientSecret' => decryptSecret((string)$row['pinterestClientSecret']),
        'redirectUrl' => (string)$row['redirectUrl'],
    ];
}

function savePinterestSettings(mysqli $con, array $settings, int $userId): bool
{
    ensurePinterestSettingsTable($con);

    $pinterestClientId = trim((string)($settings['pinterestClientId'] ?? ''));
    $pinterestClientSecret = trim((string)($settings['pinterestClientSecret'] ?? ''));
    $redirectUrl = trim((string)($settings['redirectUrl'] ?? ''));

    $existing = getPinterestSettingsRow($con);

    $encryptedSecret = $pinterestClientSecret !== ''
        ? encryptSecret($pinterestClientSecret)
        : (string)($existing['pinterestClientSecret'] ?? '');

    if ($existing) {
        $stmt = mysqli_prepare(
            $con,
            "UPDATE pinterestSettings
             SET pinterestClientId = ?, pinterestClientSecret = ?, redirectUrl = ?
             WHERE id = ?"
        );

        if (!$stmt) {
            return false;
        }

        $id = (int)$existing['id'];
        mysqli_stmt_bind_param($stmt, 'sssi', $pinterestClientId, $encryptedSecret, $redirectUrl, $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $ok;
    }

    $stmt = mysqli_prepare(
        $con,
        "INSERT INTO pinterestSettings (pinterestClientId, pinterestClientSecret, redirectUrl, createdBy)
         VALUES (?, ?, ?, ?)"
    );

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'sssi', $pinterestClientId, $encryptedSecret, $redirectUrl, $userId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}

/*
|--------------------------------------------------------------------------
| Accounts — one row per connected Pinterest user, scoped to exactly one
| Modlus client (never "latest"/"first"/"global"/"primary" — see
| docs/INSTAGRAM_AUTOMATION_PRODUCTION_STATE.md §8/§11, the same rule
| applies here).
|--------------------------------------------------------------------------
*/

function ensurePinterestAccountsTable(mysqli $con): void
{
    mysqli_query(
        $con,
        "CREATE TABLE IF NOT EXISTS pinterestAccounts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            clientId INT NULL DEFAULT NULL,
            createdBy INT UNSIGNED NOT NULL DEFAULT 0,
            pinterestUserId VARCHAR(191) NOT NULL DEFAULT '',
            username VARCHAR(191) NOT NULL DEFAULT '',
            pinterestBoardId VARCHAR(191) NOT NULL DEFAULT '',
            boardName VARCHAR(191) NOT NULL DEFAULT '',
            accessToken TEXT NOT NULL,
            refreshToken TEXT NOT NULL,
            tokenExpiry DATETIME NULL DEFAULT NULL,
            refreshTokenExpiry DATETIME NULL DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'connected',
            createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uqPinterestUserId (pinterestUserId),
            KEY idxPinterestClientId (clientId)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

/**
 * The one Pinterest account connection for a given Modlus client. One
 * user per client for this foundation phase (matches the described UI
 * flow: connect → select one board → save). Includes the decrypted
 * access/refresh tokens — for internal use only (API calls), never
 * returned from an API endpoint as-is.
 */
function getPinterestAccountByClientId(mysqli $con, int $clientId): ?array
{
    ensurePinterestAccountsTable($con);

    $stmt = mysqli_prepare(
        $con,
        "SELECT * FROM pinterestAccounts WHERE clientId = ? AND status = 'connected' LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'i', $clientId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    return $row ? pinterestAccountRowToArray($row) : null;
}

function getPinterestAccountById(mysqli $con, int $accountId): ?array
{
    ensurePinterestAccountsTable($con);

    $stmt = mysqli_prepare($con, "SELECT * FROM pinterestAccounts WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $accountId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    return $row ? pinterestAccountRowToArray($row) : null;
}

function pinterestAccountRowToArray(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'clientId' => $row['clientId'] !== null ? (int)$row['clientId'] : null,
        'pinterestUserId' => (string)$row['pinterestUserId'],
        'username' => (string)$row['username'],
        'pinterestBoardId' => (string)$row['pinterestBoardId'],
        'boardName' => (string)$row['boardName'],
        'accessToken' => decryptSecret((string)$row['accessToken']),
        'refreshToken' => decryptSecret((string)$row['refreshToken']),
        'tokenExpiry' => $row['tokenExpiry'] !== null ? (string)$row['tokenExpiry'] : null,
        'refreshTokenExpiry' => $row['refreshTokenExpiry'] !== null ? (string)$row['refreshTokenExpiry'] : null,
        'status' => (string)$row['status'],
        'createdAt' => (string)$row['createdAt'],
    ];
}

/**
 * Public-safe view of a client's Pinterest connection — never includes
 * either token. What API endpoints should actually return to the browser.
 */
function getPinterestAccountForDisplay(mysqli $con, int $clientId): ?array
{
    $account = getPinterestAccountByClientId($con, $clientId);

    if (!$account) {
        return null;
    }

    unset($account['accessToken'], $account['refreshToken']);

    return $account;
}

/**
 * Upsert keyed by pinterestUserId (unique key) — reconnecting the same
 * Pinterest user updates the existing row rather than duplicating it,
 * mirroring saveLinkedinAccountFromOAuth()'s linkedinMemberId upsert.
 * Deliberately does NOT touch pinterestBoardId/boardName on an update — a
 * token refresh/reconnect should not silently clear an already-selected
 * board.
 */
function savePinterestAccountFromOAuth(mysqli $con, array $account, int $userId): int
{
    ensurePinterestAccountsTable($con);

    $clientId = (int)($account['clientId'] ?? 0);
    $pinterestUserId = trim((string)($account['pinterestUserId'] ?? ''));
    $username = trim((string)($account['username'] ?? ''));
    $encryptedAccessToken = encryptSecret((string)($account['accessToken'] ?? ''));
    $encryptedRefreshToken = encryptSecret((string)($account['refreshToken'] ?? ''));
    $tokenExpiry = $account['tokenExpiry'] ?? null;
    $refreshTokenExpiry = $account['refreshTokenExpiry'] ?? null;

    if ($pinterestUserId === '' || $clientId <= 0) {
        return 0;
    }

    $stmt = mysqli_prepare($con, "SELECT id FROM pinterestAccounts WHERE pinterestUserId = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $pinterestUserId);
    mysqli_stmt_execute($stmt);
    $existing = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if ($existing) {
        $existingId = (int)$existing['id'];
        $stmt = mysqli_prepare(
            $con,
            "UPDATE pinterestAccounts
             SET clientId = ?, username = ?, accessToken = ?, refreshToken = ?,
                 tokenExpiry = ?, refreshTokenExpiry = ?, status = 'connected'
             WHERE id = ?"
        );
        mysqli_stmt_bind_param(
            $stmt,
            'isssssi',
            $clientId,
            $username,
            $encryptedAccessToken,
            $encryptedRefreshToken,
            $tokenExpiry,
            $refreshTokenExpiry,
            $existingId
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $existingId;
    }

    $stmt = mysqli_prepare(
        $con,
        "INSERT INTO pinterestAccounts
            (createdBy, clientId, pinterestUserId, username, accessToken, refreshToken, tokenExpiry, refreshTokenExpiry, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'connected')"
    );
    mysqli_stmt_bind_param(
        $stmt,
        'iissssss',
        $userId,
        $clientId,
        $pinterestUserId,
        $username,
        $encryptedAccessToken,
        $encryptedRefreshToken,
        $tokenExpiry,
        $refreshTokenExpiry
    );
    mysqli_stmt_execute($stmt);
    $newId = (int)mysqli_insert_id($con);
    mysqli_stmt_close($stmt);

    return $newId;
}

/**
 * Persists a refreshed token pair. Pinterest's refresh response may return
 * a different refresh_token than the one that was used to request it
 * (continuous refresh tokens rotate) — the caller must never assume the
 * original refresh token stays valid, and this function always overwrites
 * both stored tokens with whatever Pinterest actually returned, never just
 * the access token.
 */
function updatePinterestAccountTokens(
    mysqli $con,
    int $accountId,
    string $accessToken,
    string $refreshToken,
    ?string $tokenExpiry,
    ?string $refreshTokenExpiry
): bool {
    ensurePinterestAccountsTable($con);

    $encryptedAccessToken = encryptSecret($accessToken);
    $encryptedRefreshToken = encryptSecret($refreshToken);

    $stmt = mysqli_prepare(
        $con,
        "UPDATE pinterestAccounts
         SET accessToken = ?, refreshToken = ?, tokenExpiry = ?, refreshTokenExpiry = ?
         WHERE id = ?"
    );
    mysqli_stmt_bind_param(
        $stmt,
        'ssssi',
        $encryptedAccessToken,
        $encryptedRefreshToken,
        $tokenExpiry,
        $refreshTokenExpiry,
        $accountId
    );
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}

/**
 * The cross-client ownership guard for Pinterest — mirrors
 * linkedinAccountBelongsToClient()/instagramAccountBelongsToClient()
 * exactly. This is the actual mechanism (not the UI's client selector)
 * that must prevent a user working with Client A from selecting/saving/
 * disconnecting Client B's Pinterest board.
 */
function pinterestAccountBelongsToClient(mysqli $con, int $accountId, int $clientId): bool
{
    ensurePinterestAccountsTable($con);

    $stmt = mysqli_prepare($con, "SELECT id FROM pinterestAccounts WHERE id = ? AND clientId = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'ii', $accountId, $clientId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    return (bool)$row;
}

/**
 * Saves the board a client chose. Caller (api/pinterest/savePinterestBoard.php) is
 * responsible for re-verifying the boardId is actually one the
 * authenticated Pinterest user owns (server-side, not just trusting what
 * the browser posted) before calling this.
 */
function savePinterestBoardSelection(mysqli $con, int $accountId, int $clientId, string $boardId, string $boardName): bool
{
    if (!pinterestAccountBelongsToClient($con, $accountId, $clientId)) {
        return false;
    }

    $stmt = mysqli_prepare(
        $con,
        "UPDATE pinterestAccounts SET pinterestBoardId = ?, boardName = ? WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'ssi', $boardId, $boardName, $accountId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}

function disconnectPinterestAccount(mysqli $con, int $accountId): bool
{
    ensurePinterestAccountsTable($con);

    $stmt = mysqli_prepare(
        $con,
        "UPDATE pinterestAccounts SET status = 'disconnected', accessToken = '', refreshToken = '' WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $accountId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}

/*
|--------------------------------------------------------------------------
| Pinterest HTTP transport
|--------------------------------------------------------------------------
*/

/**
 * Thrown for a 403 from Pinterest specifically — almost always means the
 * app/token lacks an approved scope or Standard-access capability, not a
 * bug. Callers use this to surface "this requires Pinterest's approval"
 * distinctly from a generic failure — same spirit as
 * LinkedinPermissionException (LinkedInAutomation.php) and
 * isInstagramAuthError()'s code-190 special case.
 */
class PinterestPermissionException extends RuntimeException
{
}

/**
 * Token endpoint call (POST, application/x-www-form-urlencoded) — used
 * for both the authorization_code exchange and the refresh_token exchange.
 * Pinterest authenticates the *app* via HTTP Basic Auth
 * (base64(client_id:client_secret)) on this endpoint — a different shape
 * from LinkedIn (client_secret in the POST body) and Meta (access_token as
 * a query param) — so this is deliberately separate from
 * pinterestApiRequest() below, which is for Bearer-token REST API calls.
 */
function pinterestTokenRequest(string $clientId, string $clientSecret, array $params): array
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, PINTEREST_OAUTH_TOKEN_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret),
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $sanitizedParams = $params;
    foreach (['code', 'refresh_token'] as $sensitiveKey) {
        if (array_key_exists($sensitiveKey, $sanitizedParams)) {
            $sanitizedParams[$sensitiveKey] = '[REDACTED]';
        }
    }

    if ($response === false) {
        pinterestWriteApiDebugLog(
            'Pinterest token request network error' . "\n"
            . 'URL: ' . PINTEREST_OAUTH_TOKEN_URL . "\n"
            . 'HTTP Status: ' . $httpStatus . "\n"
            . 'Params: ' . json_encode($sanitizedParams) . "\n"
            . 'cURL error: ' . $curlError
        );

        throw new RuntimeException('Unable to reach Pinterest: ' . $curlError);
    }

    $decoded = json_decode((string)$response, true);

    if (!is_array($decoded)) {
        pinterestWriteApiDebugLog(
            'Pinterest token request invalid response' . "\n"
            . 'URL: ' . PINTEREST_OAUTH_TOKEN_URL . "\n"
            . 'HTTP Status: ' . $httpStatus . "\n"
            . 'Params: ' . json_encode($sanitizedParams)
        );

        throw new RuntimeException('Pinterest returned an invalid response.');
    }

    if ($httpStatus >= 400 || isset($decoded['error'])) {
        $message = (string)($decoded['error_description'] ?? $decoded['error'] ?? 'Unknown Pinterest error.');

        pinterestWriteApiDebugLog(
            'Pinterest token request error' . "\n"
            . 'URL: ' . PINTEREST_OAUTH_TOKEN_URL . "\n"
            . 'HTTP Status: ' . $httpStatus . "\n"
            . 'Params: ' . json_encode($sanitizedParams) . "\n"
            . 'Pinterest error: ' . json_encode($decoded)
        );

        throw new RuntimeException($message);
    }

    return $decoded;
}

/**
 * Bearer-token Pinterest REST API calls (user_account, boards, and — in a
 * later phase — pins). $accessToken is attached as an Authorization
 * header, never as a URL/query parameter, and is never included in any
 * log line this function writes.
 */
function pinterestApiRequest(string $url, string $accessToken, string $method = 'GET', ?array $jsonBody = null): array
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
    ]);

    if ($jsonBody !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonBody));
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Never log the Authorization header — only the URL/method/status/body.
    if ($response === false) {
        pinterestWriteApiDebugLog(
            'Pinterest API network error' . "\n"
            . 'URL: ' . $url . "\n"
            . 'Method: ' . strtoupper($method) . "\n"
            . 'HTTP Status: ' . $httpStatus . "\n"
            . 'cURL error: ' . $curlError
        );

        throw new RuntimeException('Unable to reach Pinterest: ' . $curlError);
    }

    $decoded = json_decode((string)$response, true);

    if ($httpStatus === 403) {
        $message = is_array($decoded) ? (string)($decoded['message'] ?? 'Access denied by Pinterest.') : 'Access denied by Pinterest.';

        pinterestWriteApiDebugLog(
            'Pinterest API permission error (403)' . "\n"
            . 'URL: ' . $url . "\n"
            . 'Method: ' . strtoupper($method) . "\n"
            . 'Pinterest message: ' . $message
        );

        throw new PinterestPermissionException($message, 403);
    }

    if ($httpStatus >= 400 || !is_array($decoded)) {
        $message = is_array($decoded) ? (string)($decoded['message'] ?? 'Unknown Pinterest API error.') : 'Pinterest returned an invalid response.';

        pinterestWriteApiDebugLog(
            'Pinterest API error' . "\n"
            . 'URL: ' . $url . "\n"
            . 'Method: ' . strtoupper($method) . "\n"
            . 'HTTP Status: ' . $httpStatus . "\n"
            . 'Response: ' . (string)$response
        );

        throw new RuntimeException($message, $httpStatus);
    }

    return $decoded;
}

function pinterestWriteApiDebugLog(string $entry): void
{
    $logDir = dirname(__DIR__) . '/logs';

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    if (!is_dir($logDir)) {
        return;
    }

    $line = '[' . date('Y-m-d H:i:s') . ']' . "\n" . $entry . "\n\n";

    @file_put_contents($logDir . '/pinterest-api.log', $line, FILE_APPEND | LOCK_EX);
}

/*
|--------------------------------------------------------------------------
| Token exchange / refresh
|--------------------------------------------------------------------------
*/

/**
 * Authorization-code → token exchange. Returns the raw token fields the
 * caller needs to persist — does not touch the database itself.
 */
function exchangePinterestAuthorizationCode(string $clientId, string $clientSecret, string $code, string $redirectUri): array
{
    $response = pinterestTokenRequest($clientId, $clientSecret, [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $redirectUri,
    ]);

    return pinterestNormalizeTokenResponse($response);
}

/**
 * Refresh-token exchange. Per current Pinterest documentation, apps using
 * continuous refresh tokens receive a new refresh_token on every refresh
 * (rotating, 60-day validity window) — never assume the refresh_token used
 * to make this call is still valid afterward. This function always
 * returns whatever token pair Pinterest actually issued; the caller
 * (updatePinterestAccountTokens()) always overwrites both stored tokens
 * with the returned values, never just the access token.
 */
function refreshPinterestAccessToken(string $clientId, string $clientSecret, string $refreshToken): array
{
    $response = pinterestTokenRequest($clientId, $clientSecret, [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
    ]);

    return pinterestNormalizeTokenResponse($response, $refreshToken);
}

/**
 * Normalizes a Pinterest token-endpoint response into the fields this
 * module persists. Pinterest's refresh response is not guaranteed to
 * include a new refresh_token on every call shape — falls back to the
 * refresh token that was just used only when Pinterest genuinely omitted
 * one, so a caller never loses the ability to refresh again.
 */
function pinterestNormalizeTokenResponse(array $response, ?string $fallbackRefreshToken = null): array
{
    $accessToken = (string)($response['access_token'] ?? '');
    $refreshToken = (string)($response['refresh_token'] ?? ($fallbackRefreshToken ?? ''));
    $expiresIn = isset($response['expires_in']) ? (int)$response['expires_in'] : null;
    $refreshTokenExpiresIn = isset($response['refresh_token_expires_in']) ? (int)$response['refresh_token_expires_in'] : null;

    return [
        'accessToken' => $accessToken,
        'refreshToken' => $refreshToken,
        'tokenExpiry' => $expiresIn !== null ? date('Y-m-d H:i:s', time() + $expiresIn) : null,
        'refreshTokenExpiry' => $refreshTokenExpiresIn !== null ? date('Y-m-d H:i:s', time() + $refreshTokenExpiresIn) : null,
    ];
}

/*
|--------------------------------------------------------------------------
| User identity + Board discovery
|--------------------------------------------------------------------------
*/

/**
 * GET /v5/user_account — the officially documented way to retrieve the
 * authenticated user's identity after the token exchange. Returns only
 * what Modlus actually stores: the Pinterest user id and a display
 * username.
 */
function fetchPinterestUserProfile(string $accessToken): array
{
    $profile = pinterestApiRequest(PINTEREST_API_BASE . '/user_account', $accessToken, 'GET');

    return [
        'pinterestUserId' => (string)($profile['id'] ?? ($profile['username'] ?? '')),
        'username' => (string)($profile['username'] ?? ''),
    ];
}

/**
 * Discovers the boards the authenticated Pinterest user owns
 * (GET /v5/boards, scope boards:read). Throws PinterestPermissionException
 * if the app/token doesn't have boards:read approved — callers must
 * surface that distinctly (external dependency, not a bug) rather than a
 * generic error.
 */
function fetchPinterestBoards(string $accessToken): array
{
    $response = pinterestApiRequest(PINTEREST_API_BASE . '/boards', $accessToken, 'GET');

    $boards = [];

    foreach (($response['items'] ?? []) as $board) {
        $boards[] = [
            'id' => (string)($board['id'] ?? ''),
            'name' => (string)($board['name'] ?? ('Board #' . ($board['id'] ?? ''))),
        ];
    }

    return $boards;
}

/**
 * Re-verifies (server-side) that $boardId is actually one the
 * authenticated Pinterest user owns, before it's ever persisted — never
 * trust a browser-submitted board id/name pair directly. This is the
 * "server-side ownership/access validation" the Pinterest foundation spec
 * explicitly requires for board selection.
 */
function pinterestUserOwnsBoard(string $accessToken, string $boardId): ?string
{
    foreach (fetchPinterestBoards($accessToken) as $board) {
        if ($board['id'] === $boardId) {
            return $board['name'];
        }
    }

    return null;
}
