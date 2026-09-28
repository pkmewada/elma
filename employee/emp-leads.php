<?php
include __DIR__ . '/../includes/emp-auth.php';
require_once __DIR__ . '/../includes/db.php';

// Page body (filters, table, modals) is shared with pages/leads.php; the
// APIs scope the data to the leads this employee may access.
$leadPageTitle = 'My Leads';
$leadPageHomeUrl = 'emp-dashboard';
$leadPageFollowUpUrl = 'emp-follow-ups';

include __DIR__ . '/../includes/emp-header.php';
include __DIR__ . '/../includes/emp-sidebar.php';
include __DIR__ . '/../includes/lead-page.php';
include __DIR__ . '/../includes/emp-footer.php';
