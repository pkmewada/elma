<?php
include __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Phase 5: the admin landing page IS the management dashboard, sharing the
// same body as pages/lead-dashboard.php (kept as its own sidebar entry per
// the approved nav structure -- both show the same live data).
$ldHomeUrl = 'dashboard';
$ldLeadsUrl = 'leads';

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/lead-dashboard-page.php';
include __DIR__ . '/../includes/footer.php';
