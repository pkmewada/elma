<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadAccess.php';

/*
|--------------------------------------------------------------------------
| CSV lead import
|--------------------------------------------------------------------------
| POST multipart {leadCsvFile, employeeId?} + CSRF (gateway) + the
| 'import_leads' special action on the caller's lead page.
|
| Columns (header row, any order): name, phone required; email, countryCode
| (default +91), country (default India), project, source (default Other),
| status (default New), assignedTo (employee email), remark optional.
| project / source / status / assignedTo must match exactly (case-insensitive)
| an existing active record - unknown values reject that row, never create
| new masters. Rows whose phone already exists are skipped (duplicates).
| Default assignee: the importing employee, or employeeId chosen by an admin.
*/
requireLeadPost();
requireActionPermission(leadCallerRoute(), 'import_leads');

$file = $_FILES['leadCsvFile'] ?? null;

if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    leadJsonExit(422, 'Please upload a valid CSV file.');
}

if ((int)$file['size'] > 5 * 1024 * 1024) {
    leadJsonExit(422, 'CSV file must be smaller than 5 MB.');
}

$csvMimeType = (string)(new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
$extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));

if ($extension !== 'csv' || !in_array($csvMimeType, ['text/plain', 'text/csv', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel'], true)) {
    leadJsonExit(422, 'Only CSV files are allowed.');
}

$defaultAssignee = resolveNewLeadAssignee($con, (int)($_POST['employeeId'] ?? 0));

// Lookup maps (lower-cased) for deterministic matching.
$projects = [];
foreach (mysqli_fetch_all(mysqli_query($con, 'SELECT id, projectName FROM projects WHERE isActive = 1'), MYSQLI_ASSOC) as $row) {
    $projects[mb_strtolower($row['projectName'])] = (int)$row['id'];
}
$sources = [];
foreach (mysqli_fetch_all(mysqli_query($con, 'SELECT id, sourceKey, sourceName FROM leadSources WHERE isActive = 1'), MYSQLI_ASSOC) as $row) {
    $sources[mb_strtolower($row['sourceName'])] = (int)$row['id'];
    $sources[mb_strtolower($row['sourceKey'])] = (int)$row['id'];
}
$statuses = [];
foreach (LEAD_STATUSES as $key => $label) {
    $statuses[$key] = $key;
    $statuses[mb_strtolower($label)] = $key;
}
$assignees = [];
if (canAssignLeads()) {
    $emails = mysqli_fetch_all(mysqli_query($con, 'SELECT id, emailAddress FROM employeeusers'), MYSQLI_ASSOC);
    $assignable = array_column(getAssignableEmployees($con), 'id');
    foreach ($emails as $row) {
        if (in_array((int)$row['id'], $assignable, true)) {
            $assignees[mb_strtolower($row['emailAddress'])] = (int)$row['id'];
        }
    }
}

$handle = fopen((string)$file['tmp_name'], 'r');
$header = array_map(static fn($h) => mb_strtolower(trim((string)$h, " \t\n\r\0\x0B\xEF\xBB\xBF")), fgetcsv($handle) ?: []);
$column = array_flip($header);

foreach (['name', 'phone'] as $required) {
    if (!isset($column[$required])) {
        fclose($handle);
        leadJsonExit(422, 'Invalid CSV header. Required columns: name, phone. Optional: email, countryCode, country, project, source, status, assignedTo, remark.');
    }
}

$get = static fn(array $row, string $key): string => isset($column[$key]) ? trim((string)($row[$column[$key]] ?? '')) : '';
$otherSourceId = $sources['other'] ?? null;
$actor = getCurrentActor();
$createdByCandidateId = $actor['type'] === 'employee' ? $actor['id'] : null;

$insert = mysqli_prepare(
    $con,
    'INSERT INTO leads (fullName, email, phone, country, countryCode, projectId, sourceId, assignedToId, status, createdByCandidateId, createdByType)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$dupCheck = mysqli_prepare($con, 'SELECT id FROM leads WHERE phone = ? AND countryCode = ? LIMIT 1');

$imported = 0;
$duplicates = 0;
$errors = [];
$line = 1;

while (($row = fgetcsv($handle)) !== false) {
    $line++;
    if (count(array_filter($row, static fn($v) => trim((string)$v) !== '')) === 0) {
        continue;
    }

    $name = $get($row, 'name');
    $phone = preg_replace('/\D/', '', $get($row, 'phone')) ?? '';
    $email = $get($row, 'email');
    $countryCode = $get($row, 'countrycode') ?: '+91';
    $country = $get($row, 'country') ?: 'India';
    $projectText = mb_strtolower($get($row, 'project'));
    $sourceText = mb_strtolower($get($row, 'source'));
    $statusText = mb_strtolower($get($row, 'status'));
    $assigneeText = mb_strtolower($get($row, 'assignedto'));
    $remark = $get($row, 'remark');

    $problem = null;
    if ($name === '' || strlen($phone) < 6 || strlen($phone) > 15) {
        $problem = 'name and a 6-15 digit phone are required';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $problem = 'invalid email';
    } elseif (!preg_match('/^\+\d{1,4}$/', $countryCode)) {
        $problem = 'invalid countryCode (use +91 format)';
    } elseif ($projectText !== '' && !isset($projects[$projectText])) {
        $problem = 'unknown project "' . $get($row, 'project') . '"';
    } elseif ($sourceText !== '' && !isset($sources[$sourceText])) {
        $problem = 'unknown source "' . $get($row, 'source') . '"';
    } elseif ($statusText !== '' && !isset($statuses[$statusText])) {
        $problem = 'unknown status "' . $get($row, 'status') . '"';
    } elseif ($assigneeText !== '' && !isset($assignees[$assigneeText])) {
        $problem = canAssignLeads() ? 'unknown salesperson "' . $get($row, 'assignedto') . '"' : 'assignedTo requires assign permission';
    }

    if ($problem) {
        $errors[] = "Row {$line}: {$problem}";
        continue;
    }

    mysqli_stmt_bind_param($dupCheck, 'ss', $phone, $countryCode);
    mysqli_stmt_execute($dupCheck);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($dupCheck))) {
        $duplicates++;
        continue;
    }

    $emailValue = $email !== '' ? $email : null;
    $projectId = $projectText !== '' ? $projects[$projectText] : null;
    $sourceId = $sourceText !== '' ? $sources[$sourceText] : $otherSourceId;
    $assignedToId = $assigneeText !== '' ? $assignees[$assigneeText] : ($defaultAssignee['id'] ?? null);
    $status = $statusText !== '' ? $statuses[$statusText] : 'new';

    mysqli_stmt_bind_param($insert, 'sssssiiisis', $name, $emailValue, $phone, $country, $countryCode,
        $projectId, $sourceId, $assignedToId, $status, $createdByCandidateId, $actor['type']);

    if (!mysqli_stmt_execute($insert)) {
        $errors[] = "Row {$line}: could not be saved";
        continue;
    }

    $leadId = (int)mysqli_insert_id($con);
    $imported++;
    saveActivityLog($con, 'Lead', $leadId, 'CREATE', 'Lead imported from CSV : ' . $name, null, ['phone' => $countryCode . ' ' . $phone]);

    if ($remark !== '') {
        createLeadRemark($con, $leadId, $remark);
    }
}

fclose($handle);
mysqli_stmt_close($insert);
mysqli_stmt_close($dupCheck);

$message = "{$imported} lead(s) imported";
if ($duplicates) {
    $message .= ", {$duplicates} duplicate(s) skipped";
}
if ($errors) {
    $message .= ', ' . count($errors) . ' row(s) rejected';
}

echo json_encode([
    'success' => $imported > 0 || (!$errors && $duplicates > 0),
    'message' => $message . '.',
    'data' => ['imported' => $imported, 'duplicates' => $duplicates, 'errors' => array_slice($errors, 0, 50)],
]);
