<?php
// Public page — no authentication required (routesMaster.isPublic = 1).
// Intentionally standalone, matching pages/terms-and-conditions.php's
// existing layoutType = 'public' convention.
$lastUpdated = 'August 28, 2026';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service — Modlus</title>
    <meta name="description" content="Modlus Terms of Service — Terms governing use of the Modlus social media automation service.">
    <style>
        body { margin:0; font-family:'Segoe UI',Tahoma,Arial,sans-serif; background:#f4f6f8; color:#333; }
        .container { max-width:900px; margin:50px auto; background:#fff; padding:40px; border-radius:12px; box-shadow:0 8px 25px rgba(0,0,0,0.08); }
        h1 { font-size:26px; margin-bottom:10px; }
        .subtitle { font-size:14px; color:#777; margin-bottom:30px; }
        .policy-content h2 { font-size:19px; margin-top:28px; color:#111; }
        .policy-content p { font-size:14px; line-height:1.7; color:#444; }
        .policy-content ul, .policy-content ol { padding-left:22px; }
        .policy-content li { margin-bottom:8px; font-size:14px; line-height:1.6; color:#444; }
        .policy-content a { color:#2f6fed; }
        .footer { margin-top:40px; font-size:13px; color:#777; }
        @media (max-width:600px) {
            .container { margin:0; border-radius:0; padding:24px; }
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Terms of Service</h1>
    <div class="subtitle">Last Updated: <?php echo htmlspecialchars($lastUpdated, ENT_QUOTES, 'UTF-8'); ?></div>

    <div class="policy-content">
        <h2>1. Acceptance of Terms</h2>
        <p>By accessing or using Modlus's social media management and automation service (the "Service"), you agree to these Terms of Service. If you do not agree, do not use the Service.</p>

        <h2>2. Description of Service</h2>
        <p>Modlus allows authorized users to connect supported social media accounts (including Instagram Business Accounts and linked Facebook Pages), create and schedule content, publish content to connected accounts, retrieve analytics/insights, and manage comments received on connected accounts.</p>

        <h2>3. User / Client Responsibilities</h2>
        <p>You are responsible for maintaining the confidentiality of your Modlus login credentials, for the accuracy of information you enter into Modlus, and for ensuring you have the right to connect and manage the social media accounts you add to the Service.</p>

        <h2>4. Social Media Account Connections</h2>
        <p>Connecting a social media account requires you to authorize Modlus through that platform's own authorization process (for example, Meta's Facebook Login for Business flow). You may disconnect a connected account at any time. Modlus's ability to act on a connected account depends on the permissions granted during that authorization and on the continued validity of the resulting access credentials.</p>

        <h2>5. Content and Publishing Responsibilities</h2>
        <p>You are solely responsible for the content you submit for publishing through Modlus, including captions, text, and media, and for ensuring that content complies with applicable law and with the terms and community standards of the platform it is published to.</p>

        <h2>6. Scheduled Posts</h2>
        <p>Scheduled content is published automatically at the requested time by Modlus's scheduling system. While Modlus is designed to publish scheduled content reliably, publishing depends on the continued availability of the connected account's credentials and on the receiving platform's own systems, and may occasionally be delayed or fail for reasons outside Modlus's control.</p>

        <h2>7. Platform Availability</h2>
        <p>The Service depends on third-party platforms, including Meta's Instagram and Facebook APIs. Modlus does not control, and cannot guarantee, the availability, performance, or behavior of these third-party platforms.</p>

        <h2>8. Third-Party Platforms</h2>
        <p>Instagram, Facebook, and Meta are third-party platforms governed by their own terms and policies. Modlus is not responsible for actions taken by these platforms, including changes to their APIs, policies, or availability. Modlus does not guarantee uninterrupted publishing, analytics accuracy, or webhook/event delivery, as these depend on systems outside Modlus's control.</p>

        <h2>9. Prohibited Use</h2>
        <p>You may not use the Service to publish unlawful content, to connect accounts you are not authorized to manage, to attempt to interfere with or disrupt the Service, or to attempt to circumvent Modlus's security or access controls.</p>

        <h2>10. Intellectual Property</h2>
        <p>The Modlus platform, including its software, design, and branding, is the property of Modlus. Content you submit for publishing remains yours; you grant Modlus only the rights necessary to transmit and publish that content to the platforms and accounts you direct.</p>

        <h2>11. Service Availability</h2>
        <p>Modlus aims to keep the Service available and reliable but does not guarantee uninterrupted or error-free operation, and may perform maintenance or updates that temporarily affect availability.</p>

        <h2>12. Account Suspension / Termination</h2>
        <p>Modlus may suspend or terminate access to the Service for accounts that violate these Terms, misuse the Service, or where required for security or legal reasons.</p>

        <h2>13. Limitation of Liability</h2>
        <p>To the maximum extent permitted by applicable law, Modlus is not liable for indirect, incidental, or consequential damages arising from use of the Service, including issues caused by third-party platform outages, API changes, or delivery failures outside Modlus's control.</p>

        <h2>14. Changes to the Service / Terms</h2>
        <p>Modlus may update these Terms or modify the Service from time to time. The current version of these Terms will always be published at this page.</p>

        <h2>15. Contact Information</h2>
        <p>Questions about these Terms can be sent to <a href="mailto:support@mqlus.in">support@mqlus.in</a>.</p>
    </div>

    <div class="footer">&copy; <?php echo date('Y'); ?> Modlus. All rights reserved.</div>
</div>
</body>
</html>
