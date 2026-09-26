<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Crypto.php';
// instagramClientExists() and getInstagramClientLabel() are generic
// clientMaster lookups (no Instagram-specific behavior) already reused
// directly by FacebookPublisher.php/SocialPostEngine.php — reused here
// too rather than duplicating them under a LinkedIn-specific name.
require_once __DIR__ . '/InstagramAutomation.php';

/*
|--------------------------------------------------------------------------
| Phase 12: LinkedIn Integration Foundation
|--------------------------------------------------------------------------
| Mirrors the Instagram module's established conventions (settings table,
| encrypted-secret storage, client-scoped account table, a dedicated
| per-platform Graph/REST API wrapper) — see
| docs/INSTAGRAM_AUTOMATION_PRODUCTION_STATE.md. LinkedIn is a wholly
| separate vendor (different host, different auth header shape — Bearer
| token + Linkedin-Version header, JSON bodies, not Meta's query-string
| access_token + form-urlencoded shape) so it gets its own transport
| function here rather than reusing instagramGraphApiRequest() — this
| matches how FacebookPublisher.php only reuses that wrapper because
| Facebook shares the same Meta app/host/token shape as Instagram; LinkedIn
| does not.
|
| Foundation phase only: connect, identify member, discover organizations,
| select one, store it against a Modlus client. No publishing yet.
*/

// LinkedIn's REST API requires a "Linkedin-Version" header in YYYYMM format.
// Not centralized/auto-updated deliberately — same rationale as the
// hardcoded Meta Graph API version (see Instagram production doc §4/§20):
// changing it is a deliberate, explicit action, not something to abstract
// away. Review/bump periodically against LinkedIn's currently supported
// version range.
const LINKEDIN_API_VERSION = '202608';

/*
|--------------------------------------------------------------------------
| Settings (LinkedIn App Client ID / Client Secret) — one active row,
| platform-wide, same shape as instagramSettings.
|--------------------------------------------------------------------------
*/

