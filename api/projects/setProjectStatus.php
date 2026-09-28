<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/projectAccess.php';

// Activate / deactivate (no hard delete: leads keep their project).
// POST {id, isActive} + CSRF (gateway) + canEdit on /projects.
requireProjectPost();
requireProjectManage('canEdit');

$id = (int)($_POST['id'] ?? 0);
$project = requireProject($con, $id);
$isActive = (int)($_POST['isActive'] ?? 0) === 1 ? 1 : 0;

$stmt = mysqli_prepare($con, 'UPDATE projects SET isActive = ? WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'ii', $isActive, $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

saveActivityLog($con, 'Project', $id, $isActive ? 'ACTIVATE' : 'DEACTIVATE',
    ($isActive ? 'Project activated : ' : 'Project deactivated : ') . $project['projectName'],
    ['isActive' => (int)$project['isActive']], ['isActive' => $isActive]);

echo json_encode(['success' => true, 'message' => $isActive ? 'Project activated.' : 'Project deactivated.', 'data' => []]);
