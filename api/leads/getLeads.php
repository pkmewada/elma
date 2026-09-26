<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['candidateId']) && empty($_SESSION['userId'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.',
    ]);
    exit;
}

$allowedStatuses = [
    'open',
    'interested',
    'connected',
    'converted',
    'not_interested',
    'not_connected',
];

$status = trim((string)($_GET['status'] ?? ''));
$employeeId = (int)($_GET['employeeId'] ?? 0);

// Optional lead-date range on l.createdAt (the "Date" column of the Leads
// table), same dateFrom/dateTo naming and DATE() comparison as
// leadDashboardEngine so a To date includes that whole day.
$dateFrom = trim((string)($_GET['dateFrom'] ?? ''));
$dateTo = trim((string)($_GET['dateTo'] ?? ''));

if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid lead status filter.',
    ]);
    exit;
}

foreach ([$dateFrom, $dateTo] as $dateValue) {
    if ($dateValue !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid date filter.',
        ]);
        exit;
    }
}

if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'From Date cannot be after To Date.',
    ]);
    exit;
}

$where = [];
$params = [];
$types = '';

if (!empty($_SESSION['candidateId'])) {
    $where[] = 'l.createdByCandidateId = ?';
    $params[] = (int)$_SESSION['candidateId'];
    $types .= 'i';
} elseif ($employeeId > 0) {
    $where[] = 'l.createdByCandidateId = ?';
    $params[] = $employeeId;
    $types .= 'i';
}

if ($status !== '') {
    $where[] = 'l.status = ?';
    $params[] = $status;
    $types .= 's';
}

if ($dateFrom !== '') {
    $where[] = 'DATE(l.createdAt) >= ?';
    $params[] = $dateFrom;
    $types .= 's';
}

if ($dateTo !== '') {
    $where[] = 'DATE(l.createdAt) <= ?';
    $params[] = $dateTo;
    $types .= 's';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = mysqli_prepare(
    $con,
    "
    SELECT
        l.*,
        c.categoryName,
        p.planName,
        u.fullName AS employeeName
    FROM leads l
    LEFT JOIN leadCategories c ON c.id = l.categoryId
    LEFT JOIN leadPlans p ON p.id = l.planId
    LEFT JOIN employeeusers u ON u.id = l.createdByCandidateId
    {$whereSql}
    ORDER BY l.id DESC
    "
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to load leads.',
    ]);
    exit;
}

if ($types !== '') {
    $bindParams = [$types];
    foreach ($params as $key => $value) {
        $bindParams[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindParams);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$leads = [];
while ($row = mysqli_fetch_assoc($result)) {
    $leads[] = $row;
}

mysqli_stmt_close($stmt);

echo json_encode([
    'success' => true,
    'data' => $leads,
]);