function ensureLinkedinSettingsTable(mysqli $con): void
{
    mysqli_query(
        $con,
        "CREATE TABLE IF NOT EXISTS linkedinSettings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            linkedinClientId VARCHAR(191) NOT NULL DEFAULT '',
            linkedinClientSecret TEXT NOT NULL,
            redirectUrl VARCHAR(255) NOT NULL DEFAULT '',
            createdBy INT UNSIGNED NOT NULL DEFAULT 0,
            createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function getLinkedinSettingsRow(mysqli $con): ?array
{
    ensureLinkedinSettingsTable($con);

    $result = mysqli_query($con, "SELECT * FROM linkedinSettings ORDER BY id DESC LIMIT 1");

    return $result ? (mysqli_fetch_assoc($result) ?: null) : null;
}

function getLinkedinSettings(mysqli $con): array
{
    $row = getLinkedinSettingsRow($con);

    if (!$row) {
        return ['linkedinClientId' => '', 'redirectUrl' => '', 'hasClientSecret' => false];
    }

    return [
        'linkedinClientId' => (string)$row['linkedinClientId'],
        'redirectUrl' => (string)$row['redirectUrl'],
        'hasClientSecret' => trim((string)$row['linkedinClientSecret']) !== '',
    ];
}

function getLinkedinSettingsForOAuth(mysqli $con): ?array
{
    $row = getLinkedinSettingsRow($con);

    if (!$row || trim((string)$row['linkedinClientId']) === '' || trim((string)$row['linkedinClientSecret']) === '') {
        return null;
    }

    return [
        'linkedinClientId' => (string)$row['linkedinClientId'],
        'linkedinClientSecret' => decryptSecret((string)$row['linkedinClientSecret']),
        'redirectUrl' => (string)$row['redirectUrl'],
    ];
}

function saveLinkedinSettings(mysqli $con, array $settings, int $userId): bool
{
    ensureLinkedinSettingsTable($con);

    $linkedinClientId = trim((string)($settings['linkedinClientId'] ?? ''));
    $linkedinClientSecret = trim((string)($settings['linkedinClientSecret'] ?? ''));
    $redirectUrl = trim((string)($settings['redirectUrl'] ?? ''));

    $existing = getLinkedinSettingsRow($con);

    $encryptedSecret = $linkedinClientSecret !== ''
        ? encryptSecret($linkedinClientSecret)
        : (string)($existing['linkedinClientSecret'] ?? '');

    if ($existing) {
        $stmt = mysqli_prepare(
            $con,
            "UPDATE linkedinSettings
             SET linkedinClientId = ?, linkedinClientSecret = ?, redirectUrl = ?
             WHERE id = ?"
        );

        if (!$stmt) {
            return false;
        }

        $id = (int)$existing['id'];
        mysqli_stmt_bind_param($stmt, 'sssi', $linkedinClientId, $encryptedSecret, $redirectUrl, $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $ok;
    }

    $stmt = mysqli_prepare(
        $con,
        "INSERT INTO linkedinSettings (linkedinClientId, linkedinClientSecret, redirectUrl, createdBy)
         VALUES (?, ?, ?, ?)"
    );

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'sssi', $linkedinClientId, $encryptedSecret, $redirectUrl, $userId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}

/*
|--------------------------------------------------------------------------
| Accounts — one row per connected LinkedIn member, scoped to exactly one
| Modlus client (never "latest"/"first"/"global"/"primary" — see
| docs/INSTAGRAM_AUTOMATION_PRODUCTION_STATE.md §8/§11, the same rule
| applies here).
|--------------------------------------------------------------------------
*/

function ensureLinkedinAccountsTable(mysqli $con): void
{
    mysqli_query(
        $con,
        "CREATE TABLE IF NOT EXISTS linkedinAccounts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            clientId INT NULL DEFAULT NULL,
            createdBy INT UNSIGNED NOT NULL DEFAULT 0,
            linkedinMemberId VARCHAR(191) NOT NULL DEFAULT '',
            memberName VARCHAR(191) NOT NULL DEFAULT '',
            linkedinOrganizationId VARCHAR(191) NOT NULL DEFAULT '',
            organizationName VARCHAR(191) NOT NULL DEFAULT '',
            accessToken TEXT NOT NULL,
            tokenExpiry DATETIME NULL DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'connected',
            createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uqLinkedinMemberId (linkedinMemberId),
            KEY idxLinkedinClientId (clientId)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

/**
 * The one LinkedIn account connection for a given Modlus client. One
 * member per client for this foundation phase (matches the described UI
 * flow: connect → select one organization → save). Includes the decrypted
 * token — for internal use only (API calls), never returned from an API
 * endpoint as-is.
 */
function getLinkedinAccountByClientId(mysqli $con, int $clientId): ?array
{
    ensureLinkedinAccountsTable($con);

    $stmt = mysqli_prepare(
        $con,
        "SELECT * FROM linkedinAccounts WHERE clientId = ? AND status = 'connected' LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'i', $clientId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    return $row ? linkedinAccountRowToArray($row) : null;
}

function getLinkedinAccountById(mysqli $con, int $accountId): ?array
{
    ensureLinkedinAccountsTable($con);

    $stmt = mysqli_prepare($con, "SELECT * FROM linkedinAccounts WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $accountId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    return $row ? linkedinAccountRowToArray($row) : null;
}

function linkedinAccountRowToArray(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'clientId' => $row['clientId'] !== null ? (int)$row['clientId'] : null,
        'linkedinMemberId' => (string)$row['linkedinMemberId'],
        'memberName' => (string)$row['memberName'],
        'linkedinOrganizationId' => (string)$row['linkedinOrganizationId'],
        'organizationName' => (string)$row['organizationName'],
        'accessToken' => decryptSecret((string)$row['accessToken']),
        'status' => (string)$row['status'],
        'createdAt' => (string)$row['createdAt'],
    ];
}

/**
 * Public-safe view of a client's LinkedIn connection — never includes the
 * access token. What API endpoints should actually return to the browser.
 */
function getLinkedinAccountForDisplay(mysqli $con, int $clientId): ?array
{
    $account = getLinkedinAccountByClientId($con, $clientId);

    if (!$account) {
        return null;
    }

    unset($account['accessToken']);

    return $account;
}

/**
 * Upsert keyed by linkedinMemberId (unique key) — reconnecting the same
 * LinkedIn member updates the existing row rather than duplicating it,
 * mirroring saveInstagramAccountFromOAuth()'s instagramUserId upsert.
 * Deliberately does NOT touch linkedinOrganizationId/organizationName on
 * an update — a token refresh/reconnect should not silently clear an
 * already-selected organization.
 */
function saveLinkedinAccountFromOAuth(mysqli $con, array $account, int $userId): int
{
    ensureLinkedinAccountsTable($con);

    $clientId = (int)($account['clientId'] ?? 0);
    $linkedinMemberId = trim((string)($account['linkedinMemberId'] ?? ''));
    $memberName = trim((string)($account['memberName'] ?? ''));
    $encryptedToken = encryptSecret((string)($account['accessToken'] ?? ''));
    $tokenExpiry = $account['tokenExpiry'] ?? null;

    if ($linkedinMemberId === '' || $clientId <= 0) {
        return 0;
    }

    $stmt = mysqli_prepare($con, "SELECT id FROM linkedinAccounts WHERE linkedinMemberId = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $linkedinMemberId);
    mysqli_stmt_execute($stmt);
    $existing = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if ($existing) {
        $existingId = (int)$existing['id'];
        $stmt = mysqli_prepare(
            $con,
            "UPDATE linkedinAccounts
             SET clientId = ?, memberName = ?, accessToken = ?, tokenExpiry = ?, status = 'connected'
             WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'isssi', $clientId, $memberName, $encryptedToken, $tokenExpiry, $existingId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $existingId;
    }

    $stmt = mysqli_prepare(
        $con,
        "INSERT INTO linkedinAccounts
            (createdBy, clientId, linkedinMemberId, memberName, accessToken, tokenExpiry, status)
         VALUES (?, ?, ?, ?, ?, ?, 'connected')"
    );
    mysqli_stmt_bind_param($stmt, 'iissss', $userId, $clientId, $linkedinMemberId, $memberName, $encryptedToken, $tokenExpiry);
    mysqli_stmt_execute($stmt);
    $newId = (int)mysqli_insert_id($con);
    mysqli_stmt_close($stmt);

    return $newId;
}

/**
 * The cross-client ownership guard for LinkedIn — mirrors
 * instagramAccountBelongsToClient() exactly. This is the actual mechanism
 * (not the UI's client selector) that must prevent a user working with
 * Client A from selecting/saving/disconnecting Client B's LinkedIn
 * organization.
 */
function linkedinAccountBelongsToClient(mysqli $con, int $accountId, int $clientId): bool
{
    ensureLinkedinAccountsTable($con);

    $stmt = mysqli_prepare($con, "SELECT id FROM linkedinAccounts WHERE id = ? AND clientId = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'ii', $accountId, $clientId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    return (bool)$row;
}

/**
 * Saves the organization a client chose. Caller (api/linkedin/saveLinkedinOrganization.php)
 * is responsible for re-verifying the organizationId is actually one Meta —
 * sorry, LinkedIn — reports the member administers (server-side, not just
 * trusting what the browser posted) before calling this.
 */
function saveLinkedinOrganizationSelection(mysqli $con, int $accountId, int $clientId, string $organizationId, string $organizationName): bool
{
    if (!linkedinAccountBelongsToClient($con, $accountId, $clientId)) {
        return false;
    }

    $stmt = mysqli_prepare(
        $con,
        "UPDATE linkedinAccounts SET linkedinOrganizationId = ?, organizationName = ? WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'ssi', $organizationId, $organizationName, $accountId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}

function disconnectLinkedinAccount(mysqli $con, int $accountId): bool
{
    ensureLinkedinAccountsTable($con);

    $stmt = mysqli_prepare(
        $con,
        "UPDATE linkedinAccounts SET status = 'disconnected', accessToken = '' WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $accountId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}

/*
|--------------------------------------------------------------------------
| LinkedIn HTTP transport
|--------------------------------------------------------------------------
*/

/**
 * Thrown for a 403 from LinkedIn specifically — almost always means the
 * app/member lacks an approved permission (e.g. Community Management API
 * product access not yet granted), not a bug. Callers use this to surface
 * "this requires LinkedIn's approval" distinctly from a generic failure —
 * same spirit as isInstagramAuthError()'s code-190 special case.
 */
class LinkedinPermissionException extends RuntimeException
{
}

/**
 * Token endpoint call (POST, application/x-www-form-urlencoded) — used
 * only for the authorization_code exchange. Deliberately separate from
 * linkedinApiRequest() below, which is for Bearer-token REST API calls
 * (different content type, different host, no access token to attach as a
 * header since we don't have one yet).
 */
function linkedinTokenRequest(string $url, array $params): array
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $sanitizedParams = $params;
    foreach (['client_secret', 'code', 'refresh_token'] as $sensitiveKey) {
        if (array_key_exists($sensitiveKey, $sanitizedParams)) {
            $sanitizedParams[$sensitiveKey] = '[REDACTED]';
        }
    }

    if ($response === false) {
        linkedinWriteApiDebugLog(
            'LinkedIn token request network error' . "\n"
            . 'URL: ' . $url . "\n"
            . 'HTTP Status: ' . $httpStatus . "\n"
            . 'Params: ' . json_encode($sanitizedParams) . "\n"
            . 'cURL error: ' . $curlError
        );

        throw new RuntimeException('Unable to reach LinkedIn: ' . $curlError);
    }

    $decoded = json_decode((string)$response, true);

    if (!is_array($decoded)) {
        linkedinWriteApiDebugLog(
            'LinkedIn token request invalid response' . "\n"
            . 'URL: ' . $url . "\n"
            . 'HTTP Status: ' . $httpStatus . "\n"
            . 'Params: ' . json_encode($sanitizedParams)
        );

        throw new RuntimeException('LinkedIn returned an invalid response.');
    }

    if ($httpStatus >= 400 || isset($decoded['error'])) {
        $message = (string)($decoded['error_description'] ?? $decoded['error'] ?? 'Unknown LinkedIn error.');

        linkedinWriteApiDebugLog(
            'LinkedIn token request error' . "\n"
            . 'URL: ' . $url . "\n"
            . 'HTTP Status: ' . $httpStatus . "\n"
            . 'Params: ' . json_encode($sanitizedParams) . "\n"
            . 'LinkedIn error: ' . json_encode($decoded)
        );

        throw new RuntimeException($message);
    }

    return $decoded;
}

/**
 * Bearer-token LinkedIn REST API calls (organizationAcls, organizations,
 * userinfo, and — in a later phase — posts). $accessToken is attached as
 * an Authorization header, never as a URL/query parameter, and is never
 * included in any log line this function writes.
 */
function linkedinApiRequest(string $url, string $accessToken, string $method = 'GET', ?array $jsonBody = null): array
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Linkedin-Version: ' . LINKEDIN_API_VERSION,
        'X-Restli-Protocol-Version: 2.0.0',
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
        linkedinWriteApiDebugLog(
            'LinkedIn API network error' . "\n"
            . 'URL: ' . $url . "\n"
            . 'Method: ' . strtoupper($method) . "\n"
            . 'HTTP Status: ' . $httpStatus . "\n"
            . 'cURL error: ' . $curlError
        );

        throw new RuntimeException('Unable to reach LinkedIn: ' . $curlError);
    }

    $decoded = json_decode((string)$response, true);

    if ($httpStatus === 403) {
        $message = is_array($decoded) ? (string)($decoded['message'] ?? 'Access denied by LinkedIn.') : 'Access denied by LinkedIn.';

        linkedinWriteApiDebugLog(
            'LinkedIn API permission error (403)' . "\n"
            . 'URL: ' . $url . "\n"
            . 'Method: ' . strtoupper($method) . "\n"
            . 'LinkedIn message: ' . $message
        );

        throw new LinkedinPermissionException($message, 403);
    }

    if ($httpStatus >= 400 || !is_array($decoded)) {
        $message = is_array($decoded) ? (string)($decoded['message'] ?? 'Unknown LinkedIn API error.') : 'LinkedIn returned an invalid response.';

        linkedinWriteApiDebugLog(
            'LinkedIn API error' . "\n"
            . 'URL: ' . $url . "\n"
            . 'Method: ' . strtoupper($method) . "\n"
            . 'HTTP Status: ' . $httpStatus . "\n"
            . 'Response: ' . (string)$response
        );

        throw new RuntimeException($message, $httpStatus);
    }

    return $decoded;
}

function linkedinWriteApiDebugLog(string $entry): void
{
    $logDir = dirname(__DIR__) . '/logs';

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    if (!is_dir($logDir)) {
        return;
    }

    $line = '[' . date('Y-m-d H:i:s') . ']' . "\n" . $entry . "\n\n";

    @file_put_contents($logDir . '/linkedin-api.log', $line, FILE_APPEND | LOCK_EX);
}

/*
|--------------------------------------------------------------------------
| Member identity + Organization discovery
|--------------------------------------------------------------------------
*/

/**
 * OIDC userinfo endpoint — the officially recommended way to retrieve the
 * authenticated member's identity after the token exchange, without needing
 * to parse/verify the ID token JWT ourselves. Returns only what Modlus
 * actually stores: the member id ('sub') and a display name.
 */
function fetchLinkedinMemberProfile(string $accessToken): array
{
    $profile = linkedinApiRequest('https://api.linkedin.com/v2/userinfo', $accessToken, 'GET');

    return [
        'linkedinMemberId' => (string)($profile['sub'] ?? ''),
        'memberName' => (string)($profile['name'] ?? ''),
    ];
}

/**
 * Discovers the Organizations (LinkedIn Company Pages) the authenticated
 * member administers: organizationAcls (role=ADMINISTRATOR, state=APPROVED)
 * for the URNs, then a batch Organization Lookup call for display names.
 * Throws LinkedinPermissionException if the app/member doesn't have
 * Community Management API access approved yet — callers must surface that
 * distinctly (external dependency, not a bug) rather than a generic error.
 */
function fetchLinkedinManagedOrganizations(string $accessToken): array
{
    $aclResponse = linkedinApiRequest(
        'https://api.linkedin.com/rest/organizationAcls?q=roleAssignee&role=ADMINISTRATOR&state=APPROVED',
        $accessToken,
        'GET'
    );

    $organizationIds = [];

    foreach (($aclResponse['elements'] ?? []) as $element) {
        $urn = (string)($element['organizationTarget'] ?? $element['organization'] ?? '');

        if (preg_match('/urn:li:organization:(\d+)/', $urn, $matches)) {
            $organizationIds[] = $matches[1];
        }
    }

    $organizationIds = array_values(array_unique($organizationIds));

    if (empty($organizationIds)) {
        return [];
    }

    $idsList = 'List(' . implode(',', $organizationIds) . ')';
    $lookupResponse = linkedinApiRequest(
        'https://api.linkedin.com/rest/organizations?ids=' . $idsList,
        $accessToken,
        'GET'
    );

    $organizations = [];

    foreach (($lookupResponse['results'] ?? []) as $id => $org) {
        $organizations[] = [
            'id' => (string)$id,
            'name' => (string)($org['localizedName'] ?? ('Organization #' . $id)),
        ];
    }

    return $organizations;
}

/**
 * Re-verifies (server-side) that $organizationId is actually one the
 * member administers, before it's ever persisted — never trust a
 * browser-submitted organization id/name pair directly. This is the
 * "server-side ownership/access validation" the LinkedIn foundation spec
 * explicitly requires for organization selection.
 */
function linkedinMemberAdministersOrganization(string $accessToken, string $organizationId): ?string
{
    foreach (fetchLinkedinManagedOrganizations($accessToken) as $organization) {
        if ($organization['id'] === $organizationId) {
            return $organization['name'];
        }
    }

    return null;
}

