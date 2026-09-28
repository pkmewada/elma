<?php
include __DIR__ . '/../includes/emp-auth.php';
require_once __DIR__ . '/../includes/db.php';

// Body shared with pages/lead-follow-up-list.php; the API limits rows to
// the leads this employee may access.
$followUpHomeUrl = 'emp-dashboard';
$followUpLeadsUrl = 'emp-leads';

include __DIR__ . '/../includes/emp-header.php';
include __DIR__ . '/../includes/emp-sidebar.php';
include __DIR__ . '/../includes/follow-up-page.php';
include __DIR__ . '/../includes/emp-footer.php';
