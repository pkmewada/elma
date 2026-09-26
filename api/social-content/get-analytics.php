<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/socialContentEngine.php';

if (!isset($_SESSION['userId'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $clientId = isset($_GET['clientId']) ? (int)$_GET['clientId'] : 0;
    $startDate = isset($_GET['startDate']) ? trim($_GET['startDate']) : '';
    $endDate = isset($_GET['endDate']) ? trim($_GET['endDate']) : '';
    // filterType ('month' | 'week' | 'custom') only steers how the caller
    // picked startDate/endDate — the query itself is always a plain date
    // range, so it isn't used here beyond validating it's one of the three.
    $filterType = isset($_GET['filterType']) ? trim($_GET['filterType']) : 'month';

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        echo json_encode(['success' => false, 'message' => 'Invalid date range']);
        exit;
    }
    if ($startDate > $endDate) {
        echo json_encode(['success' => false, 'message' => 'startDate must not be after endDate']);
        exit;
    }
    if (!in_array($filterType, ['month', 'week', 'custom'], true)) {
        $filterType = 'month';
    }

    $engine = new SocialContentEngine($con);
    $data = $engine->getAnalytics($clientId, $startDate, $endDate);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
