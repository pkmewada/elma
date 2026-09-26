<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/permission-helper.php';
require_once __DIR__ . '/../../includes/Csrf.php';
require_once __DIR__ . '/../../includes/LinkedInAutomation.php';
require_once __DIR__ . '/../../includes/leadActivityLogger.php';

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.');
}

try {
    requireValidCsrfToken();
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}

$clientId = (int)($_POST['clientId'] ?? 0);
$organizationId = trim((string)($_POST['organizationId'] ?? ''));

if ($clientId <= 0 || !instagramClientExists($con, $clientId)) {
    respond(false, 'Please select a valid client.');
}

if ($organizationId === '') {
    respond(false, 'Please select an organization.');
}

$account = getLinkedinAccountByClientId($con, $clientId);

if (!$account) {
    respond(false, 'No connected LinkedIn account for this client.');
}

try {
    // Never trust the organization name the browser posted — re-verify
    // server-side, against LinkedIn itself, that this member actually
    // administers the requested organization id before persisting anything.
    $organizationName = linkedinMemberAdministersOrganization($account['accessToken'], $organizationId);

    if ($organizationName === null) {
        respond(false, 'This LinkedIn member does not administer the selected organization.');
    }

    $saved = saveLinkedinOrganizationSelection($con, $account['id'], $clientId, $organizationId, $organizationName);

    if ($saved) {
        $clientLabel = getInstagramClientLabel($con, $clientId);
        saveActivityLog(
            $con,
            'LinkedInAutomation',
            $account['id'],
            'select_organization',
            'LinkedIn organization "' . $organizationName . '" selected for Client: ' . $clientLabel . '.'
        );
    }

    respond(
        $saved,
        $saved ? 'LinkedIn organization saved successfully.' : 'Unable to save the selected LinkedIn organization.',
        ['linkedinAccount' => getLinkedinAccountForDisplay($con, $clientId)]
    );
} catch (LinkedinPermissionException $e) {
    respond(false, 'LinkedIn has not yet granted this app access to verify organizations '
        . '(Community Management API product access is pending approval). Details: ' . $e->getMessage());
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
