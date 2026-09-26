<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

function response($success, $message)
{
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response(false, 'Invalid request.');
}

/*
|--------------------------------------------------------------------------
| Get Inputs
|--------------------------------------------------------------------------
*/
$eventTitle     = trim($_POST['eventTitle'] ?? '');
$eventType      = trim($_POST['eventType'] ?? '');
$eventCategory  = trim($_POST['eventCategory'] ?? '');
$eventDate      = trim($_POST['eventDate'] ?? '');
$eventTime      = trim($_POST['eventTime'] ?? '');
$location       = trim($_POST['location'] ?? '');
$description    = trim($_POST['description'] ?? '');
$reminderDays   = (int)($_POST['reminderDays'] ?? 0);
$mailEnabled    = (int)($_POST['mailEnabled'] ?? 1);
$status         = trim($_POST['status'] ?? 'active');

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/
if ($eventTitle === '') {
    response(false, 'Title is required.');
}

if (!in_array($eventType, ['event', 'holiday'], true)) {
    response(false, 'Invalid type selected.');
}

if ($eventDate === '') {
    response(false, 'Date is required.');
}

/*
|--------------------------------------------------------------------------
| Created By
|--------------------------------------------------------------------------
*/
$createdBy = $_SESSION['fullName'] ?? 'Admin';

/*
|--------------------------------------------------------------------------
| Insert
|--------------------------------------------------------------------------
*/
$stmt = mysqli_prepare($con, "
    INSERT INTO eventHolidayMaster (
        eventTitle,
        eventType,
        eventCategory,
        eventDate,
        eventTime,
        location,
        description,
        reminderDays,
        mailEnabled,
        status,
        createdBy
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    response(false, 'Database prepare failed.');
}

mysqli_stmt_bind_param(
    $stmt,
    'sssssssiiss',
    $eventTitle,
    $eventType,
    $eventCategory,
    $eventDate,
    $eventTime,
    $location,
    $description,
    $reminderDays,
    $mailEnabled,
    $status,
    $createdBy
);
if (mysqli_stmt_execute($stmt)) {

    $insertId = mysqli_insert_id($con);

    echo json_encode([
        'success' => true,
        'message' => ucfirst($eventType) . ' created successfully.',
        'data' => [
            'id' => $insertId,
            'eventTitle' => $eventTitle,
            'eventType' => $eventType,
            'eventCategory' => $eventCategory,
            'status' => ucfirst($status),
            'formattedDate' => date('d M Y', strtotime($eventDate)),
            'formattedTime' => $eventTime ? date('h:i A', strtotime($eventTime)) : ''
        ]
    ]);
    exit;
}

response(false, 'Unable to save record.');