<?php
require_once __DIR__ . '/../../includes/emp-auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/Csrf.php';
require_once __DIR__ . '/../../includes/SocialContentProductionEngine.php';

header('Content-Type: application/json');

try {
    requireValidCsrfToken();
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request data']);
        exit;
    }

    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $action = isset($input['action']) ? trim($input['action']) : '';
    $editorId = (int)$_SESSION['candidateId'];

    $engine = new SocialContentProductionEngine($con);

    switch ($action) {
        case 'start':
            $task = $engine->start($id, $editorId);
            break;

        // production submission moved to emp-submit-production.php — a bare
        // status flip with no actual output no longer represents a real
        // submission, and multipart/form-data (for file uploads) can't
        // share this endpoint's JSON-body request format

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action.']);
            exit;
    }

    echo json_encode(['success' => true, 'data' => $task]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
