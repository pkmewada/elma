<?php
/*
|--------------------------------------------------------------------------
| Lead capture integrations: settings, mapping, logging, provider auth
|--------------------------------------------------------------------------
| Admin-side helpers (settings CRUD, logs) reuse permission-helper.php like
| every other settings page. The public webhook/endpoint files
| (api/integrations/meta-webhook.php, google-lead.php, website-lead.php) are
| exempted from session auth in api-gateway.php and use the verify*()
| functions below instead -- provider signature/secret, never a CRM session.
|
| Secrets are stored as one encrypted JSON blob per provider
| (integrationSettings.secretEncrypted, includes/Crypto.php) - never logged,
| never returned to the settings page (write-only from the UI's point of
| view; the page only shows whether a secret is currently set).
*/
require_once __DIR__ . '/Crypto.php';
require_once __DIR__ . '/leadActivityLogger.php';

const INTEGRATION_PROVIDERS = ['meta' => 'Meta Lead Ads', 'google' => 'Google Lead Forms', 'website' => 'Website Lead Capture', 'whatsapp' => 'WhatsApp Cloud API'];

function integrationJsonExit(int $statusCode, string $message, array $data = []): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message, 'data' => $data]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Settings (admin side)
|--------------------------------------------------------------------------
*/

function getIntegrationSetting(mysqli $con, string $provider): ?array
{
    if (!isset(INTEGRATION_PROVIDERS[$provider])) {
        return null;
    }

    $stmt = mysqli_prepare($con, 'SELECT * FROM integrationSettings WHERE provider = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $provider);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

/** Decrypted secret fields for one provider (used only by the provider's own webhook/endpoint). */
function getIntegrationSecrets(mysqli $con, string $provider): array
{
    $row = getIntegrationSetting($con, $provider);
    $decrypted = $row ? decryptSecret((string)($row['secretEncrypted'] ?? '')) : '';
    $secrets = $decrypted !== '' ? json_decode($decrypted, true) : null;

    return is_array($secrets) ? $secrets : [];
}

function getIntegrationConfig(mysqli $con, string $provider): array
{
    $row = getIntegrationSetting($con, $provider);
    $config = $row && $row['configJson'] ? json_decode((string)$row['configJson'], true) : null;

    return is_array($config) ? $config : [];
}

/**
 * Non-secret $config (assoc array, JSON-encoded as-is) and $secrets (assoc
 * array of provider fields, encrypted together as one JSON blob). Pass
 * $secrets as null to leave the currently-stored secret unchanged (so the
 * settings form never needs to round-trip a decrypted value back to save).
 */
function saveIntegrationSettings(mysqli $con, string $provider, bool $isEnabled, array $config, ?array $secrets): void
{
    if (!isset(INTEGRATION_PROVIDERS[$provider])) {
        integrationJsonExit(422, 'Unknown integration provider.');
    }

    $configJson = json_encode($config);

    if ($secrets !== null) {
        $secretEncrypted = array_filter($secrets, static fn($v) => trim((string)$v) !== '') ? encryptSecret(json_encode($secrets)) : '';
        $stmt = mysqli_prepare(
            $con,
            'UPDATE integrationSettings SET isEnabled = ?, configJson = ?, secretEncrypted = ? WHERE provider = ?'
        );
        mysqli_stmt_bind_param($stmt, 'isss', $isEnabled, $configJson, $secretEncrypted, $provider);
    } else {
        $stmt = mysqli_prepare(
            $con,
            'UPDATE integrationSettings SET isEnabled = ?, configJson = ? WHERE provider = ?'
        );
        mysqli_stmt_bind_param($stmt, 'iss', $isEnabled, $configJson, $provider);
    }

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function recordIntegrationSuccess(mysqli $con, string $provider): void
{
    $stmt = mysqli_prepare($con, 'UPDATE integrationSettings SET lastSuccessAt = NOW() WHERE provider = ?');
    mysqli_stmt_bind_param($stmt, 's', $provider);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function recordIntegrationError(mysqli $con, string $provider, string $message): void
{
    $message = mb_substr($message, 0, 255);
    $stmt = mysqli_prepare($con, 'UPDATE integrationSettings SET lastErrorAt = NOW(), lastErrorMessage = ? WHERE provider = ?');
    mysqli_stmt_bind_param($stmt, 'ss', $message, $provider);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/*
|--------------------------------------------------------------------------
| Form -> project/assignee mapping (Meta / Google)
|--------------------------------------------------------------------------
*/

function getFormMapping(mysqli $con, string $provider, string $externalFormId): ?array
{
    $stmt = mysqli_prepare(
        $con,
        'SELECT * FROM integrationFormMappings WHERE provider = ? AND externalFormId = ? AND isActive = 1 LIMIT 1'
    );
    mysqli_stmt_bind_param($stmt, 'ss', $provider, $externalFormId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

/*
|--------------------------------------------------------------------------
| Integration log (append-only)
|--------------------------------------------------------------------------
*/

function logIntegrationEvent(
    mysqli $con,
    string $provider,
    ?string $externalId,
    string $eventType,
    ?int $leadId,
    string $status,
    string $message,
    array $payloadSummary = []
): void {
    $summary = $payloadSummary ? mb_substr(json_encode($payloadSummary), 0, 500) : null;
    $message = mb_substr($message, 0, 255);

    $stmt = mysqli_prepare(
        $con,
        'INSERT INTO integrationLogs (provider, externalId, eventType, leadId, status, message, payloadSummary, receivedAt, processedAt)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );
    mysqli_stmt_bind_param($stmt, 'sssisss', $provider, $externalId, $eventType, $leadId, $status, $message, $summary);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($status === 'error') {
        recordIntegrationError($con, $provider, $message);
    } elseif ($status === 'created') {
        recordIntegrationSuccess($con, $provider);
    }
}

/*
|--------------------------------------------------------------------------
| Provider authentication (public endpoints only -- never a CRM session)
|--------------------------------------------------------------------------
*/

/** Meta signs the raw POST body with the App Secret: sha256=<hmac>. */
function verifyMetaSignature(string $rawBody, string $appSecret, ?string $header): bool
{
    if ($appSecret === '' || !$header || strpos($header, 'sha256=') !== 0) {
        return false;
    }

    $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);

    return hash_equals($expected, $header);
}

/** Meta's webhook subscribe handshake: GET with hub.mode/hub.verify_token/hub.challenge. */
function verifyMetaChallenge(string $verifyToken, string $mode, string $token): bool
{
    return $verifyToken !== '' && $mode === 'subscribe' && hash_equals($verifyToken, $token);
}

/** Google Lead Form Extensions posts a shared secret ("google_key") with the lead payload. */
function verifyGoogleKey(string $configuredKey, ?string $submittedKey): bool
{
    return $configuredKey !== '' && $submittedKey !== null && hash_equals($configuredKey, $submittedKey);
}

/** Website form posts a configured API key in the X-Integration-Key header. */
function verifyWebsiteApiKey(string $configuredKey, ?string $submittedKey): bool
{
    return $configuredKey !== '' && $submittedKey !== null && hash_equals($configuredKey, $submittedKey);
}

/**
 * Minimal Graph API GET (no SDK) -- used to fetch a Meta lead's field_data
 * from its leadgen_id, since the webhook payload never contains full
 * customer fields. Returns the decoded response or null on any failure
 * (network error, invalid token, missing lead); never throws to the caller.
 */
function metaGraphApiGet(string $path, string $accessToken, ?string &$error = null): ?array
{
    $url = 'https://graph.facebook.com/v21.0/' . ltrim($path, '/') . '?access_token=' . urlencode($accessToken);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $decoded = $response === false ? null : json_decode($response, true);

    if ($response === false || $httpCode !== 200 || !is_array($decoded)) {
        // Safe to store: Meta's error text and curl transport text never contain the token.
        $metaMessage = is_array($decoded) ? ($decoded['error']['message'] ?? null) : null;
        $error = $metaMessage ?: ($response === false ? 'Could not reach Meta: ' . $curlError : 'HTTP ' . $httpCode);

        return null;
    }

    return $decoded;
}

/**
 * Splits a digits-only external phone into the CRM's (countryCode, phone)
 * shape. Meta/Google send E.164 ("+919876543210"); the CRM stores the local
 * 10 digits with "+91" (same convention as splitWaIdForLead()). Any other
 * international number is kept whole with no country code, never guessed.
 */
function splitExternalPhone(string $digits): array
{
    if (preg_match('/^(?:91|0)?(\d{10})$/', $digits, $m)) {
        return ['countryCode' => '+91', 'country' => 'India', 'phone' => $m[1]];
    }

    return ['countryCode' => '', 'country' => '', 'phone' => $digits];
}

/** Meta's field_data is [{name: 'full_name', values: ['John Doe']}, ...]; flatten to name => value. */
function flattenMetaFieldData(array $fieldData): array
{
    $flat = [];
    foreach ($fieldData as $field) {
        $name = (string)($field['name'] ?? '');
        $value = $field['values'][0] ?? null;
        if ($name !== '' && $value !== null) {
            $flat[$name] = (string)$value;
        }
    }

    return $flat;
}

/**
 * Common field-name normalization for Meta/Google lead payloads -- unknown
 * field names are ignored (never crash lead creation), a handful of known
 * aliases map onto the CRM's fields, and anything else useful is folded
 * into a remark rather than invented as a new CRM field.
 */
function normalizeExternalLeadFields(array $raw): array
{
    $get = static function (array $raw, array $keys): string {
        foreach ($keys as $key) {
            if (!empty($raw[$key])) {
                return trim((string)$raw[$key]);
            }
        }

        return '';
    };

    $fullName = $get($raw, ['full_name', 'fullName', 'name']);

    if ($fullName === '') {
        $first = $get($raw, ['first_name', 'firstName']);
        $last = $get($raw, ['last_name', 'lastName']);
        $fullName = trim($first . ' ' . $last);
    }

    $phone = preg_replace('/\D/', '', $get($raw, ['phone_number', 'phone', 'phoneNumber'])) ?? '';
    $email = $get($raw, ['email', 'email_address']);
    $projectText = $get($raw, ['project', 'interested_project', 'property_interest']);
    $message = $get($raw, ['message', 'notes', 'comments']);

    $knownKeys = ['full_name', 'fullName', 'name', 'first_name', 'firstName', 'last_name', 'lastName',
        'phone_number', 'phone', 'phoneNumber', 'email', 'email_address', 'project', 'interested_project',
        'property_interest', 'message', 'notes', 'comments'];

    $extras = [];
    foreach ($raw as $key => $value) {
        if (!in_array($key, $knownKeys, true) && is_scalar($value) && trim((string)$value) !== '') {
            $extras[] = trim((string)$key) . ': ' . trim((string)$value);
        }
    }

    if ($extras) {
        $message = trim($message . ($message !== '' ? "\n" : '') . implode("\n", $extras));
    }

    return [
        'fullName' => mb_substr($fullName !== '' ? $fullName : 'Unknown', 0, 100),
        'phone' => $phone,
        'email' => $email !== '' ? mb_substr($email, 0, 150) : '',
        'projectText' => $projectText,
        'remark' => mb_substr($message, 0, 2000),
    ];
}
