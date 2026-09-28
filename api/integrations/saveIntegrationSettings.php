<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadAccess.php';
require_once __DIR__ . '/../../includes/integrationAccess.php';

/*
| POST {provider, isEnabled, config[...], secrets[...]?} + CSRF (gateway) +
| 'manage_integrations' special action on /integrations. Leaving a secret
| field blank keeps the currently-stored value (so the form never needs to
| re-submit an already-saved secret); secrets are never echoed back.
*/
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    integrationJsonExit(405, 'Method not allowed.');
}

requireApiActionPermission(['/integrations'], 'manage_integrations');

$provider = trim((string)($_POST['provider'] ?? ''));

if (!isset(INTEGRATION_PROVIDERS[$provider])) {
    integrationJsonExit(422, 'Unknown integration provider.');
}

$isEnabled = !empty($_POST['isEnabled']);
$submittedSecrets = $_POST['secrets'] ?? [];
$submittedSecrets = is_array($submittedSecrets) ? $submittedSecrets : [];

$hasAnySecretInput = (bool)array_filter($submittedSecrets, static fn($v) => trim((string)$v) !== '');
$existing = getIntegrationSecrets($con, $provider);

// Merge: a blank field keeps whatever was already encrypted for it.
$secretFields = match ($provider) {
    'meta' => ['appSecret', 'pageAccessToken', 'verifyToken'],
    'google' => ['sharedKey'],
    'website' => ['apiKey'],
    default => [],
};

$secretsToSave = null;
if ($hasAnySecretInput || $existing) {
    $secretsToSave = [];
    foreach ($secretFields as $field) {
        $submitted = trim((string)($submittedSecrets[$field] ?? ''));
        $secretsToSave[$field] = $submitted !== '' ? $submitted : (string)($existing[$field] ?? '');
    }
}

$config = [];
if ($provider === 'website' || $provider === 'meta' || $provider === 'google') {
    $config['defaultSourceId'] = (int)($_POST['config']['defaultSourceId'] ?? 0) ?: null;
    $config['defaultAssigneeId'] = (int)($_POST['config']['defaultAssigneeId'] ?? 0) ?: null;
}

if (($config['defaultSourceId'] ?? null) !== null) {
    resolveLeadSource($con, $config['defaultSourceId']);
}

if (($config['defaultAssigneeId'] ?? null) !== null) {
    resolveAssignee($con, $config['defaultAssigneeId']);
}

if ($isEnabled && $provider === 'meta' && empty($secretsToSave['verifyToken'])) {
    integrationJsonExit(422, 'A verify token is required before Meta can be enabled (needed for the webhook handshake).');
}

if ($isEnabled && $provider === 'google' && empty($secretsToSave['sharedKey'])) {
    integrationJsonExit(422, 'A shared key is required before Google can be enabled.');
}

if ($isEnabled && $provider === 'website' && empty($secretsToSave['apiKey'])) {
    integrationJsonExit(422, 'An API key is required before the website endpoint can be enabled.');
}

saveIntegrationSettings($con, $provider, $isEnabled, $config, $secretsToSave);

saveActivityLog($con, 'Integration', 0, 'SETTINGS', INTEGRATION_PROVIDERS[$provider] . ' settings updated (' . ($isEnabled ? 'enabled' : 'disabled') . ')', null, [
    'provider' => $provider, 'isEnabled' => $isEnabled,
]);

echo json_encode(['success' => true, 'message' => 'Settings saved.', 'data' => []]);
