<?php
// Public page — no authentication required (routesMaster.isPublic = 1).
// Standalone, matching privacy-policy.php's convention. This exact URL is
// intended for use as Meta's "Data Deletion Instructions URL". Rewritten
// for Elma Real Estate CRM in Phase 6 (see CLAUDE.md "Phase 6").
// CONTACT_EMAIL is a placeholder pending the client's final legal review.
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
    <title>Data Deletion Instructions — <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="How to request deletion of your data from the <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?> CRM.">
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
    <h1>Data Deletion Instructions</h1>
    <div class="subtitle">Last Updated: <?= htmlspecialchars($lastUpdated, ENT_QUOTES, 'UTF-8') ?></div>

    <div class="notice">This page is implementation-accurate but is a placeholder pending final review by the client's own legal counsel before public/App Review use.</div>

    <div class="policy-content">
        <p>This page explains how to request deletion of data associated with an enquiry you submitted to <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?>, whether through our website, a Meta Lead Ads form, a Google Lead Form, or a WhatsApp conversation with our business number.</p>

        <h2>What Can Be Deleted</h2>
        <p>Upon a verified request, we can delete the following data that our CRM stores about you:</p>
        <ul>
            <li>Your contact details (name, phone number, email address) submitted with your enquiry.</li>
            <li>The project/property interest and any message submitted with your enquiry.</li>
            <li>Follow-up and communication records our sales team kept about your enquiry.</li>
            <li>Your WhatsApp conversation history with us, including messages and any media exchanged.</li>
            <li>The record of how your enquiry reached us (website form, Meta Lead Ads, Google Lead Forms, or WhatsApp).</li>
        </ul>

        <h2>How to Request Deletion</h2>
        <ol>
            <li>Contact us at <a href="mailto:<?= htmlspecialchars(CONTACT_EMAIL, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(CONTACT_EMAIL, ENT_QUOTES, 'UTF-8') ?></a>.</li>
            <li>Identify the phone number and/or email address used in your enquiry.</li>
            <li>State clearly that you are requesting data deletion.</li>
            <li>We will verify the request where necessary before proceeding.</li>
            <li>Applicable data will be deleted from our CRM in accordance with our data retention practices and any applicable legal or operational requirements.</li>
        </ol>

        <h2>Social Platform Data</h2>
        <p>Deleting your data from our CRM removes the applicable information from our own systems, but it does not delete information held directly by Meta, Instagram, Facebook, WhatsApp, or Google. We cannot delete data from a third-party platform's own systems. To remove information held directly by those platforms, or to stop seeing ads from us, please use that platform's own account and ad-preference settings directly; to stop receiving WhatsApp messages from us, you may also block or report our business number directly in WhatsApp.</p>

        <h2>Processing Time</h2>
        <p>Requests will be reviewed and processed within a reasonable period, subject to verification and applicable legal or operational requirements.</p>

        <h2>Confirmation</h2>
        <p>Once your data has been deleted from our CRM, you may receive confirmation at the contact address used to submit the request.</p>
    </div>

    <div class="footer">&copy; <?= date('Y') ?> <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?>. All rights reserved.</div>
</div>
</body>
</html>
