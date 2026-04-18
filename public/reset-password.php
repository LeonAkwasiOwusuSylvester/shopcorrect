<?php
date_default_timezone_set('Africa/Accra');

require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/../app/helpers/mailer.php";

if (empty($_SESSION["reset_user"])) {
    header("Location: forgot-password.php");
    exit;
}

$resetUser = $_SESSION["reset_user"];
$error = "";
$showSuccess = false; 

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $password = $_POST["password"] ?? "";
    $confirm  = $_POST["confirm_password"] ?? "";

    if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            // 1. Fetch user email and name for personalization
            $stmt = $pdo->prepare("
                SELECT u.email, v.shop_name 
                FROM users u 
                LEFT JOIN vendors v ON u.id = v.user_id 
                WHERE u.id = ?
            ");
            $stmt->execute([$resetUser]);
            $userData = $stmt->fetch();
            
            $recipientName = !empty($userData['shop_name']) ? $userData['shop_name'] : explode('@', $userData['email'])[0];
            $userEmail = $userData['email'];

            // 2. Update the password
            $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $update->execute([$hash, $resetUser]);

            // 3. Send personalized confirmation email (FIXED FOR NEW MAILER)
            $subject = "Password Changed - ShopCorrect";
            $title   = "Security Update";
            $message = "Hello <strong>$recipientName</strong>,<br>Your ShopCorrect account password was successfully changed. If you did not perform this change, please contact our support team immediately.";
            
            $button = [
                'text' => 'Login Now',
                'url'  => 'http://localhost/shopcorrect/public/login.php'
            ];

            // Send with the required 5 arguments
            sendMail($userEmail, $subject, $title, $message, $button);

            // 4. Cleanup and trigger animation
            unset($_SESSION["reset_user"], $_SESSION["reset_email"]);
            $_SESSION["flash_success"] = "Password updated successfully!";
            $showSuccess = true; 

        } catch (PDOException $e) {
            $error = "System Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Set New Password | ShopCorrect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --brand-primary: #0B2447; --brand-hover: #19376D; --border-color: #E2E8F0; }
        
        body {
            background: linear-gradient(rgba(11, 36, 71, 0.85), rgba(11, 36, 71, 0.85)), 
                        url('https://images.unsplash.com/photo-1557821552-17105176677c?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');
            background-size: cover; background-position: center; background-attachment: fixed;
            font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; overflow: hidden;
            flex-direction: column;
        }

        /* Watermark */
        body::before {
            content: "SHOPCORRECT"; position: fixed; top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg); font-size: 15vw; font-weight: 900;
            color: rgba(255, 255, 255, 0.03); white-space: nowrap; pointer-events: none; letter-spacing: 2rem; z-index: 0;
        }

        .auth-card-container {
            z-index: 1; width: 100%; max-width: 480px; padding: 15px; display: flex; flex-direction: column; align-items: center;
        }

        .auth-card {
            border: none; border-radius: 24px; background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(15px); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
            padding: 2.5rem; width: 100%; position: relative;
        }

        /* Unified Input Group Styling */
        .custom-input-group {
            border: 1.5px solid #E2E8F0;
            border-radius: 12px;
            background-color: #F8FAFC;
            display: flex;
            align-items: center;
            overflow: hidden;
            transition: all 0.2s ease-in-out;
        }

        .custom-input-group:focus-within {
            border-color: var(--brand-primary);
            background-color: #fff;
            box-shadow: 0 0 0 4px rgba(11, 36, 71, 0.1);
        }

        .custom-input-group .form-control {
            border: none;
            background: transparent;
            box-shadow: none; /* Remove default bootstrap focus shadow */
            padding: 12px 16px;
        }

        .custom-input-group .toggle-btn {
            background: transparent;
            border: none;
            color: #64748b;
            padding: 0 16px;
            cursor: pointer;
            transition: color 0.2s;
        }

        .custom-input-group .toggle-btn:hover { color: var(--brand-primary); }

        .btn-brand { background-color: var(--brand-primary); color: #fff; border-radius: 12px; padding: 14px; font-weight: 700; border: none; transition: 0.3s; }
        .btn-brand:hover { background-color: var(--brand-hover); transform: translateY(-2px); }

        /* Success Overlay */
        #successOverlay {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: #fff; border-radius: 24px; display: none;
            flex-direction: column; align-items: center; justify-content: center; z-index: 10;
            animation: fadeIn 0.4s ease;
        }
        .check-icon { font-size: 4rem; color: #22c55e; animation: scaleUp 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        @keyframes scaleUp { from { transform: scale(0); } to { transform: scale(1); } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        /* Google Translate Auto-Hide Overrides */
        body > .skiptranslate { display: none !important; }
        .goog-te-banner-frame.skiptranslate { display: none !important; }
        body { top: 0px !important; }
        .notranslate { color: inherit !important; }
    </style>
</head>
<body>

<div class="auth-card-container">
    <div class="auth-card">
        <div id="successOverlay" style="<?= $showSuccess ? 'display: flex;' : '' ?>">
            <i class="bi bi-check-circle-fill check-icon mb-3"></i>
            <h4 class="fw-bold">Success!</h4>
            <p class="text-muted small">Redirecting you to login...</p>
        </div>

        <div class="d-flex align-items-center justify-content-center gap-2 mb-4">
            <img src="/assets/images/logo_b.png" height="60" alt="Logo">
            <span class="notranslate" style="font-weight: 800; font-size: 1.5rem; letter-spacing: -1px; color: var(--brand-primary);">ShopCorrect</span>
        </div>

        <div class="text-center mb-4">
            <h4 class="fw-bold text-dark mb-1">New Password</h4>
            <p class="text-muted small">Choose a strong password to protect your account.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 small py-2 text-center mb-4"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label small fw-bold text-secondary">New Password</label>
                <div class="custom-input-group">
                    <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
                    <button type="button" class="toggle-btn" onclick="togglePassword('password', this)">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label small fw-bold text-secondary">Confirm Password</label>
                <div class="custom-input-group">
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="••••••••" required>
                    <button type="button" class="toggle-btn" onclick="togglePassword('confirm_password', this)">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            <button class="btn btn-brand w-100 shadow-sm">Update Password</button>
        </form>
    </div>

    <p class="text-center mt-4 text-white-50 small fw-medium">
         &copy; <?= date("Y") ?> <span class="notranslate">ShopCorrect</span>. Shop Smart. Shop Correct. <i class="bi bi-check-circle-fill text-success mx-1"></i>
    </p>
</div>

<div id="google_translate_element" style="display:none;"></div>
<script type="text/javascript">
    function googleTranslateElementInit() {
        new google.translate.TranslateElement({
            pageLanguage: 'en', 
            includedLanguages: 'en,fr,sw,de,zh-CN,es', 
            autoDisplay: false
        }, 'google_translate_element');
    }
</script>
<script type="text/javascript" src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>

<script>
    function togglePassword(id, el) {
        const input = document.getElementById(id);
        const icon = el.querySelector('i');
        if (input.type === "password") {
            input.type = "text";
            icon.classList.replace('bi-eye', 'bi-eye-slash-fill');
            icon.classList.add('text-primary');
        } else {
            input.type = "password";
            icon.classList.replace('bi-eye-slash-fill', 'bi-eye');
            icon.classList.remove('text-primary');
        }
    }

    <?php if($showSuccess): ?>
    setTimeout(() => {
        window.location.href = 'login.php';
    }, 2500);
    <?php endif; ?>
</script>

</body>
</html>