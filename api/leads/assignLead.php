<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadAccess.php';

/*
|--------------------------------------------------------------------------
| Assign / reassign / unassign a lead
|--------------------------------------------------------------------------
| POST {leadId, employeeId (0 = unassign)} + CSRF (gateway). Admins, or
| employees with canEdit + the 'assign_lead' special action (Roles &
| Permissions) - never role names. The lead must be in the caller's scope;
| the target must be an active sales employee. Logged in Lead Activity.
*/
requireLeadPost();
requireLeadPermission('canEdit');

if (!canAssignLeads()) {
    leadJsonExit(403, 'You do not have permission to assign leads.');
}

$leadId = (int)($_POST['leadId'] ?? 0);
$lead = requireLeadAccess($con, $leadId);
$assignee = resolveAssignee($con, (int)($_POST['employeeId'] ?? 0));

$oldId = $lead['assignedToId'] !== null ? (int)$lead['assignedToId'] : null;
$newId = $assignee['id'] ?? null;

if ($oldId === $newId) {
    echo json_encode(['success' => true, 'message' => 'Assignment unchanged.', 'data' => ['assignedToId' => $newId]]);
    exit;
}

$stmt = mysqli_prepare($con, 'UPDATE leads SET assignedToId = ?, updatedAt = NOW() WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'ii', $newId, $leadId);

if (!mysqli_stmt_execute($stmt)) {
    error_log('assignLead failed: ' . mysqli_stmt_error($stmt));
    leadJsonExit(500, 'Failed to assign lead.');
}
mysqli_stmt_close($stmt);

$oldName = $lead['assignedToName'] ?? null;

if ($newId === null) {
    $action = 'UNASSIGN';
    $description = 'Lead unassigned from ' . ($oldName ?? 'salesperson');
} elseif ($oldId === null) {
    $action = 'ASSIGN';
    $description = 'Lead assigned to ' . $assignee['fullName'];
} else {
    $action = 'REASSIGN';
    $description = 'Lead reassigned from ' . ($oldName ?? 'previous salesperson') . ' to ' . $assignee['fullName'];
}

saveActivityLog($con, 'Lead', $leadId, $action, $description . ' : ' . $lead['fullName'],
    ['assignedToId' => $oldId, 'assignedTo' => $oldName],
    ['assignedToId' => $newId, 'assignedTo' => $assignee['fullName'] ?? null]
);

echo json_encode([
    'success' => true,
    'message' => $description . '.',
    'data' => ['assignedToId' => $newId, 'assignedToName' => $assignee['fullName'] ?? null],
]);
