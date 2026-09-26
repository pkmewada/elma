<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Crypto.php';
// instagramClientExists() and getInstagramClientLabel() are generic
// clientMaster lookups (no Instagram-specific behavior) already reused
// as-is by LinkedInAutomation.php/PinterestAutomation.php — reused here
// too rather than duplicating them under a Google-specific name.
require_once __DIR__ . '/InstagramAutomation.php';

/*
|--------------------------------------------------------------------------
| Phase 14: Google Business Profile Integration Foundation
|--------------------------------------------------------------------------
| Mirrors the LinkedIn/Pinterest foundation modules' conventions (settings
| table, encrypted-secret storage, client-scoped account table, a
| dedicated per-platform HTTP transport) — see
| docs/INSTAGRAM_AUTOMATION_PRODUCTION_STATE.md §25/§26/§27 and
| docs/GMB_INTEGRATION_FOUNDATION_PHASE_14.md (source of truth for this
| phase). Google is architecturally different from every prior platform in
| one important way: it has a THREE-level resource hierarchy (Google user
| → Business Profile account → location), not a flat "member → one
| selectable resource" shape like LinkedIn's organization or Pinterest's
| board. This module therefore does two rounds of server-side discovery
| (accounts, then locations for a chosen account) before anything is
| persisted, and stores account and location identifiers as separate,
| non-collapsed columns per the source-of-truth document's explicit
| instruction (§4).
|
| Google's OAuth/token/API shapes are all vendor-specific (form-urlencoded
| client_id+client_secret in the POST body for token requests — not
| Pinterest's HTTP Basic Auth, not LinkedIn's identical-looking but
| differently-hosted POST body; a completely different REST API host/JSON
| shape for Account Management vs Business Information) so this file does
| not reuse instagramGraphApiRequest(), linkedinApiRequest(), or
| pinterestApiRequest() — it has its own transport, per the source
| document's explicit instruction (§3, "Do not reuse vendor-specific
| transports").
|
| Foundation phase only: connect, identify the Google user, discover
| Business Profile accounts, discover locations for a chosen account,
| select one location, store it against a Modlus client, and support token
| refresh. No publishing, reviews, analytics, or webhooks.
*/

const GOOGLE_OAUTH_AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
const GOOGLE_OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';
const GOOGLE_USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';
const GOOGLE_ACCOUNT_MANAGEMENT_API_BASE = 'https://mybusinessaccountmanagement.googleapis.com/v1';
const GOOGLE_BUSINESS_INFORMATION_API_BASE = 'https://mybusinessbusinessinformation.googleapis.com/v1';

// The current (non-deprecated) Business Profile management scope, per
// developers.google.com/my-business/content/implement-oauth — the older
// https://www.googleapis.com/auth/plus.business.manage is deprecated and
// must never be requested. openid+email are additionally requested
// (least-privilege, identity-only) because this module's schema stores
// googleUserId/googleUserEmail (source doc §6) and Google's Business
// Profile APIs themselves expose no "who is the authenticated user"
// endpoint of their own — the OIDC userinfo endpoint is the officially
// documented way to get that, same rationale LinkedInAutomation.php uses
// for its OIDC userinfo call.
const GOOGLE_OAUTH_SCOPE = 'openid email https://www.googleapis.com/auth/business.manage';

/*
|--------------------------------------------------------------------------
| Settings (Google Cloud OAuth Client ID / Client Secret) — one active
| row, platform-wide, same shape as linkedinSettings/pinterestSettings.
|--------------------------------------------------------------------------
*/

