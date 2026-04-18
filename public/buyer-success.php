<?php
require_once __DIR__ . "/../app/config/session.php";

/*
|--------------------------------------------------------------------------
| Guard – Must Be Logged In Buyer
|--------------------------------------------------------------------------
*/
if (
    empty($_SESSION["user_id"]) ||
    empty($_SESSION["role"]) ||
    $_SESSION["role"] !== "buyer"
) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Ready | ShopCorrect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --brand-primary: #0B2447;
            --brand-hover: #19376D;
            --success-color: #10B981;
        }

        body {
            background: linear-gradient(rgba(11, 36, 71, 0.9), rgba(11, 36, 71, 0.9)), 
                        url('https://images.unsplash.com/photo-1557821552-17105176677c?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow: hidden;
        }

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
            max-width: 500px; /* Narrower card for success message */
        }

        .auth-card {
            border: none;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(15px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
            padding: 3rem 2rem;
            text-align: center;
        }
        
        .brand-logo-text { font-weight: 800; font-size: 1.5rem; letter-spacing: -1px; color: var(--brand-primary); margin: 0; }

        .step-badge {
            background: rgba(16, 185, 129, 0.1); /* Green tint for success */
            color: var(--success-color);
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .success-icon-wrapper {
            width: 80px;
            height: 80px;
            background-color: rgba(16, 185, 129, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem auto;
            animation: bounceIn 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        .success-icon {
            font-size: 40px;
            color: var(--success-color);
        }

        .btn-brand {
            background-color: var(--brand-primary);
            color: #fff;
            border-radius: 12px;
            padding: 14px 28px;
            font-weight: 700;
            border: none;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            width: 100%;
        }

        .btn-brand:hover {
            background-color: var(--brand-hover);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(11, 36, 71, 0.3);
        }

        .progress {
            height: 6px;
            border-radius: 10px;
            background-color: #E2E8F0;
            overflow: hidden;
            margin: 1.5rem auto;
            max-width: 150px;
        }
        
        .progress-bar {
            background-color: var(--success-color); /* Green progress bar */
        }

        .animate-up { animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
        @keyframes slideUp { 0% { opacity: 0; transform: translateY(20px); } 100% { opacity: 1; transform: translateY(0); } }
        @keyframes bounceIn { 0% { transform: scale(0); } 60% { transform: scale(1.1); } 100% { transform: scale(1); } }

        /* Google Translate Auto-Hide Overrides */
        body > .skiptranslate { display: none !important; }
        .goog-te-banner-frame.skiptranslate { display: none !important; }
        body { top: 0px !important; }
        .notranslate { color: inherit !important; }
    </style>
</head>

<body>

<div class="auth-card-container">
    <div class="card auth-card animate-up">
        
        <div class="d-flex align-items-center justify-content-center gap-2 mb-4">
            <img src="/assets/images/logo_b.png" height="60" alt="Logo">
            <span class="brand-logo-text notranslate">ShopCorrect</span>
        </div>

        <div class="success-icon-wrapper">
            <i class="bi bi-check-lg success-icon"></i>
        </div>

        <h3 class="fw-bold text-dark mb-2">You're All Set!</h3>
        <p class="text-muted small mb-4">
            Your profile has been created successfully.<br>
            Welcome to the <span class="notranslate">ShopCorrect</span> family.
        </p>

        <div class="text-center">
            <span class="step-badge">Step 3 of 3 • Complete</span>
            <div class="progress">
                <div class="progress-bar" style="width: 100%;"></div>
            </div>
        </div>

        <div class="alert alert-light border border-light-subtle text-muted small py-3 mb-4">
            Redirecting to homepage in <strong id="countdown" class="text-dark">10</strong> seconds...
        </div>

        <a href="index.php" class="btn btn-brand">
            Start Shopping Now <i class="bi bi-bag-check-fill ms-2"></i>
        </a>

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
    // Updated countdown logic to 10 seconds
    let seconds = 10;
    const countdownEl = document.getElementById("countdown");

    const timer = setInterval(() => {
        seconds--;
        countdownEl.textContent = seconds;

        if (seconds <= 0) {
            clearInterval(timer);
            window.location.href = "index.php"; // Change this if your homepage is in a different folder
        }
    }, 1000);
</script>

</body>
</html>