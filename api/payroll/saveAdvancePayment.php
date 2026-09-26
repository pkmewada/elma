<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/AdvancePaymentEngine.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$userId = (int)($_SESSION['userId'] ?? 0);

try {
    $engine = new AdvancePaymentEngine($con);
    $id = $engine->saveAdvance($payload, $userId);

    echo json_encode([
        'success' => true,
        'message' => 'Advance payment saved successfully.',
        'data' => ['id' => $id],
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
