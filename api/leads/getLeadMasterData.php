<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadAccess.php';

// Options for the lead form, filters and assignment modal (lead page view
// permission). Statuses are the system-defined pipeline; projects and
// sources come from their masters (active only).
requireLeadPermission('canView');

$projects = [];
$result = mysqli_query($con, 'SELECT id, projectName FROM projects WHERE isActive = 1 ORDER BY projectName ASC');
while ($row = mysqli_fetch_assoc($result)) {
    $projects[] = ['id' => (int)$row['id'], 'projectName' => $row['projectName']];
}

$sources = [];
$result = mysqli_query($con, 'SELECT id, sourceKey, sourceName FROM leadSources WHERE isActive = 1 ORDER BY sortOrder ASC, sourceName ASC');
while ($row = mysqli_fetch_assoc($result)) {
    $sources[] = ['id' => (int)$row['id'], 'sourceKey' => $row['sourceKey'], 'sourceName' => $row['sourceName']];
}

$canAssign = canAssignLeads();

echo json_encode([
    'success' => true,
    'message' => 'Lead master data loaded successfully.',
    'data' => [
        'statuses' => LEAD_STATUSES,
        'closingStatuses' => LEAD_CLOSING_STATUSES,
        'projects' => $projects,
        'sources' => $sources,
        'assignees' => $canAssign ? getAssignableEmployees($con) : [],
        'permissions' => [
            'canAssign' => $canAssign,
            'canViewAll' => canAccessAllLeads(),
            'canAdd' => hasRoutePermission(leadCallerRoute(), 'canAdd'),
            'canEdit' => hasRoutePermission(leadCallerRoute(), 'canEdit'),
            'canDelete' => hasRoutePermission(leadCallerRoute(), 'canDelete'),
        ],
    ],
]);
