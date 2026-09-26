<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadEngine.php';
require_once __DIR__ . '/../../includes/permission-helper.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
| Admins as before; employees need the /emp-onboarding-lead route permission.
*/
requireApiPermission(['/oboarding-lead', '/emp-onboarding-lead'], 'canEdit');

$createdByUserId = getLoggedInUserId();

if ($createdByUserId <= 0) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.'
    ]);

    exit();
}

/*
|--------------------------------------------------------------------------
| Method Validation
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);

    exit();
}

/*
|--------------------------------------------------------------------------
| Request Data
|--------------------------------------------------------------------------
*/
$rawInput =
    file_get_contents(
        'php://input'
    );

$payload =
    json_decode(
        (string)$rawInput,
        true
    );

if (!is_array($payload)) {
    $payload = $_POST;
}

/*
|--------------------------------------------------------------------------
| Inputs
|--------------------------------------------------------------------------
*/
$leadId =
    (int)($payload['leadId'] ?? 0);

$agreementContent =
    trim(
        (string)(
            $payload['agreementContent']
            ?? ''
        )
    );

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/
if (
    $leadId <= 0 ||
    $agreementContent === ''
) {

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Agreement content is required.'
    ]);

    exit();
}

/*
|--------------------------------------------------------------------------
| Save Draft
|--------------------------------------------------------------------------
*/
$leadEngine =
    new LeadEngine(
        $con
    );

$saved =
    $leadEngine->saveAgreementDraft(
        $leadId,
        $agreementContent,
        $createdByUserId
    );

if (!$saved) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Failed to save agreement.'
    ]);

    exit();
}

/*
|--------------------------------------------------------------------------
| Success
|--------------------------------------------------------------------------
*/
echo json_encode([
    'success' => true,
    'message' => 'Agreement draft saved successfully.'
]);