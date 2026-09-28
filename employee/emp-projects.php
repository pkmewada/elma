<?php
include __DIR__ . '/../includes/emp-auth.php';
require_once __DIR__ . '/../includes/db.php';

// Read-only project portfolio for sales staff (active projects only; the
// API enforces it).
$projectHomeUrl = 'emp-dashboard';

include __DIR__ . '/../includes/emp-header.php';
include __DIR__ . '/../includes/emp-sidebar.php';
include __DIR__ . '/../includes/project-page.php';
include __DIR__ . '/../includes/emp-footer.php';
