<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadAccess.php';

/*
| POST action=save {id?, sourceName} | action=toggle {id, isActive}
| + CSRF (gateway) + canEdit on /lead-setup. System sources (used by the
| integrations by sourceKey) cannot be renamed; any source can be
| deactivated (existing leads keep it). Nothing is deleted.
*/
requireLeadPost();
requireApiPermission(['/lead-setup'], 'canEdit');

$action = (string)($_POST['action'] ?? '');
$id = (int)($_POST['id'] ?? 0);

if ($action === 'toggle') {
    $isActive = (int)($_POST['isActive'] ?? 0) === 1 ? 1 : 0;
    $stmt = mysqli_prepare($con, 'UPDATE leadSources SET isActive = ? WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $isActive, $id);
    mysqli_stmt_execute($stmt);
    $changed = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($changed < 0) {
        leadJsonExit(404, 'Source not found.');
    }

    echo json_encode(['success' => true, 'message' => $isActive ? 'Source activated.' : 'Source deactivated.', 'data' => []]);
    exit;
}

if ($action !== 'save') {
    leadJsonExit(422, 'Invalid action.');
}

$sourceName = trim((string)($_POST['sourceName'] ?? ''));

if ($sourceName === '' || mb_strlen($sourceName) > 100) {
    leadJsonExit(422, 'Source name is required (max 100 characters).');
}

if ($id > 0) {
    $stmt = mysqli_prepare($con, 'SELECT isSystem FROM leadSources WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$existing) {
        leadJsonExit(404, 'Source not found.');
    }

    if ((int)$existing['isSystem'] === 1) {
        leadJsonExit(422, 'System sources cannot be renamed.');
    }

    $stmt = mysqli_prepare($con, 'UPDATE leadSources SET sourceName = ? WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'si', $sourceName, $id);
} else {
    $sourceKey = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($sourceName)) ?? '', '_') ?: 'source';
    $sourceKey = 'custom_' . substr($sourceKey, 0, 40);
    $stmt = mysqli_prepare($con, 'INSERT INTO leadSources (sourceKey, sourceName, isSystem, sortOrder) VALUES (?, ?, 0, 200)');
    mysqli_stmt_bind_param($stmt, 'ss', $sourceKey, $sourceName);
}

if (!mysqli_stmt_execute($stmt)) {
    $duplicate = mysqli_stmt_errno($stmt) === 1062;
    mysqli_stmt_close($stmt);
    leadJsonExit($duplicate ? 409 : 500, $duplicate ? 'A source with this name already exists.' : 'Unable to save source.');
}
mysqli_stmt_close($stmt);

echo json_encode(['success' => true, 'message' => 'Source saved.', 'data' => []]);
