<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/calendarEngine.php';

if (!isset($_SESSION['userId'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $clientId = isset($_GET['clientId']) ? (int)$_GET['clientId'] : 0;
    $month = isset($_GET['month']) ? trim($_GET['month']) : '';

    if ($clientId <= 0 || !preg_match('/^\d{4}-\d{2}$/', $month)) {
        echo json_encode(['success' => false, 'message' => 'Invalid clientId or month']);
        exit;
    }

    $engine = new CalendarEngine($con);
    $plan = $engine->getClientCalendarPlan($clientId, $month);

    echo json_encode([
        'success' => true,
        'platforms' => $plan['platforms'],
        'saved_plans' => $plan['saved_plans']
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
