<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/../app/helpers/mailer.php"; 

error_reporting(E_ALL);
ini_set('display_errors', 1);

$error = $_SESSION["flash_error"] ?? null;
unset($_SESSION["flash_error"]);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["start_registration"])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION["flash_error"] = "Invalid request.";
        header("Location: register.php");
        exit;
    }

    $email    = trim($_POST["email"] ?? "");
    $phone    = trim($_POST["phone"] ?? ""); 
    $password = $_POST["password"] ?? "";
    $role     = $_POST["role"] ?? "";
    $country  = $_POST["country"] ?? ""; 

    if (empty($email) || empty($phone) || empty($password) || empty($role) || empty($country)) {
        $_SESSION["flash_error"] = "All fields are required.";
        header("Location: register.php");
        exit;
    }

    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($cleanPhone) < 10 || strlen($cleanPhone) > 15) {
        $_SESSION["flash_error"] = "Please enter a valid complete phone number.";
        header("Location: register.php");
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION["flash_error"] = "Invalid email.";
        header("Location: register.php");
        exit;
    }

    if (strlen($password) < 8) {
        $_SESSION["flash_error"] = "Password must be at least 8 characters.";
        header("Location: register.php");
        exit;
    }

    // Role is back to "buyer" to match your updated database
    if (!in_array($role, ["buyer", "vendor"])) {
        $_SESSION["flash_error"] = "Invalid role.";
        header("Location: register.php");
        exit;
    }

    $otp = (string) random_int(100000, 999999);
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
    $expiresAt = date("Y-m-d H:i:s", time() + 300);

    $stmt = $pdo->prepare("SELECT id, email_verified_at FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingUser) {
        // Smart check: Make sure it's not a zero-date
        $isVerified = !empty($existingUser['email_verified_at']) && $existingUser['email_verified_at'] !== '0000-00-00 00:00:00';

        if ($isVerified) {
            $_SESSION["flash_error"] = "Email already registered. Try logging in.";
            header("Location: register.php");
            exit;
        } else {
            // Unverified user. Update their details and send a new OTP.
            $userId = $existingUser['id'];
            $stmt = $pdo->prepare("
                UPDATE users 
                SET phone = ?, password_hash = ?, role = ?, country = ?, otp_code = ?, otp_expires_at = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $phone,
                password_hash($password, PASSWORD_DEFAULT),
                $role,
                $country,
                $otpHash,
                $expiresAt,
                $userId
            ]);
        }
    } else {
        // Entirely new user
        $stmt = $pdo->prepare("
            INSERT INTO users (email, phone, password_hash, role, country, otp_code, otp_expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $email,
            $phone,
            password_hash($password, PASSWORD_DEFAULT),
            $role,
            $country,
            $otpHash,
            $expiresAt
        ]);
        $userId = $pdo->lastInsertId();
    }

    $subject = "Verify your ShopCorrect Account";
    $title   = "Verification Code";
    
    $message = "Thank you for joining <strong>ShopCorrect</strong>. To complete your account registration, please use the 6-digit verification code below. This code expires in <strong>5 minutes</strong>.
    <br><br>
    <span style='font-size: 13px; color: #94A3B8;'>If you didn't attempt to register an account with us, you can safely ignore this email. Someone else might have typed your email address by mistake.</span>";

    sendMail($email, $subject, $title, $message, null, $otp);

    $_SESSION["register_otp_user"] = $userId;
    header("Location: verify-registration-otp.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Account | ShopCorrect</title>
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
            justify-content: center;
            overflow-y: auto; 
            margin: 0;
            padding: 20px 0;
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
            max-width: 680px;
            padding: 15px;
            margin: auto;
        }

        .auth-card {
            border: none;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(15px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
            padding: 1.75rem 2.5rem;
        }

        .brand-text { color: var(--brand-primary); }
        
        .brand-logo-text {
            font-weight: 800;
            font-size: 1.5rem;
            letter-spacing: -1px;
            color: var(--brand-primary);
            margin: 0;
        }

        .form-control, .form-select {
            border-radius: 10px;
            padding: 10px 14px;
            border: 1.5px solid #E2E8F0;
            background-color: #F8FAFC;
            font-size: 14px;
            transition: 0.2s ease-in-out;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 4px rgba(11, 36, 71, 0.1);
            background-color: #fff;
        }

        .btn-brand {
            background-color: var(--brand-primary);
            color: #fff;
            border-radius: 10px;
            padding: 12px;
            font-weight: 700;
            border: none;
            transition: 0.3s;
        }

        .btn-brand:hover:not(:disabled) {
            background-color: var(--brand-hover);
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(11, 36, 71, 0.3);
        }

        .role-box {
            border: 1.5px solid var(--border-color);
            border-radius: 10px;
            padding: 10px;
            cursor: pointer;
            transition: 0.2s;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
        }

        .role-active {
            border-color: var(--brand-primary);
            background: #f1f5f9;
            box-shadow: inset 0 0 0 1px var(--brand-primary);
        }

        /* Premium Shake Animations */
        .error-shake { animation: shake 0.4s; border-color: #ef4444 !important; background-color: #fef2f2 !important; }
        @keyframes shake { 0%, 100% {transform: translateX(0);} 25% {transform: translateX(-5px);} 75% {transform: translateX(5px);} }

        .animate-pop { animation: popIn 0.5s cubic-bezier(0.26, 0.53, 0.74, 1.48); }
        @keyframes popIn { 0% { opacity: 0; transform: scale(0.95); } 100% { opacity: 1; transform: scale(1); } }
        
        @media (max-width: 768px) {
            .auth-card { padding: 1.5rem; }
        }

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
            <h4 class="fw-bold brand-text mb-1">Create Account</h4>
            <p class="text-muted small mb-0">Join our marketplace community today.</p>
        </div>

        <?php if($error): ?>
            <div class="alert alert-danger border-0 small py-2 text-center mb-3">
                <i class="bi bi-exclamation-circle-fill me-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="registerForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="start_registration" value="1">

            <div class="row g-3 mb-3">
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-secondary">Location</label>
                    <select name="country" id="countrySelect" class="form-select" required>
                        <option value="" selected disabled>Select country...</option>
                        <optgroup label="Africa">
                            <option value="Ghana">Ghana</option>
                            <option value="Nigeria">Nigeria</option>
                            <option value="Cote d'Ivoire">Cote d'Ivoire</option>
                            <option value="South Africa">South Africa</option>
                            <option value="Kenya">Kenya</option>
                            <option value="Togo">Togo</option>
                        </optgroup>
                        <optgroup label="International">
                            <option value="United Kingdom">United Kingdom</option>
                            <option value="United States">United States</option>
                            <option value="Canada">Canada</option>
                            <option value="Germany">Germany</option>
                            <option value="China">China</option>
                            <option value="Spain">Spain</option>
                        </optgroup>
                    </select>
                </div>
                <div class="col-md-7">
                    <label class="form-label small fw-bold text-secondary">Phone Number</label>
                    <input type="tel" name="phone" id="phoneInput" class="form-control" placeholder="Select country first..." required>
                    <div id="phoneError" class="text-danger small fw-bold mt-1 d-none"></div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold text-secondary">Email Address</label>
                <input type="email" name="email" id="emailInput" class="form-control" placeholder="example@mail.com" required>
                <div id="emailError" class="text-danger small fw-bold mt-1 d-none"></div>
            </div>

            <div class="row g-3 mb-2">
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-secondary">Password</label>
                    <div class="position-relative">
                        <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
                        <i class="bi bi-eye position-absolute top-50 end-0 translate-middle-y me-3 text-muted" style="cursor:pointer" onclick="togglePassword('password', this)"></i>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-secondary">Confirm Password</label>
                    <div class="position-relative">
                        <input type="password" id="confirmPassword" class="form-control" placeholder="••••••••" required>
                        <i class="bi bi-eye position-absolute top-50 end-0 translate-middle-y me-3 text-muted" style="cursor:pointer" onclick="togglePassword('confirmPassword', this)"></i>
                    </div>
                </div>
            </div>

            <div id="passwordMatch" class="small fw-bold mb-3" style="min-height: 18px;"></div>

            <div class="row align-items-end g-3 mb-3">
                <div class="col-md-7">
                    <label class="form-label small fw-bold text-secondary">Registration Type</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="role-box" id="buyerBox">
                                <input type="radio" name="role" value="buyer" class="d-none" checked required>
                                <i class="bi bi-bag brand-text"></i> <span class="small fw-bold">Buyer</span>
                            </label>
                        </div>
                        <div class="col-6">
                            <label class="role-box" id="vendorBox">
                                <input type="radio" name="role" value="vendor" class="d-none" required>
                                <i class="bi bi-shop brand-text"></i> <span class="small fw-bold">Vendor</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="col-md-5">
                     <button type="submit" class="btn btn-brand w-100 shadow-sm" id="submitBtn" disabled>
                        Create Account
                    </button>
                </div>
            </div>

            <div id="vendorNotice" class="alert alert-warning border-0 small py-2 d-none mb-3">
                <i class="bi bi-info-circle-fill me-2"></i>Vendor emails cannot be changed later.
            </div>

            <div class="text-center mt-3 pt-2 border-top">
                <span class="text-muted small">Joined us before?</span>
                <a href="login.php" class="small text-decoration-none fw-bold brand-text">Log in</a>
            </div>
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
const registerForm = document.getElementById("registerForm");
const countrySelect = document.getElementById("countrySelect");
const phoneInput = document.getElementById("phoneInput");
const phoneError = document.getElementById("phoneError");
const emailInput = document.getElementById("emailInput");
const emailError = document.getElementById("emailError");
const radios = document.querySelectorAll("input[name='role']");
const vendorNotice = document.getElementById("vendorNotice");
const password = document.getElementById("password");
const confirmPassword = document.getElementById("confirmPassword");
const passwordMatch = document.getElementById("passwordMatch");
const submitBtn = document.getElementById("submitBtn");

// Phone Formatting Masks
const phoneMasks = {
    "Ghana": "+233 ## ### ####",
    "Nigeria": "+234 ### ### ####",
    "Cote d'Ivoire": "+225 ## ## ## ## ##",
    "South Africa": "+27 ## ### ####",
    "Kenya": "+254 ### ######",
    "Togo": "+228 ## ## ## ##",
    "United Kingdom": "+44 #### ######",
    "United States": "+1 (###) ###-####",
    "Canada": "+1 (###) ###-####",
    "Germany": "+49 ### #######",
    "China": "+86 ### #### ####",
    "Spain": "+34 ### ## ## ##"
};

countrySelect.addEventListener("change", function() {
    const selectedCountry = this.value;
    if (phoneMasks[selectedCountry]) {
        const prefix = phoneMasks[selectedCountry].split('#')[0];
        phoneInput.value = prefix;
        phoneInput.focus();
        phoneError.classList.add("d-none");
        countrySelect.classList.remove("error-shake");
    }
});

phoneInput.addEventListener("input", function(e) {
    phoneError.classList.add("d-none");
    
    const country = countrySelect.value;
    const mask = phoneMasks[country];
    if (!mask) return;

    if (e.inputType === 'deleteContentBackward') return;

    let val = this.value.replace(/\D/g, ''); 
    let newVal = '';
    let valIndex = 0;

    for (let i = 0; i < mask.length; i++) {
        if (valIndex >= val.length) break;

        if (mask[i] === '#') {
            newVal += val[valIndex++];
        } else if (mask[i].match(/[0-9]/)) {
            newVal += mask[i];
            if (val[valIndex] === mask[i]) {
                valIndex++;
            }
        } else {
            newVal += mask[i];
        }
    }
    
    this.value = newVal;
});

function triggerError(inputElement, errorElement, message) {
    if (errorElement) {
        errorElement.innerHTML = "<i class='bi bi-exclamation-circle-fill me-1'></i> " + message;
        errorElement.classList.remove("d-none");
    }
    inputElement.classList.add("error-shake");
    setTimeout(() => inputElement.classList.remove("error-shake"), 400);
}

emailInput.addEventListener("input", () => emailError.classList.add("d-none"));

registerForm.addEventListener("submit", function(e) {
    let isValid = true;
    let firstErrorField = null;

    if (!countrySelect.value) {
        triggerError(countrySelect, null, null);
        isValid = false;
        firstErrorField = firstErrorField || countrySelect;
    }

    const rawDigits = phoneInput.value.replace(/\D/g, '');
    if (rawDigits.length < 10) {
        triggerError(phoneInput, phoneError, "Please enter a complete phone number.");
        isValid = false;
        firstErrorField = firstErrorField || phoneInput;
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailInput.value.trim() || !emailRegex.test(emailInput.value.trim())) {
        triggerError(emailInput, emailError, "Please enter a valid email address.");
        isValid = false;
        firstErrorField = firstErrorField || emailInput;
    }

    if (password.value.length < 8) {
        triggerError(password, passwordMatch, "<span class='text-danger'>Password must be at least 8 characters.</span>");
        isValid = false;
        firstErrorField = firstErrorField || password;
    } else if (password.value !== confirmPassword.value) {
        triggerError(confirmPassword, passwordMatch, "<span class='text-danger'>Passwords do not match.</span>");
        isValid = false;
        firstErrorField = firstErrorField || confirmPassword;
    }

    if (!isValid) {
        e.preventDefault(); 
        if (firstErrorField) firstErrorField.focus();
    }
});

function updateRoleUI(){
    document.querySelectorAll('.role-box').forEach(box => {
        box.classList.remove('role-active');
    });
    const checkedRadio = document.querySelector("input[name='role']:checked");
    if(checkedRadio){
        checkedRadio.closest('.role-box').classList.add('role-active');
        if(checkedRadio.value === "vendor") vendorNotice.classList.remove("d-none");
        else vendorNotice.classList.add("d-none");
    }
}

function validatePasswords(){
    if(password.value.length < 8){
        passwordMatch.innerHTML = "";
        submitBtn.disabled = true;
        return;
    }
    if(password.value === confirmPassword.value){
        passwordMatch.innerHTML = "<span class='text-success'>Passwords match <i class='bi bi-check-circle-fill'></i></span>";
        submitBtn.disabled = false;
    } else {
        passwordMatch.innerHTML = "<span class='text-danger'>Passwords do not match</span>";
        submitBtn.disabled = true;
    }
}

function togglePassword(id, icon){
    const field = document.getElementById(id);
    if(field.type === "password"){
        field.type = "text";
        icon.classList.replace("bi-eye", "bi-eye-slash");
    } else {
        field.type = "password";
        icon.classList.replace("bi-eye-slash", "bi-eye");
    }
}

password.addEventListener("input", validatePasswords);
confirmPassword.addEventListener("input", validatePasswords);
radios.forEach(r => r.addEventListener("change", updateRoleUI));
updateRoleUI();
</script>

</body>
</html>