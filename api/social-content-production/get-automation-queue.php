<?php
/*
|--------------------------------------------------------------------------
| Automation Queue — read-only listing
|--------------------------------------------------------------------------
|
| Backs the /social-automation page. Thin wrapper around
| SocialAutomationHandoffEngine::listQueue() -- this file owns no query
| logic of its own, same convention as get-tasks.php in this directory.
|
*/
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/SocialAutomationHandoffEngine.php';

if (!isset($_SESSION['userId'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $filters = [
        'clientId' => isset($_GET['clientId']) ? (int)$_GET['clientId'] : 0,
        'status' => isset($_GET['status']) ? trim($_GET['status']) : '',
        'fromDate' => isset($_GET['fromDate']) ? trim($_GET['fromDate']) : '',
        'toDate' => isset($_GET['toDate']) ? trim($_GET['toDate']) : '',
    ];

    $engine = new SocialAutomationHandoffEngine($con);
    $rows = $engine->listQueue($filters);

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Unable to load the automation queue.']);
}
