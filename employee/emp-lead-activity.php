<?php
include __DIR__ . '/../includes/emp-auth.php';

// Body shared with the admin page; see includes/lead-activity-page.php.
$activityLayout = 'employee';
$activityHomeUrl = 'emp-dashboard';
$activityLeadsUrl = 'emp-leads';
$activitySelfUrl = 'emp-lead-activity';

include __DIR__ . '/../includes/lead-activity-page.php';
