<?php

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="lead-import-template.csv"');

$output = fopen('php://output', 'w');

fputcsv($output, [
    'fullName',
    'email',
    'phone',
    'country',
    'countryCode',
    'source',
    'orgName'
]);

fputcsv($output, [
    'Rahul Sharma',
    'rahul@example.com',
    '9876543210',
    'India',
    '+91',
    'Website',
    'TEST COMPANY'
]);

fputcsv($output, [
    'Priya Verma',
    'priya@example.com',
    '9876501234',
    'India',
    '+91',
    'Instagram',
    'DEMO COMPANY'
]);

fclose($output);
exit;