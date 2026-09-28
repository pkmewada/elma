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
    'whatsapp' => ['accessToken', 'appSecret', 'verifyToken'],
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

if ($provider === 'whatsapp') {
    $config['phoneNumberId'] = trim((string)($_POST['config']['phoneNumberId'] ?? ''));
    $config['wabaId'] = trim((string)($_POST['config']['wabaId'] ?? ''));
    $config['apiVersion'] = trim((string)($_POST['config']['apiVersion'] ?? '')) ?: null;

    // Templates are managed in Meta itself; the CRM only needs the approved
    // name/language/variable-count to build the send UI (Section 17) --
    // one template per line: name|language|variableCount|Display label
    $templates = [];
    foreach (explode("\n", (string)($_POST['config']['templatesRaw'] ?? '')) as $line) {
        $parts = array_map('trim', explode('|', $line));
        if (($parts[0] ?? '') === '') {
            continue;
        }
        $templates[] = [
            'name' => $parts[0],
            'language' => $parts[1] ?? 'en_US',
            'variableCount' => (int)($parts[2] ?? 0),
            'label' => $parts[3] ?? $parts[0],
        ];
    }
    $config['templates'] = $templates;
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

if ($isEnabled && $provider === 'whatsapp') {
    if (empty($secretsToSave['verifyToken'])) {
        integrationJsonExit(422, 'A verify token is required before WhatsApp can be enabled (needed for the webhook handshake).');
    }
    if (empty($secretsToSave['accessToken']) || $config['phoneNumberId'] === '') {
        integrationJsonExit(422, 'An access token and Phone Number ID are required before WhatsApp can be enabled.');
    }
}

saveIntegrationSettings($con, $provider, $isEnabled, $config, $secretsToSave);

saveActivityLog($con, 'Integration', 0, 'SETTINGS', INTEGRATION_PROVIDERS[$provider] . ' settings updated (' . ($isEnabled ? 'enabled' : 'disabled') . ')', null, [
    'provider' => $provider, 'isEnabled' => $isEnabled,
]);

echo json_encode(['success' => true, 'message' => 'Settings saved.', 'data' => []]);
