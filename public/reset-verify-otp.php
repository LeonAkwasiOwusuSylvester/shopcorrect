<?php
date_default_timezone_set('Africa/Accra');

require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/../app/helpers/mailer.php";

// Check which language is active for stealth translation
$activeLangCode = $_SESSION['lang'] ?? $_COOKIE['shop_lang'] ?? 'en'; 

/*
|--------------------------------------------------------------------------
| Guard: Ensure user started the reset process
|--------------------------------------------------------------------------
*/
if (empty($_SESSION["reset_email"])) {
    header("Location: forgot-password.php");
    exit;
}

$resetEmail = $_SESSION["reset_email"];
$error = "";

/*
|--------------------------------------------------------------------------
| AJAX Resend Logic (Integrated with Branded Template)
|--------------------------------------------------------------------------
*/
if (isset($_POST["ajax_resend"])) {
    header("Content-Type: application/json");
    
    $otp = random_int(100000, 999999);
    $hashedOtp = password_hash($otp, PASSWORD_DEFAULT);
    $expiryTime = date('Y-m-d H:i:s', time() + 300); // Exact PHP Expiry Time (5 Mins)

    // Set expiration to exact PHP time
    $pdo->prepare("
        UPDATE users
        SET reset_otp = ?,
            reset_otp_expires = ?,
            reset_otp_attempts = 0
        WHERE email = ?
    ")->execute([$hashedOtp, $expiryTime, $resetEmail]);

    // --- MAILER FIX ---
    $subject = "New Recovery Code - ShopCorrect";
    $title   = "New Recovery Code";
    
    // CHANGED TEXT TO 5 MINUTES
    $message = "As requested, here is your new 6-digit password recovery code. This code expires in <strong>5 minutes</strong>. If you didn't ask for this, your account is still safe—just ignore this email.";

    // Pass the raw OTP as the 6th argument so the mailer formats it inside the Glass Box
    sendMail($resetEmail, $subject, $title, $message, null, $otp);
    
    echo json_encode(["success" => true, "message" => "A new code has been sent."]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Verify OTP Logic
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST["ajax_resend"])) {
    $otp = implode("", $_POST["otp"] ?? []);

    if (!preg_match('/^\d{6}$/', $otp)) {
        $error = "Invalid OTP format.";
    } else {
        $stmt = $pdo->prepare("
            SELECT id, reset_otp, reset_otp_expires, reset_otp_attempts 
            FROM users 
            WHERE email = ? 
            LIMIT 1
        ");
        $stmt->execute([$resetEmail]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = "Invalid request.";
        } elseif ($user["reset_otp_attempts"] >= 5) {
            $error = "Too many attempts. Request a new OTP.";
        } elseif (strtotime($user["reset_otp_expires"]) < time()) {
            $error = "OTP expired. Request a new one.";
        } elseif (!password_verify($otp, $user["reset_otp"])) {
            $pdo->prepare("UPDATE users SET reset_otp_attempts = reset_otp_attempts + 1 WHERE id = ?")->execute([$user["id"]]);
            $error = "Incorrect verification code.";
        }

        if ($error === "") {
            $pdo->prepare("UPDATE users SET reset_otp = NULL, reset_otp_expires = NULL, reset_otp_attempts = 0 WHERE id = ?")->execute([$user["id"]]);
            $_SESSION["reset_user"] = $user["id"];
            header("Location: reset-password.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Identity | ShopCorrect</title>
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
            font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            overflow: hidden; flex-direction: column;
        }

        body::before {
            content: "SHOPCORRECT"; position: fixed; top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg); font-size: 15vw; font-weight: 900;
            color: rgba(255, 255, 255, 0.03); white-space: nowrap; pointer-events: none; letter-spacing: 2rem;
            z-index: 0;
        }

        .auth-card-container {
            z-index: 1; width: 100%; max-width: 450px; padding: 15px; display: flex; flex-direction: column; align-items: center;
        }

        .auth-card {
            border: none; border-radius: 24px; background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(15px); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
            padding: 2.5rem; width: 100%;
        }

        .otp-box {
            width: 48px; height: 60px; font-size: 1.6rem; text-align: center; font-weight: 800;
            border-radius: 12px; border: 2px solid var(--border-color); background: #F8FAFC; transition: all 0.2s;
        }

        .otp-box:focus {
            border-color: var(--brand-primary); background: #fff;
            box-shadow: 0 0 0 4px rgba(11, 36, 71, 0.1); outline: none;
        }

        .btn-brand {
            background-color: var(--brand-primary); color: #fff; border-radius: 12px; padding: 14px;
            font-weight: 700; border: none; transition: 0.3s;
        }

        .btn-brand:hover:not(:disabled) { background-color: var(--brand-hover); transform: translateY(-2px); }

        .resend-link { font-weight: 700; text-decoration: none; color: var(--brand-primary); border: none; background: none; }
        .resend-link:disabled { color: #A0AEC0; cursor: not-allowed; }

        @keyframes shake { 0%, 100% {transform: translateX(0)} 25% {transform: translateX(-8px)} 75% {transform: translateX(8px)} }
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

<div class="auth-card-container">
    <div class="auth-card animate-pop <?= $error ? 'shake' : '' ?>">
        <div class="d-flex align-items-center justify-content-center gap-2 mb-4">
            <img src="assets/images/logo_b.png" height="60" alt="Logo" onerror="this.style.display='none'">
            <span class="notranslate" style="font-weight: 800; font-size: 1.5rem; letter-spacing: -1px; color: var(--brand-primary);">ShopCorrect</span>
        </div>

        <div class="text-center mb-4">
            <h4 class="fw-bold text-dark mb-1">Verify Identity</h4>
            <p class="text-muted small">We've sent a 6-digit code to <br><strong><?= htmlspecialchars($resetEmail) ?></strong></p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 small py-2 text-center mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="otpForm" autocomplete="off">
            <div class="d-flex justify-content-center gap-2 mb-4">
                <?php for ($i=0; $i<6; $i++): ?>
                    <input class="otp-box" maxlength="1" name="otp[]" inputmode="numeric" pattern="[0-9]*" required>
                <?php endfor; ?>
            </div>

            <button class="btn btn-brand w-100 shadow-sm" id="verifyBtn">Verify & Continue</button>
        </form>

        <div class="text-center mt-4 small">
            <div id="timerContainer" class="text-muted">
                Code expires in <span id="timer" class="fw-bold fs-6 text-dark ms-1">05:00</span>
            </div>
            <button id="resendBtn" class="resend-link d-none" disabled>
                <i class="bi bi-arrow-clockwise me-1"></i>Resend OTP
            </button>
        </div>

        <div class="text-center mt-4 pt-3 border-top">
            <a href="forgot-password.php" class="small text-decoration-none fw-bold" style="color: var(--brand-primary);">
                <i class="bi bi-arrow-left me-1"></i> Change Email
            </a>
        </div>
    </div>

    <p class="text-center mt-4 text-white-50 small fw-medium">
        &copy; <?= date("Y") ?> <span class="notranslate">ShopCorrect</span>. Shop Smart. Shop Correct. <i class="bi bi-check-circle-fill text-success mx-1"></i>
    </p>
</div>

<script>
const inputs = document.querySelectorAll(".otp-box");
const form = document.getElementById("otpForm");
const timerSpan = document.getElementById("timer");
const timerContainer = document.getElementById("timerContainer");
const resendBtn = document.getElementById("resendBtn");

// 🔢 Intelligent Input & Auto-Paste
inputs.forEach((input, i) => {
    input.addEventListener("input", (e) => {
        if (input.value && inputs[i + 1]) inputs[i + 1].focus();
        if ([...inputs].every(inp => inp.value)) form.submit();
    });

    input.addEventListener("keydown", e => {
        if (e.key === "Backspace" && !input.value && inputs[i - 1]) inputs[i - 1].focus();
    });

    // Auto-Paste Logic
    input.addEventListener('paste', e => {
        if (i !== 0) return;
        const data = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
        if (data.length === 6) {
            inputs.forEach((inp, idx) => inp.value = data[idx]);
            form.submit();
        }
    });
});

// ⏳ Resend Timer Logic (5 Minutes = 300 seconds)
let time = 300;
let countdownInterval;

const updateTimerDisplay = () => {
    let minutes = Math.floor(time / 60);
    let seconds = time % 60;
    timerSpan.textContent = 
        (minutes < 10 ? "0" : "") + minutes + ":" + 
        (seconds < 10 ? "0" : "") + seconds;
};

const startTimer = () => {
    time = 300;
    updateTimerDisplay();
    timerContainer.classList.remove("d-none");
    resendBtn.classList.add("d-none");
    resendBtn.disabled = true;
    
    clearInterval(countdownInterval);
    countdownInterval = setInterval(() => {
        time--;
        updateTimerDisplay();
        
        if (time <= 0) {
            clearInterval(countdownInterval);
            timerContainer.classList.add("d-none");
            resendBtn.classList.remove("d-none");
            resendBtn.disabled = false;
        }
    }, 1000);
};

startTimer();

// 🔄 AJAX Resend
resendBtn.onclick = async () => {
    const originalText = resendBtn.innerHTML;
    resendBtn.disabled = true;
    resendBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span>Sending...`;

    const formData = new FormData();
    formData.append('ajax_resend', '1');

    try {
        const res = await fetch(window.location.href, { method: "POST", body: formData });
        const data = await res.json();
        
        if (data.success) {
            startTimer();
        } else {
            alert(data.message || "Failed to resend code.");
            resendBtn.disabled = false;
        }
    } catch (err) {
        alert("Failed to resend code. Please check your connection.");
        resendBtn.disabled = false;
    } finally {
        resendBtn.innerHTML = originalText;
    }
};

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