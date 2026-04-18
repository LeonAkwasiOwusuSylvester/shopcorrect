<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";

// Check which language is active for stealth translation
$activeLangCode = $_SESSION['lang'] ?? $_COOKIE['shop_lang'] ?? 'en'; 

/*
|--------------------------------------------------------------------------
| Guard: OTP session must exist 
|--------------------------------------------------------------------------
*/
$otpUserId = $_SESSION["otp_user_id"] ?? $_SESSION["otp_user"] ?? null;

if (empty($otpUserId)) {
    header("Location: login.php");
    exit;
}

$error = "";
$is_locked = false;

// 1. Fetch user data including database lock status
$stmt = $pdo->prepare("SELECT id, name, role, otp_code, otp_expires_at, otp_attempts, otp_locked_until FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$otpUserId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: login.php");
    exit;
}

// 2. Check if locked in the database
if ($user['otp_locked_until'] && strtotime($user['otp_locked_until']) > time()) {
    $is_locked = true;
    $remaining_lock = ceil((strtotime($user['otp_locked_until']) - time()) / 60);
    $error = "Too many attempts. Locked for $remaining_lock more minutes.";
}

/*
|--------------------------------------------------------------------------
| VERIFY OTP
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && !$is_locked) {
    $otp = isset($_POST['otp_code']) ? implode('', $_POST['otp_code']) : trim($_POST['otp'] ?? "");

    if (!preg_match('/^\d{6}$/', $otp)) {
        $error = "Please enter all 6 digits.";
    } else {
        // Check if OTP is expired or incorrect
        if (strtotime($user["otp_expires_at"]) < time() || !password_verify($otp, $user["otp_code"])) {
            
            $attempts = $user['otp_attempts'] + 1;
            
            if ($attempts >= 5) {
                // Lock the account in the database for 10 minutes
                $lockUntil = date("Y-m-d H:i:s", time() + (10 * 60));
                $pdo->prepare("UPDATE users SET otp_attempts = ?, otp_locked_until = ? WHERE id = ?")->execute([$attempts, $lockUntil, $user["id"]]);
                
                $error = "Too many failed attempts. Locked for 10 minutes.";
                $is_locked = true;
            } else {
                // Increment attempts in the database
                $pdo->prepare("UPDATE users SET otp_attempts = ? WHERE id = ?")->execute([$attempts, $user["id"]]);
                $remaining = 5 - $attempts;
                
                if (strtotime($user["otp_expires_at"]) < time()) {
                    $error = "Code has expired. Please request a new one.";
                } else {
                    $error = "Invalid code. $remaining attempts remaining.";
                }
            }
        } else {
            // Success! Clear OTP data and reset attempts.
            $pdo->prepare("UPDATE users SET otp_code = NULL, otp_expires_at = NULL, otp_attempts = 0, otp_locked_until = NULL WHERE id = ?")->execute([$user["id"]]);
            
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["role"]    = $user["role"];
            $_SESSION["name"]    = $user["name"];
            unset($_SESSION["otp_user"], $_SESSION["otp_user_id"]);
            
            // ✅ THE FIX: Smart Role-Based Routing
            $role = $user["role"];
            $finalRedirect = "index.php"; // Normal buyers go home by default

            if ($role === "vendor") {
                $finalRedirect = "vendor/index.php"; // Vendors go to dashboard
            } elseif (in_array($role, ["admin", "supadmin"])) {
                $finalRedirect = "admin/index.php"; // Admins go to dashboard
            }

            // Check if we captured the page they were looking at before logging in
            if (!empty($_SESSION["login_redirect"])) {
                $savedUrl = $_SESSION["login_redirect"];
                unset($_SESSION["login_redirect"]); // Clear the ticket

                // Only normal buyers get sent back to their previous page (like a product page)
                if ($role === "user") {
                    $finalRedirect = $savedUrl;
                } 
                // Allow unapproved vendors to go to the upload page if they need to
                elseif ($role === "vendor" && strpos($savedUrl, 'verification-upload.php') !== false) {
                    $vCheck = $pdo->prepare("SELECT status FROM vendors WHERE user_id = ?");
                    $vCheck->execute([$user["id"]]);
                    if ($vCheck->fetchColumn() !== "approved") {
                        $finalRedirect = $savedUrl;
                    }
                }
            }
            
            header("Location: " . $finalRedirect);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Account | ShopCorrect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { 
            --sc-navy: #0B2447; 
            --sc-blue: #19376D;
            --sc-accent: #3b82f6;
        }
        
        body {
            /* Consistent Secure Background */
            background-image: 
                linear-gradient(135deg, rgba(11, 36, 71, 0.92), rgba(25, 55, 109, 0.85)),
                url('https://images.unsplash.com/photo-1563013544-824ae1b704d3?q=80&w=1000&auto=format&fit=crop');
            background-size: cover; 
            background-position: center; 
            background-attachment: fixed;
            font-family: 'Plus Jakarta Sans', sans-serif; 
            min-height: 100vh; 
            display: flex; 
            align-items: center;
            position: relative; 
            overflow-x: hidden;
        }

        /* Subtle floating background text */
        body::before {
            content: "SECURE"; 
            position: fixed; top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg); 
            font-size: 15vw; font-weight: 900;
            color: rgba(255, 255, 255, 0.03); 
            white-space: nowrap; pointer-events: none; 
            letter-spacing: 2rem; z-index: 0;
        }

        .auth-card {
            border: 1px solid rgba(255,255,255,0.1); 
            border-radius: 24px; 
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px); 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); 
            z-index: 1;
            position: relative;
        }

        .brand-text { color: var(--sc-navy); }
        
        /* Improved OTP Inputs */
        .otp-container { display: flex; gap: 10px; justify-content: center; margin-bottom: 1.5rem; }
        .otp-box {
            width: 48px; height: 58px; 
            text-align: center; font-size: 1.5rem;
            font-weight: 700; color: var(--sc-navy);
            border: 2px solid #E2E8F0; border-radius: 12px;
            background: #F8FAFC; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .otp-box:focus { 
            border-color: var(--sc-accent); 
            background: #fff; 
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15); 
            outline: none; 
            transform: translateY(-2px);
        }

        .btn-brand {
            background-color: var(--sc-navy); color: #fff; border-radius: 12px; padding: 14px;
            font-weight: 700; border: none; transition: 0.3s;
        }
        .btn-brand:hover { 
            background-color: var(--sc-blue); 
            transform: translateY(-2px); 
            box-shadow: 0 10px 20px -5px rgba(11, 36, 71, 0.4); 
            color: white;
        }
        .btn-brand:disabled { background-color: #94a3b8; transform: none; box-shadow: none; cursor: not-allowed; }

        @keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-8px); } 75% { transform: translateX(8px); } }
        .shake { animation: shake 0.4s ease-in-out; }

        /* Google Translate Auto-Hide Overrides (Top Layer) */
        body > .skiptranslate { display: none !important; }
        .goog-te-banner-frame.skiptranslate { display: none !important; }
        iframe.skiptranslate, iframe.goog-te-banner-frame { display: none !important; }
        body { top: 0px !important; }
        .notranslate { color: inherit !important; }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-8 col-lg-5 col-xl-4">
            
            <div class="card auth-card p-4 p-md-5 <?= $error ? 'shake' : '' ?>">
                
                <div class="text-center mb-4">
                    <div class="d-flex align-items-center justify-content-center gap-2 mb-4">
                        <img src="assets/images/logo_b.png" height="60" alt="Logo" onerror="this.style.display='none'">
                        <span class="notranslate" style="font-weight: 800; font-size: 1.5rem; letter-spacing: -1px; color: var(--sc-navy);">ShopCorrect</span>
                    </div>
                    
                    <h4 class="fw-bold brand-text mb-1">Two-Step Verification</h4>
                    <p class="text-muted small">We sent a secure code to your email.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger border-0 rounded-3 small py-2 text-center mb-4 shadow-sm bg-danger-subtle text-danger fw-semibold">
                        <i class="bi bi-exclamation-circle-fill me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form id="otpForm" method="POST" autocomplete="off">
                    
                    <div class="otp-container" id="otpInputs">
                        <?php for($i=0; $i<6; $i++): ?>
                            <input type="text" name="otp_code[]" class="otp-box" maxlength="1" inputmode="numeric" required <?= $is_locked ? 'disabled' : '' ?>>
                        <?php endfor; ?>
                    </div>
                    
                    <button type="submit" class="btn btn-brand w-100 shadow-sm" <?= $is_locked ? 'disabled' : '' ?>>
                        Verify & Access Account <i class="bi bi-arrow-right-short fs-5 ms-1" style="vertical-align: middle;"></i>
                    </button>
                </form>

                <div class="text-center mt-4 small">
                    <div class="mb-2" id="timerContainer">
                        <span class="text-muted">Code expires in</span> 
                        <span id="timer" class="fw-bold fs-6 ms-1" style="color: var(--sc-navy);">05:00</span>
                    </div>
                    <a href="javascript:void(0)" id="resendBtn" class="d-none fw-bold text-decoration-none" style="color: var(--sc-accent);">
                        <i class="bi bi-arrow-clockwise me-1"></i>Resend Code
                    </a>
                </div>
            </div>

            <p class="text-center mt-4 text-white-50 small fw-medium">
                 &copy; <?= date("Y") ?> <span class="notranslate">ShopCorrect</span>. Shop Smart. Shop Correct. <i class="bi bi-check-circle-fill text-success mx-1"></i>
            </p>
        </div>
    </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="liveToast" class="toast align-items-center text-white border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center">
                <i id="toastIcon" class="bi me-2 fs-5"></i>
                <span id="toastMessage" class="fw-medium"></span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const inputs = document.querySelectorAll('.otp-box');
    const form = document.getElementById('otpForm');
    const resendBtn = document.getElementById('resendBtn');
    const timerSpan = document.getElementById('timer');
    const timerContainer = document.getElementById('timerContainer');
    
    // Toast Elements
    const toastEl = document.getElementById('liveToast');
    const toast = new bootstrap.Toast(toastEl);
    const toastMsg = document.getElementById('toastMessage');
    const toastIcon = document.getElementById('toastIcon');

    // 5 Minutes (300 Seconds)
    let timeLeft = 300; 
    let countdown;

    const showNotice = (message, type = 'success') => {
        toastMsg.innerText = message;
        toastEl.classList.remove('bg-success', 'bg-danger');
        if (type === 'success') {
            toastEl.classList.add('bg-success');
            toastIcon.className = "bi bi-check-circle-fill me-2";
        } else {
            toastEl.classList.add('bg-danger');
            toastIcon.className = "bi bi-exclamation-triangle-fill me-2";
        }
        toast.show();
    };

    const updateTimerDisplay = () => {
        let minutes = Math.floor(timeLeft / 60);
        let seconds = timeLeft % 60;
        timerSpan.textContent = 
            (minutes < 10 ? "0" : "") + minutes + ":" + 
            (seconds < 10 ? "0" : "") + seconds;
    };

    const startTimer = () => {
        timeLeft = 300; // Reset to 5 minutes
        updateTimerDisplay();
        timerContainer.classList.remove('d-none');
        resendBtn.classList.add('d-none');
        clearInterval(countdown);
        
        countdown = setInterval(() => {
            timeLeft--;
            updateTimerDisplay();
            
            if (timeLeft <= 0) {
                clearInterval(countdown);
                timerContainer.classList.add('d-none');
                resendBtn.classList.remove('d-none');
            }
        }, 1000);
    };

    startTimer();

    resendBtn.addEventListener('click', async () => {
        const originalHtml = resendBtn.innerHTML;
        resendBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Sending...`;
        resendBtn.style.pointerEvents = 'none';

        try {
            const response = await fetch('resend-otp-ajax.php', { method: 'POST' });
            
            const text = await response.text();
            try {
                const result = JSON.parse(text);
                if (result.success) {
                    showNotice(result.message, 'success');
                    startTimer();
                } else {
                    showNotice(result.message, 'danger');
                }
            } catch(e) {
                console.error('Server Error:', text);
                showNotice("Server error occurred", 'danger');
            }

        } catch (error) {
            showNotice("Connection error. Try again.", 'danger');
        } finally {
            resendBtn.innerHTML = originalHtml;
            resendBtn.style.pointerEvents = 'auto';
        }
    });

    inputs.forEach((input, index) => {
        // Auto-select content on click
        input.addEventListener('click', () => { input.select(); });

        input.addEventListener('input', (e) => {
            if (e.inputType === "deleteContentBackward") return;
            if (input.value && index < inputs.length - 1) inputs[index + 1].focus();
            if (Array.from(inputs).every(i => i.value !== "")) form.submit();
        });
        
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !input.value && index > 0) inputs[index - 1].focus();
        });
        
        input.addEventListener('focus', () => { if (window.navigator.vibrate) window.navigator.vibrate(5); });
    });

    inputs[0].addEventListener('paste', (e) => {
        e.preventDefault();
        const data = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6).split('');
        data.forEach((char, i) => { if (inputs[i]) inputs[i].value = char; });
        if (data.length === 6) form.submit();
    });
});
</script> 

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