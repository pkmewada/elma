<?php
// Public page — no authentication required (routesMaster.isPublic = 1).
// Standalone, matching privacy-policy.php's convention. Rewritten for Elma
// Real Estate CRM in Phase 6 (see CLAUDE.md "Phase 6"). CONTACT_EMAIL is a
// placeholder pending the client's final legal review.
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
    <title>Terms of Service — <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="Terms governing enquiries submitted to the <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?> CRM.">
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
    <h1>Terms of Service</h1>
    <div class="subtitle">Last Updated: <?= htmlspecialchars($lastUpdated, ENT_QUOTES, 'UTF-8') ?></div>

    <div class="notice">This page is implementation-accurate but is a placeholder pending final review by the client's own legal counsel before public/App Review use.</div>

    <div class="policy-content">
        <h2>1. Acceptance of Terms</h2>
        <p>By submitting an enquiry to <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?> through our website, a Meta Lead Ads form, or a Google Lead Form (the "Service"), you agree to these Terms of Service. If you do not agree, do not submit your details to us.</p>

        <h2>2. Description of Service</h2>
        <p><?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?> operates an internal CRM that receives property enquiries submitted through our website's enquiry form, Meta Lead Ads, and Google Lead Forms, so that our sales team can follow up with you about the project(s) you are interested in.</p>

        <h2>3. Your Responsibilities</h2>
        <p>When submitting an enquiry, you are responsible for providing accurate contact information (name, phone number, email) so that our team can reach you, and for the accuracy of any message or project interest you provide.</p>

        <h2>4. Lead Ads Connections</h2>
        <p>If you submit your details through a Meta or Google lead ad, that platform's own authorization/notification mechanism is used to pass your submitted information to our CRM. We do not control, and are not responsible for, the availability or behavior of Meta's or Google's advertising platforms.</p>

        <h2>5. Follow-Up</h2>
        <p>Submitting an enquiry does not guarantee any specific response time, pricing, availability, or outcome. Our sales team will attempt to follow up using the contact details you provided, through call and/or WhatsApp where applicable.</p>

        <h2>6. Third-Party Platforms</h2>
        <p>Meta (Facebook/Instagram) and Google are third-party platforms governed by their own terms and policies. We are not responsible for actions taken by these platforms, including changes to their APIs, policies, or availability, or for delivery failures of lead notifications caused by systems outside our control.</p>

        <h2>7. Prohibited Use</h2>
        <p>You may not use our enquiry channels to submit unlawful content, to submit enquiries on behalf of someone else without their consent, or to attempt to interfere with or disrupt our CRM or its integrations.</p>

        <h2>8. Intellectual Property</h2>
        <p>Our website, CRM, project listings, and branding are the property of <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?>. Information you submit through an enquiry remains yours; you grant us only the right to use it to respond to your enquiry.</p>

        <h2>9. Service Availability</h2>
        <p>We aim to keep our enquiry channels available and reliable but do not guarantee uninterrupted or error-free operation, and may perform maintenance or updates that temporarily affect availability.</p>

        <h2>10. Limitation of Liability</h2>
        <p>To the maximum extent permitted by applicable law, <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?> is not liable for indirect, incidental, or consequential damages arising from submitting or relying on an enquiry through our Service, including issues caused by third-party platform outages or API changes outside our control.</p>

        <h2>11. Changes to These Terms</h2>
        <p>We may update these Terms from time to time. The current version of these Terms will always be published at this page.</p>

        <h2>12. Contact Information</h2>
        <p>Questions about these Terms can be sent to <a href="mailto:<?= htmlspecialchars(CONTACT_EMAIL, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(CONTACT_EMAIL, ENT_QUOTES, 'UTF-8') ?></a>.</p>
    </div>

    <div class="footer">&copy; <?= date('Y') ?> <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?>. All rights reserved.</div>
</div>
</body>
</html>
