<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!isset($_SESSION['userId'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$clientId = isset($_GET['clientId']) ? (int)$_GET['clientId'] : 0;
$month = isset($_GET['month']) ? trim($_GET['month']) : '';

if ($clientId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid client ID']);
    exit;
}

$sql = "
    SELECT 
        l.id,
        l.platformId,
        l.featureId,
        l.month,
        l.action,
        l.oldDates,
        l.newDates,
        l.oldIsEdited,
        l.newIsEdited,
        l.userId,
        l.createdAt,
        dp.platformName,
        df.featureName,
        u.fullName AS userName
    FROM clientCalendarActivityLog l
    LEFT JOIN deliverablePlatforms dp ON dp.id = l.platformId
    LEFT JOIN deliverableFeatures df ON df.id = l.featureId
    LEFT JOIN users u ON u.id = l.userId
    WHERE l.clientId = " . (int)$clientId .
    (!empty($month) ? " AND l.month = '" . mysqli_real_escape_string($con, $month) . "'" : "") .
    " ORDER BY l.createdAt DESC
";

$result = mysqli_query($con, $sql);
$logs = [];
while ($row = mysqli_fetch_assoc($result)) {
    $logs[] = [
        'id'           => (int)$row['id'],
        'platformId'   => (int)$row['platformId'],
        'featureId'    => (int)$row['featureId'],
        'platformName' => $row['platformName'] ?? 'Unknown',
        'featureName'  => $row['featureName'] ?? 'Unknown',
        'month'        => $row['month'],
        'action'       => $row['action'],
        'oldDates'     => $row['oldDates'] ? json_decode($row['oldDates'], true) : [],
        'newDates'     => json_decode($row['newDates'], true) ?: [],
        'oldIsEdited'  => $row['oldIsEdited'],
        'newIsEdited'  => $row['newIsEdited'],
        'userId'       => (int)$row['userId'],
        'userName'     => $row['userName'] ?? 'System',
        'createdAt'    => $row['createdAt']
    ];
}

echo json_encode([
    'success' => true,
    'data' => $logs
]);