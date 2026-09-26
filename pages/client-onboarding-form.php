<?php
/**
 * Client Onboarding Form
 * 
 * This is a public page - no authentication required
 * Clients access this page via a secure token from email
 */

// ============================================================
// SECURITY: Prevent direct access, enable error reporting
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors to users
ini_set('log_errors', 1);

// ============================================================
// INCLUDES
// ============================================================
include __DIR__ . "/../includes/db.php";
include __DIR__ . "/../includes/leadEngine.php";

// ============================================================
// FUNCTION: Display Beautiful Error Page
// ============================================================
function showErrorPage($title, $message, $icon = 'ri-error-warning-line', $buttonText = '', $buttonLink = '')
{
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/remixicon@4.0.0/fonts/remixicon.css" rel="stylesheet">
        <style>
            body {
                background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                margin: 0;
                padding: 20px;
            }
            .error-container {
                background: white;
                border-radius: 20px;
                padding: 50px 40px;
                max-width: 550px;
                width: 100%;
                text-align: center;
                box-shadow: 0 20px 60px rgba(0,0,0,0.15);
                animation: fadeInUp 0.6s ease;
            }
            .error-container .icon {
                font-size: 70px;
                display: block;
                margin-bottom: 15px;
            }
            .error-container .icon.error {
                color: #dc3545;
            }
            .error-container .icon.success {
                color: #28a745;
            }
            .error-container .icon.warning {
                color: #ffc107;
            }
            .error-container .icon.info {
                color: #17a2b8;
            }
            .error-container h1 {
                font-size: 28px;
                font-weight: 700;
                color: #1a1a2e;
                margin-bottom: 12px;
            }
            .error-container .subtitle {
                font-size: 16px;
                color: #6c757d;
                line-height: 1.7;
                margin-bottom: 8px;
            }
            .error-container .divider {
                width: 50px;
                height: 3px;
                background: #e0e0e0;
                margin: 18px auto;
                border-radius: 2px;
            }
            .error-container .message-box {
                background: #f8f9fa;
                border-radius: 10px;
                padding: 15px 20px;
                margin: 15px 0 25px;
                text-align: left;
                border-left: 4px solid #6c757d;
            }
            .error-container .message-box.error-border {
                border-left-color: #dc3545;
            }
            .error-container .message-box.success-border {
                border-left-color: #28a745;
            }
            .error-container .message-box.warning-border {
                border-left-color: #ffc107;
            }
            .error-container .message-box.info-border {
                border-left-color: #17a2b8;
            }
            .error-container .message-box p {
                margin: 0;
                font-size: 14px;
                color: #333;
                line-height: 1.6;
            }
            .error-container .btn-custom {
                padding: 12px 35px;
                border-radius: 50px;
                font-weight: 600;
                font-size: 15px;
                transition: all 0.3s ease;
                text-decoration: none;
                display: inline-block;
            }
            .error-container .btn-custom:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(0,0,0,0.15);
            }
            .error-container .btn-primary-custom {
                background: #1a1a2e;
                color: white;
                border: none;
            }
            .error-container .btn-primary-custom:hover {
                background: #2d2d44;
                color: white;
            }
            .error-container .btn-success-custom {
                background: #28a745;
                color: white;
                border: none;
            }
            .error-container .btn-success-custom:hover {
                background: #218838;
                color: white;
            }
            .error-container .btn-danger-custom {
                background: #dc3545;
                color: white;
                border: none;
            }
            .error-container .btn-danger-custom:hover {
                background: #c82333;
                color: white;
            }
            .error-container .btn-warning-custom {
                background: #ffc107;
                color: #1a1a2e;
                border: none;
            }
            .error-container .btn-warning-custom:hover {
                background: #e0a800;
                color: #1a1a2e;
            }
            .error-container .contact-support {
                margin-top: 20px;
                font-size: 13px;
                color: #6c757d;
            }
            .error-container .contact-support a {
                color: #1a1a2e;
                text-decoration: none;
                font-weight: 600;
            }
            .error-container .contact-support a:hover {
                text-decoration: underline;
            }
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            @media (max-width: 576px) {
                .error-container {
                    padding: 30px 20px;
                }
                .error-container h1 {
                    font-size: 22px;
                }
                .error-container .icon {
                    font-size: 50px;
                }
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <i class="ri <?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?> icon 
                <?php 
                    if (strpos($icon, 'error') !== false) echo 'error';
                    elseif (strpos($icon, 'success') !== false) echo 'success';
                    elseif (strpos($icon, 'warning') !== false) echo 'warning';
                    else echo 'info';
                ?>
            "></i>
            <h1><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="divider"></div>
            <div class="message-box 
                <?php 
                    if (strpos($icon, 'error') !== false) echo 'error-border';
                    elseif (strpos($icon, 'success') !== false) echo 'success-border';
                    elseif (strpos($icon, 'warning') !== false) echo 'warning-border';
                    else echo 'info-border';
                ?>
            ">
                <p><?php echo nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')); ?></p>
            </div>
            <?php if (!empty($buttonText) && !empty($buttonLink)): ?>
            <a href="<?php echo htmlspecialchars($buttonLink, ENT_QUOTES, 'UTF-8'); ?>" class="btn-custom btn-primary-custom">
                <i class="ri-arrow-left-line me-1"></i> <?php echo htmlspecialchars($buttonText, ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <?php endif; ?>
            <div class="contact-support">
                Need help? Contact our <a href="mailto:support@mqlus.in">Support Team</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ============================================================
// GET AND VALIDATE TOKEN
// ============================================================
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

// Validate token format (must be 64 characters hex)
if (empty($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    showErrorPage(
        'Invalid Access Token',
        'The link you used appears to be invalid or malformed. Please check the URL and try again, or contact our support team for assistance.',
        'ri-error-warning-line'
    );
}

// ============================================================
// LOAD FORM DATA
// ============================================================
$leadEngine = new LeadEngine($con);
$formData = $leadEngine->getOnboardingFormByToken($token);

if (!$formData) {
    showErrorPage(
        'Form Expired or Invalid',
        'This onboarding form link has expired or is no longer valid. This could happen if the form has been inactive for more than 7 days. Please request a new link from your account manager.',
        'ri-time-line'
    );
}

// ============================================================
// CHECK IF ALREADY SUBMITTED
// ============================================================
if ($formData['formStatus'] === 'clientSubmitted') {
    showErrorPage(
        'Form Already Submitted ✅',
        'Thank you! Your onboarding form has already been submitted successfully. Our team is reviewing your information and will get back to you shortly.',
        'ri-check-double-line',
        'Go to Home',
        '/'
    );
}

// ============================================================
// MARK AS VIEWED
// ============================================================
$leadEngine->markFormAsViewed($formData['id']);

// ============================================================
// DETERMINE WHICH SERVICES TO SHOW (ONLY NEW SERVICES)
// ============================================================
$allSelectedServices = $formData['selectedServices'] ?? [];

// Get already submitted services from clientServiceCredentials
$submittedServices = [];
$stmt = mysqli_prepare($con, "
    SELECT DISTINCT serviceType 
    FROM clientServiceCredentials 
    WHERE onboardingFormId = ?
");

if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $formData['id']);
    mysqli_stmt_execute($stmt);
    $credResult = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($credResult)) {
        $submittedServices[] = $row['serviceType'];
    }
    mysqli_stmt_close($stmt);
}

// Get services that still need to be configured (NEW services)
$servicesToShow = array_diff($allSelectedServices, $submittedServices);

// If no services to show, display message
if (empty($servicesToShow)) {
    showErrorPage(
        'All Services Configured ✅',
        'All selected services have already been configured successfully. No further action is required from your side. Thank you for completing the onboarding process!',
        'ri-check-double-line',
        'Go to Home',
        '/'
    );
}

// ✅ Use only NEW services for the form
$selectedServices = $servicesToShow;

// Check if this is a partial form
$totalServices = count($allSelectedServices);
$newServicesCount = count($servicesToShow);
$isPartial = $newServicesCount < $totalServices;

// ============================================================
// SERVICE CONFIGURATIONS
// ============================================================
$serviceConfigs = [
    'socialMedia' => [
        'label' => 'Social Media (Instagram | Facebook)',
        'icon' => 'ri-instagram-line',
        'fields' => [
            'username' => ['label' => 'Username/Page Name', 'type' => 'text', 'required' => true],
            'password' => ['label' => 'Password', 'type' => 'password', 'required' => true]
        ]
    ],
    'youtube' => [
        'label' => 'YouTube',
        'icon' => 'ri-youtube-line',
        'fields' => [
            'grantAccess' => ['label' => 'Grant Access to MQlus Team', 'type' => 'checkbox', 'required' => false],
            'screenshot' => ['label' => 'Upload Screenshot', 'type' => 'file', 'required' => false]
        ]
    ],
    'pinterest' => [
        'label' => 'Pinterest',
        'icon' => 'ri-pinterest-line',
        'fields' => [
            'username' => ['label' => 'Username', 'type' => 'text', 'required' => true],
            'password' => ['label' => 'Password', 'type' => 'password', 'required' => true]
        ]
    ],
    'twitter' => [
        'label' => 'Twitter (X)',
        'icon' => 'ri-twitter-x-line',
        'fields' => [
            'username' => ['label' => 'Username', 'type' => 'text', 'required' => true],
            'password' => ['label' => 'Password', 'type' => 'password', 'required' => true]
        ]
    ],
    'gmb' => [
        'label' => 'Google My Business',
        'icon' => 'ri-google-line',
        'fields' => [
            'username' => ['label' => 'Username', 'type' => 'text', 'required' => true],
            'password' => ['label' => 'Password', 'type' => 'password', 'required' => true]
        ]
    ],
    'googleAds' => [
        'label' => 'Google Ads',
        'icon' => 'ri-google-line',
        'fields' => [
            'email' => ['label' => 'Google Account Email', 'type' => 'email', 'required' => true],
            'password' => ['label' => 'Google Account Password', 'type' => 'password', 'required' => true]
        ]
    ],
    'metaAds' => [
        'label' => 'Meta Ads (Facebook + Instagram)',
        'icon' => 'ri-facebook-circle-line',
        'fields' => [
            'fbUsername' => ['label' => 'Facebook Username', 'type' => 'text', 'required' => true],
            'fbPassword' => ['label' => 'Facebook Password', 'type' => 'password', 'required' => true],
            'igUsername' => ['label' => 'Instagram Username', 'type' => 'text', 'required' => true],
            'igPassword' => ['label' => 'Instagram Password', 'type' => 'password', 'required' => true]
        ]
    ],
    'videos' => [
        'label' => 'Videos',
        'icon' => 'ri-video-line',
        'fields' => []
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Client Onboarding Form</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Remix Icon -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.0.0/fonts/remixicon.css" rel="stylesheet">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* ============================================================
           GLOBAL STYLES
           ============================================================ */
        * {
            box-sizing: border-box;
        }
        
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            min-height: 100vh;
        }
        
        .form-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 40px rgba(0,0,0,0.1);
            padding: 40px;
        }
        
        /* ============================================================
           HEADER
           ============================================================ */
        .header-section {
            text-align: center;
            padding-bottom: 25px;
            border-bottom: 2px solid #f0f0f0;
            margin-bottom: 25px;
        }
        
        .header-section h2 {
            color: #1a1a2e;
            font-weight: 700;
            font-size: 28px;
        }
        
        .client-badge {
            display: inline-block;
            padding: 8px 20px;
            background: #e8f5e9;
            color: #2e7d32;
            border-radius: 50px;
            font-weight: 500;
            margin: 5px;
            font-size: 14px;
        }
        
        /* ============================================================
           ALERTS
           ============================================================ */
        .alert-info-custom {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            color: #1e3a8a;
        }
        
        .alert-info-custom i {
            font-size: 18px;
            margin-right: 8px;
        }
        
        /* ============================================================
           SERVICE TABS
           ============================================================ */
        .service-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 25px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .service-tab {
            padding: 10px 20px;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            user-select: none;
        }
        
        .service-tab:hover {
            border-color: #4CAF50;
            background: #f1f8f1;
        }
        
        .service-tab.active {
            border-color: #4CAF50;
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .service-tab .badge {
            font-size: 10px;
            padding: 2px 8px;
        }
        
        .service-tab .badge.bg-success {
            background-color: #4CAF50 !important;
        }
        
        /* ============================================================
           SERVICE CONTENT
           ============================================================ */
        .service-content {
            display: none;
            padding: 20px;
            background: #fafafa;
            border-radius: 10px;
            border: 1px solid #e8e8e8;
            animation: fadeIn 0.3s ease;
        }
        
        .service-content.active {
            display: block;
        }
        
        /* ============================================================
           COMMON FIELDS
           ============================================================ */
        .common-fields {
            background: #f0f7ff;
            border: 1px solid #d4e4ff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .common-fields .common-title {
            font-weight: 600;
            color: #1a56db;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        /* ============================================================
           FORM ELEMENTS
           ============================================================ */
        .required-field::after {
            content: ' *';
            color: #d32f2f;
            font-weight: bold;
        }
        
        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            color: #666;
            transition: color 0.2s;
        }
        
        .password-toggle:hover {
            color: #333;
        }
        
        .form-control.password-field {
            padding-right: 40px;
        }
        
        .file-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .file-preview img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        
        /* ============================================================
           PROGRESS BAR
           ============================================================ */
        #progressBar {
            height: 6px;
            border-radius: 3px;
            background: #e0e0e0;
            margin-bottom: 25px;
            overflow: hidden;
        }
        
        #progressBar .progress-fill {
            height: 100%;
            border-radius: 3px;
            background: linear-gradient(90deg, #4CAF50, #45a049);
            transition: width 0.5s ease;
            width: 0%;
        }
        
        /* ============================================================
           STEP INDICATOR
           ============================================================ */
        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .step-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #ddd;
            transition: all 0.3s ease;
        }
        
        .step-dot.active {
            background: #4CAF50;
            transform: scale(1.3);
        }
        
        .step-dot.completed {
            background: #4CAF50;
        }
        
        /* ============================================================
           DYNAMIC LISTS
           ============================================================ */
        .dynamic-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .dynamic-item {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .dynamic-item input {
            flex: 1;
        }
        
        /* ============================================================
           FORM ACTIONS
           ============================================================ */
        .form-actions {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        /* ============================================================
           ANIMATIONS
           ============================================================ */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeOutOverlay {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(-10px);
            }
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 768px) {
            .form-container {
                padding: 20px;
                margin: 10px;
            }
            
            .service-tabs {
                flex-direction: column;
            }
            
            .service-tab {
                width: 100%;
                justify-content: center;
            }
            
            .form-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .form-actions .btn {
                width: 100%;
            }
            
            .header-section h2 {
                font-size: 22px;
            }
        }
        
        @media (max-width: 576px) {
            body {
                padding: 10px;
            }
            
            .form-container {
                padding: 15px;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="form-container">
            
            <!-- ============================================================
            HEADER
            ============================================================ -->
            <div class="header-section">
                <h2>📋 Client Onboarding Form</h2>
                <p class="text-muted">Please provide the required information for your selected services.</p>
                <div>
                    <span class="client-badge">
                        <i class="ri-user-line me-1"></i>
                        <?php echo htmlspecialchars($formData['fullName'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                    <span class="client-badge">
                        <i class="ri-barcode-line me-1"></i>
                        <?php echo htmlspecialchars($formData['clientCode'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
            </div>

            <!-- ============================================================
            PARTIAL FORM NOTIFICATION
            ============================================================ -->
            <?php if ($isPartial): ?>
            <div class="alert-info-custom">
                <i class="ri-information-line"></i>
                <strong>New Services Added:</strong> 
                You are only configuring the newly added services. 
                Previously configured services are already completed.
            </div>
            <?php endif; ?>

            <!-- ============================================================
            PROGRESS BAR
            ============================================================ -->
            <div id="progressBar">
                <div class="progress-fill" id="progressFill"></div>
            </div>

            <!-- ============================================================
            STEP DOTS
            ============================================================ -->
            <div class="step-indicator">
                <span class="step-dot active" id="stepDot1"></span>
                <span class="step-dot" id="stepDot2"></span>
            </div>

            <!-- ============================================================
            FORM
            ============================================================ -->
            <form id="clientForm" onsubmit="submitForm(event)" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="formId" value="<?php echo (int)$formData['id']; ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">

                <?php if (!empty($selectedServices)): ?>
                
                <!-- ============================================================
                STEP 1: SERVICE SELECTION
                ============================================================ -->
                <div id="step1">
                    <h5 class="mb-3">Select Services to Configure</h5>
                    <p class="text-muted small mb-3">Click on a service below to provide its credentials.</p>
                    
                    <!-- Service Tabs -->
                    <div class="service-tabs">
                        <?php foreach ($selectedServices as $service): ?>
                            <?php 
                            $config = $serviceConfigs[$service] ?? null;
                            if (!$config) continue;
                            ?>
                            <div class="service-tab" 
                                 data-service="<?php echo htmlspecialchars($service, ENT_QUOTES, 'UTF-8'); ?>"
                                 onclick="selectService('<?php echo htmlspecialchars($service, ENT_QUOTES, 'UTF-8'); ?>')">
                                <i class="<?php echo htmlspecialchars($config['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                                <?php echo htmlspecialchars($config['label'], ENT_QUOTES, 'UTF-8'); ?>
                                <span class="badge bg-secondary" id="badge_<?php echo htmlspecialchars($service, ENT_QUOTES, 'UTF-8'); ?>">Pending</span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- ============================================================
                    COMMON FIELDS
                    ============================================================ -->
                    <?php if (!$isPartial): ?>
                    <div class="common-fields">
                        <div class="common-title">
                            <i class="ri-file-info-line me-1"></i>
                            Common Information
                            <small class="text-muted">(Please fill all fields)</small>
                        </div>
                        
                        <!-- 1. Brand Logo -->
                        <div class="mb-3">
                            <label class="form-label">Brand Logo</label>
                            <input type="file" class="form-control" 
                                   name="brandLogo[]" 
                                   id="brandLogo"
                                   accept="image/*" 
                                   multiple>
                            <small class="text-muted">Upload your brand logo (multiple files allowed, max 5MB each)</small>
                            <div class="file-preview" id="brandLogoPreview"></div>
                        </div>
                    
                        <!-- 2. Official Email -->
                        <div class="mb-3">
                            <label class="form-label required-field">Official Email</label>
                            <input type="email" class="form-control" 
                                   name="officialEmail" 
                                   id="officialEmail"
                                   placeholder="Enter official email address"
                                   value="<?php echo htmlspecialchars($formData['officialEmail'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                   required>
                            <small class="text-muted">Official email for communication</small>
                        </div>
                    
                        <!-- 3. Contact Number -->
                        <div class="mb-3">
                            <label class="form-label required-field">Contact Number</label>
                            <input type="tel" class="form-control" 
                                   name="contactPhone" 
                                   id="contactPhone"
                                   placeholder="Enter contact phone number"
                                   value="<?php echo htmlspecialchars($formData['contactPhone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                   required>
                            <small class="text-muted">Contact number for profile</small>
                        </div>
                    
                        <!-- 4. Image and Video Links -->
                        <div class="mb-3">
                            <label class="form-label">Image and Video Links</label>
                            <div id="mediaLinksContainer" class="dynamic-list">
                                <div class="dynamic-item">
                                    <input type="url" class="form-control media-link" 
                                           placeholder="Enter media URL (image/video)">
                                    <button type="button" class="btn btn-outline-danger btn-sm" 
                                            onclick="removeMediaLink(this)">
                                        <i class="ri-close-line"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" 
                                    onclick="addMediaLink()">
                                <i class="ri-add-line me-1"></i> Add More
                            </button>
                            <small class="d-block text-muted mt-1">Add links to images, videos, or any media</small>
                        </div>
                    
                        <!-- 5. Competitors -->
                        <div class="mb-3">
                            <label class="form-label">Competitors <small class="text-muted">(Optional)</small></label>
                            <div id="competitorsContainer" class="dynamic-list">
                                <div class="dynamic-item">
                                    <input type="text" class="form-control competitor" 
                                           placeholder="Enter competitor name">
                                    <button type="button" class="btn btn-outline-danger btn-sm" 
                                            onclick="removeCompetitor(this)">
                                        <i class="ri-close-line"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" 
                                    onclick="addCompetitor()">
                                <i class="ri-add-line me-1"></i> Add Competitor
                            </button>
                            <small class="d-block text-muted mt-1">Add your competitors (optional)</small>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- Show message that common fields are already filled -->
                    <div class="alert alert-success mb-3">
                        <i class="ri-check-circle-line me-1"></i>
                        <strong>Common Information Already Completed:</strong> 
                        Your brand logos, email, phone, media links, and competitors have already been provided.
                        You only need to configure the new services below.
                    </div>
                    <?php endif; ?>

                    <!-- ============================================================
                    SERVICE CONTENT AREAS
                    ============================================================ -->
                    <?php foreach ($selectedServices as $service): ?>
                        <?php 
                        $config = $serviceConfigs[$service] ?? null;
                        if (!$config) continue; 
                        ?>
                        <div class="service-content" id="service_<?php echo htmlspecialchars($service, ENT_QUOTES, 'UTF-8'); ?>" data-service="<?php echo htmlspecialchars($service, ENT_QUOTES, 'UTF-8'); ?>">
                            <h6 class="mb-3">
                                <i class="<?php echo htmlspecialchars($config['icon'], ENT_QUOTES, 'UTF-8'); ?> me-1"></i>
                                <?php echo htmlspecialchars($config['label'], ENT_QUOTES, 'UTF-8'); ?>
                            </h6>
                            
                            <?php if (!empty($config['fields'])): ?>
                                <?php foreach ($config['fields'] as $field => $fieldConfig): ?>
                                    <div class="mb-3">
                                        <label class="form-label <?php echo $fieldConfig['required'] ? 'required-field' : ''; ?>">
                                            <?php echo htmlspecialchars($fieldConfig['label'], ENT_QUOTES, 'UTF-8'); ?>
                                        </label>
                                        
                                        <?php if ($fieldConfig['type'] === 'checkbox'): ?>
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" 
                                                       name="<?php echo htmlspecialchars($service, ENT_QUOTES, 'UTF-8'); ?>_<?php echo htmlspecialchars($field, ENT_QUOTES, 'UTF-8'); ?>" 
                                                       id="<?php echo htmlspecialchars($service, ENT_QUOTES, 'UTF-8'); ?>_<?php echo htmlspecialchars($field, ENT_QUOTES, 'UTF-8'); ?>">
                                                <label class="form-check-label" for="<?php echo htmlspecialchars($service, ENT_QUOTES, 'UTF-8'); ?>_<?php echo htmlspecialchars($field, ENT_QUOTES, 'UTF-8'); ?>">
                                                    Grant access to MQlus Team
                                                </label>
                                                <?php if ($field === 'grantAccess'): ?>
                                                    <small class="d-block text-muted mt-1">
                                                        Emails: uday.work@gmail.com, social@mqlus.in
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                            
                                        <?php elseif ($fieldConfig['type'] === 'file'): ?>
                                            <input type="file" class="form-control" 
                                                   name="<?php echo htmlspecialchars($service, ENT_QUOTES, 'UTF-8'); ?>_<?php echo htmlspecialchars($field, ENT_QUOTES, 'UTF-8'); ?>" 
                                                   id="<?php echo htmlspecialchars($service, ENT_QUOTES, 'UTF-8'); ?>_<?php echo htmlspecialchars($field, ENT_QUOTES, 'UTF-8'); ?>"
                                                   accept="image/*">
                                            <small class="text-muted">Upload screenshot (optional, max 5MB)</small>
                                            
                                        <?php elseif ($fieldConfig['type'] === 'password'): ?>
                                            <div class="position-relative">
                                                <input type="password" class="form-control password-field" 
                                                       name="<?php echo htmlspecialchars($service, ENT_QUOTES, 'UTF-8'); ?>_<?php echo htmlspecialchars($field, ENT_QUOTES, 'UTF-8'); ?>" 
                                                       id="<?php echo htmlspecialchars($service, ENT_QUOTES, 'UTF-8'); ?>_<?php echo htmlspecialchars($field, ENT_QUOTES, 'UTF-8'); ?>"
                                                       placeholder="Enter <?php echo strtolower(htmlspecialchars($fieldConfig['label'], ENT_QUOTES, 'UTF-8')); ?>"
                                                       <?php echo $fieldConfig['required'] ? 'required' : ''; ?>
                                                       autocomplete="off">
                                                <i class="ri-eye-line password-toggle" onclick="togglePasswordVisibility(this)"></i>
                                            </div>
                                            
                                        <?php else: ?>
                                            <input type="<?php echo htmlspecialchars($fieldConfig['type'], ENT_QUOTES, 'UTF-8'); ?>" class="form-control" 
                                                   name="<?php echo htmlspecialchars($service, ENT_QUOTES, 'UTF-8'); ?>_<?php echo htmlspecialchars($field, ENT_QUOTES, 'UTF-8'); ?>" 
                                                   id="<?php echo htmlspecialchars($service, ENT_QUOTES, 'UTF-8'); ?>_<?php echo htmlspecialchars($field, ENT_QUOTES, 'UTF-8'); ?>"
                                                   placeholder="Enter <?php echo strtolower(htmlspecialchars($fieldConfig['label'], ENT_QUOTES, 'UTF-8')); ?>"
                                                   <?php echo $fieldConfig['required'] ? 'required' : ''; ?>>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="ri-information-line me-1"></i>
                                    No credentials required for this service.
                                </div>
                            <?php endif; ?>
                            
                            <button type="button" class="btn btn-sm btn-success mt-2" onclick="markServiceComplete('<?php echo htmlspecialchars($service, ENT_QUOTES, 'UTF-8'); ?>')">
                                <i class="ri-check-line me-1"></i> Mark as Complete
                            </button>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- ============================================================
                    NAVIGATION
                    ============================================================ -->
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline-secondary" id="prevStepBtn" style="display:none;">
                            <i class="ri-arrow-left-line me-1"></i> Previous
                        </button>
                        <div>
                            <span id="stepCounter" class="text-muted me-3">Step 1 of 2</span>
                            <button type="button" class="btn btn-primary" id="nextStepBtn" onclick="goToStep(2)">
                                Next <i class="ri-arrow-right-line ms-1"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- ============================================================
                STEP 2: REVIEW & SUBMIT
                ============================================================ -->
                <div id="step2" style="display:none;">
                    <h5 class="mb-3">Review & Submit</h5>
                    <p class="text-muted small mb-3">Please review your selections before submitting.</p>
                    
                    <div id="reviewContent"></div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline-secondary" onclick="goToStep(1)">
                            <i class="ri-arrow-left-line me-1"></i> Previous
                        </button>
                        <button type="submit" class="btn btn-success" id="submitBtn">
                            <i class="ri-send-plane-line me-1"></i> Submit Form
                        </button>
                    </div>
                </div>
                
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="ri-alert-line me-1"></i>
                        No services selected. Please contact your account manager.
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- ============================================================
    JAVASCRIPT
    ============================================================ -->
    <script>
        'use strict';
        
        // ============================================================
        // STATE
        // ============================================================
        let currentStep = 1;
        let serviceStatus = {};
        
        <?php foreach ($selectedServices as $service): ?>
            serviceStatus['<?php echo addslashes($service); ?>'] = false;
        <?php endforeach; ?>

        // ============================================================
        // SELECT SERVICE
        // ============================================================
        function selectService(serviceId) {
            // Hide all service contents
            document.querySelectorAll('.service-content').forEach(function(el) {
                el.classList.remove('active');
            });
            
            // Show selected service content
            var content = document.getElementById('service_' + serviceId);
            if (content) {
                content.classList.add('active');
            }
            
            // Update active tab
            document.querySelectorAll('.service-tab').forEach(function(el) {
                el.classList.remove('active');
                if (el.dataset.service === serviceId) {
                    el.classList.add('active');
                }
            });
        }

        // ============================================================
        // DYNAMIC LISTS - Media Links
        // ============================================================
        function addMediaLink() {
            var container = document.getElementById('mediaLinksContainer');
            if (container) {
                var div = document.createElement('div');
                div.className = 'dynamic-item';
                div.innerHTML = `
                    <input type="url" class="form-control media-link" placeholder="Enter media URL (image/video)">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeMediaLink(this)">
                        <i class="ri-close-line"></i>
                    </button>
                `;
                container.appendChild(div);
            }
        }

        function removeMediaLink(btn) {
            var container = document.getElementById('mediaLinksContainer');
            if (container && container.children.length > 1) {
                btn.closest('.dynamic-item').remove();
            } else {
                alert('At least one media link field is required.');
            }
        }

        // ============================================================
        // DYNAMIC LISTS - Competitors
        // ============================================================
        function addCompetitor() {
            var container = document.getElementById('competitorsContainer');
            if (container) {
                var div = document.createElement('div');
                div.className = 'dynamic-item';
                div.innerHTML = `
                    <input type="text" class="form-control competitor" placeholder="Enter competitor name">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeCompetitor(this)">
                        <i class="ri-close-line"></i>
                    </button>
                `;
                container.appendChild(div);
            }
        }

        function removeCompetitor(btn) {
            var container = document.getElementById('competitorsContainer');
            if (container && container.children.length > 1) {
                btn.closest('.dynamic-item').remove();
            } else {
                alert('At least one competitor field is required.');
            }
        }

        // ============================================================
        // FILE PREVIEW
        // ============================================================
        document.addEventListener('change', function(e) {
            if (e.target && e.target.id === 'brandLogo') {
                var files = e.target.files;
                var preview = document.getElementById('brandLogoPreview');
                preview.innerHTML = '';
                
                for (var i = 0; i < files.length; i++) {
                    (function(file) {
                        var reader = new FileReader();
                        reader.onload = function(event) {
                            var img = document.createElement('img');
                            img.src = event.target.result;
                            preview.appendChild(img);
                        };
                        reader.readAsDataURL(file);
                    })(files[i]);
                }
            }
        });

        // ============================================================
        // MARK SERVICE COMPLETE
        // ============================================================
        function markServiceComplete(serviceId) {
            var content = document.getElementById('service_' + serviceId);
            if (!content) return;
            
            var inputs = content.querySelectorAll('input[required]');
            var allFilled = true;
            
            inputs.forEach(function(input) {
                if (!input.value.trim()) {
                    allFilled = false;
                    input.style.borderColor = 'red';
                    setTimeout(function() {
                        input.style.borderColor = '';
                    }, 2000);
                }
            });
            
            if (!allFilled) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Incomplete Fields',
                    text: 'Please fill all required fields for this service.',
                    confirmButtonColor: '#4CAF50'
                });
                return;
            }
            
            serviceStatus[serviceId] = true;
            
            var badge = document.getElementById('badge_' + serviceId);
            if (badge) {
                badge.textContent = '✓ Complete';
                badge.className = 'badge bg-success';
            }
            
            var tab = document.querySelector('.service-tab[data-service="' + serviceId + '"]');
            if (tab) {
                tab.style.borderColor = '#4CAF50';
                tab.style.background = '#e8f5e9';
            }
            
            updateProgress();
            
            Swal.fire({
                icon: 'success',
                title: 'Completed!',
                text: serviceId + ' marked as complete!',
                timer: 1500,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
            
            // Auto-select next incomplete service
            for (var key in serviceStatus) {
                if (serviceStatus.hasOwnProperty(key) && !serviceStatus[key]) {
                    selectService(key);
                    return;
                }
            }
            
            Swal.fire({
                icon: 'success',
                title: '🎉 All Services Completed!',
                text: 'You can now proceed to review and submit.',
                confirmButtonColor: '#4CAF50'
            });
        }

        // ============================================================
        // UPDATE PROGRESS
        // ============================================================
        function updateProgress() {
            var total = Object.keys(serviceStatus).length;
            var completed = 0;
            for (var key in serviceStatus) {
                if (serviceStatus.hasOwnProperty(key) && serviceStatus[key]) {
                    completed++;
                }
            }
            var percentage = total > 0 ? (completed / total) * 100 : 0;
            document.getElementById('progressFill').style.width = percentage + '%';
        }

        // ============================================================
        // TOGGLE PASSWORD VISIBILITY
        // ============================================================
        function togglePasswordVisibility(icon) {
            var input = icon.parentElement.querySelector('input');
            if (input) {
                var type = input.type === 'password' ? 'text' : 'password';
                input.type = type;
                icon.className = type === 'password' ? 'ri-eye-line password-toggle' : 'ri-eye-off-line password-toggle';
            }
        }

        // ============================================================
        // GO TO STEP
        // ============================================================
        function goToStep(step) {
            currentStep = step;
            
            if (step === 1) {
                document.getElementById('step1').style.display = 'block';
                document.getElementById('step2').style.display = 'none';
                document.getElementById('prevStepBtn').style.display = 'none';
                document.getElementById('nextStepBtn').style.display = 'inline-block';
                document.getElementById('stepCounter').textContent = 'Step 1 of 2';
                document.getElementById('stepDot1').className = 'step-dot active';
                document.getElementById('stepDot2').className = 'step-dot';
            } else {
                // Check if all services are completed
                var allComplete = true;
                for (var key in serviceStatus) {
                    if (serviceStatus.hasOwnProperty(key) && !serviceStatus[key]) {
                        allComplete = false;
                        break;
                    }
                }
                
                if (!allComplete) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Incomplete Services',
                        text: 'Please complete all services before proceeding.',
                        confirmButtonColor: '#4CAF50'
                    });
                    return;
                }
                
                generateReview();
                
                document.getElementById('step1').style.display = 'none';
                document.getElementById('step2').style.display = 'block';
                document.getElementById('prevStepBtn').style.display = 'inline-block';
                document.getElementById('nextStepBtn').style.display = 'none';
                document.getElementById('stepCounter').textContent = 'Step 2 of 2';
                document.getElementById('stepDot1').className = 'step-dot completed';
                document.getElementById('stepDot2').className = 'step-dot active';
            }
        }

        // ============================================================
        // GENERATE REVIEW
        // ============================================================
        function generateReview() {
            var container = document.getElementById('reviewContent');
            
            <?php if (!$isPartial): ?>
            var officialEmail = document.getElementById('officialEmail')?.value || 'Not provided';
            var contactPhone = document.getElementById('contactPhone')?.value || 'Not provided';
            
            var mediaLinks = [];
            document.querySelectorAll('.media-link').forEach(function(el) {
                if (el.value.trim()) mediaLinks.push(el.value.trim());
            });
            
            var competitors = [];
            document.querySelectorAll('.competitor').forEach(function(el) {
                if (el.value.trim()) competitors.push(el.value.trim());
            });
            <?php endif; ?>
            
            var html = '';
            
            <?php if (!$isPartial): ?>
            html += `
                <div class="common-fields mb-3">
                    <div class="common-title">Common Information</div>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Official Email:</strong> ${officialEmail}
                        </div>
                        <div class="col-md-6">
                            <strong>Contact Phone:</strong> ${contactPhone}
                        </div>
                        <div class="col-md-12 mt-2">
                            <strong>Brand Logo:</strong> ${document.getElementById('brandLogo')?.files?.length || 0} file(s) selected
                        </div>
                        <div class="col-md-12 mt-2">
                            <strong>Media Links:</strong> ${mediaLinks.length > 0 ? mediaLinks.join(', ') : 'None'}
                        </div>
                        <div class="col-md-12 mt-2">
                            <strong>Competitors:</strong> ${competitors.length > 0 ? competitors.join(', ') : 'None'}
                        </div>
                    </div>
                </div>
            `;
            <?php else: ?>
            html += `
                <div class="alert alert-success mb-3">
                    <i class="ri-check-circle-line me-1"></i>
                    <strong>Common Information Already Completed:</strong> 
                    Your common information has already been provided.
                </div>
            `;
            <?php endif; ?>
            
            html += `
                <h6 class="mb-2">Selected Services</h6>
                <div class="list-group">
            `;
            
            <?php foreach ($selectedServices as $service): ?>
                <?php $config = $serviceConfigs[$service] ?? null; ?>
                html += `
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong><i class="<?php echo htmlspecialchars($config['icon'] ?? 'ri-service-line', ENT_QUOTES, 'UTF-8'); ?> me-1"></i> <?php echo htmlspecialchars($config['label'] ?? $service, ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span class="badge bg-success">✓ Completed</span>
                        </div>
                    </div>
                `;
            <?php endforeach; ?>
            
            html += `
                </div>
                <div class="alert alert-success mt-3">
                    <i class="ri-check-circle-line me-1"></i>
                    All services have been completed. You're ready to submit!
                </div>
            `;
            
            container.innerHTML = html;
        }

        // ============================================================
        // SUBMIT FORM
        // ============================================================
        async function submitForm(e) {
            e.preventDefault();
            
            var form = document.getElementById('clientForm');
            var formData = new FormData(form);
            
            // Get common fields
            var officialEmail = document.getElementById('officialEmail')?.value || '';
            var contactPhone = document.getElementById('contactPhone')?.value || '';
            
            // Get media links
            var mediaLinks = [];
            document.querySelectorAll('.media-link').forEach(function(el) {
                if (el.value.trim()) mediaLinks.push(el.value.trim());
            });
            
            // Get competitors
            var competitors = [];
            document.querySelectorAll('.competitor').forEach(function(el) {
                if (el.value.trim()) competitors.push(el.value.trim());
            });
            
            // Build JSON data
            var data = {
                formId: formData.get('formId'),
                token: formData.get('token'),
                officialEmail: officialEmail,
                contactPhone: contactPhone,
                mediaLinks: mediaLinks,
                competitors: competitors,
                services: []
            };
            
            // Group fields by service
            var serviceGroups = {};
            for (var pair of formData.entries()) {
                var key = pair[0];
                var value = pair[1];
                
                if (key === 'formId' || key === 'token' || 
                    key === 'officialEmail' || key === 'contactPhone' ||
                    key === 'brandLogo[]') continue;
                
                var parts = key.split('_');
                var service = parts[0];
                var field = parts.slice(1).join('_');
                
                if (!serviceGroups[service]) {
                    serviceGroups[service] = {
                        serviceType: service,
                        platform: service,
                        fields: {}
                    };
                }
                serviceGroups[service].fields[field] = value;
            }
            
            // Convert to services array
            for (var service in serviceGroups) {
                if (serviceGroups.hasOwnProperty(service)) {
                    var group = serviceGroups[service];
                    var serviceData = {
                        serviceType: service,
                        platform: service,
                        username: group.fields.username || '',
                        password: group.fields.password || '',
                        youtubeGrantAccess: group.fields.grantAccess === 'on' ? true : false,
                        youtubeScreenshot: group.fields.screenshot || '',
                        fbUsername: group.fields.fbUsername || '',
                        fbPassword: group.fields.fbPassword || '',
                        igUsername: group.fields.igUsername || '',
                        igPassword: group.fields.igPassword || ''
                    };
                    data.services.push(serviceData);
                }
            }
            
            // Show loading
            var submitBtn = document.getElementById('submitBtn');
            var originalHtml = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="ri-loader-4-line ri-spin me-1"></i> Submitting...';
            submitBtn.disabled = true;
            
            try {
                var response = await fetch('api/client-onboarding/submitClientForm.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                var result = await response.json();
                
                if (result.success) {
                    // Upload files after form submission
                    await uploadFiles(formData.get('formId'), formData.get('token'));
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Form Submitted!',
                        text: 'Thank you for submitting the form. Our team will verify the information.',
                        confirmButtonText: 'OK'
                    }).then(function() {
                        window.location.href = '/client-thankyou';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: result.message || 'Failed to submit form. Please try again.',
                        confirmButtonColor: '#d32f2f'
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred. Please try again.',
                    confirmButtonColor: '#d32f2f'
                });
            } finally {
                submitBtn.innerHTML = originalHtml;
                submitBtn.disabled = false;
            }
        }
        
        // ============================================================
        // UPLOAD FILES AFTER FORM SUBMISSION
        // ============================================================
        async function uploadFiles(formId, token) {
            var form = document.getElementById('clientForm');
            var fileData = new FormData();
            
            fileData.append('formId', formId);
            fileData.append('token', token);
            
            // Check brand logo
            var brandLogoInput = document.getElementById('brandLogo');
            if (brandLogoInput && brandLogoInput.files.length > 0) {
                for (var i = 0; i < brandLogoInput.files.length; i++) {
                    fileData.append('brandLogo[]', brandLogoInput.files[i]);
                }
            }
            
            // Check YouTube screenshots
            document.querySelectorAll('input[type="file"]').forEach(function(input) {
                if (input.id && input.id.includes('youtubeScreenshot')) {
                    if (input.files.length > 0) {
                        var service = input.id.split('_')[0];
                        fileData.append(service + '_youtubeScreenshot', input.files[0]);
                    }
                }
            });
            
            // Check if any files to upload
            var hasBrandLogos = fileData.getAll('brandLogo[]').length > 0;
            var hasScreenshots = fileData.getAll('youtubeScreenshot').length > 0;
            
            if (!hasBrandLogos && !hasScreenshots) {
                return; // No files to upload
            }
            
            try {
                var response = await fetch('api/client-onboarding/uploadClientFiles.php', {
                    method: 'POST',
                    body: fileData
                });
                
                var result = await response.json();
                
                if (result.success) {
                    console.log('Files uploaded:', result.uploaded);
                } else {
                    console.warn('File upload warning:', result.message);
                }
            } catch (error) {
                console.error('File upload error:', error);
            }
        }

        // ============================================================
        // INITIALIZATION
        // ============================================================
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($selectedServices)): ?>
                var firstService = '<?php echo addslashes($selectedServices[0]); ?>';
                selectService(firstService);
                updateProgress();
            <?php endif; ?>
        });
    </script>

</body>
</html>