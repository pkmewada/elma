<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);


header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadEngine.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../includes/permission-helper.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// --------------------------
// Authentication
// --------------------------
requireApiPermission(['/oboarding-lead', '/emp-onboarding-lead'], 'canApprove');

// --------------------------
// Agreement Id
// --------------------------
$agreementId = (int)($_POST['agreementId'] ?? 0);
if ($agreementId <= 0) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid agreement.'
    ]);
    exit;
}

// --------------------------
// Action
// --------------------------
$action = trim($_POST['action'] ?? '');
if (!in_array($action, ['approved', 'rejected'], true)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid review action.'
    ]);
    exit;
}


// --------------------------
// Company Signatory
// --------------------------


$companySignatoryName =
    trim($_POST['companySignatoryName'] ?? '');

$companySignature =
    $_POST['companySignature'] ?? '';

if ($action === 'approved') {

    if ($companySignatoryName === '') {

        http_response_code(422);

        echo json_encode([
            'success' => false,
            'message' => 'Company signatory name is required.'
        ]);

        exit;
    }

    if ($companySignature === '') {

        http_response_code(422);

        echo json_encode([
            'success' => false,
            'message' => 'Company signature is required.'
        ]);

        exit;
    }

}

// --------------------------
// Remark
// --------------------------
$remark = trim($_POST['remark'] ?? '');
if ($action === 'rejected' && $remark === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Remark is required for rejection.'
    ]);
    exit;
}

// --------------------------
// Load Submission
// --------------------------
$leadEngine = new LeadEngine($con);
$submission = $leadEngine->getSubmissionByAgreementId($agreementId);

if (!$submission) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Submission not found.'
    ]);
    exit;
}

// --------------------------
// Prevent Double Review
// --------------------------
if (
    !empty($submission['reviewStatus']) &&
    $submission['reviewStatus'] === 'approved'
) {

    echo json_encode([
        'success' => false,
        'message' => 'Submission already approved.'
    ]);

    exit;
}

if ($action === 'approved') {

    $uploadDir =
        __DIR__ .
        '/../uploads/onboarding/company-signatures/';

    if (!is_dir($uploadDir)) {

        mkdir(
            $uploadDir,
            0755,
            true
        );

    }

    $signatureFile =
        'company_' .
        $agreementId .
        '_' .
        time() .
        '.png';

    $signaturePath =
        $uploadDir .
        $signatureFile;

    $result = file_put_contents(
        $signaturePath,
        base64_decode(
            preg_replace(
                '#^data:image/\w+;base64,#i',
                '',
                $companySignature
            )
        )
    );
    
    if ($result === false) {
    
        http_response_code(500);
    
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save company signature image.'
        ]);
    
        exit;
    }

    $savedCompanySignature =
        $leadEngine->saveCompanySignature(

            $agreementId,

            $companySignatoryName,

            $signatureFile

        );

    if (!$savedCompanySignature) {

        http_response_code(500);

        echo json_encode([

            'success' => false,

            'message' => 'Failed to save company signature.'

        ]);

        exit;

    }

}

// --------------------------
// Save Review
// --------------------------
$userId = getLoggedInUserId();
$reviewSaved = $leadEngine->saveAgreementReview($agreementId, $action, $remark, $userId);

if (!$reviewSaved) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save review.'
    ]);
    exit;
}

// --------------------------
// Update Agreement Status
// --------------------------
$statusUpdated = $leadEngine->updateAgreementReviewStatus(
    $agreementId,
    $action
);

if (!$statusUpdated) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Failed to update agreement status.'
    ]);

    exit;
}

// --------------------------
// Generate Signed Agreement
// --------------------------
$signedAgreementFile = null;


// --------------------------
// Save Company Signature
// --------------------------

if ($action === 'approved') {

    $signedAgreementFile =
        $leadEngine->generateSignedAgreementPdf(
            $agreementId
        );

    if (!$signedAgreementFile) {

        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => 'Agreement approved, but signed PDF generation failed.'
        ]);

        exit;
    }
}

// --------------------------
// Trigger Email
// --------------------------
$leadId = (int)$submission['leadId'];
$clientEmail = $submission['email'];
$clientName = $submission['fullName'];
$agreementLink = SITE_URL . '/agreement?token=' . $submission['agreementToken'];

if ($action === 'approved') {

    $signedAgreementUrl =
        SITE_URL .
        '/uploads/onboarding/agreements/' .
        $signedAgreementFile;

    $signedAgreementPath =
        __DIR__ .
        '/../uploads/onboarding/agreements/' .
        $signedAgreementFile;

    sendAgreementCompletedMail(
        $leadId,
        $clientEmail,
        $clientName,
        $signedAgreementUrl,
        $signedAgreementPath
    );
    
    $leadEngine->createClientMaster($agreementId);

} else {

    sendAgreementCorrectionMail(
        $leadId,
        $clientEmail,
        $clientName,
        $agreementLink,
        $remark
    );
}

// --------------------------
// Success Response
// --------------------------
echo json_encode([
    'success' => true,
    'agreementStatus' => $action,
    'message' => $action === 'approved' ? 'Agreement approved successfully.' : 'Agreement rejected successfully.'
]);