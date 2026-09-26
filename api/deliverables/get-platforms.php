<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!isset($_SESSION['userId'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $db = getDBConnection();
    
    $sql = "SELECT id, platformName, icon, displayOrder, isActive, createdAt 
            FROM deliverablePlatforms 
            WHERE isActive = 1 
            ORDER BY displayOrder";
    
    $result = $db->query($sql);
    $platforms = [];
    
    while ($row = $result->fetch_assoc()) {
        $platforms[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $platforms
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>