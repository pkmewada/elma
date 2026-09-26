<?php

require_once __DIR__ . '/Crypto.php';

/*
|--------------------------------------------------------------------------
| AI Caption Config — central configuration layer
|--------------------------------------------------------------------------
|
| The one place that resolves Caption Area's AI provider settings, DB
| first, falling back to the pre-existing environment variables
| (MODLUS_AI_CAPTION_PROVIDER, ANTHROPIC_API_KEY, MODLUS_OLLAMA_HOST,
| MODLUS_OLLAMA_MODEL) so environment-based deployments keep working
| unchanged. CaptionGeneratorFactory is the only caller — it still owns
| provider *selection* (which class to instantiate); this file only owns
| *where the values come from*.
|
| Single active-row settings table (aiCaptionSettings), identical pattern
| to includes/InstagramAutomation.php's instagramSettings. The API key is
| encrypted at rest with the existing includes/Crypto.php
| encryptSecret()/decryptSecret() (AES-256-CBC via ENCRYPTION_KEY) — the
| same mechanism instagramSettings.metaAppSecret already uses. No new
| encryption scheme, no plaintext key ever written to the database.
|
| Model/host fields are intentionally NOT given a hardcoded default here —
| when the DB has no override, null is returned and each generator class's
| own existing constructor fallback (env var, then its own hardcoded
| default) runs completely unchanged. Only `provider` is fully resolved
| here, since CaptionGeneratorFactory's switch needs a definitive value.
|
*/

