<?php
// Public page — no authentication required (routesMaster.isPublic = 1).
// Intentionally standalone, matching pages/terms-and-conditions.php's
// existing layoutType = 'public' convention. This exact URL is intended
// for use as Meta's "Data Deletion Instructions URL".
$lastUpdated = 'August 28, 2026';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Deletion Instructions — Modlus</title>
    <meta name="description" content="Modlus Data Deletion Instructions — How to request deletion of your Modlus data.">
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
    <h1>Data Deletion Instructions</h1>
    <div class="subtitle">Last Updated: <?php echo htmlspecialchars($lastUpdated, ENT_QUOTES, 'UTF-8'); ?></div>

    <div class="policy-content">
        <p>This page explains how to request deletion of data associated with your use of Modlus's social media management and automation service.</p>

        <h2>What Can Be Deleted</h2>
        <p>Upon a verified request, Modlus can delete the following data that it stores:</p>
        <ul>
            <li>Connected social media account information (such as stored Instagram/Facebook account identifiers and connection status).</li>
            <li>Stored social account metadata (such as username and linked Page information).</li>
            <li>Scheduled and published post records maintained by Modlus for the applicable account.</li>
            <li>Comments and webhook event records associated with the applicable account.</li>
            <li>Analytics/insights data stored by Modlus for the applicable account.</li>
            <li>Other personal information maintained by Modlus in connection with your account.</li>
        </ul>

        <h2>How to Request Deletion</h2>
        <ol>
            <li>Contact Modlus at <a href="mailto:support@mqlus.in">support@mqlus.in</a>.</li>
            <li>Identify the Modlus account and/or connected social media account(s) involved in your request.</li>
            <li>State clearly that you are requesting data deletion.</li>
            <li>Modlus will verify the request where necessary before proceeding.</li>
            <li>Applicable data will be deleted in accordance with Modlus's data retention practices and any applicable legal or operational requirements.</li>
        </ol>

        <h2>Social Platform Data</h2>
        <p>Deleting data from Modlus removes the applicable information from Modlus's own systems, but it does not delete information held directly by Meta, Instagram, Facebook, or other third-party platforms. Modlus cannot delete data from a third-party platform's own systems. To remove information held directly by Meta/Instagram/Facebook, or to revoke Modlus's access to your account, please use that platform's own account and app-permission settings directly.</p>

        <h2>Processing Time</h2>
        <p>Requests will be reviewed and processed within a reasonable period, subject to verification and applicable legal or operational requirements.</p>

        <h2>Confirmation</h2>
        <p>Once the applicable Modlus data has been deleted, you may receive confirmation at the contact address used to submit the request.</p>
    </div>

    <div class="footer">&copy; <?php echo date('Y'); ?> Modlus. All rights reserved.</div>
</div>
</body>
</html>
