<?php
require_once __DIR__ . "/../app/config/session.php";

/*
|--------------------------------------------------------------------------
| Guard: Prevent direct access without submission
|--------------------------------------------------------------------------
*/
if (empty($_SESSION["vendor_application_submitted"])) {
    // If they aren't coming from the upload page, send them to login
    header("Location: login.php");
    exit;
}

// Clear the flag so this page is "one-time use" per submission
unset($_SESSION["vendor_application_submitted"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Application Submitted | ShopCorrect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --brand-primary: #0B2447;
            --brand-hover: #19376D;
            --border-color: #E2E8F0;
            --success-bg: #dcfce7;
            --success-text: #166534;
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

        /* Success Icon Wrapper */
        .icon-wrapper {
            width: 75px; height: 75px;
            background-color: var(--success-bg);
            color: var(--success-text);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem auto;
            font-size: 2.2rem;
            position: relative;
        }

        .icon-wrapper::after {
            content: ''; position: absolute; width: 100%; height: 100%;
            border-radius: 50%; border: 2px solid var(--success-bg);
            animation: pulse 2s infinite;
            top: 0; left: 0;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            100% { transform: scale(1.4); opacity: 0; }
        }

        /* Steps Box */
        .next-steps-box {
            background: #F8FAFC;
            border: 1.5px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
            text-align: left;
            margin-bottom: 2rem;
        }

        .step-item { 
            display: flex; 
            gap: 15px; 
            margin-bottom: 1rem; 
            font-size: 0.95rem; 
            color: #334155; 
            align-items: flex-start; 
        }
        .step-item:last-child { margin-bottom: 0; }
        .step-bullet { 
            color: #10b981; 
            font-size: 1.25rem; 
            line-height: 1; 
            min-width: 24px; 
        }

        .btn-brand {
            background-color: var(--brand-primary);
            color: #fff;
            border-radius: 12px;
            padding: 14px;
            font-weight: 700;
            border: none;
            transition: 0.3s;
            width: 100%;
            display: block;
            text-align: center;
            text-decoration: none;
        }

        .btn-brand:hover {
            background-color: var(--brand-hover);
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(11, 36, 71, 0.3);
            color: white;
        }

        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-up { animation: slideUp 0.5s ease-out; }

        /* Google Translate Auto-Hide Overrides */
        body > .skiptranslate { display: none !important; }
        .goog-te-banner-frame.skiptranslate { display: none !important; }
        body { top: 0px !important; }
        .notranslate { color: inherit !important; }
    </style>
</head>

<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-11 col-md-9 col-lg-7 col-xl-6 col-xxl-5">

            <div class="card login-card p-4 p-md-5 animate-up">
                <div class="d-flex align-items-center justify-content-center gap-2 mb-4">
                    <img src="assets/images/logo_b.png" height="60" alt="Logo" onerror="this.style.display='none'">
                    <span class="brand-logo-text notranslate">ShopCorrect</span>
                </div>

                <div class="text-center mb-4">
                    <div class="icon-wrapper"><i class="bi bi-shield-check"></i></div>
                    <h3 class="fw-bold text-dark mb-2">Account Under Review</h3>
                    <p class="text-muted" style="font-size: 0.95rem;">
                        Success! Your documents have been submitted and your account is now in our verification queue.
                    </p>
                </div>

                <div class="next-steps-box">
                    <h6 class="fw-bold text-dark mb-3 text-uppercase small" style="letter-spacing: 1px;">
                        <i class="bi bi-stars text-warning me-1"></i> What happens now?
                    </h6>
                    <div class="step-item">
                        <span class="step-bullet"><i class="bi bi-check-circle-fill"></i></span>
                        <span>Our compliance team will review your ID and business information to ensure platform safety.</span>
                    </div>
                    <div class="step-item">
                        <span class="step-bullet"><i class="bi bi-clock-fill text-primary"></i></span>
                        <span>The verification process typically takes <strong>24 to 48 hours</strong>.</span>
                    </div>
                    <div class="step-item">
                        <span class="step-bullet"><i class="bi bi-envelope-check-fill text-success"></i></span>
                        <span>We will notify you via email the moment your shop is approved and ready to sell.</span>
                    </div>
                </div>

                <a href="login.php" class="btn btn-brand shadow-sm py-3 fs-6">
                    Return to Login
                </a>

                <div class="text-center pt-4">
                    <p class="small text-muted mb-0">Need help? 
                        <a href="mailto:support@shopcorrect.com" class="fw-bold text-decoration-none" style="color: var(--brand-primary)">Contact Support</a>
                    </p>
                </div>
            </div>

           <p class="text-center mt-4 text-white-50 small fw-medium">
             &copy; <?= date("Y") ?> <span class="notranslate">ShopCorrect</span>. Shop Smart. Shop Correct. <i class="bi bi-check-circle-fill text-success mx-1"></i>
           </p>

        </div>
    </div>
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

</body>
</html>