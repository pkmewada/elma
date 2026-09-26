<?php
require_once __DIR__ . '/../includes/basic-config.php';

$config = getBasicConfig();
$termsHtml = (string) ($config['terms_and_conditions_html'] ?? '');
$termsUpdated = trim((string) ($config['terms_last_updated'] ?? ''));

if ($termsHtml === '') {
    $termsHtml = (string) (getBasicConfigDefaults()['terms_and_conditions_html'] ?? '');
}

$updatedLabel = $termsUpdated !== ''
    ? date('F d, Y h:i A', strtotime($termsUpdated))
    : date('F d, Y');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Terms & Conditions</title>
    <style>
        body { margin:0; font-family:'Segoe UI',Tahoma,sans-serif; background:#f4f6f8; color:#333; }
        .container { max-width:900px; margin:50px auto; background:#fff; padding:40px; border-radius:12px; box-shadow:0 8px 25px rgba(0,0,0,0.08); }
        h1 { font-size:26px; margin-bottom:10px; }
        .subtitle { font-size:14px; color:#777; margin-bottom:30px; }
        .terms-content h2, .terms-content h3, .terms-content h4 { margin-top:26px; color:#111; }
        .terms-content p { font-size:14px; line-height:1.7; color:#444; }
        .terms-content ul, .terms-content ol { padding-left:20px; }
        .terms-content li { margin-bottom:8px; font-size:14px; }
        .footer { margin-top:40px; font-size:13px; color:#777; }
    </style>
</head>
<body>
<div class="container">
    <h1>Terms & Conditions</h1>
    <div class="subtitle">Last Updated: <?php echo htmlspecialchars($updatedLabel, ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="terms-content">
        <?php echo $termsHtml; ?>
    </div>
    <div class="footer">&copy; <?php echo date('Y'); ?> Modlus HRMS. All rights reserved.</div>
</div>
</body>
</html>
