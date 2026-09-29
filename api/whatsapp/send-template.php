<?php
/*
|--------------------------------------------------------------------------
| Outbound WhatsApp TEMPLATE message
|--------------------------------------------------------------------------
| Same session + CSRF (api-gateway.php) + permission + conversation-access
| gate as send-message.php, and the same shared pending/sent/failed
| lifecycle (includes/whatsappAccess.php's insertPendingWhatsappMessage()/
| finalizeWhatsappSend()) so behavior and logging are identical to a normal
| send. Split into its own endpoint (rather than send-message.php's old
| messageType=template branch) so the template name/language/variable count
| can be validated against the admin-approved list
| (integrationSettings.whatsapp.configJson.templates, set under
| Integrations -> WhatsApp Cloud API -> Approved Templates) before ever
| reaching the Graph API -- Meta itself remains the source of truth for
| whether a template is actually approved; this only catches a typo'd name
| or a wrong variable count client-side.
|
| A template may be sent whether the 24h customer service window is open or
| closed -- that's the whole point of a template (Meta's docs: template
| sends are never blocked by the window).
|
| POST {conversationId, templateName, templateLanguage, templateVariables[]}
*/
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/whatsappAccess.php';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    whatsappJsonExit(405, 'Method not allowed.');
}

requireApiActionPermission([whatsappCallerRoute()], 'send_whatsapp_message');

$conversationId = (int)($_POST['conversationId'] ?? 0);
$conversation = requireConversationAccess($con, $conversationId);

$setting = getIntegrationSetting($con, 'whatsapp');

if (!$setting || !$setting['isEnabled']) {
    whatsappJsonExit(503, 'WhatsApp integration is not enabled.');
}

$secrets = getIntegrationSecrets($con, 'whatsapp');
$config = getIntegrationConfig($con, 'whatsapp');

if (empty($secrets['accessToken']) || empty($config['phoneNumberId'])) {
    whatsappJsonExit(503, 'WhatsApp integration is not fully configured.');
}

$templateName = trim((string)($_POST['templateName'] ?? ''));
$templateLanguage = trim((string)($_POST['templateLanguage'] ?? ''));
$variables = array_values(array_map(static fn($v) => trim((string)$v), (array)($_POST['templateVariables'] ?? [])));

$template = findApprovedWhatsappTemplate($config, $templateName, $templateLanguage);

if (!$template) {
    whatsappJsonExit(422, 'Unknown template. Select one of the approved templates configured under Integrations.');
}

$expectedVarCount = (int)($template['variableCount'] ?? 0);
$providedVarCount = count(array_filter($variables, static fn($v) => $v !== ''));

if ($providedVarCount !== $expectedVarCount) {
    whatsappJsonExit(422, "This template needs {$expectedVarCount} variable(s); {$providedVarCount} provided.");
}

$actor = getCurrentActor();
$messageText = 'Template: ' . $template['name'] . ' (' . $template['language'] . ')';

// Insert as 'pending' before any Meta API call -- same rule as send-message.php.
$messageId = insertPendingWhatsappMessage($con, $conversationId, 'template', $messageText, null, null, $actor);

$sendResult = sendWhatsappTemplate($secrets, $config, $conversation['waId'], $template['name'], $template['language'], $variables);

finalizeWhatsappSend($con, $conversation, $messageId, $sendResult, 'template', $messageText);
