<?php
// No authentication required - public page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You</title>
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
        .thank-you-container {
            background: white;
            border-radius: 20px;
            padding: 60px 50px;
            max-width: 600px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            animation: fadeInUp 0.6s ease;
        }
        .thank-you-container .icon {
            font-size: 80px;
            color: #4CAF50;
            margin-bottom: 20px;
            display: block;
        }
        .thank-you-container h1 {
            font-size: 36px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 15px;
        }
        .thank-you-container p {
            font-size: 18px;
            color: #555;
            line-height: 1.7;
            margin-bottom: 8px;
        }
        .thank-you-container .sub-text {
            font-size: 15px;
            color: #888;
            margin-top: 15px;
        }
        .thank-you-container .divider {
            width: 60px;
            height: 3px;
            background: #4CAF50;
            margin: 20px auto;
            border-radius: 2px;
        }
        .thank-you-container .checkmark {
            width: 80px;
            height: 80px;
            background: #e8f5e9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .thank-you-container .checkmark i {
            font-size: 40px;
            color: #4CAF50;
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
            .thank-you-container {
                padding: 40px 25px;
            }
            .thank-you-container h1 {
                font-size: 28px;
            }
            .thank-you-container p {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>

    <div class="thank-you-container">
        <!-- Checkmark Icon -->
        <div class="checkmark">
            <i class="ri-check-double-line"></i>
        </div>

        <!-- Heading -->
        <h1>Thank You!</h1>

        <!-- Divider -->
        <div class="divider"></div>

        <!-- Message -->
        <p>Your onboarding form has been submitted successfully.</p>
        <p>Our team will review your information and get back to you shortly.</p>

        <!-- Sub Text -->
        <p class="sub-text">
            <i class="ri-mail-line me-1"></i>
            You will receive a confirmation email shortly.
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>