<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['userId'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['updates']) || !is_array($input['updates'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$updates = $input['updates'];
$month = trim($input['month'] ?? date('Y-m'));

if (empty($updates)) {
    echo json_encode(['success' => false, 'message' => 'No updates to save']);
    exit;
}

$success = true;
$errorCount = 0;

foreach ($updates as $update) {
    $clientMasterId = (int)($update['clientMasterId'] ?? 0);
    $platform = trim($update['platform'] ?? '');
    $feature = trim($update['feature'] ?? '');
    $subFeature = trim($update['subFeature'] ?? '');
    $plannedCount = (int)($update['plannedCount'] ?? 0);
    
    if ($clientMasterId <= 0 || empty($platform)) {
        continue;
    }
    
    // Check if record exists
    $stmt = mysqli_prepare($con, "
        SELECT id FROM clientDeliverables 
        WHERE clientMasterId = ? AND platform = ? AND feature = ? AND subFeature = ? AND month = ?
    ");
    mysqli_stmt_bind_param($stmt, 'issss', $clientMasterId, $platform, $feature, $subFeature, $month);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $existing = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($existing) {
        // Update existing
        $stmt = mysqli_prepare($con, "
            UPDATE clientDeliverables 
            SET plannedCount = ?, updatedAt = NOW() 
            WHERE id = ?
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $plannedCount, $existing['id']);
    } else {
        // Insert new
        $stmt = mysqli_prepare($con, "
            INSERT INTO clientDeliverables 
            (clientMasterId, platform, feature, subFeature, plannedCount, month)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, 'isssis', $clientMasterId, $platform, $feature, $subFeature, $plannedCount, $month);
    }
    
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if (!$result) {
        $success = false;
        $errorCount++;
    }
}

echo json_encode([
    'success' => $success,
    'message' => $success ? 'All changes saved successfully' : "Some changes failed to save ($errorCount errors)",
    'errorCount' => $errorCount
]);