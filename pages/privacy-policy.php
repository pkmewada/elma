<?php
// Public page — no authentication required (routesMaster.isPublic = 1).
// Intentionally standalone (no includes/header.php or includes/sidebar.php)
// so it renders correctly for an anonymous visitor and for Meta App Review
// reviewers with no session/cookies/JS. Rewritten for Elma Real Estate CRM
// in Phase 6 (previously Modlus/social-media-branded content — see
// CLAUDE.md "Phase 6"). Content describes the CRM's actual behavior only;
// CONTACT_EMAIL below is a placeholder pending the client's final legal
// review and does not represent a company address, registration, or legal
// entity claim.
require_once __DIR__ . '/../includes/config.php';
$lastUpdated = 'September 28, 2026';
$brand = BRAND_NAME;
if (!defined('CONTACT_EMAIL')) {
    define('CONTACT_EMAIL', 'privacy@elmarealestate.example'); // PLACEHOLDER — replace before publishing.
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy — <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?> Privacy Policy — how this CRM collects, uses, and protects lead and account data.">
    <style>
        body { margin:0; font-family:'Segoe UI',Tahoma,Arial,sans-serif; background:#f4f6f8; color:#333; }
        .container { max-width:900px; margin:50px auto; background:#fff; padding:40px; border-radius:12px; box-shadow:0 8px 25px rgba(0,0,0,0.08); }
        h1 { font-size:26px; margin-bottom:10px; }
        .subtitle { font-size:14px; color:#777; margin-bottom:30px; }
        .notice { font-size:13px; background:#fff8e1; border:1px solid #ffe08a; border-radius:8px; padding:12px 16px; margin-bottom:24px; color:#6b5500; }
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
    <h1>Privacy Policy</h1>
    <div class="subtitle">Last Updated: <?= htmlspecialchars($lastUpdated, ENT_QUOTES, 'UTF-8') ?></div>

    <div class="notice">This page describes how the <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?> CRM technically handles data today. It is implementation-accurate but is a placeholder pending final review by the client's own legal counsel before public/App Review use.</div>

    <div class="policy-content">
        <h2>A. Introduction</h2>
        <p><?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?> ("<?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?>", "we", "us", or "our") operates a Customer Relationship Management (CRM) system used internally by our sales team to capture, track, and follow up on real estate leads (people interested in our projects/properties). This Privacy Policy explains what information the CRM collects, how it is used, and the choices available to you as a lead or enquirer.</p>

        <h2>B. Information We Collect</h2>
        <p>The CRM collects the information reasonably necessary to respond to a property enquiry, including:</p>
        <ul>
            <li>Contact details you provide when enquiring about a property: full name, phone number, and email address.</li>
            <li>The project or property you expressed interest in, and any message or notes submitted with your enquiry.</li>
            <li>How your enquiry reached us: directly through our website's enquiry form, through a Meta (Facebook/Instagram) Lead Ads form, through a Google Lead Form ad, or by messaging our WhatsApp Business number.</li>
            <li>If you message our WhatsApp Business number: the content of the messages you send us (text, and any image or document you share), and the messages/templates we send back to you.</li>
            <li>Follow-up activity recorded by our sales team, such as call outcomes, status updates, and scheduled follow-ups.</li>
            <li>Technical information reasonably needed to operate and secure the CRM, such as request logs used for troubleshooting.</li>
        </ul>
        <p>The CRM does not collect payment information, precise device location, contacts, SMS messages, microphone or camera data, or other device data unrelated to handling a property enquiry.</p>

        <h2>C. How We Use Information</h2>
        <p>Information is used to:</p>
        <ul>
            <li>Respond to your enquiry and get in touch about the property/project you are interested in.</li>
            <li>Assign your enquiry to a member of our sales team and track follow-up (call, WhatsApp, site visit, etc.).</li>
            <li>Maintain a record of our communication history with you.</li>
            <li>Operate, maintain, and improve our internal sales process.</li>
            <li>Maintain account security for our CRM's staff users, including detecting and responding to unauthorized access.</li>
        </ul>

        <h2>D. Meta and Google Lead Ads</h2>
        <p>If you submit your details through a Meta (Facebook/Instagram) Lead Ads form or a Google Lead Form advertising one of our projects, the information you provide on that form (such as your name, phone number, and email address) is passed to our CRM through that platform's own lead-notification mechanism, strictly according to the permissions associated with that ad form. We only request the information needed to follow up on your enquiry.</p>

        <h2>D2. WhatsApp Messaging (Meta WhatsApp Cloud API)</h2>
        <p>If you message our WhatsApp Business number, or we message you there in connection with your enquiry, that conversation (including message text, media you or we share, and delivery/read status) is received and sent through Meta's WhatsApp Cloud API and stored in our CRM so our sales team can view and continue the conversation. Outside of an active conversation window, we may only send you a pre-approved WhatsApp message template, per Meta's WhatsApp messaging rules. We do not use your WhatsApp number or conversation content for advertising or marketing without your enquiry giving rise to that contact.</p>

        <h2>E. Credentials Used to Receive Leads</h2>
        <p>API keys, access tokens, and shared secrets used by the CRM to receive leads or messages from Meta, Google, WhatsApp, or our website form are stored in encrypted form and are used only to authenticate incoming lead/message notifications and to send messages back through those platforms. These credentials are not displayed in the CRM interface after they are saved, are not included in application logs, and are not shared outside of what is required to operate these channels.</p>

        <h2>F. Data Sharing</h2>
        <p>We do not sell personal information. Information may be processed by service providers who help operate our CRM (such as hosting/infrastructure providers), and by the platforms themselves (Meta/WhatsApp, Google) where necessary to deliver your enquiry or message to us, or to deliver our reply to you — always limited to what is required for that purpose.</p>

        <h2>G. Data Retention</h2>
        <p>We retain enquiry and lead information for as long as reasonably necessary to respond to your enquiry, maintain accurate sales records, and comply with applicable legal or operational requirements. You may request deletion of your data at any time — see the <a href="/data-deletion">Data Deletion Instructions</a> page for details.</p>

        <h2>H. Data Security</h2>
        <p>We apply reasonable technical and organizational measures to protect information, including encrypting stored credentials/secrets and restricting CRM access to authorized staff. No method of storage or transmission is completely secure, and we cannot guarantee absolute security. We do not claim any specific third-party security certification (such as SOC 2, ISO 27001, or PCI DSS).</p>

        <h2>I. Your Rights / Data Requests</h2>
        <p>You may contact us to request access to, correction of, or deletion of personal information associated with your enquiry. For deletion requests specifically, please follow the process described on the <a href="/data-deletion">Data Deletion Instructions</a> page.</p>

        <h2>J. Children's Privacy</h2>
        <p>Our CRM is a business tool used by our sales team and is not directed to children. We do not knowingly collect personal information from children.</p>

        <h2>K. Changes to This Privacy Policy</h2>
        <p>This Privacy Policy may be updated from time to time. The current version will always be published at this page, with the "Last Updated" date above reflecting the most recent revision.</p>

        <h2>L. Contact</h2>
        <p>Questions about this Privacy Policy can be sent to <a href="mailto:<?= htmlspecialchars(CONTACT_EMAIL, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(CONTACT_EMAIL, ENT_QUOTES, 'UTF-8') ?></a>.</p>
    </div>

    <div class="footer">&copy; <?= date('Y') ?> <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?>. All rights reserved.</div>
</div>
</body>
</html>
