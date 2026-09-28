<?php
include __DIR__ . '/../includes/emp-auth.php';
require_once __DIR__ . '/../includes/db.php';

// Body shared with pages/dashboard.php and pages/lead-dashboard.php. Route
// permission (Sales Manager by default) gates access; data is auto-scoped
// by api/leads/get-dashboard-summary.php.
$ldHomeUrl = 'emp-dashboard';
$ldLeadsUrl = 'emp-leads';

include __DIR__ . '/../includes/emp-header.php';
include __DIR__ . '/../includes/emp-sidebar.php';
include __DIR__ . '/../includes/lead-dashboard-page.php';
include __DIR__ . '/../includes/emp-footer.php';
