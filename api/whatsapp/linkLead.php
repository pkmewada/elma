<?php
/*
|--------------------------------------------------------------------------
| Link an unlinked WhatsApp conversation to an existing lead, or create one
|--------------------------------------------------------------------------
| Simple opt-in action for an unlinked conversation (section 2 -- no
| automatic lead creation). Only full-scope callers (admin / view-all-leads)
| can act here, same as who could already see an unlinked conversation via
| requireConversationAccess().
*/
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/whatsappAccess.php';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    whatsappJsonExit(405, 'Method not allowed.');
}

requireApiActionPermission([whatsappCallerRoute()], 'send_whatsapp_message');

if (getLeadScopeEmployeeId() !== 0) {
    whatsappJsonExit(403, 'Only an admin or a manager with full lead access can link/create leads from WhatsApp.');
}

$conversationId = (int)($_POST['conversationId'] ?? 0);
$conversation = requireConversationAccess($con, $conversationId);
$action = (string)($_POST['action'] ?? '');

if ($conversation['leadId']) {
    whatsappJsonExit(422, 'This conversation is already linked to a lead.');
}

if ($action === 'link') {
    $leadId = (int)($_POST['leadId'] ?? 0);
    $lead = requireLeadAccess($con, $leadId);

    $stmt = mysqli_prepare($con, 'UPDATE whatsappConversations SET leadId = ? WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $leadId, $conversationId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    saveActivityLog($con, 'Lead', $leadId, 'WHATSAPP_LINKED', 'WhatsApp conversation linked : ' . $conversation['waId']);
    echo json_encode(['success' => true, 'message' => 'Conversation linked to ' . $lead['fullName'] . '.', 'data' => ['leadId' => $leadId]]);
} elseif ($action === 'create') {
    $sourceStmt = mysqli_prepare($con, "SELECT id, sourceName FROM leadSources WHERE sourceKey = 'whatsapp' AND isActive = 1 LIMIT 1");
    mysqli_stmt_execute($sourceStmt);
    $source = mysqli_fetch_assoc(mysqli_stmt_get_result($sourceStmt));
    mysqli_stmt_close($sourceStmt);

    if (!$source) {
        whatsappJsonExit(422, 'The WhatsApp lead source is not active. Enable it under Lead Setup first.');
    }

    $phoneParts = splitWaIdForLead($conversation['waId']);

    $result = createLeadFromSource($con, 'whatsapp', null, [
        'fullName' => $conversation['customerName'] ?: $conversation['phoneNumber'],
        'phone' => $phoneParts['phone'],
        'countryCode' => $phoneParts['countryCode'],
        'country' => $phoneParts['countryCode'] === '+91' ? 'India' : '',
        'sourceId' => (int)$source['id'],
        'sourceName' => $source['sourceName'],
        'createLabel' => 'Lead created from WhatsApp conversation',
    ]);

    $stmt = mysqli_prepare($con, 'UPDATE whatsappConversations SET leadId = ? WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $result['leadId'], $conversationId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    saveActivityLog($con, 'Lead', $result['leadId'], 'WHATSAPP_LINKED', 'Lead created from WhatsApp conversation : ' . $conversation['waId']);
    echo json_encode(['success' => true, 'message' => $result['duplicate'] ? 'Linked to an existing matching lead.' : 'Lead created and linked.', 'data' => ['leadId' => $result['leadId']]]);
} else {
    whatsappJsonExit(422, 'Invalid action.');
}
