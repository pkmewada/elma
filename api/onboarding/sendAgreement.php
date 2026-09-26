<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadEngine.php';
require_once __DIR__ . '/../../includes/permission-helper.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Special Action, checked on the caller's own page (admin or employee).
requireApiActionPermission(['/oboarding-lead', '/emp-onboarding-lead'], 'send_agreement');

$userId = getLoggedInUserId();



if (
    $userId <= 0
) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.'
    ]);

    exit;
}

$leadId =
    (int)($_POST['leadId'] ?? 0);

if ($leadId <= 0) {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid lead.'
    ]);

    exit;
}

$leadEngine =
    new LeadEngine($con);

$response =
    $leadEngine->sendAgreementMail(
        $leadId
    );

echo json_encode(
    $response
);