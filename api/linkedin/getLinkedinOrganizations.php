<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/LinkedInAutomation.php';

header('Content-Type: application/json; charset=UTF-8');

function respond(bool $success, string $message, array $data = []): void
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ]);

    exit;
}

$clientId = (int)($_GET['clientId'] ?? 0);

if ($clientId <= 0) {
    respond(false, 'Please select a client.');
}

$account = getLinkedinAccountByClientId($con, $clientId);

if (!$account) {
    respond(false, 'No connected LinkedIn account for this client.');
}

try {
    $organizations = fetchLinkedinManagedOrganizations($account['accessToken']);

    respond(true, 'LinkedIn organizations loaded successfully.', ['organizations' => $organizations]);
} catch (LinkedinPermissionException $e) {
    // Not a bug — this app/member does not yet have LinkedIn's Community
    // Management API access approved. Surfaced distinctly so it reads as an
    // external dependency, not a broken feature (see docs Phase 12 section).
    respond(false, 'LinkedIn has not yet granted this app access to discover organizations '
        . '(Community Management API product access is pending approval). Details: ' . $e->getMessage());
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
