<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Csrf.php';
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

    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $action = isset($input['action']) ? trim($input['action']) : '';
    $userId = (int)$_SESSION['userId'];

    $engine = new SocialContentProductionEngine($con);

    switch ($action) {
        case 'assign':
            $task = $engine->assign(
                $id,
                $input['editorId'] ?? 0,
                $userId,
                'admin',
                $input['dueAt'] ?? null,
                $input['remark'] ?? null
            );
            break;

        case 'due_date':
            $task = $engine->setDueAt($id, $input['dueAt'] ?? null, $userId, 'admin', $input['remark'] ?? null);
            break;

        case 'approve':
            $task = $engine->review($id, 'approve', $userId, 'admin', $input['remark'] ?? null);
            break;

        case 'request_correction':
            $task = $engine->review($id, 'request_correction', $userId, 'admin', $input['remark'] ?? null);
            break;

        case 'mark_ready':
            $task = $engine->markReady($id, $userId, 'admin');
            break;

        case 'send_to_caption':
            $task = $engine->sendToCaption($id, $userId, 'admin');
            break;

        // Other Graphic Content only ("What do you want to do with this
        // content?" modal, Send to Client option). No client-delivery
        // mechanism exists in this codebase -- this only records the
        // decision in the task's own Production History (reuses the
        // existing, already-public recordExternalEvent(), same as
        // SocialAutomationHandoffEngine already does for its own events).
        // No lock, no new column/table.
        case 'send_to_client':
            $engine->recordExternalEvent($id, 'sent_to_client', $input['remark'] ?? 'Marked as sent to client.', $userId, 'admin');
            $task = $engine->getTask($id);
            break;

        case 'review_status':
            $task = $engine->updateReviewStatus(
                $id,
                $input['reviewStatus'] ?? '',
                $userId,
                'admin',
                $input['remark'] ?? null
            );
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action.']);
            exit;
    }

    echo json_encode(['success' => true, 'data' => $task, 'message' => $task['reviewMessage'] ?? null]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
