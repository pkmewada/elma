<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/socialMediaSetupEngine.php';

if (!isset($_SESSION['userId'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $engine = new SocialMediaSetupEngine($con);

    echo json_encode([
        'success' => true,
        'data' => [
            'featureConfig' => $engine->getFeatureConfig(),
            'planningRules' => $engine->getPlanningRules(),
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
