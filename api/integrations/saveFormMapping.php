<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/permission-helper.php';
require_once __DIR__ . '/../../includes/leadAccess.php';
require_once __DIR__ . '/../../includes/integrationAccess.php';

/*
| Smallest useful mapping config: provider + externalFormId -> project +
| default assignee (Section 15). One row is add-or-update (matched on
| provider+externalFormId); toggling isActive=0 unmaps without deleting
| history.
*/
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    integrationJsonExit(405, 'Method not allowed.');
}

requireApiActionPermission(['/integrations'], 'manage_integrations');

$provider = trim((string)($_POST['provider'] ?? ''));
$externalFormId = trim((string)($_POST['externalFormId'] ?? ''));
$externalPageId = trim((string)($_POST['externalPageId'] ?? ''));
$formLabel = trim((string)($_POST['formLabel'] ?? ''));
$isActive = isset($_POST['isActive']) ? (int)!!$_POST['isActive'] : 1;

if (!in_array($provider, ['meta', 'google'], true)) {
    integrationJsonExit(422, 'Provide provider=meta or provider=google.');
}

if ($externalFormId === '' || mb_strlen($externalFormId) > 100) {
    integrationJsonExit(422, 'Form ID is required (max 100 characters).');
}

$projectId = (int)($_POST['projectId'] ?? 0);
$project = $projectId > 0 ? resolveLeadProject($con, $projectId) : null;

$assigneeId = (int)($_POST['defaultAssigneeId'] ?? 0);
$assignee = $assigneeId > 0 ? resolveAssignee($con, $assigneeId) : null;

$stmt = mysqli_prepare($con, '
    INSERT INTO integrationFormMappings (provider, externalFormId, externalPageId, formLabel, projectId, defaultAssigneeId, isActive)
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        externalPageId = VALUES(externalPageId),
        formLabel = VALUES(formLabel),
        projectId = VALUES(projectId),
        defaultAssigneeId = VALUES(defaultAssigneeId),
        isActive = VALUES(isActive)
');
$projectIdParam = $project['id'] ?? null;
$assigneeIdParam = $assignee['id'] ?? null;
$externalPageIdParam = $externalPageId !== '' ? $externalPageId : null;
$formLabelParam = $formLabel !== '' ? $formLabel : null;

mysqli_stmt_bind_param(
    $stmt,
    'ssssiii',
    $provider,
    $externalFormId,
    $externalPageIdParam,
    $formLabelParam,
    $projectIdParam,
    $assigneeIdParam,
    $isActive
);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

saveActivityLog($con, 'Integration', 0, 'MAPPING', ucfirst($provider) . ' form mapping saved for ' . $externalFormId, null, [
    'provider' => $provider, 'externalFormId' => $externalFormId, 'projectId' => $projectIdParam, 'defaultAssigneeId' => $assigneeIdParam,
]);

echo json_encode(['success' => true, 'message' => 'Mapping saved.', 'data' => []]);
