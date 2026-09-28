<?php
include __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Page body (filters, table, modals) is shared with employee/emp-leads.php.
$leadPageTitle = 'All Leads';
$leadPageHomeUrl = 'dashboard';
$leadPageFollowUpUrl = 'lead-follow-up-list';
$leadPageWhatsappUrl = 'whatsapp';

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/lead-page.php';
include __DIR__ . '/../includes/footer.php';
