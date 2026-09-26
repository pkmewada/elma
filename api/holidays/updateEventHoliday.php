<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

function response($success, $message, $data = [])
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response(false, 'Invalid request.');
}

$id            = (int)($_POST['id'] ?? 0);
$eventTitle    = trim($_POST['eventTitle'] ?? '');
$eventType     = trim($_POST['eventType'] ?? '');
$eventCategory = trim($_POST['eventCategory'] ?? '');
$eventDate     = trim($_POST['eventDate'] ?? '');
$eventTime     = trim($_POST['eventTime'] ?? '');
$location      = trim($_POST['location'] ?? '');
$description   = trim($_POST['description'] ?? '');
$reminderDays  = (int)($_POST['reminderDays'] ?? 0);
$mailEnabled   = (int)($_POST['mailEnabled'] ?? 1);
$status        = trim($_POST['status'] ?? 'active');

if ($id <= 0) {
    response(false, 'Invalid record id.');
}

if ($eventTitle === '') {
    response(false, 'Title is required.');
}

if (!in_array($eventType, ['event', 'holiday'], true)) {
    response(false, 'Invalid type selected.');
}

if ($eventDate === '') {
    response(false, 'Date is required.');
}

$stmt = mysqli_prepare($con, "
    UPDATE eventHolidayMaster SET
        eventTitle = ?,
        eventType = ?,
        eventCategory = ?,
        eventDate = ?,
        eventTime = ?,
        location = ?,
        description = ?,
        reminderDays = ?,
        mailEnabled = ?,
        status = ?
    WHERE id = ?
");

if (!$stmt) {
    response(false, 'Database prepare failed.');
}

mysqli_stmt_bind_param(
    $stmt,
    'sssssssissi',
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
    $id
);

if (!mysqli_stmt_execute($stmt)) {
    response(false, 'Unable to update record.');
}

response(true, 'Record updated successfully.', [
    'id'            => $id,
    'eventTitle'    => $eventTitle,
    'eventType'     => $eventType,
    'eventCategory' => $eventCategory,
    'eventDate'     => $eventDate,
    'formattedDate' => date('d M Y', strtotime($eventDate)),
    'eventTime'     => $eventTime,
    'formattedTime' => $eventTime ? date('h:i A', strtotime($eventTime)) : '-',
    'location'      => $location,
    'description'   => $description,
    'reminderDays'  => $reminderDays,
    'mailEnabled'   => $mailEnabled,
    'status'        => ucfirst($status)
]);