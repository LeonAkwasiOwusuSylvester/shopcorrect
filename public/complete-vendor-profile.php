<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";

/* 1. SECURITY & ROLE GUARD */
if (empty($_SESSION["user_id"]) || $_SESSION["role"] !== 'vendor') {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION["user_id"];
$error = "";

/* 2. FETCH DEFAULT VALUES (Auto-populate phone from registration) */
// Fetch user's registered phone number
$userStmt = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$userPhone = $userStmt->fetchColumn() ?: '';

// Fetch existing vendor data (in case they navigate back here later)
$vendorStmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ?");
$vendorStmt->execute([$userId]);
$existingVendor = $vendorStmt->fetch(PDO::FETCH_ASSOC);

/* 3. FORM PROCESSING */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $storeName    = trim($_POST["store_name"] ?? '');
    $description  = trim($_POST["description"] ?? '');
    $phone        = trim($_POST["phone"] ?? '');
    $address      = trim($_POST["address"] ?? '');
    $location     = trim($_POST["location"] ?? ''); 
    $businessType = $_POST["business_type"] ?? 'individual';
    $regNumber    = ($businessType === 'registered_business') ? trim($_POST["business_registration_number"] ?? '') : null;

    // Professional Slug Generator
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $storeName), '-'));

    if (empty($storeName) || empty($phone) || empty($address) || empty($location)) {
        $error = "Please fill in all required fields.";
    } elseif ($businessType === 'registered_business' && empty($regNumber)) {
        $error = "Please provide your business registration number.";
    } else {
        try {
            $pdo->beginTransaction();

            if ($existingVendor) {
                // UPDATE existing profile
                $stmt = $pdo->prepare("
                    UPDATE vendors 
                    SET shop_name = ?, shop_slug = ?, shop_description = ?, 
                        business_phone = ?, business_address = ?, business_location = ?, 
                        business_type = ?, business_registration_number = ?,
                        status = 'pending'
                    WHERE user_id = ?
                ");
                $stmt->execute([$storeName, $slug, $description, $phone, $address, $location, $businessType, $regNumber, $userId]);
            } else {
                // INSERT new profile
                $stmt = $pdo->prepare("
                    INSERT INTO vendors (user_id, shop_name, shop_slug, shop_description, 
                                        business_phone, business_address, business_location, 
                                        business_type, business_registration_number,
                                        status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([$userId, $storeName, $slug, $description, $phone, $address, $location, $businessType, $regNumber]);
            }
            
            $pdo->commit();
            header("Location: verification-upload.php");
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "System Error: " . $e->getMessage();
        }
    }
}

// Prepare values to inject into the form (Prioritize POST data > Existing Vendor Data > User Data)
$defStoreName = $_POST['store_name'] ?? ($existingVendor['shop_name'] ?? '');
$defDesc      = $_POST['description'] ?? ($existingVendor['shop_description'] ?? '');
$defPhone     = $_POST['phone'] ?? ($existingVendor['business_phone'] ?? $userPhone); // Auto-fills the phone here!
$defAddress   = $_POST['address'] ?? ($existingVendor['business_address'] ?? '');
$defLocation  = $_POST['location'] ?? ($existingVendor['business_location'] ?? '');
$defBusType   = $_POST['business_type'] ?? ($existingVendor['business_type'] ?? 'individual');
$defRegNum    = $_POST['business_registration_number'] ?? ($existingVendor['business_registration_number'] ?? '');

$steps = [
    ["label" => "Acct", "status" => "completed"],
    ["label" => "Verif", "status" => "completed"],
    ["label" => "Info", "status" => "active"],
    ["label" => "Doc", "status" => "pending"],
    ["label" => "Done", "status" => "pending"]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Business Profile | ShopCorrect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --brand-primary: #0B2447; --brand-hover: #19376D; --border-color: #E2E8F0; }
        body {
            background: linear-gradient(rgba(11, 36, 71, 0.85), rgba(11, 36, 71, 0.85)), 
                        url('https://images.unsplash.com/photo-1557821552-17105176677c?auto=format&fit=crop&w=1920&q=80');
            background-size: cover; background-position: center; background-attachment: fixed;
            font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; overflow-x: hidden;
        }
        .auth-card-container { z-index: 1; width: 100%; max-width: 680px; padding: 15px; }
        .auth-card { border: none; border-radius: 24px; background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(15px); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6); padding: 2rem 2.5rem; }
        .brand-logo-text { font-weight: 800; font-size: 1.5rem; letter-spacing: -1px; color: var(--brand-primary); margin: 0; }
        
        .steps-wrapper { margin-bottom: 25px; padding: 0 5px; }
        .steps-list { list-style: none; padding: 0; margin: 0; display: flex; justify-content: space-between; position: relative; }
        .steps-list::before { content: ''; position: absolute; top: 9px; left: 0; width: 100%; height: 2px; background: var(--border-color); z-index: 0; }
        .step-item { position: relative; z-index: 1; text-align: center; flex: 1; }
        .step-dot { width: 18px; height: 18px; border-radius: 50%; background: #fff; border: 2px solid var(--border-color); margin: 0 auto 4px auto; }
        .step-text { font-size: 0.65rem; color: #64748b; font-weight: 600; text-transform: uppercase; display: block; }
        .step-item.active .step-dot { background: var(--brand-primary); border-color: var(--brand-primary); transform: scale(1.1); }
        .step-item.active .step-text { color: var(--brand-primary); }
        .step-item.completed .step-dot { background: var(--brand-primary); border-color: var(--brand-primary); }
        
        .form-control, .form-select { border-radius: 10px; padding: 10px 14px; border: 1.5px solid #E2E8F0; background-color: #F8FAFC; font-size: 14px; }
        .form-control:focus, .form-select:focus { border-color: var(--brand-primary); box-shadow: 0 0 0 4px rgba(11, 36, 71, 0.1); background-color: #fff; outline: none; }
        .btn-brand { background-color: var(--brand-primary); color: #fff; border-radius: 10px; padding: 12px; font-weight: 700; border: none; transition: 0.3s; }
        .btn-brand:hover { background-color: var(--brand-hover); transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(11, 36, 71, 0.3); }
        .btn-ghost { background: transparent; border: 1.5px solid var(--border-color); color: #64748b; padding: 12px; border-radius: 10px; font-weight: 600; text-decoration: none; display: block; text-align: center; transition: 0.2s; }
        .btn-ghost:hover { background-color: #f8fafc; color: var(--brand-primary); border-color: #cbd5e1; }
        .animate-pop { animation: popIn 0.5s cubic-bezier(0.26, 0.53, 0.74, 1.48); }
        @keyframes popIn { 0% { opacity: 0; transform: scale(0.95); } 100% { opacity: 1; transform: scale(1); } }
        
        @media (max-width: 768px) {
            .auth-card { padding: 1.5rem; }
        }

        /* Google Translate Auto-Hide Overrides */
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
            <img src="/assets/images/logo_b.png" height="60" alt="Logo" onerror="this.style.display='none'">
            <span class="brand-logo-text notranslate">ShopCorrect</span>
        </div>

        <div class="steps-wrapper">
            <ul class="steps-list">
                <?php foreach($steps as $step): ?>
                    <li class="step-item <?= $step['status'] ?>">
                        <div class="step-dot"></div>
                        <span class="step-text"><?= $step['label'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="text-center mb-3">
            <h4 class="fw-bold text-dark mb-1">Business Profile</h4>
            <p class="text-muted small mb-0">Fill in your store details to continue.</p>
        </div>

        <?php if($error): ?>
            <div class="alert alert-danger border-0 small py-2 text-center mb-3">
                <i class="bi bi-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="row g-3 mb-3">
                <div class="col-md-7">
                    <label class="form-label small fw-bold text-secondary">Business Name <span class="text-danger">*</span></label>
                    <input type="text" name="store_name" class="form-control" value="<?= htmlspecialchars($defStoreName) ?>" placeholder="e.g. Best Gadgets GH" required autofocus>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-secondary">Business Type <span class="text-danger">*</span></label>
                    <select name="business_type" id="business_type" class="form-select" required>
                        <option value="individual" <?= ($defBusType === 'individual') ? 'selected' : '' ?>>Individual / Sole Proprietor</option>
                        <option value="registered_business" <?= ($defBusType === 'registered_business') ? 'selected' : '' ?>>Registered Business</option>
                    </select>
                </div>
            </div>

            <div id="registration_number_wrapper" class="mb-3 d-none">
                <label class="form-label small fw-bold text-secondary">Business Registration Number <span class="text-danger">*</span></label>
                <input type="text" name="business_registration_number" id="reg_number" class="form-control" value="<?= htmlspecialchars($defRegNum) ?>" placeholder="Enter BN Number (e.g. BN0012023)">
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-secondary">Phone <span class="text-danger">*</span></label>
                    <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($defPhone) ?>" placeholder="+233 XX XXX XXXX" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-secondary">Location <span class="text-danger">*</span></label>
                    <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($defLocation) ?>" placeholder="e.g. East Legon" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-secondary">Address <span class="text-danger">*</span></label>
                    <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($defAddress) ?>" placeholder="Building/Street" required>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-bold text-secondary">Short Description</label>
                <textarea name="description" class="form-control" rows="2" placeholder="What does your store sell?"><?= htmlspecialchars($defDesc) ?></textarea>
            </div>
            
            <div class="row g-2">
                <div class="col-4">
                    <a href="register.php" class="btn btn-ghost">Back</a>
                </div>
                <div class="col-8">
                    <button type="submit" class="btn btn-brand w-100 shadow-sm">Save & Continue</button>
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
    const businessTypeSelect = document.getElementById('business_type');
    const regNumWrapper = document.getElementById('registration_number_wrapper');
    const regNumInput = document.getElementById('reg_number');

    function checkBusinessType() {
        if (businessTypeSelect.value === 'registered_business') {
            regNumWrapper.classList.remove('d-none');
            regNumInput.setAttribute('required', 'required');
        } else {
            regNumWrapper.classList.add('d-none');
            regNumInput.removeAttribute('required');
            // We only clear the value if they actively change it away, not on page load
        }
    }

    businessTypeSelect.addEventListener('change', function() {
        checkBusinessType();
        if (this.value !== 'registered_business') {
            regNumInput.value = '';
        }
    });

    // Run on page load to ensure correct state if prepopulated
    checkBusinessType();
</script>
</body>
</html>