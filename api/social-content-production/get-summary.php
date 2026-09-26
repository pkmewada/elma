<?php
/*
|--------------------------------------------------------------------------
| Production Summary — Phase 6
|--------------------------------------------------------------------------
|
| Server-side status/overdue/editor-workload counts for the manager queue.
| Thin wrapper only -- all counting logic lives in
| SocialContentProductionEngine::getProductionSummary(), reused as-is by
| the existing engine's own SQL/JOIN conventions. Never trust a client-side
| count of an already-filtered table.
|
*/
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/SocialContentProductionEngine.php';

if (!isset($_SESSION['userId'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $filters = [
        'clientId' => isset($_GET['clientId']) ? (int)$_GET['clientId'] : 0,
        'platformId' => isset($_GET['platformId']) ? (int)$_GET['platformId'] : 0,
        'fromDate' => isset($_GET['fromDate']) ? trim($_GET['fromDate']) : '',
        'toDate' => isset($_GET['toDate']) ? trim($_GET['toDate']) : '',
    ];

    $engine = new SocialContentProductionEngine($con);
    $summary = $engine->getProductionSummary($filters);

    echo json_encode(['success' => true, 'data' => $summary]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
