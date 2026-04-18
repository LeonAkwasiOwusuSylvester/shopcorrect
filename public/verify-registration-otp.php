<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/../app/helpers/mailer.php"; 

/* ============================================================
    1. IDENTIFY THE USER
============================================================ */
$userId = $_SESSION["register_otp_user"] ?? $_SESSION["user_id"] ?? null;

if (!$userId) {
    header("Location: register.php");
    exit;
}

$userId = (int) $userId;

/* ============================================================
    2. FETCH USER DETAILS
============================================================ */
$stmt = $pdo->prepare("
    SELECT id, email, role, otp_code, otp_expires_at, email_verified_at 
    FROM users 
    WHERE id = ? 
    LIMIT 1
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_unset();
    session_destroy();
    header("Location: register.php");
    exit;
}

// STRICT CHECK: Make sure it's not a zero-date!
$isVerified = !empty($user['email_verified_at']) && $user['email_verified_at'] !== '0000-00-00 00:00:00';

/* ============================================================
    3. CHECK STATUS & REDIRECT
============================================================ */
if ($isVerified) {
    $_SESSION["user_id"] = $user["id"];
    $_SESSION["role"] = $user["role"];
    $_SESSION["logged_in"] = true;
    
    unset($_SESSION["register_otp_user"]);

    $redirect = ($user["role"] === "vendor") ? "complete-vendor-profile.php" : "buyer-info.php";

    if (isset($_POST["ajax_verify"])) {
        header("Content-Type: application/json");
        echo json_encode(["success" => true, "redirect" => $redirect]);
        exit;
    }

    header("Location: " . $redirect);
    exit;
}

$email = $user["email"];
$role  = $user["role"];
$otpExpires = $user["otp_expires_at"] ? strtotime($user["otp_expires_at"]) : 0;

/* ============================================================
    BACK ACTION (Now triggered via GET request link)
============================================================ */
if (isset($_GET["action"]) && $_GET["action"] === "back") {
    unset($_SESSION["register_otp_user"]);
    header("Location: register.php");
    exit;
}

/* ============================================================
    MASK EMAIL
============================================================ */
function maskEmail($email) {
    $parts = explode("@", $email);
    if (count($parts) !== 2) return $email;
    $name = $parts[0];
    $domain = $parts[1];
    return substr($name, 0, 1) . str_repeat("*", max(strlen($name) - 2, 3)) . substr($name, -1) . "@" . $domain;
}
$maskedEmail = maskEmail($email);

$_SESSION['reg_otp_attempts']   = $_SESSION['reg_otp_attempts'] ?? 0;
$_SESSION['reg_otp_lock_until'] = $_SESSION['reg_otp_lock_until'] ?? 0;
$isLocked = time() < $_SESSION['reg_otp_lock_until'];

/* ============================================================
    AJAX VERIFY
============================================================ */
if (isset($_POST["ajax_verify"])) {
    header("Content-Type: application/json");
    if ($isLocked) {
        echo json_encode(["success" => false, "message" => "Too many attempts. Try again later."]);
        exit;
    }
    $otp = trim($_POST["otp"] ?? "");
    if (!preg_match('/^\d{6}$/', $otp)) {
        echo json_encode(["success" => false, "message" => "Invalid code format."]);
        exit;
    }
    if (empty($user["otp_code"]) || !$otpExpires || $otpExpires < time() || !password_verify($otp, $user["otp_code"])) {
        $_SESSION['reg_otp_attempts']++;
        if ($_SESSION['reg_otp_attempts'] >= 5) { $_SESSION['reg_otp_lock_until'] = time() + 600; }
        
        if ($otpExpires && $otpExpires < time()) {
            echo json_encode(["success" => false, "message" => "Code expired. Please request a new one."]);
        } else {
            echo json_encode(["success" => false, "message" => "Incorrect verification code."]);
        }
        exit;
    }
    
    // SUCCESS: Mark as verified and trigger the timestamp correctly
    $pdo->prepare("UPDATE users SET otp_code = NULL, otp_expires_at = NULL, email_verified_at = NOW() WHERE id = ?")->execute([$userId]);
    
    // Set Login Session
    $_SESSION["user_id"] = $userId;
    $_SESSION["role"] = $role;
    $_SESSION["logged_in"] = true;
    
    unset($_SESSION["register_otp_user"]);
    
    echo json_encode(["success" => true, "redirect" => ($role === "vendor" ? "complete-vendor-profile.php" : "buyer-info.php")]);
    exit;
}

