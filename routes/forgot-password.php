<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/../app/helpers/mailer.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../public/forgot-password.php");
    exit;
}

$email = trim($_POST["email"] ?? "");

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION["flash_error"] = "Please enter a valid email address.";
    header("Location: ../public/forgot-password.php");
    exit;
}

// Set the session variable immediately so the OTP page can load (Anti-enumeration tactic)
$_SESSION["reset_email"] = $email;
$_SESSION["flash_success"] = "If the email exists in our system, a recovery code has been sent.";

$stmt = $pdo->prepare("
    SELECT id, email, name
    FROM users
    WHERE email = ?
    LIMIT 1
");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    // 1. Generate 6-Digit OTP
    $otp = (string) random_int(100000, 999999);
    $hashedOtp = password_hash($otp, PASSWORD_DEFAULT);
    
    // 2. Set Expiration to exactly 5 Minutes
    $expiresAt = date("Y-m-d H:i:s", time() + 300);

    // 3. Update Database (Using the exact columns from your verify-otp file)
    $pdo->prepare("
        UPDATE users
        SET reset_otp = ?, 
            reset_otp_expires = ?, 
            reset_otp_attempts = 0
        WHERE id = ?
    ")->execute([$hashedOtp, $expiresAt, $user["id"]]);

    // 4. Prepare Branded Email
    $subject = "Password Reset Request - ShopCorrect";
    $title   = "Reset Your Password";
    
    // Message with 5-minute rule and Security Warning
    $message = "Hello <strong>" . htmlspecialchars($user['name']) . "</strong>,<br><br>We received a request to reset your ShopCorrect password. Please use the 6-digit recovery code below to proceed. This code expires in <strong>5 minutes</strong>.
    <br><br>
    <span style='font-size: 13px; color: #94A3B8;'>If you didn't request a password reset, you can safely ignore this email. Your account remains secure and your password will not be changed.</span>";

    // 5. Send Email (Passing $otp as the 6th argument triggers the Glass Box UI)
    sendMail($user["email"], $subject, $title, $message, null, $otp);
}

// Redirect to the OTP verification page 
// (Make sure the filename matches whatever you saved the file from our previous step as!)
header("Location: ../public/forgot-password.php");
exit;