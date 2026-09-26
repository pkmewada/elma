<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadFollowUpEngine.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['candidateId']) && empty($_SESSION['userId'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Same scoping rule as getScheduledCalls.php: admins (userId session) see
// every lead's follow ups, employees only see follow ups for leads they
// created/own.
$isAdmin = !empty($_SESSION['userId']);
$candidateId = (int)($_SESSION['candidateId'] ?? 0);

$filters = [
    'status' => trim((string)($_GET['status'] ?? '')),
    'dateFrom' => trim((string)($_GET['dateFrom'] ?? '')),
    'dateTo' => trim((string)($_GET['dateTo'] ?? '')),
    'search' => trim((string)($_GET['search'] ?? '')),
    'leadStatus' => trim((string)($_GET['leadStatus'] ?? '')),
    'scopeCandidateId' => $isAdmin ? 0 : $candidateId,
];

try {
    $engine = new LeadFollowUpEngine($con);
    echo json_encode(['success' => true, 'data' => $engine->getFollowUpList($filters)]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
