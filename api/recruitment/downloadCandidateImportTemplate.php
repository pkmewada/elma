<?php
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="candidate-import-template.csv"');

$output = fopen('php://output', 'w');

// Headers
fputcsv($output, [
    'fullName',
    'email',
    'phoneNumber',
    'currentLocation',
    'appliedRole',
    'experienceYears',
    'expectedSalary'
]);

// Sample rows
fputcsv($output, [
    'Rahul Sharma',
    'rahul@example.com',
    '9876543210',
    'Mumbai',
    'Graphic Executive',
    '3',
    '4,50,000 - 6,00,000'
]);

fputcsv($output, [
    'Priya Verma',
    'priya@example.com',
    '9876501234',
    'Delhi',
    'Video Editor',
    '2',
    '3,50,000 - 5,00,000'
]);

fputcsv($output, [
    'Amit Kumar',
    'amit@example.com',
    '9876543211',
    'Bangalore',
    'Graphic Intern',
    '0',
    '2,00,000 - 2,50,000'
]);

fclose($output);
exit;
?>