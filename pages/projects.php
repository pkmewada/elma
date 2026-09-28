<?php
include __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Body shared with employee/emp-projects.php (read-only there).
$projectHomeUrl = 'dashboard';

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/project-page.php';
include __DIR__ . '/../includes/footer.php';