function ensureGoogleBusinessProfileSettingsTable(mysqli $con): void
{
    mysqli_query(
        $con,
        "CREATE TABLE IF NOT EXISTS googleBusinessProfileSettings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            googleClientId VARCHAR(191) NOT NULL DEFAULT '',
            googleClientSecret TEXT NOT NULL,
            redirectUrl VARCHAR(255) NOT NULL DEFAULT '',
            createdBy INT UNSIGNED NOT NULL DEFAULT 0,
            createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function getGoogleBusinessProfileSettingsRow(mysqli $con): ?array
{
    ensureGoogleBusinessProfileSettingsTable($con);

    $result = mysqli_query($con, "SELECT * FROM googleBusinessProfileSettings ORDER BY id DESC LIMIT 1");

    return $result ? (mysqli_fetch_assoc($result) ?: null) : null;
}

function getGoogleBusinessProfileSettings(mysqli $con): array
{
    $row = getGoogleBusinessProfileSettingsRow($con);

    if (!$row) {
        return ['googleClientId' => '', 'redirectUrl' => '', 'hasClientSecret' => false];
    }

    return [
        'googleClientId' => (string)$row['googleClientId'],
        'redirectUrl' => (string)$row['redirectUrl'],
        'hasClientSecret' => trim((string)$row['googleClientSecret']) !== '',
    ];
}

function getGoogleBusinessProfileSettingsForOAuth(mysqli $con): ?array
{
    $row = getGoogleBusinessProfileSettingsRow($con);

    if (!$row || trim((string)$row['googleClientId']) === '' || trim((string)$row['googleClientSecret']) === '') {
        return null;
    }

    return [
        'googleClientId' => (string)$row['googleClientId'],
        'googleClientSecret' => decryptSecret((string)$row['googleClientSecret']),
        'redirectUrl' => (string)$row['redirectUrl'],
    ];
}

function saveGoogleBusinessProfileSettings(mysqli $con, array $settings, int $userId): bool
{
    ensureGoogleBusinessProfileSettingsTable($con);

    $googleClientId = trim((string)($settings['googleClientId'] ?? ''));
    $googleClientSecret = trim((string)($settings['googleClientSecret'] ?? ''));
    $redirectUrl = trim((string)($settings['redirectUrl'] ?? ''));

    $existing = getGoogleBusinessProfileSettingsRow($con);

    $encryptedSecret = $googleClientSecret !== ''
        ? encryptSecret($googleClientSecret)
        : (string)($existing['googleClientSecret'] ?? '');

    if ($existing) {
        $stmt = mysqli_prepare(
            $con,
            "UPDATE googleBusinessProfileSettings
             SET googleClientId = ?, googleClientSecret = ?, redirectUrl = ?
             WHERE id = ?"
        );

        if (!$stmt) {
            return false;
        }

        $id = (int)$existing['id'];
        mysqli_stmt_bind_param($stmt, 'sssi', $googleClientId, $encryptedSecret, $redirectUrl, $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $ok;
    }

    $stmt = mysqli_prepare(
        $con,
        "INSERT INTO googleBusinessProfileSettings (googleClientId, googleClientSecret, redirectUrl, createdBy)
         VALUES (?, ?, ?, ?)"
    );

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'sssi', $googleClientId, $encryptedSecret, $redirectUrl, $userId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}

/*
|--------------------------------------------------------------------------
| Accounts — one row per connected Modlus client's Google Business Profile
| selection (never "latest"/"first"/"global"/"primary" — see
| docs/INSTAGRAM_AUTOMATION_PRODUCTION_STATE.md §8/§11, the same rule
| applies here). Unlike LinkedIn/Pinterest, the unique key is
| (googleUserId, googleAccountId) rather than the vendor user id alone —
| per the source document §10 ("Do not assume the OAuth Google Account has
| only one Business Profile account"), the same Google identity may
| legitimately manage more than one Business Profile account across
| different Modlus clients (an agency scenario). googleAccountId is empty
| until the operator completes account/location discovery and selection.
|--------------------------------------------------------------------------
*/

function ensureGoogleBusinessProfileAccountsTable(mysqli $con): void
{
    mysqli_query(
        $con,
        "CREATE TABLE IF NOT EXISTS googleBusinessProfileAccounts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            clientId INT NULL DEFAULT NULL,
            createdBy INT UNSIGNED NOT NULL DEFAULT 0,

            googleUserId VARCHAR(191) NOT NULL DEFAULT '',
            googleUserEmail VARCHAR(191) NOT NULL DEFAULT '',

            googleAccountId VARCHAR(191) NOT NULL DEFAULT '',
            googleAccountName VARCHAR(255) NOT NULL DEFAULT '',
            googleAccountType VARCHAR(100) NOT NULL DEFAULT '',

            googleLocationId VARCHAR(191) NOT NULL DEFAULT '',
            googleLocationName VARCHAR(255) NOT NULL DEFAULT '',
            locationTitle VARCHAR(255) NOT NULL DEFAULT '',
            locationAddress TEXT NULL,

            accessToken TEXT NOT NULL,
            refreshToken TEXT NOT NULL,

            tokenExpiry DATETIME NULL DEFAULT NULL,

            status VARCHAR(20) NOT NULL DEFAULT 'connected',

            createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            UNIQUE KEY uqGoogleUserAccount (googleUserId, googleAccountId),
            KEY idxGoogleBusinessProfileClientId (clientId)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

/**
 * The one Google Business Profile connection for a given Modlus client.
 * Includes the decrypted access/refresh tokens — for internal use only
 * (API calls), never returned from an API endpoint as-is.
 */
function getGoogleBusinessProfileAccountByClientId(mysqli $con, int $clientId): ?array
{
    ensureGoogleBusinessProfileAccountsTable($con);

    $stmt = mysqli_prepare(
        $con,
        "SELECT * FROM googleBusinessProfileAccounts WHERE clientId = ? AND status = 'connected' LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'i', $clientId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    return $row ? googleBusinessProfileAccountRowToArray($row) : null;
}

function getGoogleBusinessProfileAccountById(mysqli $con, int $accountId): ?array
{
    ensureGoogleBusinessProfileAccountsTable($con);

    $stmt = mysqli_prepare($con, "SELECT * FROM googleBusinessProfileAccounts WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $accountId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    return $row ? googleBusinessProfileAccountRowToArray($row) : null;
}

/**
 * Looks up a not-yet-account-selected connection row (googleAccountId =
 * '') for a given Google identity, regardless of which client it's
 * currently attached to. Used only by
 * saveGoogleBusinessProfileAccountFromOAuth()'s fallback anchor — see its
 * doc comment for why this is needed.
 */
function getGoogleBusinessProfileUnselectedAccountByGoogleUserId(mysqli $con, string $googleUserId): ?array
{
    ensureGoogleBusinessProfileAccountsTable($con);

    $stmt = mysqli_prepare(
        $con,
        "SELECT * FROM googleBusinessProfileAccounts WHERE googleUserId = ? AND googleAccountId = '' LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 's', $googleUserId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    return $row ? googleBusinessProfileAccountRowToArray($row) : null;
}

function googleBusinessProfileAccountRowToArray(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'clientId' => $row['clientId'] !== null ? (int)$row['clientId'] : null,
        'googleUserId' => (string)$row['googleUserId'],
        'googleUserEmail' => (string)$row['googleUserEmail'],
        'googleAccountId' => (string)$row['googleAccountId'],
        'googleAccountName' => (string)$row['googleAccountName'],
        'googleAccountType' => (string)$row['googleAccountType'],
        'googleLocationId' => (string)$row['googleLocationId'],
        'googleLocationName' => (string)$row['googleLocationName'],
        'locationTitle' => (string)$row['locationTitle'],
        'locationAddress' => $row['locationAddress'] !== null ? (string)$row['locationAddress'] : null,
        'accessToken' => decryptSecret((string)$row['accessToken']),
        'refreshToken' => decryptSecret((string)$row['refreshToken']),
        'tokenExpiry' => $row['tokenExpiry'] !== null ? (string)$row['tokenExpiry'] : null,
        'status' => (string)$row['status'],
        'createdAt' => (string)$row['createdAt'],
    ];
}

/**
 * Public-safe view of a client's Google Business Profile connection —
 * never includes either token. What API endpoints should actually return
 * to the browser.
 */
function getGoogleBusinessProfileAccountForDisplay(mysqli $con, int $clientId): ?array
{
    $account = getGoogleBusinessProfileAccountByClientId($con, $clientId);

    if (!$account) {
        return null;
    }

    unset($account['accessToken'], $account['refreshToken']);

    return $account;
}

/**
 * Upsert on OAuth connect/reconnect. Deliberately anchored on the Modlus
 * client first (not the Google identity alone, unlike
 * saveLinkedinAccountFromOAuth()/savePinterestAccountFromOAuth()) —
 * because the same Google identity can legitimately manage more than one
 * Business Profile account across different clients (source doc §10), a
 * vendor-identity-only upsert would incorrectly move an already-connected
 * client's row to whichever client last reconnected with that Google
 * login. Anchoring on clientId first means: reconnecting for the SAME
 * client always updates that client's own row (preserving any already
 * selected googleAccountId/location, exactly like the LinkedIn/Pinterest
 * "does not touch the selection fields on update" rule) and only inserts
 * a new row when this client has no connection yet.
 *
 * A second anchor (googleAccountId = '' for this Google identity, any
 * client) is checked before inserting, purely to avoid violating the
 * (googleUserId, googleAccountId) unique key in the narrow edge case where
 * a not-yet-account-selected row already exists for this Google identity
 * under a different, still-in-progress client connection — accepted as a
 * known "last reconnect wins" limitation for that specific pre-selection
 * edge case, the same class of limitation LinkedIn/Pinterest already
 * accept for their own upsert-by-vendor-id behavior.
 */
function saveGoogleBusinessProfileAccountFromOAuth(mysqli $con, array $account, int $userId): int
{
    ensureGoogleBusinessProfileAccountsTable($con);

    $clientId = (int)($account['clientId'] ?? 0);
    $googleUserId = trim((string)($account['googleUserId'] ?? ''));
    $googleUserEmail = trim((string)($account['googleUserEmail'] ?? ''));
    $encryptedAccessToken = encryptSecret((string)($account['accessToken'] ?? ''));
    $encryptedRefreshToken = encryptSecret((string)($account['refreshToken'] ?? ''));
    $tokenExpiry = $account['tokenExpiry'] ?? null;

    if ($googleUserId === '' || $clientId <= 0) {
        return 0;
    }

    $existing = getGoogleBusinessProfileAccountByClientId($con, $clientId);

    if (!$existing) {
        $existing = getGoogleBusinessProfileUnselectedAccountByGoogleUserId($con, $googleUserId);
    }

    if ($existing) {
        $existingId = (int)$existing['id'];
        $stmt = mysqli_prepare(
            $con,
            "UPDATE googleBusinessProfileAccounts
             SET clientId = ?, googleUserId = ?, googleUserEmail = ?, accessToken = ?, refreshToken = ?,
                 tokenExpiry = ?, status = 'connected'
             WHERE id = ?"
        );
        mysqli_stmt_bind_param(
            $stmt,
            'isssssi',
            $clientId,
            $googleUserId,
            $googleUserEmail,
            $encryptedAccessToken,
            $encryptedRefreshToken,
            $tokenExpiry,
            $existingId
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $existingId;
    }

    $stmt = mysqli_prepare(
        $con,
        "INSERT INTO googleBusinessProfileAccounts
            (createdBy, clientId, googleUserId, googleUserEmail, accessToken, refreshToken, tokenExpiry, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'connected')"
    );
    mysqli_stmt_bind_param(
        $stmt,
        'iisssss',
        $userId,
        $clientId,
        $googleUserId,
        $googleUserEmail,
        $encryptedAccessToken,
        $encryptedRefreshToken,
        $tokenExpiry
    );
    mysqli_stmt_execute($stmt);
    $newId = (int)mysqli_insert_id($con);
    mysqli_stmt_close($stmt);

    return $newId;
}

/**
 * Persists a refreshed token pair. Per the source document §20, Google
 * may or may not return a new refresh_token on a refresh call — this
 * function always overwrites the access token and expiry, and only
 * overwrites the refresh token when a non-empty one is actually passed in
 * (the caller is responsible for falling back to the prior refresh token
 * when Google's response omitted one — see
 * googleBusinessProfileNormalizeTokenResponse()). Never assume the old
 * refresh token remains valid when Google did return a new one.
 */
function updateGoogleBusinessProfileAccountTokens(
    mysqli $con,
    int $accountId,
    string $accessToken,
    string $refreshToken,
    ?string $tokenExpiry
): bool {
    ensureGoogleBusinessProfileAccountsTable($con);

    $encryptedAccessToken = encryptSecret($accessToken);
    $encryptedRefreshToken = encryptSecret($refreshToken);

    $stmt = mysqli_prepare(
        $con,
        "UPDATE googleBusinessProfileAccounts
         SET accessToken = ?, refreshToken = ?, tokenExpiry = ?
         WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'sssi', $encryptedAccessToken, $encryptedRefreshToken, $tokenExpiry, $accountId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}

/**
 * The cross-client ownership guard for Google Business Profile — mirrors
 * pinterestAccountBelongsToClient()/linkedinAccountBelongsToClient()
 * exactly. This is the actual mechanism (not the UI's client selector)
 * that must prevent a user working with Client A from selecting/saving/
 * disconnecting Client B's Google Business Profile connection.
 */
function googleBusinessProfileAccountBelongsToClient(mysqli $con, int $accountId, int $clientId): bool
{
    ensureGoogleBusinessProfileAccountsTable($con);

    $stmt = mysqli_prepare($con, "SELECT id FROM googleBusinessProfileAccounts WHERE id = ? AND clientId = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'ii', $accountId, $clientId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    return (bool)$row;
}

/**
 * Saves the Business Profile account + location the operator chose, in
 * one step (the source document lists no separate "save account"
 * endpoint — see api/google-business-profile/saveGoogleBusinessProfileLocation.php). Caller is
 * responsible for re-verifying, server-side against Google, that this
 * Google identity actually has access to $googleAccountId AND that
 * $googleLocationId actually belongs to that account, before calling
 * this — never trust browser-submitted account/location id/name pairs
 * directly.
 */
function saveGoogleBusinessProfileLocationSelection(
    mysqli $con,
    int $accountId,
    int $clientId,
    string $googleAccountId,
    string $googleAccountName,
    string $googleAccountType,
    string $googleLocationId,
    string $googleLocationName,
    string $locationTitle,
    ?string $locationAddress
): bool {
    if (!googleBusinessProfileAccountBelongsToClient($con, $accountId, $clientId)) {
        return false;
    }

    $stmt = mysqli_prepare(
        $con,
        "UPDATE googleBusinessProfileAccounts
         SET googleAccountId = ?, googleAccountName = ?, googleAccountType = ?,
             googleLocationId = ?, googleLocationName = ?, locationTitle = ?, locationAddress = ?
         WHERE id = ?"
    );
    mysqli_stmt_bind_param(
        $stmt,
        'sssssssi',
        $googleAccountId,
        $googleAccountName,
        $googleAccountType,
        $googleLocationId,
        $googleLocationName,
        $locationTitle,
        $locationAddress,
        $accountId
    );
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}

function disconnectGoogleBusinessProfileAccount(mysqli $con, int $accountId): bool
{
    ensureGoogleBusinessProfileAccountsTable($con);

    $stmt = mysqli_prepare(
        $con,
        "UPDATE googleBusinessProfileAccounts SET status = 'disconnected', accessToken = '', refreshToken = '' WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $accountId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}

/*
|--------------------------------------------------------------------------
| Google HTTP transport
|--------------------------------------------------------------------------
*/

/**
 * Thrown for a 403/PERMISSION_DENIED from Google specifically — almost
 * always means the Google Cloud project's Business Profile API access has
 * not been approved yet (source doc §2: 0 QPM until approved), not a bug.
 * Callers use this to surface "this requires Google's approval" distinctly
 * from a generic failure — same spirit as PinterestPermissionException/
 * LinkedinPermissionException.
 */
class GoogleBusinessProfilePermissionException extends RuntimeException
{
}

/**
 * Token endpoint call (POST, application/x-www-form-urlencoded) — used
 * for both the authorization_code exchange and the refresh_token
 * exchange. Google authenticates the app via client_id+client_secret as
 * ordinary POST body fields (not Pinterest's HTTP Basic Auth header, not
 * a query string) — Google's documented shape — so this is deliberately
 * separate from googleBusinessProfileApiRequest() below, which is for
 * Bearer-token REST API calls.
 */
function googleBusinessProfileTokenRequest(string $clientId, string $clientSecret, array $params): array
{
    $params['client_id'] = $clientId;
    $params['client_secret'] = $clientSecret;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, GOOGLE_OAUTH_TOKEN_URL);
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
        googleBusinessProfileWriteApiDebugLog(
            'Google token request network error' . "\n"
            . 'URL: ' . GOOGLE_OAUTH_TOKEN_URL . "\n"
            . 'HTTP Status: ' . $httpStatus . "\n"
            . 'Params: ' . json_encode($sanitizedParams) . "\n"
            . 'cURL error: ' . $curlError
        );

        throw new RuntimeException('Unable to reach Google: ' . $curlError);
    }

    $decoded = json_decode((string)$response, true);

    if (!is_array($decoded)) {
        googleBusinessProfileWriteApiDebugLog(
            'Google token request invalid response' . "\n"
            . 'URL: ' . GOOGLE_OAUTH_TOKEN_URL . "\n"
            . 'HTTP Status: ' . $httpStatus . "\n"
            . 'Params: ' . json_encode($sanitizedParams)
        );

        throw new RuntimeException('Google returned an invalid response.');
    }

    if ($httpStatus >= 400 || isset($decoded['error'])) {
        $message = (string)($decoded['error_description'] ?? $decoded['error'] ?? 'Unknown Google error.');

        googleBusinessProfileWriteApiDebugLog(
            'Google token request error' . "\n"
            . 'URL: ' . GOOGLE_OAUTH_TOKEN_URL . "\n"
            . 'HTTP Status: ' . $httpStatus . "\n"
            . 'Params: ' . json_encode($sanitizedParams) . "\n"
            . 'Google error: ' . json_encode($decoded)
        );

        throw new RuntimeException($message);
    }

    return $decoded;
}

/**
 * Bearer-token Google REST API calls (userinfo, Account Management API,
 * Business Information API, and — in a later phase — posts/reviews/media).
 * $accessToken is attached as an Authorization header, never as a
 * URL/query parameter, and is never included in any log line this
 * function writes.
 */
function googleBusinessProfileApiRequest(string $url, string $accessToken, string $method = 'GET', ?array $jsonBody = null): array
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
        googleBusinessProfileWriteApiDebugLog(
            'Google API network error' . "\n"
            . 'URL: ' . $url . "\n"
            . 'Method: ' . strtoupper($method) . "\n"
            . 'HTTP Status: ' . $httpStatus . "\n"
            . 'cURL error: ' . $curlError
        );

        throw new RuntimeException('Unable to reach Google: ' . $curlError);
    }

    $decoded = json_decode((string)$response, true);

    if ($httpStatus === 403) {
        $message = is_array($decoded) ? (string)($decoded['error']['message'] ?? 'Access denied by Google.') : 'Access denied by Google.';

        googleBusinessProfileWriteApiDebugLog(
            'Google API permission error (403)' . "\n"
            . 'URL: ' . $url . "\n"
            . 'Method: ' . strtoupper($method) . "\n"
            . 'Google message: ' . $message
        );

        throw new GoogleBusinessProfilePermissionException($message, 403);
    }

    if ($httpStatus >= 400 || !is_array($decoded)) {
        $message = is_array($decoded) ? (string)($decoded['error']['message'] ?? 'Unknown Google API error.') : 'Google returned an invalid response.';

        googleBusinessProfileWriteApiDebugLog(
            'Google API error' . "\n"
            . 'URL: ' . $url . "\n"
            . 'Method: ' . strtoupper($method) . "\n"
            . 'HTTP Status: ' . $httpStatus . "\n"
            . 'Response: ' . (string)$response
        );

        throw new RuntimeException($message, $httpStatus);
    }

    return $decoded;
}

function googleBusinessProfileWriteApiDebugLog(string $entry): void
{
    $logDir = dirname(__DIR__) . '/logs';

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    if (!is_dir($logDir)) {
        return;
    }

    $line = '[' . date('Y-m-d H:i:s') . ']' . "\n" . $entry . "\n\n";

    @file_put_contents($logDir . '/google-business-profile-api.log', $line, FILE_APPEND | LOCK_EX);
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
function exchangeGoogleBusinessProfileAuthorizationCode(string $clientId, string $clientSecret, string $code, string $redirectUri): array
{
    $response = googleBusinessProfileTokenRequest($clientId, $clientSecret, [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $redirectUri,
    ]);

    return googleBusinessProfileNormalizeTokenResponse($response);
}

/**
 * Refresh-token exchange. Per the source document §20, Google may or may
 * not return a new refresh_token on a given refresh call — never assume
 * the refresh token used to make this call is still the one Google wants
 * used next. This function returns whatever token pair Google actually
 * issued; the caller (updateGoogleBusinessProfileAccountTokens()) stores
 * the new refresh token when one was returned, and otherwise keeps the
 * one that was already stored.
 */
function refreshGoogleBusinessProfileAccessToken(string $clientId, string $clientSecret, string $refreshToken): array
{
    $response = googleBusinessProfileTokenRequest($clientId, $clientSecret, [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
    ]);

    return googleBusinessProfileNormalizeTokenResponse($response, $refreshToken);
}

/**
 * Normalizes a Google token-endpoint response into the fields this module
 * persists. Falls back to the refresh token that was just used only when
 * Google's response genuinely omitted a new one, so a caller never loses
 * the ability to refresh again.
 */
function googleBusinessProfileNormalizeTokenResponse(array $response, ?string $fallbackRefreshToken = null): array
{
    $accessToken = (string)($response['access_token'] ?? '');
    $refreshToken = (string)($response['refresh_token'] ?? ($fallbackRefreshToken ?? ''));
    $expiresIn = isset($response['expires_in']) ? (int)$response['expires_in'] : null;

    return [
        'accessToken' => $accessToken,
        'refreshToken' => $refreshToken,
        'tokenExpiry' => $expiresIn !== null ? date('Y-m-d H:i:s', time() + $expiresIn) : null,
    ];
}

/**
 * Returns a Google access token guaranteed valid for immediate use,
 * refreshing and persisting a new one first if the stored token is
 * missing or has already expired (with a small safety buffer). This is
 * the "support token refresh when appropriate" mechanism the source
 * document (§9/§20) calls for — endpoints that call live Google APIs
 * should go through this rather than reading $account['accessToken']
 * directly, so a connection that's been idle past the (short-lived)
 * Google access token expiry doesn't fail outright.
 */
function googleBusinessProfileValidAccessToken(mysqli $con, array $account, array $oauthSettings): string
{
    $expiry = $account['tokenExpiry'] ?? null;
    $stillValid = $expiry !== null && strtotime($expiry) > (time() + 60);

    if ($stillValid && $account['accessToken'] !== '') {
        return $account['accessToken'];
    }

    if ($account['refreshToken'] === '') {
        throw new RuntimeException('This Google Business Profile connection has no refresh token on file and must be reconnected.');
    }

    $tokens = refreshGoogleBusinessProfileAccessToken(
        $oauthSettings['googleClientId'],
        $oauthSettings['googleClientSecret'],
        $account['refreshToken']
    );

    if ($tokens['accessToken'] === '') {
        throw new RuntimeException('Unable to refresh the Google Business Profile access token.');
    }

    updateGoogleBusinessProfileAccountTokens($con, $account['id'], $tokens['accessToken'], $tokens['refreshToken'], $tokens['tokenExpiry']);

    return $tokens['accessToken'];
}

/*
|--------------------------------------------------------------------------
| User identity + Business Profile account/location discovery
|--------------------------------------------------------------------------
*/

/**
 * GET the OIDC userinfo endpoint — the officially documented way to
 * retrieve the authenticated Google user's identity after the token
 * exchange, without needing to parse/verify the id_token JWT ourselves
 * (same rationale LinkedInAutomation.php's fetchLinkedinMemberProfile()
 * uses). Returns only what this module actually stores: the Google user
 * id ('sub') and email.
 */
function fetchGoogleUserProfile(string $accessToken): array
{
    $profile = googleBusinessProfileApiRequest(GOOGLE_USERINFO_URL, $accessToken, 'GET');

    return [
        'googleUserId' => (string)($profile['sub'] ?? ''),
        'googleUserEmail' => (string)($profile['email'] ?? ''),
    ];
}

/**
 * Discovers the Business Profile accounts accessible to the authenticated
 * Google user (GET .../v1/accounts, Account Management API). Per source
 * doc §10, a Google identity may have more than one — this returns all of
 * them (single page; this foundation phase does not paginate beyond the
 * API's default page, matching the equivalent simplification in
 * fetchLinkedinManagedOrganizations()/fetchPinterestBoards()). Throws
 * GoogleBusinessProfilePermissionException if the Google Cloud project's
 * Business Profile API access has not been approved yet.
 */
function fetchGoogleBusinessProfileAccounts(string $accessToken): array
{
    $response = googleBusinessProfileApiRequest(GOOGLE_ACCOUNT_MANAGEMENT_API_BASE . '/accounts', $accessToken, 'GET');

    $accounts = [];

    foreach (($response['accounts'] ?? []) as $account) {
        $resourceName = (string)($account['name'] ?? ''); // "accounts/{id}"
        $id = preg_match('#^accounts/(.+)$#', $resourceName, $matches) ? $matches[1] : $resourceName;

        $accounts[] = [
            'id' => $id,
            'name' => (string)($account['accountName'] ?? ('Account #' . $id)),
            'type' => (string)($account['type'] ?? ''),
        ];
    }

    return $accounts;
}

/**
 * Discovers the locations belonging to one Business Profile account (GET
 * .../v1/accounts/{id}/locations, Business Information API). readMask is
 * a required query parameter on this endpoint per Google's current
 * documentation — requests exactly the fields this module stores
 * (name/title/storefrontAddress), nothing broader.
 */
function fetchGoogleBusinessProfileLocations(string $accessToken, string $googleAccountId): array
{
    $url = GOOGLE_BUSINESS_INFORMATION_API_BASE . '/accounts/' . rawurlencode($googleAccountId) . '/locations?'
        . http_build_query([
            'readMask' => 'name,title,storefrontAddress',
            'pageSize' => 100,
        ]);

    $response = googleBusinessProfileApiRequest($url, $accessToken, 'GET');

    $locations = [];

    foreach (($response['locations'] ?? []) as $location) {
        $resourceName = (string)($location['name'] ?? ''); // "locations/{id}"
        $id = preg_match('#^locations/(.+)$#', $resourceName, $matches) ? $matches[1] : $resourceName;

        $locations[] = [
            'id' => $id,
            'title' => (string)($location['title'] ?? ('Location #' . $id)),
            'address' => isset($location['storefrontAddress']) ? json_encode($location['storefrontAddress']) : null,
        ];
    }

    return $locations;
}

/**
 * Re-verifies (server-side) that $googleAccountId is actually one the
 * authenticated Google user has access to, before it's ever persisted —
 * never trust a browser-submitted account id/name pair directly.
 */
function googleBusinessProfileAccountAccessible(string $accessToken, string $googleAccountId): ?array
{
    foreach (fetchGoogleBusinessProfileAccounts($accessToken) as $account) {
        if ($account['id'] === $googleAccountId) {
            return $account;
        }
    }

    return null;
}

/**
 * Re-verifies (server-side) that $googleLocationId actually belongs to
 * $googleAccountId, before it's ever persisted — never trust a
 * browser-submitted location id/title pair directly. This is the
 * "server-side ownership/access validation" the source document (§11)
 * explicitly requires, mirroring Pinterest's board re-verification.
 */
function googleBusinessProfileLocationBelongsToAccount(string $accessToken, string $googleAccountId, string $googleLocationId): ?array
{
    foreach (fetchGoogleBusinessProfileLocations($accessToken, $googleAccountId) as $location) {
        if ($location['id'] === $googleLocationId) {
            return $location;
        }
    }

    return null;
}
