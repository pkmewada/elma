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

$rawInput = file_get_contents('php://input');
$payload = json_decode((string)$rawInput, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$userId = !empty($_SESSION['candidateId'])
    ? (int)$_SESSION['candidateId']
    : (int)($_SESSION['userId'] ?? 0);

try {
    $engine = new LeadFollowUpEngine($con);
    $id = $engine->saveSetting($payload, $userId);

    echo json_encode([
        'success' => true,
        'message' => 'Follow up rule saved successfully.',
        'data' => ['id' => $id],
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
