<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../app/models/AssetModel.php';
require_once __DIR__ . '/../../includes/permission-helper.php';

header('Content-Type: application/json');

requireApiPermission('/assets-management', 'canAdd');

try {

    $assetName     = trim($_POST['assetName'] ?? '');
    $categoryId    = (int)($_POST['categoryId'] ?? 0);
    $brand         = trim($_POST['brand'] ?? '');
    $serialNumber  = trim($_POST['serialNumber'] ?? '');

    if ($assetName === '') {
        throw new Exception('Asset name is required');
    }

    $model = new AssetModel($con);

    $result = $model->createAsset([
        'assetName'    => $assetName,
        'categoryId'   => $categoryId,
        'brand'        => $brand,
        'serialNumber' => $serialNumber
    ]);

    if (!$result) {
        throw new Exception('Failed to create asset');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Asset created successfully'
    ]);

} catch (Throwable $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}