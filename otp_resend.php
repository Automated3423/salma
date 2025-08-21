<?php
session_start();
header('Content-Type: application/json');

// CSRF Protection
$headers = getallheaders();
$csrfToken = $headers['X-CSRF-Token'] ?? '';

if (!isset($_SESSION['csrf_token']) || $csrfToken !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Check if user has a pending booking
if (!isset($_SESSION['pending_booking'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No pending booking found']);
    exit;
}

// Rate limiting for resend (max 1 per 30 seconds)
$lastResend = $_SESSION['last_otp_resend'] ?? 0;
$timeSinceLastResend = time() - $lastResend;

if ($timeSinceLastResend < 30) {
    $cooldown = 30 - $timeSinceLastResend;
    echo json_encode([
        'success' => false, 
        'message' => 'يرجى الانتظار قبل إعادة الإرسال',
        'cooldown' => $cooldown
    ]);
    exit;
}

// Get phone number from request or session
$requestData = json_decode(file_get_contents('php://input'), true);
$phone = $requestData['phone'] ?? $_SESSION['pending_booking']['phone'] ?? '';

if (empty($phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Phone number not found']);
    exit;
}

// Generate new OTP
$newOtp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

// Update session
$_SESSION['otp_code'] = $newOtp;
$_SESSION['otp_expires'] = time() + 300; // 5 minutes
$_SESSION['otp_attempts'] = 0; // Reset attempts
$_SESSION['last_otp_resend'] = time();

// TODO: Send SMS here using your SMS provider
// Example with Twilio:
// require_once 'vendor/autoload.php';
// $twilio = new Twilio\Rest\Client($sid, $token);
// $message = $twilio->messages->create(
//     $phone,
//     [
//         'from' => $twilioNumber,
//         'body' => "رمز التحقق الخاص بك: $newOtp"
//     ]
// );

// Log for development (remove in production)
error_log("OTP Resent: $newOtp for phone: $phone");

// Response
echo json_encode([
    'success' => true,
    'message' => 'تم إرسال رمز جديد بنجاح',
    'cooldown' => 30
]);
?>