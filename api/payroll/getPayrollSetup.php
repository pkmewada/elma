<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/PayrollEngine.php';

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
    $engine = new PayrollEngine($con);

    respond(
        true,
        'Payroll setup loaded successfully.',
        [
            'payrollSettings' => $engine->getSettings(),
        ]
    );
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
