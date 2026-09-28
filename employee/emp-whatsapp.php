<?php
include __DIR__ . '/../includes/emp-auth.php';
require_once __DIR__ . '/../includes/db.php';

// Page body is shared with pages/whatsapp.php; the APIs scope conversations
// to the leads this employee may access.
include __DIR__ . '/../includes/emp-header.php';
include __DIR__ . '/../includes/emp-sidebar.php';
include __DIR__ . '/../includes/whatsapp-page.php';
include __DIR__ . '/../includes/emp-footer.php';
