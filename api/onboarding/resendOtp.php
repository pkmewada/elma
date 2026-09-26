<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/auth-functions.php';
require_once __DIR__ . '/../../includes/sendOtp.php';
require_once __DIR__ . '/../../app/models/UserModel.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.',
    ]);
    exit();
}

$rawInput = file_get_contents('php://input');
$payload = json_decode((string) $rawInput, true);

if (!is_array($payload)) {
    $payload = $_POST;
}

$email = trim((string) ($payload['email'] ?? ''));

if ($email === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Email is required.',
    ]);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email address.',
    ]);
    exit();
}

$userModel = new UserModel();
$user = $userModel->getUserByEmail($email);

if (!$user) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'User not found.',
    ]);
    exit();
}

$otp = generateOtp();
$otpExpiresAt = date('Y-m-d H:i:s', time() + (5 * 60));

if (!$userModel->updateOtp($email, $otp, $otpExpiresAt)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to update OTP.',
    ]);
    exit();
}

$isSent = sendOtpEmail($email, $otp);

if ($isSent) {
    echo json_encode([
        'success' => true,
        'message' => 'OTP resent',
    ]);
    exit();
}

http_response_code(500);
echo json_encode([
    'success' => false,
    'message' => 'Unable to send OTP. Try again later.',
    // Temporary fallback visibility for SMTP troubleshooting.
    'otp' => $otp,
]);
