<?php
include __DIR__ . '/../includes/auth.php';

// Body shared with the employee page; see includes/lead-activity-page.php.
$activityLayout = 'admin';
$activityHomeUrl = 'dashboard';
$activityLeadsUrl = 'leads';
$activitySelfUrl = 'lead-activity';

include __DIR__ . '/../includes/lead-activity-page.php';
