<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/../app/helpers/mailer.php"; // Added the mailer helper

/*
|--------------------------------------------------------------------------
| Basic rate limiting (per session)
|--------------------------------------------------------------------------
| Prevents abuse / brute-force reset attempts
*/
$now = time();
$_SESSION['pw_reset_attempts'] ??= [];
$_SESSION['pw_reset_attempts'] = array_filter(
    $_SESSION['pw_reset_attempts'],
    fn ($t) => $t > ($now - 600) // keep last 10 minutes
);

if (count($_SESSION['pw_reset_attempts']) >= 5) {
    $_SESSION["flash_error"] = "Too many reset attempts. Please wait a few minutes.";
    header("Location: ../public/forgot-password.php");
    exit;
}

$_SESSION['pw_reset_attempts'][] = $now;

/*
|--------------------------------------------------------------------------
| Validate request
|--------------------------------------------------------------------------
*/
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

/*
|--------------------------------------------------------------------------
| Look up user (NO user enumeration)
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT id, email, name
    FROM users
    WHERE email = ?
    LIMIT 1
");
$stmt->execute([$email]);
$user = $stmt->fetch();

/*
|--------------------------------------------------------------------------
| Setup State for OTP Verification Page
|--------------------------------------------------------------------------
*/
// We set the session email so the OTP page knows who is trying to reset
$_SESSION["reset_email"] = $email;

// Always show same success message (Anti-user-enumeration)
$_SESSION["flash_success"] = "If an account with that email exists, a recovery code has been sent.";

/*
|--------------------------------------------------------------------------
| If user exists → generate OTP and send email
|--------------------------------------------------------------------------
*/
if ($user) {

    // Generate 6-digit OTP
    $otp = (string) random_int(100000, 999999);
    $hashedOtp = password_hash($otp, PASSWORD_DEFAULT);
    
    // Set to 5 minutes to match UI and email copy
    $expiresAt = date("Y-m-d H:i:s", time() + 300); 

    // Store OTP in database (using the columns we set up in the previous step)
    $stmt = $pdo->prepare("
        UPDATE users
        SET reset_otp = ?, 
            reset_otp_expires = ?, 
            reset_otp_attempts = 0
        WHERE id = ?
    ");
    $stmt->execute([
        $hashedOtp,
        $expiresAt,
        $user["id"]
    ]);

    // Send Branded Email
    $subject = "Password Reset Request - ShopCorrect";
    $title   = "Reset Your Password";
    
    // Message with 5-minute rule and Security Warning
    $message = "Hello <strong>" . htmlspecialchars($user['name']) . "</strong>,<br><br>We received a request to reset your ShopCorrect password. Please use the 6-digit recovery code below to proceed. This code expires in <strong>5 minutes</strong>.
    <br><br>
    <span style='font-size: 13px; color: #94A3B8;'>If you didn't request a password reset, you can safely ignore this email. Your account remains secure and your password will not be changed.</span>";

    // Pass $otp as the 6th argument to trigger the Glass Box UI
    sendMail($user["email"], $subject, $title, $message, null, $otp);

    /*
    |--------------------------------------------------------------------------
    | TEMP: DEV MODE – show code in logs
    |--------------------------------------------------------------------------
    */
    if (defined("APP_ENV") && APP_ENV === "dev") {
        error_log("Password reset OTP for {$email}: $otp");
    }
}

/*
|--------------------------------------------------------------------------
| Redirect to OTP Verification
|--------------------------------------------------------------------------
*/
// Note: Make sure the filename below matches your actual forgot-password verification page!
header("Location: ../public/verify-reset-otp.php");
exit;