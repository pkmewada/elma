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

$userId = (int)($_SESSION['userId'] ?? 0);

try {
    $engine = new WalkInCandidateEngine($con);
    $id = $engine->saveCandidate($payload, $userId);

    echo json_encode([
        'success' => true,
        'message' => 'Walk-in candidate saved successfully.',
        'data' => ['id' => $id],
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
