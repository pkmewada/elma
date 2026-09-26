<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Csrf.php';
require_once __DIR__ . '/../../includes/socialContentEngine.php';
require_once __DIR__ . '/../../includes/SocialContentProductionEngine.php';

if (!isset($_SESSION['userId'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

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

    $userId = (int)$_SESSION['userId'];
    $engine = new SocialContentEngine($con);
    $productionEngine = new SocialContentProductionEngine($con);

    // "Complete Entry" is no longer a separate manual step — every entry that
    // clears saveEntry()'s own minimum-content bar (title or raw content) is
    // automatically completed and handed to Production in the same request,
    // reusing the exact same engine methods (and the same transaction
    // pattern) the old manual complete-entry.php endpoint used.
    mysqli_begin_transaction($con);
    try {
        $entry = $engine->saveEntry($input, $userId);
        $entry = $engine->completeEntry($entry['id'], $userId);

        // idempotent: reuse an existing task instead of racing createTask()'s
        // own clash check — safe against double-click / retry / re-save
        $task = $productionEngine->getTaskByContentId($entry['id']);
        if (!$task) {
            $productionEngine->createTask($entry['id'], $userId, 'admin', 'Auto-created on save.');
        }

        mysqli_commit($con);
    } catch (Exception $e) {
        mysqli_rollback($con);
        throw $e;
    }

    echo json_encode(['success' => true, 'data' => $entry]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
