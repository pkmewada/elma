<?php
include __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/permission-helper.php';

requireApiPermission('/onboarding-queue', 'canApprove');

$id = (int)($_POST['id'] ?? 0);

mysqli_query($con,"
UPDATE employeeUsers
SET profileStatus='Verified'
WHERE candidateRecordId='$id'
");

echo json_encode(['success'=>true]);