function ensureAiCaptionSettingsTable(mysqli $con): void
{
    mysqli_query(
        $con,
        "CREATE TABLE IF NOT EXISTS aiCaptionSettings (
            id INT NOT NULL AUTO_INCREMENT,
            provider VARCHAR(20) NOT NULL DEFAULT 'anthropic',
            anthropicApiKey TEXT NULL,
            anthropicModel VARCHAR(100) NULL,
            ollamaHost VARCHAR(255) NULL,
            ollamaModel VARCHAR(100) NULL,
            isActive TINYINT(1) NOT NULL DEFAULT 1,
            updatedBy INT NULL,
            createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function getAiCaptionSettingsRow(mysqli $con): ?array
{
    ensureAiCaptionSettingsTable($con);

    $stmt = mysqli_prepare($con, 'SELECT * FROM aiCaptionSettings WHERE isActive = 1 ORDER BY id DESC LIMIT 1');
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

/**
 * The effective, resolved config CaptionGeneratorFactory builds providers
 * from. `provider` always has a real value (DB -> env -> 'anthropic').
 * Every other field is either the DB's own value (already decrypted for
 * anthropicApiKey) or null -- never re-derived from env here, so each
 * generator's own constructor fallback stays the single source of truth
 * for "what happens when nothing is configured".
 */
function getAiCaptionConfig(): array
{
    global $con;

    $row = getAiCaptionSettingsRow($con);

    $provider = trim((string) ($row['provider'] ?? ''));
    if ($provider === '') {
        $provider = strtolower(trim((string) getenv('MODLUS_AI_CAPTION_PROVIDER')));
    }
    if ($provider === '') {
        $provider = 'anthropic';
    }

    $anthropicApiKeyEncrypted = trim((string) ($row['anthropicApiKey'] ?? ''));
    $anthropicModel = trim((string) ($row['anthropicModel'] ?? ''));
    $ollamaHost = trim((string) ($row['ollamaHost'] ?? ''));
    $ollamaModel = trim((string) ($row['ollamaModel'] ?? ''));

    return [
        'provider' => $provider,
        'anthropicApiKey' => $anthropicApiKeyEncrypted !== '' ? decryptSecret($anthropicApiKeyEncrypted) : null,
        'hasAnthropicApiKey' => $anthropicApiKeyEncrypted !== '',
        'anthropicModel' => $anthropicModel !== '' ? $anthropicModel : null,
        'ollamaHost' => $ollamaHost !== '' ? $ollamaHost : null,
        'ollamaModel' => $ollamaModel !== '' ? $ollamaModel : null,
        'hasDbRow' => $row !== null,
    ];
}

/**
 * Safe-for-the-browser shape of the same config -- used by the AI
 * Configuration page/API to populate the form. Never includes the API key
 * itself, only whether one is set.
 */
function getAiCaptionConfigForDisplay(): array
{
    $config = getAiCaptionConfig();
    unset($config['anthropicApiKey']);

    return $config;
}

/**
 * @param array $input provider, anthropicApiKey (blank = keep existing),
 *   anthropicModel, ollamaHost, ollamaModel
 * @throws Exception on invalid provider/host — never on a missing/blank
 *   API key, which is allowed (Test Connection then reports "not
 *   configured", same as the environment-variable path always has).
 */
function saveAiCaptionConfig(array $input, int $userId): array
{
    global $con;

    $provider = strtolower(trim((string) ($input['provider'] ?? '')));
    if (!in_array($provider, ['anthropic', 'ollama'], true)) {
        throw new Exception('Invalid provider. Choose Anthropic or Ollama.');
    }

    $anthropicApiKey = trim((string) ($input['anthropicApiKey'] ?? ''));
    $anthropicModel = trim((string) ($input['anthropicModel'] ?? ''));
    $ollamaHost = trim((string) ($input['ollamaHost'] ?? ''));
    $ollamaModel = trim((string) ($input['ollamaModel'] ?? ''));

    if ($ollamaHost !== '') {
        // Same validation tier as instagramSettings.redirectUrl
        // (FILTER_VALIDATE_URL) plus an explicit scheme allow-list -- this
        // value is used server-side to make an outbound HTTP request, so a
        // malformed or non-http(s) scheme is rejected outright rather than
        // handed to Guzzle. Deliberately not restricted to
        // localhost-only: a legitimately remote/containerized Ollama host
        // is a normal deployment, and this endpoint is already admin-only.
        $scheme = strtolower((string) parse_url($ollamaHost, PHP_URL_SCHEME));
        if (!filter_var($ollamaHost, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
            throw new Exception('Enter a valid http:// or https:// Ollama host URL.');
        }
        $ollamaHost = rtrim($ollamaHost, '/');
    }

    if ($anthropicModel !== '' && strlen($anthropicModel) > 100) {
        throw new Exception('Model name is too long.');
    }
    if ($ollamaModel !== '' && strlen($ollamaModel) > 100) {
        throw new Exception('Model name is too long.');
    }

    ensureAiCaptionSettingsTable($con);
    $existing = getAiCaptionSettingsRow($con);

    // Blank submitted key = "keep whatever is already saved" (identical
    // convention to saveInstagramSettings()'s metaAppSecret handling) --
    // this is what lets the admin replace/update the key without the
    // masked input ever needing to reveal or round-trip the old value.
    $encryptedKey = $anthropicApiKey !== ''
        ? encryptSecret($anthropicApiKey)
        : (string) ($existing['anthropicApiKey'] ?? '');

    $anthropicModelValue = $anthropicModel !== '' ? $anthropicModel : null;
    $ollamaHostValue = $ollamaHost !== '' ? $ollamaHost : null;
    $ollamaModelValue = $ollamaModel !== '' ? $ollamaModel : null;

    if ($existing) {
        $stmt = mysqli_prepare(
            $con,
            'UPDATE aiCaptionSettings
             SET provider = ?, anthropicApiKey = ?, anthropicModel = ?, ollamaHost = ?, ollamaModel = ?, updatedBy = ?
             WHERE id = ?'
        );
        $existingId = (int) $existing['id'];
        mysqli_stmt_bind_param($stmt, 'sssssii', $provider, $encryptedKey, $anthropicModelValue, $ollamaHostValue, $ollamaModelValue, $userId, $existingId);
    } else {
        $stmt = mysqli_prepare(
            $con,
            'INSERT INTO aiCaptionSettings (provider, anthropicApiKey, anthropicModel, ollamaHost, ollamaModel, isActive, updatedBy)
             VALUES (?, ?, ?, ?, ?, 1, ?)'
        );
        mysqli_stmt_bind_param($stmt, 'sssssi', $provider, $encryptedKey, $anthropicModelValue, $ollamaHostValue, $ollamaModelValue, $userId);
    }

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_error($con);
        mysqli_stmt_close($stmt);
        throw new Exception('Failed to save AI configuration: ' . $error);
    }
    mysqli_stmt_close($stmt);

    return getAiCaptionConfigForDisplay();
}
