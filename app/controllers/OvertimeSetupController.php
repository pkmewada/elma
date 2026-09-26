<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../models/OvertimeSetupModel.php';

header('Content-Type: application/json');

$model = new OvertimeSetupModel();
$action = $_POST['action'] ?? '';

function jsonResponse($success, $message, $data = null)
{
    $response = [
        'success' => (bool) $success,
        'message' => $message,
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response);
    exit;
}

switch ($action) {
    case 'saveSettings':
        $otType = trim((string) ($_POST['otType'] ?? ''));
        $minHoursRequired = trim((string) ($_POST['minHoursRequired'] ?? ''));
        $maxHoursPerDay = trim((string) ($_POST['maxHoursPerDay'] ?? ''));
        $rateType = trim((string) ($_POST['rateType'] ?? ''));
        $rateValue = trim((string) ($_POST['rateValue'] ?? ''));
        $roundingRule = trim((string) ($_POST['roundingRule'] ?? ''));
        $effectiveFrom = trim((string) ($_POST['effectiveFrom'] ?? date('Y-m-d')));

        if ($otType === '' || $minHoursRequired === '' || $maxHoursPerDay === '' || $rateType === '' || $rateValue === '' || $roundingRule === '') {
            jsonResponse(false, 'Please fill all required overtime setup fields.');
        }

        if (!in_array($otType, ['daily', 'weekly', 'holiday'], true)) {
            jsonResponse(false, 'Invalid overtime type selected.');
        }

        if (!is_numeric($minHoursRequired) || !is_numeric($maxHoursPerDay)) {
            jsonResponse(false, 'Min and max hours must be numeric values.');
        }

        if (!in_array($rateType, ['fixed', 'multiplier'], true)) {
            jsonResponse(false, 'Invalid rate type selected.');
        }

        if (!is_numeric($rateValue)) {
            jsonResponse(false, 'Rate value must be numeric.');
        }

        if (!in_array($roundingRule, ['15min', '30min', 'exact'], true)) {
            jsonResponse(false, 'Invalid rounding rule selected.');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $effectiveFrom)) {
            jsonResponse(false, 'Effective date format is invalid.');
        }

        $companyId = (int) ($_SESSION['companyId'] ?? $_SESSION['userId'] ?? 0);
        if ($companyId <= 0) {
            jsonResponse(false, 'Unable to determine company context. Please login again.');
        }

        $activeSettings = $model->getActiveSettings();

        $payload = [
            'id' => $activeSettings['id'] ?? 0,
            'companyId' => $companyId,
            'otType' => $otType,
            'minHoursRequired' => (float) $minHoursRequired,
            'maxHoursPerDay' => (float) $maxHoursPerDay,
            'rateType' => $rateType,
            'rateValue' => (float) $rateValue,
            'roundingRule' => $roundingRule,
            'autoApprove' => (int) ($_POST['autoApprove'] ?? 0),
            'requiresManagerApproval' => (int) ($_POST['requiresManagerApproval'] ?? 0),
            'requiresHrApproval' => (int) ($_POST['requiresHrApproval'] ?? 0),
            'effectiveFrom' => $effectiveFrom,
            'status' => 'active',
        ];

        $saved = $model->saveSettings($payload);

        if ($saved) {
            jsonResponse(true, 'Settings saved successfully');
        }

        jsonResponse(false, 'Unable to save settings. Please try again.');
        break;

    case 'getSettings':
        $data = $model->getActiveSettings();
        jsonResponse(true, 'Settings fetched successfully', $data);
        break;

    default:
        jsonResponse(false, 'Invalid action requested.');
}
