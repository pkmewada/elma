<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/integrationAccess.php';

requireApiPermission(['/integrations'], 'canView');

$data = [];

foreach (array_keys(INTEGRATION_PROVIDERS) as $provider) {
    $row = getIntegrationSetting($con, $provider);
    $secrets = getIntegrationSecrets($con, $provider);

    $data[] = [
        'provider' => $provider,
        'label' => INTEGRATION_PROVIDERS[$provider],
        'isEnabled' => (bool)($row['isEnabled'] ?? false),
        // Never return the secret itself -- only whether one is currently set.
        'hasSecret' => (bool)array_filter($secrets, static fn($v) => trim((string)$v) !== ''),
        'config' => getIntegrationConfig($con, $provider),
        'lastSuccessAt' => $row['lastSuccessAt'] ?? null,
        'lastErrorAt' => $row['lastErrorAt'] ?? null,
        'lastErrorMessage' => $row['lastErrorMessage'] ?? null,
    ];
}

$webhookUrl = static fn(string $file) => BASE_URL . '/api/integrations/' . $file;

echo json_encode([
    'success' => true,
    'data' => $data,
    'urls' => [
        'meta' => $webhookUrl('meta-webhook.php'),
        'google' => $webhookUrl('google-lead.php'),
        'website' => $webhookUrl('website-lead.php'),
    ],
]);
