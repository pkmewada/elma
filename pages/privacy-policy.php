<?php
// Public page — no authentication required (routesMaster.isPublic = 1).
// Intentionally standalone (no includes/header.php or includes/sidebar.php),
// matching the existing pages/terms-and-conditions.php convention for
// layoutType = 'public' pages, so it renders correctly for an anonymous
// visitor and for Meta's App Review reviewers with no session/cookies/JS.
$lastUpdated = 'August 28, 2026';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy — Modlus</title>
    <meta name="description" content="Modlus Privacy Policy — Information about how Modlus collects, uses, protects, and manages data.">
    <style>
        body { margin:0; font-family:'Segoe UI',Tahoma,Arial,sans-serif; background:#f4f6f8; color:#333; }
        .container { max-width:900px; margin:50px auto; background:#fff; padding:40px; border-radius:12px; box-shadow:0 8px 25px rgba(0,0,0,0.08); }
        h1 { font-size:26px; margin-bottom:10px; }
        .subtitle { font-size:14px; color:#777; margin-bottom:30px; }
        .policy-content h2 { font-size:19px; margin-top:28px; color:#111; }
        .policy-content h3 { font-size:16px; margin-top:20px; color:#111; }
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
    <h1>Privacy Policy</h1>
    <div class="subtitle">Last Updated: <?php echo htmlspecialchars($lastUpdated, ENT_QUOTES, 'UTF-8'); ?></div>

    <div class="policy-content">
        <h2>A. Introduction</h2>
        <p>Modlus ("Modlus", "we", "us", or "our") provides social media management and automation functionality that allows businesses and authorized users to connect, manage, publish to, and review results from supported social media platforms from a single dashboard. This Privacy Policy explains what information Modlus collects, how it is used, and the choices available to you.</p>

        <h2>B. Information We Collect</h2>
        <p>Modlus collects only the information reasonably necessary to provide its social media management features, including:</p>
        <ul>
            <li>Account and profile information provided when a Modlus account or client record is set up (such as name and login credentials).</li>
            <li>Client/business information entered into Modlus by an authorized user.</li>
            <li>Social media account identifiers for connected accounts (for example, an Instagram Business Account ID or a linked Facebook Page ID).</li>
            <li>Instagram account information made available through the connection (such as username and follower/media counts).</li>
            <li>Facebook Page information made available through the connection.</li>
            <li>Content submitted for publishing, including captions, text, and uploaded media (images/video).</li>
            <li>Scheduled-post information, including scheduling time and publishing status/results.</li>
            <li>Analytics and insights data retrieved through authorized platform APIs (for example, reach, follower counts, and post-level engagement metrics).</li>
            <li>Comments and related webhook data received from supported social platforms for accounts you have connected.</li>
            <li>Technical information reasonably needed to operate and secure the service (such as request logs used for troubleshooting).</li>
        </ul>
        <p>Modlus does not collect payment information, precise device location, contacts, SMS messages, microphone or camera data, or other device data unrelated to the features described above.</p>

        <h2>C. How We Use Information</h2>
        <p>Information is used to:</p>
        <ul>
            <li>Connect and maintain authorized social media account connections.</li>
            <li>Publish and schedule content to the platforms you have connected and authorized.</li>
            <li>Retrieve and display analytics/insights for connected accounts.</li>
            <li>Receive and process supported comment and webhook events for connected accounts.</li>
            <li>Display results, statuses, and history inside the Modlus dashboard.</li>
            <li>Maintain account security, including detecting and responding to unauthorized access.</li>
            <li>Troubleshoot issues and provide support.</li>
            <li>Operate, maintain, and improve the Modlus service.</li>
        </ul>

        <h2>D. Social Platform Data</h2>
        <p>When you connect a supported Meta account (Instagram Business Account and/or linked Facebook Page) to Modlus, Modlus may access and process information made available through Meta's APIs, strictly according to the permissions you grant during the connection process and in accordance with Meta's own platform policies. Modlus only requests the permissions needed to provide the publishing, scheduling, analytics, and comment-management features described in this policy.</p>

        <h2>E. Access Tokens / Credentials</h2>
        <p>Access tokens and other credentials used to connect your social media accounts are stored in encrypted form and are handled by Modlus only for the purpose of communicating with the relevant platform's API on your behalf. Access tokens are not intentionally displayed to users within the Modlus interface, are not included in application logs, and are not shared outside of what is required to operate the connection.</p>

        <h2>F. Data Sharing</h2>
        <p>Modlus does not sell personal information. Information may be processed by service providers who help operate the Modlus platform (for example, hosting and infrastructure providers), and by the social platform providers themselves (such as Meta) where necessary to deliver the features you use — always limited to what is required for that purpose.</p>

        <h2>G. Data Retention</h2>
        <p>Modlus retains information for as long as reasonably necessary to provide the service, maintain accurate records, support security and troubleshooting, and comply with applicable legal or operational requirements. You may request deletion of applicable data at any time — see the <a href="/data-deletion">Data Deletion Instructions</a> page for details.</p>

        <h2>H. Data Security</h2>
        <p>Modlus applies reasonable technical and organizational measures to protect information, including encrypting stored access tokens and restricting access to authorized systems and personnel. No method of storage or transmission is completely secure, and Modlus cannot guarantee absolute security. Modlus does not claim any specific third-party security certification (such as SOC 2, ISO 27001, or PCI DSS).</p>

        <h2>I. User Rights / Data Requests</h2>
        <p>You may contact Modlus to request access to, correction of, or deletion of applicable personal information associated with your account. For deletion requests specifically, please follow the process described on the <a href="/data-deletion">Data Deletion Instructions</a> page.</p>

        <h2>J. Children's Privacy</h2>
        <p>Modlus is a business tool intended for use by businesses and authorized adult users, and is not directed to children. Modlus does not knowingly collect personal information from children.</p>

        <h2>K. Changes to This Privacy Policy</h2>
        <p>This Privacy Policy may be updated from time to time. The current version will always be published at this page, with the "Last Updated" date above reflecting the most recent revision.</p>

        <h2>L. Contact</h2>
        <p>Questions about this Privacy Policy can be sent to <a href="mailto:support@mqlus.in">support@mqlus.in</a>.</p>
    </div>

    <div class="footer">&copy; <?php echo date('Y'); ?> Modlus. All rights reserved.</div>
</div>
</body>
</html>
