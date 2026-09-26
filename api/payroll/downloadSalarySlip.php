<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/PayrollApprovalEngine.php';

function salarySlipDownloadError(string $message, int $statusCode = 400): void
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $message;
    exit;
}

$employeeId = (int)($_GET['employeeId'] ?? 0);
$periodStart = trim((string)($_GET['periodStart'] ?? ''));
$periodEnd = trim((string)($_GET['periodEnd'] ?? ''));

if ($employeeId <= 0 || $periodStart === '' || $periodEnd === '') {
    salarySlipDownloadError('Employee and payroll period are required.');
}

if (strtotime($periodStart) === false || strtotime($periodEnd) === false) {
    salarySlipDownloadError('Invalid payroll period.');
}

if (strtotime($periodEnd) < strtotime($periodStart)) {
    salarySlipDownloadError('Period end must be after period start.');
}

try {
    $engine = new PayrollApprovalEngine($con);
    $slip = $engine->getExistingSlip($employeeId, $periodStart, $periodEnd);

    if (!$slip || (string)$slip['status'] !== 'approved' || trim((string)$slip['pdfPath']) === '') {
        salarySlipDownloadError('Salary slip PDF is available only after Super Admin approval.', 403);
    }

    $filePath = dirname(__DIR__, 2) . '/' . ltrim((string)$slip['pdfPath'], '/');

    if (!is_file($filePath)) {
        salarySlipDownloadError('Approved salary slip PDF file was not found.', 404);
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
} catch (Throwable $e) {
    salarySlipDownloadError($e->getMessage(), 500);
}
