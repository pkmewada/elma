<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/permission-helper.php';
require_once __DIR__ . '/../../includes/Csrf.php';
require_once __DIR__ . '/../../includes/InstagramAutomation.php';
require_once __DIR__ . '/../../includes/SocialPostEngine.php';
require_once __DIR__ . '/../../includes/leadActivityLogger.php';

header('Content-Type: application/json; charset=UTF-8');

function respond(bool $success, string $message, array $data = []): void
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ]);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.');
}

try {
    requireValidCsrfToken();
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}

$clientId = (int)($_POST['clientId'] ?? 0);
$accountId = (int)($_POST['instagramAccountId'] ?? 0);
$platforms = isset($_POST['platforms']) && is_array($_POST['platforms']) ? array_map('strval', $_POST['platforms']) : [];
$caption = trim((string)($_POST['caption'] ?? ''));
$mediaType = trim((string)($_POST['mediaType'] ?? 'image'));

// Same two guards used by api/saveSocialPost.php — no second
// client/account validation system introduced for this endpoint.
if ($clientId <= 0 || !instagramClientExists($con, $clientId)) {
    respond(false, 'Please select a valid client.');
}

if ($accountId <= 0 || !instagramAccountBelongsToClient($con, $accountId, $clientId)) {
    respond(false, 'Please select an Instagram account that belongs to the selected client.');
}

if (empty($platforms)) {
    respond(false, 'Please select at least one platform to publish to.');
}

if (strlen($caption) > 2200) {
    respond(false, 'Caption must be 2200 characters or fewer.');
}

if ($mediaType === 'text') {
    // Facebook text publishing (Phase 10) — reuses the existing
    // SocialPostEngine text dispatch (publishFacebookTextPost()); no new
    // publishing code. Instagram has no text-only feed post, so it's
    // rejected here even if somehow still selected client-side.
    if (in_array('instagram', $platforms, true)) {
        respond(false, 'Text posts can only be published to Facebook.');
    }

    if ($caption === '') {
        respond(false, 'Please enter the text for this post.');
    }

    $type = 'text';
    $content = ['message' => $caption];
} else {
    // Publish Now currently supports image and text posts only (see docs
    // §22.7/§22.12) — reels/carousels still go through the existing
    // Draft/Schedule + cron flow.
    $imageUrl = '';

    if (isset($_FILES['media']) && !empty($_FILES['media']['name'][0] ?? '')) {
        $uploadResult = saveInstagramMediaFiles($_FILES['media'], 'image', 1);

        if (!empty($uploadResult['errors'])) {
            respond(false, implode(' ', $uploadResult['errors']));
        }

        if (!empty($uploadResult['paths'])) {
            $absoluteUrls = socialPostMediaAbsoluteUrls([$uploadResult['paths'][0]]);
            $imageUrl = $absoluteUrls[0] ?? '';
        }
    }

    if ($imageUrl === '') {
        respond(false, 'Please upload an image to publish now.');
    }

    $type = 'image';
    $content = ['imageUrl' => $imageUrl, 'caption' => $caption];
}

try {
    $result = publishSocialPost($con, $clientId, $accountId, $platforms, $type, $content);

    $clientLabel = getInstagramClientLabel($con, $clientId);

    saveActivityLog(
        $con,
        'InstagramAutomation',
        0,
        'publish-now',
        'Social post published now (' . implode('+', $platforms) . ', status: ' . $result['status'] . ') for Client: ' . $clientLabel . '.'
    );

    $message = $result['status'] === 'success'
        ? 'Published successfully.'
        : ($result['status'] === 'partial'
            ? 'Published with partial success — check platform results.'
            : ($result['message'] ?? 'Publishing failed.'));

    // 'partial' is reported as success=true at the HTTP layer too — the
    // caller must render per-platform results, not a blanket failure,
    // whenever at least one platform actually published (docs §22.7).
    respond($result['status'] !== 'failed', $message, [
        'result' => $result,
    ]);
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
