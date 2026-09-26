<?php
require_once __DIR__ . '/../../includes/permission-helper.php';

requireApiPermission('/candidate-record', 'canEdit');

require_once '../../includes/db.php';

$candidateId = (int) $_POST['candidateId'];
$remark = trim($_POST['remark']);
$followUpType = $_POST['followUpType'] ?? null;
$followUpDateTime = $_POST['followUpDateTime'] ?? null;

$stmt = mysqli_prepare($con, "
    INSERT INTO candidateRemarks 
    (candidateId, remark, followUpType, followUpDateTime)
    VALUES (?, ?, ?, ?)
");

mysqli_stmt_bind_param($stmt, "isss", $candidateId, $remark, $followUpType, $followUpDateTime);
mysqli_stmt_execute($stmt);

echo json_encode(['success' => true]);