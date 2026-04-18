<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/../app/helpers/mailer.php";

/*
|--------------------------------------------------------------------------
| BACK LOGIC (Reset & Return to Register)
|--------------------------------------------------------------------------
*/
if (isset($_POST['back_action'])) {
    session_unset();
    session_destroy();
    header("Location: register.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Guard – Must Be Buyer In Onboarding Flow
|--------------------------------------------------------------------------
*/
// FIX: Accept both 'buyer' and 'user' based on your database ENUM setup
if (
    empty($_SESSION["user_id"]) || 
    empty($_SESSION["role"]) || 
    !in_array($_SESSION["role"], ["buyer", "user"])
) {
    if (!empty($_SESSION["role"]) && $_SESSION["role"] === "vendor") {
        header("Location: complete-vendor-profile.php");
        exit;
    }
    header("Location: register.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

/*
|--------------------------------------------------------------------------
| HANDLE FORM SUBMISSION
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['back_action'])) {
    
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid security token. Please refresh and try again.");
    }

    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $country  = trim($_POST['country'] ?? '');
    $address  = trim($_POST['address'] ?? '');

    // Stripping spaces/symbols for true digit count validation
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

    if (empty($fullName) || empty($email) || empty($phone) || empty($country) || empty($address)) {
        $_SESSION["flash_error"] = "Please fill in all fields.";
    } elseif (strlen($cleanPhone) < 10) {
        $_SESSION["flash_error"] = "Please enter a valid, complete phone number.";
    } else {
        try {
            // FIX: We now set status = 'active' upon completion!
            $updateStmt = $pdo->prepare("
                UPDATE users 
                SET name = ?, email = ?, phone = ?, country = ?, address = ?, status = 'active' 
                WHERE id = ?
            ");
            $updateStmt->execute([$fullName, $email, $phone, $country, $address, $userId]);

            // UPDATE SESSION
            $_SESSION['user_name'] = $fullName; 
            $_SESSION['name'] = $fullName; 

            // Send Welcome Email
            $subject = "Welcome to ShopCorrect!";
            $title   = "Welcome, $fullName!";
            $message = "Your account has been successfully set up. You can now browse our marketplace and shop for authentic products.";
           
            $button = [
               'text' => 'Start Shopping',
               'url'  => SHOP_URL . '/public/login.php' 
           ];
            
            try {
                sendMail($email, $subject, $title, $message, $button);
            } catch (Exception $e) {
                // Email failed, but proceed
            }

            header("Location: buyer-success.php");
            exit;

        } catch (Exception $e) {
            $_SESSION["flash_error"] = "Database Error: " . $e->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| Fetch User Data
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("SELECT name, email, country, phone FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_unset();
    session_destroy();
    header("Location: register.php");
    exit;
}

$name    = $user["name"] ?? "";
$email   = $user["email"] ?? "";
$country = $user["country"] ?? "";
$phone   = $user["phone"] ?? "";

$error = $_SESSION["flash_error"] ?? null;
unset($_SESSION["flash_error"]);

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Personal Information | ShopCorrect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root { --brand-primary: #0B2447; --brand-hover: #19376D; --border-color: #E2E8F0; }
        
        body {
            background: linear-gradient(rgba(11, 36, 71, 0.9), rgba(11, 36, 71, 0.9)), 
                        url('https://images.unsplash.com/photo-1557821552-17105176677c?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            overflow-y: auto;        
            padding: 40px 0;         
            margin: 0;
        }

        body::before {
            content: "SHOPCORRECT";
            position: fixed; top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 15vw; font-weight: 900;
            color: rgba(255, 255, 255, 0.03);
            white-space: nowrap; pointer-events: none; letter-spacing: 2rem;
            z-index: 0;
        }

        .auth-card-container {
            z-index: 1; width: 100%; max-width: 900px; padding: 20px;
            margin: auto; 
        }

        .auth-card {
            border: none; border-radius: 24px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(15px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
            padding: 3rem;
        }
        
        .brand-logo-text { font-weight: 800; font-size: 1.5rem; letter-spacing: -1px; color: var(--brand-primary); margin: 0; }

        .step-badge {
            background: rgba(11, 36, 71, 0.1); color: var(--brand-primary);
            padding: 6px 16px; border-radius: 30px;
            font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
        }

        .form-label { color: #334155; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem; }

        .form-control, .form-select {
            padding: 12px; border-radius: 10px;
            border: 1px solid var(--border-color);
            background-color: #F8FAFC; font-size: 0.95rem;
            transition: 0.2s ease-in-out;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 4px rgba(11, 36, 71, 0.1);
            background-color: #fff;
        }

        .btn-brand {
            background-color: var(--brand-primary); color: #fff;
            border-radius: 12px; padding: 14px; font-weight: 700;
            border: none; transition: all 0.3s ease;
        }

        .btn-brand:hover:not(:disabled) {
            background-color: var(--brand-hover);
            transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(11, 36, 71, 0.3);
        }
        
        .btn-brand:disabled { background-color: #cbd5e1; cursor: not-allowed; opacity: 0.6; }

        .btn-back {
            background-color: transparent; color: #64748b;
            border: 2px solid #E2E8F0; border-radius: 12px;
            padding: 12px 24px; font-weight: 600;
            text-decoration: none; transition: 0.3s;
        }
        .btn-back:hover { background-color: #f1f5f9; color: var(--brand-primary); border-color: #cbd5e1; }

        .progress { height: 6px; border-radius: 10px; background-color: #E2E8F0; overflow: hidden; }
        .progress-bar { background-color: var(--brand-primary); }

        .form-check-input { width: 1.25em; height: 1.25em; border: 2px solid #94a3b8; cursor: pointer; }
        .form-check-input:checked { background-color: var(--brand-primary); border-color: var(--brand-primary); }
        .checkbox-label { cursor: pointer; user-select: none; color: #475569; font-size: 0.9rem; font-weight: 500; }

        .animate-up { animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
        @keyframes slideUp { 0% { opacity: 0; transform: translateY(20px); } 100% { opacity: 1; transform: translateY(0); } }
        
        /* Premium Shake Animations */
        .error-shake { animation: shake 0.4s; border-color: #ef4444 !important; background-color: #fef2f2 !important; }
        @keyframes shake { 0%, 100% {transform: translateX(0);} 25% {transform: translateX(-5px);} 75% {transform: translateX(5px);} }

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
    <div class="card auth-card animate-up">
        
        <div class="d-flex align-items-center justify-content-center gap-2 mb-4">
            <img src="assets/images/logo_b.png" height="60" alt="Logo">
            <span class="brand-logo-text notranslate">ShopCorrect</span>
        </div>

        <div class="text-center mb-4">
            <span class="step-badge">Step 2 of 3</span>
            <h4 class="fw-bold text-dark mt-3 mb-1">Personal Information</h4>
            <p class="text-muted small">Complete your account setup with accurate delivery details.</p>
            <div class="progress mt-3 mx-auto" style="max-width: 200px;">
                <div class="progress-bar" style="width: 66%;"></div>
            </div>
        </div>

        <?php if($error): ?>
            <div class="alert alert-danger small text-center border-0 bg-danger bg-opacity-10 text-danger mb-4 error-shake">
                <i class="bi bi-exclamation-circle-fill me-1"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" id="onboardingForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="row g-4">
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" id="fullNameInput" class="form-control" value="<?= htmlspecialchars($name) ?>" placeholder="John Doe" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                            <input type="email" name="email" class="form-control border-start-0 ps-0" value="<?= htmlspecialchars($email) ?>" readonly>
                        </div>
                        <small class="text-muted" style="font-size: 0.7rem;">We will send your order receipts here.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" id="phoneInput" class="form-control" value="<?= htmlspecialchars($phone) ?>" placeholder="+233 XX XXX XXXX" required>
                        <div id="phoneError" class="text-danger small fw-bold mt-1 d-none"></div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Country</label>
                        <select name="country" id="countrySelect" class="form-select" required>
                            <option value="" <?= empty($country) ? 'selected' : '' ?> disabled>Select country...</option>
                            <optgroup label="Africa">
                                <option value="Ghana" <?= $country === 'Ghana' ? 'selected' : '' ?>>Ghana</option>
                                <option value="Nigeria" <?= $country === 'Nigeria' ? 'selected' : '' ?>>Nigeria</option>
                                <option value="Cote d'Ivoire" <?= $country === "Cote d'Ivoire" ? 'selected' : '' ?>>Cote d'Ivoire</option>
                                <option value="South Africa" <?= $country === 'South Africa' ? 'selected' : '' ?>>South Africa</option>
                                <option value="Kenya" <?= $country === 'Kenya' ? 'selected' : '' ?>>Kenya</option>
                                <option value="Togo" <?= $country === 'Togo' ? 'selected' : '' ?>>Togo</option>
                            </optgroup>
                            <optgroup label="International">
                                <option value="United Kingdom" <?= $country === 'United Kingdom' ? 'selected' : '' ?>>United Kingdom</option>
                                <option value="United States" <?= $country === 'United States' ? 'selected' : '' ?>>United States</option>
                                <option value="Canada" <?= $country === 'Canada' ? 'selected' : '' ?>>Canada</option>
                                <option value="Germany" <?= $country === 'Germany' ? 'selected' : '' ?>>Germany</option>
                                <option value="China" <?= $country === 'China' ? 'selected' : '' ?>>China</option>
                                <option value="Spain" <?= $country === 'Spain' ? 'selected' : '' ?>>Spain</option>
                            </optgroup>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Residential Address</label>
                        <textarea name="address" id="addressInput" class="form-control" rows="4" placeholder="House Number, Street Name, Area..." required></textarea>
                    </div>
                </div>

                <div class="col-12 mt-2">
                    <hr class="text-muted opacity-25">
                    
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        
                        <button type="submit" name="back_action" value="1" class="btn btn-back formnovalidate">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </button>

                        <div class="d-flex align-items-center gap-4">
                            <div class="form-check d-flex align-items-center gap-2 m-0 p-0">
                                <input class="form-check-input mt-0" type="checkbox" name="confirm_info" id="confirm_info" required>
                                <label class="form-check-label checkbox-label pt-1" for="confirm_info">
                                    Confirm info is accurate
                                </label>
                            </div>
                            
                            <button type="submit" id="submitBtn" class="btn btn-brand px-4" disabled>
                                Next Step <i class="bi bi-arrow-right ms-2"></i>
                            </button>
                        </div>
                    </div>
                </div>

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
    const onboardingForm = document.getElementById('onboardingForm');
    const checkbox = document.getElementById('confirm_info');
    const btn = document.getElementById('submitBtn');
    
    const countrySelect = document.getElementById('countrySelect');
    const phoneInput = document.getElementById('phoneInput');
    const phoneError = document.getElementById("phoneError");
    const fullNameInput = document.getElementById("fullNameInput");
    const addressInput = document.getElementById("addressInput");

    // 1. Checkbox Logic
    checkbox.addEventListener('change', function() {
        btn.disabled = !this.checked;
    });

    // 2. Real-time Phone Formatting Masks
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

    countrySelect.addEventListener('change', function() {
        const selectedCountry = this.value;
        if (phoneMasks[selectedCountry]) {
            const prefix = phoneMasks[selectedCountry].split('#')[0];
            // Only auto-fill if the input is currently empty or just has a different prefix
            if (phoneInput.value.length < 5 || !phoneInput.value.startsWith('+')) {
                phoneInput.value = prefix;
            }
            countrySelect.classList.remove("error-shake");
            phoneError.classList.add("d-none");
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
                if (val[valIndex] === mask[i]) valIndex++;
            } else {
                newVal += mask[i];
            }
        }
        this.value = newVal;
    });

    // 3. Shake Validation Helper
    function triggerError(inputElement, errorElement, message) {
        if (errorElement && message) {
            errorElement.innerHTML = "<i class='bi bi-exclamation-circle-fill me-1'></i> " + message;
            errorElement.classList.remove("d-none");
        }
        inputElement.classList.add("error-shake");
        setTimeout(() => inputElement.classList.remove("error-shake"), 400);
    }

    // Clear errors on typing
    fullNameInput.addEventListener("input", () => fullNameInput.classList.remove("error-shake"));
    addressInput.addEventListener("input", () => addressInput.classList.remove("error-shake"));

    // 4. Form Validation before Submit
    onboardingForm.addEventListener("submit", function(e) {
        // If clicking the "Back" button, skip validation
        if (e.submitter && e.submitter.name === 'back_action') return;

        let isValid = true;
        let firstErrorField = null;

        if (!fullNameInput.value.trim()) {
            triggerError(fullNameInput, null, null);
            isValid = false;
            firstErrorField = firstErrorField || fullNameInput;
        }

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

        if (!addressInput.value.trim()) {
            triggerError(addressInput, null, null);
            isValid = false;
            firstErrorField = firstErrorField || addressInput;
        }

        if (!isValid) {
            e.preventDefault(); 
            if (firstErrorField) firstErrorField.focus();
        }
    });

</script>

</body>
</html>