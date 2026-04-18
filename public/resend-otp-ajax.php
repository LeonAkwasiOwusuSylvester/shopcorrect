<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/../app/helpers/mailer.php"; 

header('Content-Type: application/json');

$userId = $_SESSION["otp_user_id"] ?? $_SESSION["otp_user"] ?? null;

if (empty($userId)) {
    echo json_encode(["success" => false, "message" => "Session expired. Please login again."]);
    exit;
}

if (isset($_SESSION['last_otp_resend']) && (time() - $_SESSION['last_otp_resend']) < 60) {
    echo json_encode(["success" => false, "message" => "Please wait before requesting another code."]);
    exit;
}

try {
    // 1. Added otp_locked_until to the fetch list
    $stmt = $pdo->prepare("SELECT email, name, otp_locked_until FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(["success" => false, "message" => "Account not found."]);
        exit;
    }

    // 2. Block the resend if the user is currently locked out in the database
    if (!empty($user['otp_locked_until']) && strtotime($user['otp_locked_until']) > time()) {
        $remaining = ceil((strtotime($user['otp_locked_until']) - time()) / 60);
        echo json_encode(["success" => false, "message" => "Account locked from too many failed attempts. Try again in $remaining minutes."]);
        exit;
    }

    $newOtp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hashedOtp = password_hash($newOtp, PASSWORD_DEFAULT);
    
    $expiresAt = date("Y-m-d H:i:s", strtotime("+5 minutes")); 

    // 3. Added otp_attempts = 0 to give them a fresh start with the new code
    $updateStmt = $pdo->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ?, otp_attempts = 0 WHERE id = ?");
    $updateStmt->execute([$hashedOtp, $expiresAt, $userId]);

    $subject = "Your New Verification Code";
    $title = "Two-Step Verification";
    $message = "Hello <strong>" . htmlspecialchars($user['name']) . "</strong>,<br><br>You requested a new verification code. Please enter the code below to securely access your account.";
    
    $mailSent = sendMail($user['email'], $subject, $title, $message, null, $newOtp);

    if ($mailSent) {
        $_SESSION['last_otp_resend'] = time();
        
        echo json_encode([
            "success" => true, 
            "message" => "A new verification code has been sent."
        ]);
    } else {
        echo json_encode([
            "success" => false, 
            "message" => "Failed to send email. Check your mailer settings."
        ]);
    }

} catch (Exception $e) {
    error_log("Resend OTP Error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Server error. Failed to resend code."]);
}
exit;