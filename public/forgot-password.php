<?php
// Set Timezone
date_default_timezone_set('Africa/Accra'); 

require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/../app/helpers/mailer.php";

$success = $_SESSION["flash_success"] ?? null;
unset($_SESSION["flash_success"]);
$error = "";

// Check which language is active for stealth translation
$activeLangCode = $_SESSION['lang'] ?? $_COOKIE['shop_lang'] ?? 'en'; 

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"]);

    // Security: Validate Email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        $_SESSION["flash_success"] = "If an account exists, a recovery code has been sent.";

        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // 1. Generate 6-Digit OTP and exact PHP expiry time
            $otp = random_int(100000, 999999);
            $hashedOtp = password_hash($otp, PASSWORD_DEFAULT);
            $expiryTime = date('Y-m-d H:i:s', time() + 300); // Exactly 5 minutes from Accra time

            // 2. Update DB using PHP exact time
            $pdo->prepare("
                UPDATE users
                SET reset_otp = ?,
                    reset_otp_expires = ?,
                    reset_otp_attempts = 0
                WHERE id = ?
            ")->execute([$hashedOtp, $expiryTime, $user["id"]]);

            // 3. Send Email using the New Mailer
            $subject = "Password Reset Code - ShopCorrect";
            $title   = "Reset Your Password";
            
            // Updated Message: 5 Minute Rule + Ignore Note
            $message = "Hello <strong>{$user['name']}</strong>,<br><br>We received a request to reset your password. Use the 6-digit recovery code below to proceed. This code expires in <strong>5 minutes</strong>.
            <br><br>
            <span style='font-size: 13px; color: #94A3B8;'>If you didn't request a password reset, you can safely ignore this email. Your account remains secure and your password will not be changed.</span>";
            
            // Pass OTP as 6th argument for the Glass Box
            sendMail($email, $subject, $title, $message, null, $otp);

            $_SESSION["reset_email"] = $email;
        }

        header("Location: reset-verify-otp.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password | ShopCorrect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --brand-primary: #0B2447;
            --brand-hover: #19376D;
            --border-color: #E2E8F0;
        }

        body {
            background: linear-gradient(rgba(11, 36, 71, 0.85), rgba(11, 36, 71, 0.85)), 
                        url('https://images.unsplash.com/photo-1557821552-17105176677c?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow: hidden;
        }

        /* Branding Watermark */
        body::before {
            content: "SHOPCORRECT";
            position: fixed;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 15vw; font-weight: 900;
            color: rgba(255, 255, 255, 0.03);
            white-space: nowrap; pointer-events: none; letter-spacing: 2rem;
            z-index: 0;
        }

        .auth-card-container {
            z-index: 1;
            width: 100%;
            max-width: 500px;
            padding: 15px;
        }

        .auth-card {
            border: none;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(15px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
            padding: 2.5rem;
        }

        .brand-logo-text {
            font-weight: 800;
            font-size: 1.5rem;
            letter-spacing: -1px;
            color: var(--brand-primary);
            margin: 0;
        }

        .form-control {
            border-radius: 12px;
            padding: 12px 16px;
            border: 1.5px solid #E2E8F0;
            background-color: #F8FAFC;
            font-size: 14px;
        }

        .form-control:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 4px rgba(11, 36, 71, 0.1);
            background-color: #fff;
        }

        .btn-brand {
            background-color: var(--brand-primary);
            color: #fff;
            border-radius: 12px;
            padding: 14px;
            font-weight: 700;
            border: none;
            transition: 0.3s;
        }

        .btn-brand:hover {
            background-color: var(--brand-hover);
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(11, 36, 71, 0.3);
        }

        .animate-pop { animation: popIn 0.5s cubic-bezier(0.26, 0.53, 0.74, 1.48); }
        @keyframes popIn { 0% { opacity: 0; transform: scale(0.95); } 100% { opacity: 1; transform: scale(1); } }

        /* Google Translate Auto-Hide Overrides (Top Layer) */
        body > .skiptranslate { display: none !important; }
        .goog-te-banner-frame.skiptranslate { display: none !important; }
        iframe.skiptranslate, iframe.goog-te-banner-frame { display: none !important; }
        body { top: 0px !important; }
        .notranslate { color: inherit !important; }
    </style>
</head>

<body>

<div class="auth-card-container">
    <div class="card auth-card animate-pop">
        <div class="d-flex align-items-center justify-content-center gap-2 mb-4">
            <img src="assets/images/logo_b.png" height="60" alt="Logo" onerror="this.style.display='none'">
            <span class="brand-logo-text notranslate">ShopCorrect</span>
        </div>

        <div class="text-center mb-4">
            <h4 class="fw-bold text-dark mb-1">Forgot Password?</h4>
            <p class="text-muted small">Enter your email and we will send you a 6-digit OTP to reset your account.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success border-0 small py-2 text-center mb-4">
                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 small py-2 text-center mb-4 bg-danger-subtle text-danger">
                <i class="bi bi-exclamation-circle-fill me-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-4">
                <label class="form-label small fw-bold text-secondary">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="example@mail.com" required autofocus>
            </div>

            <button type="submit" class="btn btn-brand w-100 shadow-sm mb-3">
                Send Recovery Code
            </button>
        </form>

        <div class="text-center mt-2 border-top pt-3">
            <a href="login.php" class="small text-decoration-none fw-bold" style="color: var(--brand-primary);">
                <i class="bi bi-arrow-left me-1"></i> Back to Login
            </a>
        </div>
    </div>

    <p class="text-center mt-4 text-white-50 small fw-medium">
         &copy; <?= date("Y") ?> <span class="notranslate">ShopCorrect</span>. Shop Smart. Shop Correct. <i class="bi bi-check-circle-fill text-success mx-1"></i>
    </p>
</div>

<?php if (isset($activeLangCode) && $activeLangCode !== 'en'): ?>
    <style>
        /* ════ COMPLETELY HIDE ALL GOOGLE WIDGETS (Bottom Layer) ════ */
        body > .skiptranslate { display: none !important; }
        .goog-te-banner-frame.skiptranslate { display: none !important; }
        iframe.skiptranslate, iframe.goog-te-banner-frame { display: none !important; }
        .goog-te-gadget { display: none !important; }
        #google_translate_element { display: none !important; }
        body { top: 0px !important; position: static !important; }
        .notranslate { color: inherit !important; }
        
        /* Extra nuke for the floating blue icon */
        .VIpgJd-Zvi9od-aZ2wEe-wOHMyf, .VIpgJd-Zvi9od-aZ2wEe-OiiCO, #goog-gt-tt { display: none !important; }
    </style>
    
    <div id="google_translate_element"></div>
    <script type="text/javascript">
        function googleTranslateElementInit() {
            new google.translate.TranslateElement({
                pageLanguage: 'en',
                includedLanguages: 'en,fr,de,es,sw,zh-CN',
                autoDisplay: false
            }, 'google_translate_element');
        }
    </script>
    <script type="text/javascript" src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
<?php endif; ?>

</body>
</html>