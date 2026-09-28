<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadFollowUpEngine.php';
require_once __DIR__ . '/../../includes/leadAccess.php';

// Follow-up views: today | upcoming | overdue | completed (+ optional
// leadId / search). Same lead scope as every lead API: admins and
// 'view-all-leads' employees see all, others only their assigned leads.
requireApiPermission([LEAD_ADMIN_ROUTE, LEAD_EMPLOYEE_ROUTE, '/lead-follow-up-list', '/emp-follow-ups'], 'canView');

$filters = [
    'view' => trim((string)($_GET['view'] ?? 'today')),
    'leadId' => (int)($_GET['leadId'] ?? 0),
    'search' => trim((string)($_GET['search'] ?? '')),
    'scopeEmployeeId' => getLeadScopeEmployeeId(),
];

try {
    $engine = new LeadFollowUpEngine($con);
    echo json_encode([
        'success' => true,
        'data' => $engine->getFollowUpList($filters),
        'counts' => $engine->getViewCounts($filters['scopeEmployeeId']),
    ]);
} catch (Exception $e) {
    leadJsonExit(422, $e->getMessage());
}
