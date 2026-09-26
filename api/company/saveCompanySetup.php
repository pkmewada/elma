<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/CompanySettings.php';

header('Content-Type: application/json; charset=UTF-8');

function respond(bool $success, string $message, array $data = []): void
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ]);

    exit;
}

function postValue(string $key): string
{
    return trim((string)($_POST[$key] ?? ''));
}

function saveCompanyLogoUpload(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        respond(false, 'Unable to upload company logo.');
    }

    if ((int)($file['size'] ?? 0) > 2 * 1024 * 1024) {
        respond(false, 'Company logo must be 2 MB or smaller.');
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    $imageInfo = $tmpName !== '' ? getimagesize($tmpName) : false;

    if ($imageInfo === false) {
        respond(false, 'Please upload a valid image logo.');
    }

    $mimeType = (string)($imageInfo['mime'] ?? '');
    $extensions = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($extensions[$mimeType])) {
        respond(false, 'Logo must be PNG, JPG, WEBP, or GIF.');
    }

    $uploadDir = dirname(__DIR__, 2) . '/uploads/company/';

    if (
        !is_dir($uploadDir) &&
        !mkdir($uploadDir, 0755, true) &&
        !is_dir($uploadDir)
    ) {
        respond(false, 'Unable to create company upload directory.');
    }

    $filename = 'company-logo-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extensions[$mimeType];
    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($tmpName, $destination)) {
        respond(false, 'Unable to save company logo.');
    }

    return 'uploads/company/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.');
}

$settings = [
    'companyName' => postValue('companyName'),
    'legalName' => postValue('legalName'),
    'addressLine1' => postValue('addressLine1'),
    'addressLine2' => postValue('addressLine2'),
    'city' => postValue('city'),
    'stateName' => postValue('stateName'),
    'pincode' => postValue('pincode'),
    'country' => postValue('country') ?: 'India',
    'phone' => postValue('phone'),
    'email' => postValue('email'),
    'website' => postValue('website'),
    'gstNumber' => strtoupper(postValue('gstNumber')),
    'panNumber' => strtoupper(postValue('panNumber')),
    'cinNumber' => strtoupper(postValue('cinNumber')),
    'companyLogo' => postValue('existingCompanyLogo'),
];

if ($settings['companyName'] === '') {
    respond(false, 'Company name is required.');
}

if ($settings['email'] !== '' && !filter_var($settings['email'], FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Please enter a valid company email.');
}

if ($settings['gstNumber'] !== '' && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $settings['gstNumber'])) {
    respond(false, 'Please enter a valid GST number.');
}

if ($settings['panNumber'] !== '' && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $settings['panNumber'])) {
    respond(false, 'Please enter a valid PAN number.');
}

if (isset($_FILES['companyLogo']) && is_array($_FILES['companyLogo'])) {
    $uploadedLogo = saveCompanyLogoUpload($_FILES['companyLogo']);

    if ($uploadedLogo !== '') {
        $settings['companyLogo'] = $uploadedLogo;
    }
}

try {
    $saved = saveCompanySettings($con, $settings);

    respond(
        $saved,
        $saved ? 'Company setup saved successfully.' : 'Unable to save company setup.',
        ['companySettings' => getCompanySettings($con)]
    );
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
