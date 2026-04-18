<?php
// Enable Error Reporting so we can see if it crashes
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(__DIR__) . "/app/config/session.php";
require_once dirname(__DIR__) . "/app/config/db.php";

/*
|--------------------------------------------------------------------------
| LOAD MAIL SYSTEM SAFELY
|--------------------------------------------------------------------------
*/
$mailHelperPath = dirname(__DIR__) . "/app/helpers/mailer.php";

if (!file_exists($mailHelperPath)) {
    die("Mail system not found.");
}

require_once $mailHelperPath;

if (!function_exists("sendMail")) {
    die("Mail system not initialized properly.");
}

/*
|--------------------------------------------------------------------------
| START REGISTRATION
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && (isset($_POST["register"]) || ($_POST["action"] ?? '') === 'register')) {

    $country  = trim($_POST["country"] ?? "");
    $phone    = trim($_POST["phone"] ?? ""); 
    $email    = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $role     = $_POST["role"] ?? "buyer";
    $name     = trim($_POST["name"] ?? "Customer"); 

    if (!$country || !$email || !$password) {
        $_SESSION["flash_error"] = "All fields are required.";
        header("Location: ../public/register.php");
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION["flash_error"] = "Invalid email address.";
        header("Location: ../public/register.php");
        exit;
    }

    if (strlen($password) < 8) {
        $_SESSION["flash_error"] = "Password must be at least 8 characters.";
        header("Location: ../public/register.php");
        exit;
    }

    if (!in_array($role, ["buyer", "vendor"], true)) {
        $_SESSION["flash_error"] = "Invalid account type.";
        header("Location: ../public/register.php");
        exit;
    }

    $check = $pdo->prepare("SELECT id, email_verified_at FROM users WHERE email = ?");
    $check->execute([$email]);
    $existingUser = $check->fetch(PDO::FETCH_ASSOC);

    $isUpdate = false;
    if ($existingUser) {
        $isVerified = !empty($existingUser['email_verified_at']) && $existingUser['email_verified_at'] !== '0000-00-00 00:00:00';

        if ($isVerified) {
            $_SESSION["flash_error"] = "Email already registered. Please log in.";
            header("Location: ../public/register.php");
            exit;
        } else {
            $userId = $existingUser['id'];
            $isUpdate = true;
        }
    }

    try {
        $pdo->beginTransaction();

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $otp = (string) random_int(100000, 999999);
        $expiresAt = date("Y-m-d H:i:s", time() + 300); 
        $hashedOtp = password_hash($otp, PASSWORD_DEFAULT);

        if ($isUpdate) {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET name = ?, phone = ?, password_hash = ?, role = ?, country = ?, otp_code = ?, otp_expires_at = ?, otp_attempts = 0, otp_locked_until = NULL
                WHERE id = ?
            ");
            $stmt->execute([$name, $phone, $passwordHash, $role, $country, $hashedOtp, $expiresAt, $userId]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, phone, password_hash, role, country, otp_code, otp_expires_at, otp_attempts, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
            ");
            $stmt->execute([$name, $email, $phone, $passwordHash, $role, $country, $hashedOtp, $expiresAt]);
            $userId = $pdo->lastInsertId();
        }

        $pdo->commit();

        $subject = "Verify Your Account - ShopCorrect (" . date("h:i:s A") . ")";
        $title   = "Verify Your Email";
        
        $message = "Welcome to <strong>ShopCorrect</strong>! Please use the code below to complete your registration. This code expires in <strong>5 minutes</strong>.
        <br><br>
        <span style='font-size: 13px; color: #94A3B8;'>If you didn't attempt to register an account with us, you can safely ignore this email.</span>";
        
        if (!sendMail($email, $subject, $title, $message, null, $otp)) {
            throw new Exception("Unable to send verification code. Please check your internet connection.");
        }

        $_SESSION["register_otp_user"] = $userId;
        header("Location: ../public/verify-registration-otp.php");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION["flash_error"] = "Registration failed: " . $e->getMessage();
        header("Location: ../public/register.php");
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| LOGIN + OTP
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && (isset($_POST["login"]) || ($_POST["action"] ?? '') === 'login')) {
    
    try {
        $email    = trim($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";

        if (!$email || !$password) {
            throw new Exception("Email and password required.");
        }

        $stmt = $pdo->prepare("SELECT id, name, role, email, password_hash, status, email_verified_at FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user["password_hash"])) {
            throw new Exception("Invalid email or password.");
        }

        $isVerified = !empty($user['email_verified_at']) && $user['email_verified_at'] !== '0000-00-00 00:00:00';

        if (!$isVerified) {
            $otp = (string) random_int(100000, 999999);
            $expiresAt = date("Y-m-d H:i:s", time() + 300); 

            $updateStmt = $pdo->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ?, otp_attempts = 0, otp_locked_until = NULL WHERE id = ?");
            $updateStmt->execute([password_hash($otp, PASSWORD_DEFAULT), $expiresAt, $user["id"]]);

            $subject = "Resume Registration - ShopCorrect (" . date("h:i:s A") . ")";
            $title   = "Verify Your Email";
            $message = "Welcome back! Please use the code below to finish setting up your account. This code expires in <strong>5 minutes</strong>.";
            
            sendMail($user["email"], $subject, $title, $message, null, $otp);

            $_SESSION["register_otp_user"] = $user["id"]; 
            header("Location: ../public/verify-registration-otp.php");
            exit;
        }

        if ($user["status"] === "suspended") {
            throw new Exception("Your account has been suspended. Please contact support.");
        }

        // 3. SMART VENDOR CHECK (Fixed!)
        if ($user["role"] === "vendor") {
            
            // I REMOVED the strict "active" check that was causing your error!
            
            $vendorCheck = $pdo->prepare("SELECT id, status FROM vendors WHERE user_id = ?");
            $vendorCheck->execute([$user["id"]]);
            $vendor = $vendorCheck->fetch(PDO::FETCH_ASSOC);

            if (!$vendor) {
                // They haven't created a vendor profile yet -> send them to step 1
                $_SESSION["login_redirect"] = "complete-vendor-profile.php";
            } else {
                
                // If they are under review, block login with a clear message
                if ($vendor["status"] === "pending") {
                    throw new Exception("Your vendor account is still pending approval by our team. You will be notified via email once approved.");
                }

                // If they need to fix something, send them directly to the upload page
                if ($vendor["status"] === "correction" || $vendor["status"] === "rejected") {
                    $_SESSION["login_redirect"] = "verification-upload.php";
                } elseif ($vendor["status"] !== "approved") {
                    // Any other incomplete status goes back to the upload page
                    $_SESSION["login_redirect"] = "verification-upload.php";
                }
            }
        }

        // 4. Generate standard Login OTP
        $otp = (string) random_int(100000, 999999);
        $expiresAt = date("Y-m-d H:i:s", time() + 300); 

        $updateStmt = $pdo->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ?, otp_attempts = 0, otp_locked_until = NULL WHERE id = ?");
        $updateStmt->execute([password_hash($otp, PASSWORD_DEFAULT), $expiresAt, $user["id"]]);

        $subject = "Login Verification - ShopCorrect (" . date("h:i:s A") . ")";
        $title   = "Login Verification";
        
        $message = "Hello <strong>" . htmlspecialchars($user['name']) . "</strong>,<br><br>Use the OTP below to complete your login. This code expires in <strong>5 minutes</strong>.
        <br><br>
        <span style='font-size: 13px; color: #94A3B8;'>If you didn't attempt to log in, you can safely ignore this email. Your account remains secure.</span>";
        
        if (!sendMail($user["email"], $subject, $title, $message, null, $otp)) {
            throw new Exception("Unable to send verification code. Please check your internet connection.");
        }

        $_SESSION["otp_user_id"] = $user["id"];
        header("Location: ../public/verify-otp.php");
        exit;

    } catch (Exception $e) {
        $_SESSION["flash_error"] = $e->getMessage();
        header("Location: ../public/login.php");
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| LOGOUT
|--------------------------------------------------------------------------
*/
if (isset($_GET["logout"])) {
    session_destroy();
    header("Location: ../public/login.php");
    exit;
}

$_SESSION["flash_error"] = "Invalid request action. The server did not understand what to do.";
header("Location: ../public/login.php");
exit;