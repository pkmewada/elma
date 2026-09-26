<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/permission-helper.php';

header('Content-Type: application/json');

requireApiPermission('/assets-management', 'canDelete');

$response = ['success' => false];

try {

    // =========================================================
    // ✅ VALIDATION
    // =========================================================
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        throw new Exception('Invalid asset ID');
    }

    // =========================================================
    // ✅ CHECK EXISTS
    // =========================================================
    $check = mysqli_query($con, "SELECT status FROM assetMaster WHERE id = $id LIMIT 1");

    if (!$check || mysqli_num_rows($check) === 0) {
        throw new Exception('Asset not found');
    }

    $asset = mysqli_fetch_assoc($check);

    // =========================================================
    // ❌ OPTIONAL SAFETY (recommended UX)
    // =========================================================
    if ($asset['status'] === 'assigned') {
        throw new Exception('Asset is currently assigned. Please return it first.');
    }

    // =========================================================
    // ✅ DELETE (CASCADE handles assignments)
    // =========================================================
    $delete = mysqli_query($con, "DELETE FROM assetMaster WHERE id = $id");

    if (!$delete) {
        throw new Exception('Failed to delete asset');
    }

    $response['success'] = true;

} catch (Exception $e) {

    $response['message'] = $e->getMessage();
}

echo json_encode($response);