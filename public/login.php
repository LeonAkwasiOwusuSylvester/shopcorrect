<?php
require_once __DIR__ . "/../app/config/session.php";

/*
|--------------------------------------------------------------------------
| Capture last page (avoid redirect loop) - FIXED FOR ALL PHP VERSIONS
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION["login_redirect"])) {
    if (
        !empty($_SERVER["HTTP_REFERER"]) &&
        strpos($_SERVER["HTTP_REFERER"], "login.php") === false &&
        strpos($_SERVER["HTTP_REFERER"], "register.php") === false
    ) {
        $_SESSION["login_redirect"] = $_SERVER["HTTP_REFERER"];
    }
}

// BULLETPROOF ERROR CATCHING: Catch both "error" and "flash_error" 
$error = $_SESSION["flash_error"] ?? $_SESSION["error"] ?? null;
$success = $_SESSION["flash_success"] ?? $_SESSION["success"] ?? null; 
unset($_SESSION["flash_error"], $_SESSION["error"], $_SESSION["flash_success"], $_SESSION["success"]);

// Check which language is active
$activeLangCode = $_SESSION['lang'] ?? $_COOKIE['shop_lang'] ?? 'en'; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | ShopCorrect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --brand-primary: #0B2447;
            --brand-hover: #19376D;
            --border-color: #E2E8F0;
        }

        body {
            background: linear-gradient(rgba(11, 36, 71, 0.85), rgba(11, 36, 71, 0.85)), 
                        url('https://images.unsplash.com/photo-1557821552-17105176677c?auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            overflow-x: hidden;
            margin: 0;
            padding: 20px 0;
        }

        /* Watermark Background */
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

        .login-card {
            border: none;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(15px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
            z-index: 1;
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
            box-shadow: none;
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
            color: white;
        }

        .error-box, .success-box {
            padding: 1rem;
            border-radius: 12px;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }

        .error-box {
            background-color: #fef2f2;
            border-left: 4px solid #ef4444;
            color: #b91c1c;
        }

        .success-box {
            background-color: #f0fdf4;
            border-left: 4px solid #10b981;
            color: #166534;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-up { animation: slideUp 0.5s ease-out; }

        .error-shake { animation: shake 0.4s; }
        @keyframes shake { 0%, 100% {transform: translateX(0);} 25% {transform: translateX(-5px);} 75% {transform: translateX(5px);} }

        /* Google Translate Auto-Hide Overrides */
        body > .skiptranslate { display: none !important; }
        .goog-te-banner-frame.skiptranslate { display: none !important; }
        body { top: 0px !important; }
        .notranslate { color: inherit !important; }
    </style>
</head>

<body>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-11 col-md-9 col-lg-7 col-xl-6 col-xxl-5">

            <div class="card login-card p-4 p-md-5 animate-up">
                <div class="d-flex align-items-center justify-content-center gap-2 mb-4">
                    <img src="assets/images/logo_b.png" height="60" alt="Logo" onerror="this.style.display='none'">
                    <span class="brand-logo-text notranslate">ShopCorrect</span>
                </div>

                <div class="text-center mb-4">
                    <h4 class="fw-bold text-dark mb-1">Welcome back</h4>
                    <p class="text-muted small">Access your ShopCorrect account</p>
                </div>

                <?php if ($error): ?>
                    <div class="error-box error-shake shadow-sm">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-exclamation-octagon-fill me-3 fs-4"></i>
                            <div>
                                <strong class="d-block mb-1">Login Failed</strong>
                                <span><?= htmlspecialchars($error) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="success-box shadow-sm">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-check-circle-fill me-3 fs-4"></i>
                            <div>
                                <strong class="d-block mb-1">Success</strong>
                                <span><?= htmlspecialchars($success) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="../routes/auth.php">
                    <input type="hidden" name="action" value="login">
    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Email address</label>
                        <input type="email" name="email" class="form-control" placeholder="name@example.com" required autofocus>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between">
                            <label class="form-label small fw-bold text-secondary">Password</label>
                            <a href="forgot-password.php" class="small text-decoration-none fw-bold" style="color: var(--brand-primary)">Forgot?</a>
                        </div>
                        
                        <div class="custom-input-group">
                            <input type="password" name="password" id="passwordInput" class="form-control" placeholder="••••••••" required>
                            <button type="button" class="toggle-btn" id="togglePassword">
                                <i class="bi bi-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-brand w-100 shadow-sm mb-4 fs-6">
                        Sign In
                    </button>
                </form>
                
                <div class="text-center pt-2 border-top">
                    <p class="small text-muted mb-0">New to ShopCorrect? 
                        <a href="register.php" class="fw-bold text-decoration-none" style="color: var(--brand-primary)">Join now</a>
                    </p>
                </div>
            </div>

            <p class="text-center mt-4 text-white-50 small fw-medium">
                 &copy; <?= date("Y") ?> <span class="notranslate">ShopCorrect</span>. Shop Smart. Shop Correct. <i class="bi bi-check-circle-fill text-success mx-1"></i>
            </p>

        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        const input = document.getElementById("passwordInput");
        const toggle = document.getElementById("togglePassword");
        const icon = document.getElementById("eyeIcon");

        toggle.addEventListener("click", () => {
            const isPassword = input.type === "password";
            input.type = isPassword ? "text" : "password";
            icon.className = isPassword ? "bi bi-eye-slash-fill text-primary" : "bi bi-eye";
            if (navigator.vibrate) navigator.vibrate(10);
        });
    });
</script>

    <?php if ($activeLangCode !== 'en'): ?>
    <style>
        /* ════ COMPLETELY HIDE ALL GOOGLE WIDGETS ════ */
        #google_translate_element, 
        .goog-te-banner-frame, 
        .goog-te-gadget, 
        .skiptranslate { 
            display: none !important; 
        }
        body { top: 0px !important; }
        .notranslate { color: inherit !important; }
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