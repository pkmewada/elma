<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadAccess.php';

/*
|--------------------------------------------------------------------------
| Log a Call / WhatsApp quick action
|--------------------------------------------------------------------------
| POST {leadId, channel: call|whatsapp} + CSRF (gateway) + view permission +
| lead in scope. Records only that the action was OPENED from the CRM (a
| tel: / wa.me link); no call or message is claimed as completed/sent.
*/
requireLeadPost();
requireLeadPermission('canView');

$leadId = (int)($_POST['leadId'] ?? 0);
$lead = requireLeadAccess($con, $leadId);
$channel = (string)($_POST['channel'] ?? '');

$channels = [
    'call' => ['CALL', 'Call action opened'],
    'whatsapp' => ['WHATSAPP', 'WhatsApp action opened'],
];

if (!isset($channels[$channel])) {
    leadJsonExit(422, 'Invalid contact channel.');
}

[$action, $description] = $channels[$channel];
saveActivityLog($con, 'Lead', $leadId, $action, $description . ' : ' . $lead['fullName'], null, [
    'phone' => trim(($lead['countryCode'] ?? '') . ' ' . $lead['phone']),
]);

echo json_encode(['success' => true, 'message' => $description . '.', 'data' => []]);
