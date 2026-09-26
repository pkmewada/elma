<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/deliverableEngine.php';

if (!isset($_SESSION['userId'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $db = getDBConnection();
    $deliverableEngine = new DeliverableEngine($db);

    // Get all active features - using correct column names
    $sql = "SELECT id, platformId, featureName, displayOrder, isActive, createdAt
            FROM deliverableFeatures
            WHERE isActive = 1
            ORDER BY platformId, displayOrder";

    $result = $db->query($sql);
    if (!$result) {
        throw new Exception("Query failed: " . $db->error);
    }

    $features = [];
    while ($row = $result->fetch_assoc()) {
        if (!$deliverableEngine->isSocialFeatureAllowed($row['platformId'], $row['id'])) {
            continue;
        }
        $features[] = $row;
    }

    echo json_encode([
        'success' => true,
        'data' => $features
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>