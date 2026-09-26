 <?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/PayrollEngine.php';

header('Content-Type: application/json; charset=UTF-8');

function respond(bool $success, string $message): void
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
    ]);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.');
}

$payload = json_decode((string)file_get_contents('php://input'), true);

if (!is_array($payload)) {
    respond(false, 'Invalid payload.');
}

$settings = is_array($payload['payrollSettings'] ?? null)
    ? $payload['payrollSettings']
    : [];

if ((float)($settings['standardWorkingHours'] ?? 0) <= 0) {
    respond(false, 'Standard working hours must be greater than 0.');
}

if ((float)($settings['fixedPaidDays'] ?? 0) <= 0) {
    respond(false, 'Fixed paid days must be greater than 0.');
}

if ((float)($settings['monthlyPaidLeaveDays'] ?? 0) < 0) {
    respond(false, 'Monthly paid leave must be 0 or greater.');
}

if ((float)($settings['monthlyPaidLeaveCarryForwardLimit'] ?? 0) < 0) {
    respond(false, 'Monthly carry forward limit must be 0 or greater.');
}

if ((int)($settings['probationDays'] ?? 0) < 0) {
    respond(false, 'Probation days must be 0 or greater.');
}



if((int)($settings['trainingHoldDays'] ?? 0)< 0){
    respond( false,'Training hold days must be 0 or greater.');
}


if((int)($settings['trainingAmountReleaseAfterDays']?? 0)<=0){
    respond(false,'Training release days must be greater than 0.');
}

if ((float)($settings['pointDeductionThreshold'] ?? 0) < 0) {
    respond(false, 'Point deduction threshold must be 0 or greater.');
}

foreach ([
    'providentFundWageCeiling',
    'esicWageLimit',
    'professionalTaxAmount',
    'incomeTaxTdsAmount',
] as $amountField) {
    if ((float)($settings[$amountField] ?? 0) < 0) {
        respond(false, 'Statutory tax amounts and limits must be 0 or greater.');
    }
}

if (!in_array((string)($settings['providentFundBasis'] ?? 'basic'), ['basic', 'gross'], true)) {
    respond(false, 'Invalid P.F. basis.');
}

if (!in_array((string)($settings['incomeTaxTdsType'] ?? 'fixed'), ['fixed', 'percent'], true)) {
    respond(false, 'Invalid TDS type.');
}

foreach ([
    'probationLeaveDeductionPercent',
    'noticeLeaveDeductionPercent',
    'pointDeductionPercent',
    'providentFundPercent',
    'esicEmployeePercent',
    'incomeTaxTdsPercent',
    'gstPercent',
] as $percentField) {
    $value = (float)($settings[$percentField] ?? 0);

    if ($value < 0 || $value > 100) {
        respond(false, 'Deduction percentages must be between 0 and 100.');
    }
}

try {
    $engine = new PayrollEngine($con);
    $saved = $engine->saveSettings($settings);

    respond(
        $saved,
        $saved
            ? 'Payroll setup saved successfully.'
            : 'Unable to save payroll setup.'
    );
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
