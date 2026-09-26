<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/walkInCandidateEngine.php';

$rawInput = file_get_contents('php://input');
$payload = json_decode((string)$rawInput, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

try {
    $engine = new WalkInCandidateEngine($con);
    $engine->updateStatus($payload['id'] ?? 0, $payload['status'] ?? '');

    echo json_encode(['success' => true, 'message' => 'Status updated successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
