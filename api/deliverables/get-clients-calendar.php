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
    $clientId   = isset($_GET['client'])   ? (int)$_GET['client']   : null;
    $platformId = isset($_GET['platform']) ? (int)$_GET['platform'] : null;
    $monthIndex = isset($_GET['month'])    ? (int)$_GET['month']    : 0;

    $engine = new CalendarEngine($con);
    $data = $engine->getCalendarData($clientId, $platformId, $monthIndex);

    echo json_encode([
        'success' => true,
        'data' => $data['data'],
        'filters' => $data['filters'],
        'month' => $data['month']
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}