<?php

require_once __DIR__ . '/../../includes/leadAccess.php';
requireLeadPermission('canAdd');

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="lead-import-template.csv"');

$output = fopen('php://output', 'w');

fputcsv($output, ['name', 'phone', 'email', 'countryCode', 'country', 'project', 'source', 'status', 'assignedTo', 'remark']);
fputcsv($output, ['Rahul Sharma', '9876543210', 'rahul@example.com', '+91', 'India', '', 'Website', 'New', '', 'Asked for 2 BHK price list']);

fclose($output);
exit;