/* ============================================================
    AJAX RESEND
============================================================ */
if (isset($_POST["ajax_resend"])) {
    header("Content-Type: application/json");
    
    if (isset($_SESSION["resend_lock"]) && time() < $_SESSION["resend_lock"]) {
        echo json_encode(["success" => false, "message" => "Please wait before requesting another code."]);
        exit;
    }
    
    $otp = (string) random_int(100000, 999999);
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
    $expiresAt = date("Y-m-d H:i:s", time() + 300); 
    
    $pdo->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?")->execute([$otpHash, $expiresAt, $userId]);
    
    $subject = "Your New Verification Code - ShopCorrect (" . date("h:i:s A") . ")";
    $title   = "New Verification Code";
    $message = "As requested, here is your new verification code. This code expires in <strong>5 minutes</strong>. For security, this code replaces any previous codes sent to you.";

    sendMail($email, $subject, $title, $message, null, $otp);
    
    $_SESSION["resend_lock"] = time() + 60; 
    echo json_encode(["success" => true, "message" => "New verification code sent.", "expires" => strtotime($expiresAt)]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Email | ShopCorrect</title>
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
            background-image: 
                linear-gradient(135deg, rgba(11, 36, 71, 0.92), rgba(25, 55, 109, 0.85)),
                url('https://images.unsplash.com/photo-1563013544-824ae1b704d3?q=80&w=1000&auto=format&fit=crop');
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

        body::before {
            content: "SECURE";
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
            max-width: 580px;
            padding: 15px;
        }

        .auth-card {
            border: 1px solid rgba(255,255,255,0.1); 
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            padding: 2.5rem;
        }

        .brand-logo-text { font-weight: 800; font-size: 1.5rem; letter-spacing: -1px; color: var(--brand-primary); margin: 0; }

        .otp-container { display: flex; justify-content: center; gap: 10px; margin: 2rem 0; }
        .otp-input {
            width: 55px; height: 65px; font-size: 1.8rem; font-weight: 800; text-align: center;
            border: 2px solid var(--border-color); border-radius: 12px; background-color: #F8FAFC; transition: 0.2s;
        }
        .otp-input:focus { border-color: var(--brand-primary); box-shadow: 0 0 0 4px rgba(11, 36, 71, 0.1); background-color: #fff; outline: none; }
        .error-shake { animation: shake 0.4s; border-color: #ef4444 !important; }
        @keyframes shake { 0%, 100% {transform: translateX(0);} 25% {transform: translateX(-5px);} 75% {transform: translateX(5px);} }

        .btn-brand { background-color: var(--brand-primary); color: #fff; border-radius: 12px; padding: 14px; font-weight: 700; border: none; transition: 0.3s; }
        .btn-brand:hover:not(:disabled) { background-color: var(--brand-hover); transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(11, 36, 71, 0.3); }
        .btn-brand:disabled { background: #cbd5e1; cursor: not-allowed; }

        .animate-pop { animation: popIn 0.5s cubic-bezier(0.26, 0.53, 0.74, 1.48); }
        @keyframes popIn { 0% { opacity: 0; transform: scale(0.95); } 100% { opacity: 1; transform: scale(1); } }

        body > .skiptranslate { display: none !important; }
        .goog-te-banner-frame.skiptranslate { display: none !important; }
        body { top: 0px !important; }
        .notranslate { color: inherit !important; }
    </style>
</head>

<body>

<div class="auth-card-container">
    <div class="card auth-card animate-pop">
        <div class="d-flex align-items-center justify-content-center gap-2 mb-3">
            <img src="/assets/images/logo_b.png" height="60" alt="Logo">
            <span class="brand-logo-text notranslate">ShopCorrect</span>
        </div>

        <div class="text-center mb-3">
            <h4 class="fw-bold text-dark mb-1">Verify Email</h4>
            <p class="text-muted small mb-0">Code sent to <strong><?= htmlspecialchars($maskedEmail) ?></strong></p>
        </div>

        <div id="notice"></div>

        <form id="otpForm">
            <div class="otp-container">
                <?php for($i=0; $i<6; $i++): ?>
                    <input type="text" maxlength="1" class="otp-input" inputmode="numeric" <?= $isLocked ? 'disabled' : '' ?>>
                <?php endfor; ?>
            </div>

            <button type="submit" id="verifyBtn" class="btn btn-brand w-100 mb-3" <?= $isLocked ? 'disabled' : '' ?>>
                <span id="btnText">Verify & Continue</span>
                <span id="spinner" class="spinner-border spinner-border-sm d-none ms-2"></span>
            </button>
        </form>

        <div class="text-center mt-2">
            <div id="countdown" class="text-muted small mb-1"></div>
            <button id="resendBtn" class="btn btn-link p-0 text-decoration-none fw-bold small d-none" style="color: var(--brand-primary);">Resend Code</button>
        </div>

        <div class="text-center mt-3 pt-3 border-top">
            <a href="verify-registration-otp.php?action=back" class="btn btn-link p-0 text-decoration-none small text-secondary fw-medium">
                <i class="bi bi-arrow-left"></i> Change Email / Back
            </a>
        </div>
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
const inputs = document.querySelectorAll('.otp-input');
const otpForm = document.getElementById('otpForm');
const verifyBtn = document.getElementById('verifyBtn');
const spinner = document.getElementById('spinner');
const btnText = document.getElementById('btnText');
const notice = document.getElementById('notice');
const resendBtn = document.getElementById('resendBtn');
const countdown = document.getElementById('countdown');

let expiresAt = <?= $otpExpires ?>;
let timerInterval;
let isVerifying = false; 

function updateTimer() {
    let now = Math.floor(Date.now() / 1000);
    let remaining = expiresAt - now;
    if (remaining <= 0) {
        clearInterval(timerInterval);
        countdown.innerHTML = '<span class="text-danger fw-bold">Code expired</span>';
        resendBtn.classList.remove('d-none');
    } else {
        let mins = Math.floor(remaining / 60);
        let secs = remaining % 60;
        countdown.innerHTML = `Expires in <strong>${mins}:${secs.toString().padStart(2, '0')}</strong>`;
    }
}
timerInterval = setInterval(updateTimer, 1000);
updateTimer();

function verifyOtp(code) {
    if (isVerifying) return; 
    
    isVerifying = true;
    verifyBtn.disabled = true;
    spinner.classList.remove('d-none');
    btnText.textContent = "Verifying...";
    
    const formData = new URLSearchParams();
    formData.append('ajax_verify', '1');
    formData.append('otp', code);

    fetch('verify-registration-otp.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) { 
            window.location.href = data.redirect; 
        } else {
            isVerifying = false;
            verifyBtn.disabled = false;
            spinner.classList.add('d-none');
            btnText.textContent = "Verify & Continue";
            inputs.forEach(i => { i.value = ''; i.classList.add('error-shake'); });
            setTimeout(() => inputs.forEach(i => i.classList.remove('error-shake')), 400);
            notice.innerHTML = `<div class="alert alert-danger py-2 small border-0 bg-danger bg-opacity-10 text-danger text-center mb-3">${data.message}</div>`;
            inputs[0].focus();
        }
    })
    .catch(err => {
        isVerifying = false;
        verifyBtn.disabled = false;
        spinner.classList.add('d-none');
        btnText.textContent = "Verify & Continue";
        notice.innerHTML = `<div class="alert alert-danger py-2 small border-0 bg-danger bg-opacity-10 text-danger text-center mb-3">Network error. Please try again.</div>`;
    });
}

otpForm.addEventListener('submit', function(e) {
    e.preventDefault();
    let code = [...inputs].map(i => i.value).join('');
    if (code.length === 6) {
        verifyOtp(code);
    } else {
        inputs.forEach(i => i.classList.add('error-shake'));
        setTimeout(() => inputs.forEach(i => i.classList.remove('error-shake')), 400);
    }
});

inputs[0].addEventListener('paste', function(e) {
    e.preventDefault();
    const pasteData = (e.clipboardData || window.clipboardData).getData('text');
    const digits = pasteData.replace(/\D/g, '').slice(0, 6); 
    
    if (digits.length === 6) {
        inputs.forEach((input, i) => {
            input.value = digits[i];
        });
        verifyOtp(digits);
    }
});

inputs.forEach((input, index) => {
    input.addEventListener('input', () => {
        input.value = input.value.replace(/\D/g, '');
        if (input.value && index < inputs.length - 1) inputs[index + 1].focus();
        
        let code = [...inputs].map(i => i.value).join('');
        if (code.length === 6) {
            if (!isVerifying) verifyOtp(code); 
        }
    });
    input.addEventListener('keydown', (e) => {
        if (e.key === "Backspace" && !input.value && index > 0) inputs[index - 1].focus();
    });
});

resendBtn.addEventListener('click', () => {
    resendBtn.disabled = true;
    const formData = new URLSearchParams();
    formData.append('ajax_resend', '1');
    fetch('verify-registration-otp.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        resendBtn.disabled = false;
        notice.innerHTML = `<div class="alert alert-${data.success ? 'info' : 'danger'} py-2 small border-0 bg-${data.success ? 'info' : 'danger'} bg-opacity-10 text-${data.success ? 'info' : 'danger'} text-center mb-3">${data.message}</div>`;
        if (data.success) {
            expiresAt = data.expires;
            resendBtn.classList.add('d-none');
            clearInterval(timerInterval);
            timerInterval = setInterval(updateTimer, 1000);
            updateTimer();
        }
    });
});
</script>
</body>
</html>