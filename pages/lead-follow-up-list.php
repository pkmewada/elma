<?php
include __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Body shared with employee/emp-follow-ups.php.
$followUpHomeUrl = 'dashboard';
$followUpLeadsUrl = 'leads';

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/follow-up-page.php';
include __DIR__ . '/../includes/footer.php';
