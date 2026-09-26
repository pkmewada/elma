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

try {
    respond(true, 'Company setup loaded successfully.', [
        'companySettings' => getCompanySettings($con),
    ]);
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
