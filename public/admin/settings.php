<?php
require_once __DIR__ . "/../../app/config/db.php";
require_once __DIR__ . "/../../app/config/session.php";
require_once __DIR__ . "/../../app/helpers/mailer.php";

// 1. SESSION & ROLE SECURITY
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'supadmin') {
    header("Location: ../login.php");
    exit;
}

// Ensure dynamic base URL for the email buttons
$isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$protocol = $isSecure ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$basePath = (strpos($_SERVER['REQUEST_URI'], '/shopcorrect/') !== false) ? '/shopcorrect' : '';
$dynamicBaseUrl = $protocol . $host . $basePath;

/* =========================
   FETCH SETTINGS
========================= */
$stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Fallback defaults if the table is completely empty
if (!$settings) {
    $settings = [
        'id' => null,
        'site_name' => 'ShopCorrect',
        'support_email' => '',
        'support_phone' => '',
        'currency' => 'GHS',
        'commission_percent' => 10,
        'vendor_grace_period_months' => 3, // ✅ NEW DEFAULT
        'maintenance_mode' => 0,
        'maintenance_roles' => 'user,vendor',
        'promo_active' => 0,
        'promo_text' => '',
        'promo_code' => '',
        'promo_end_date' => null,
        'promo_image' => ''
    ];
}

$oldMaintMode = (int)($settings['maintenance_mode'] ?? 0);
$oldBlockedRolesStr = $settings['maintenance_roles'] ?? '';

// Convert blocked roles string to an array for the checkboxes
$blockedRoles = !empty($settings['maintenance_roles']) ? explode(',', $settings['maintenance_roles']) : [];

// Format datetime for the HTML5 datetime-local input
$promoEndDateHTML = '';
if (!empty($settings['promo_end_date'])) {
    $promoEndDateHTML = date('Y-m-d\TH:i', strtotime($settings['promo_end_date']));
}

/* =========================
   SAVE SETTINGS LOGIC
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $site_name  = trim($_POST["site_name"]);
    $email      = trim($_POST["support_email"]);
    $phone      = trim($_POST["support_phone"]);
    $currency   = trim($_POST["currency"]);
    
    $commission = (int) $_POST["commission_percent"]; 
    $grace_period = (int) $_POST["vendor_grace_period_months"]; // ✅ CAPTURE NEW SETTING
    
    $maint      = isset($_POST["maintenance_mode"]) ? 1 : 0;
    
    $blockedRolesArray = isset($_POST['blocked_roles']) ? $_POST['blocked_roles'] : [];
    $blockedRolesStr   = implode(',', $blockedRolesArray);

    // PROMO SETTINGS
    $promo_active   = isset($_POST["promo_active"]) ? 1 : 0;
    $promo_text     = trim($_POST["promo_text"] ?? '');
    $promo_code     = trim($_POST["promo_code"] ?? '');
    $promo_end_date = !empty($_POST["promo_end_date"]) ? $_POST["promo_end_date"] : null;
    
    $promo_images_arr = !empty($settings['promo_image']) ? explode(',', $settings['promo_image']) : [];

    // Handle Multiple Hero Banner Image Uploads
    if (isset($_FILES['promo_images']) && !empty($_FILES['promo_images']['name'][0])) {
        $uploadDir = __DIR__ . '/../../public/uploads/banners/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $new_uploaded_images = [];
        $fileCount = count($_FILES['promo_images']['name']);
        
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['promo_images']['error'][$i] === UPLOAD_ERR_OK) {
                $fileExt = strtolower(pathinfo($_FILES['promo_images']['name'][$i], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                
                if (in_array($fileExt, $allowed)) {
                    $newFileName = 'hero_promo_' . time() . '_' . $i . '.' . $fileExt;
                    if (move_uploaded_file($_FILES['promo_images']['tmp_name'][$i], $uploadDir . $newFileName)) {
                        $new_uploaded_images[] = $newFileName;
                    }
                }
            }
        }
        
        if (!empty($new_uploaded_images)) {
            $promo_images_arr = $new_uploaded_images;
        }
    }

    $promo_image = implode(',', $promo_images_arr);

    // --- EXECUTE DATABASE UPDATE ---
    if ($settings['id']) {
        $updateStmt = $pdo->prepare("
            UPDATE settings SET
            site_name = ?, support_email = ?, support_phone = ?,
            currency = ?, commission_percent = ?, vendor_grace_period_months = ?, 
            maintenance_mode = ?, maintenance_roles = ?,
            promo_active = ?, promo_text = ?, promo_code = ?, promo_end_date = ?, promo_image = ?
            WHERE id = ?
        ");
        $updateStmt->execute([
            $site_name, $email, $phone, $currency, $commission, $grace_period, 
            $maint, $blockedRolesStr, 
            $promo_active, $promo_text, $promo_code, $promo_end_date, $promo_image, 
            $settings["id"]
        ]);
    } else {
        $insertStmt = $pdo->prepare("
            INSERT INTO settings (site_name, support_email, support_phone, currency, commission_percent, vendor_grace_period_months, maintenance_mode, maintenance_roles, promo_active, promo_text, promo_code, promo_end_date, promo_image) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insertStmt->execute([
            $site_name, $email, $phone, $currency, $commission, $grace_period, 
            $maint, $blockedRolesStr,
            $promo_active, $promo_text, $promo_code, $promo_end_date, $promo_image
        ]);
    }

    // --- AUTOMATED MAINTENANCE EMAIL NOTIFICATIONS ---
    set_time_limit(0); 

    if ($oldMaintMode === 0 && $maint === 1 && !empty($blockedRolesArray)) {
        $placeholders = str_repeat('?,', count($blockedRolesArray) - 1) . '?';
        $userStmt = $pdo->prepare("SELECT email, name FROM users WHERE role IN ($placeholders) AND status = 'active'");
        $userStmt->execute($blockedRolesArray);
        $usersToNotify = $userStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($usersToNotify) {
            $subject = "Scheduled Maintenance Notice - ShopCorrect ⚙️";
            $title = "System Maintenance in Progress";
            $msg = "<p>Hello,</p><p>We are currently performing scheduled maintenance on the ShopCorrect platform to improve your experience and add new features.</p><p>During this time, access to your account is temporarily disabled. We apologize for the inconvenience and will notify you as soon as the system is back online.</p>";
            
            foreach ($usersToNotify as $user) {
                sendMail($user['email'], $subject, $title, str_replace('Hello,', "Hello {$user['name']},", $msg), null, null, 'correction');
            }
        }
    }

    if ($oldMaintMode === 1 && $maint === 0) {
        $previouslyBlocked = !empty($oldBlockedRolesStr) ? explode(',', $oldBlockedRolesStr) : [];
        if (!empty($previouslyBlocked)) {
            $placeholders = str_repeat('?,', count($previouslyBlocked) - 1) . '?';
            $userStmt = $pdo->prepare("SELECT email, name FROM users WHERE role IN ($placeholders) AND status = 'active'");
            $userStmt->execute($previouslyBlocked);
            $usersToNotify = $userStmt->fetchAll(PDO::FETCH_ASSOC);

            if ($usersToNotify) {
                $subject = "ShopCorrect is Back Online! 🎉";
                $title = "Maintenance Complete";
                $msg = "<p>Hello,</p><p>Great news! The scheduled system maintenance is complete and ShopCorrect is fully operational again.</p><p>Thank you for your patience. You can now log back into your account and resume normal activities.</p>";
                $btn = ['text' => 'Log In Now', 'url' => $dynamicBaseUrl . "/public/login.php"];

                foreach ($usersToNotify as $user) {
                    sendMail($user['email'], $subject, $title, str_replace('Hello,', "Hello {$user['name']},", $msg), $btn, null, 'approved');
                }
            }
        }
    }

    header("Location: settings.php?saved=1");
    exit;
}

// INCLUDE HEADER
require_once __DIR__ . "/includes/header.php";
?>

<style>
    .form-label { font-weight: 700; color: var(--text-color); font-size: 0.85rem; text-transform: uppercase; margin-bottom: 8px; }
    .form-control-custom { 
        border-radius: 12px; padding: 12px 16px; border: 1.5px solid #E2E8F0; background-color: #F8FAFC; 
        color: var(--text-color); font-weight: 500; transition: all 0.2s;
    }
    .form-control-custom:focus { 
        border-color: var(--shop-accent); box-shadow: 0 0 0 4px rgba(25, 55, 109, 0.1); 
        background-color: #fff; outline: none; 
    }
    .btn-brand { 
        background: var(--shop-brand); color: white; border-radius: 12px; 
        padding: 12px 30px; font-weight: 700; border: none; transition: 0.3s; 
    }
    .btn-brand:hover { background: var(--shop-accent); transform: translateY(-2px); color: white; box-shadow: 0 10px 15px -3px rgba(11,36,71,0.3); }
    
    .switch-card { background: #F8FAFC; border-radius: 12px; border: 1px solid #E2E8F0; transition: 0.3s; cursor: pointer; }
    .switch-card:hover { border-color: #cbd5e1; background: #f1f5f9; }

    .role-checkbox-wrapper { border: 1px solid #E2E8F0; border-radius: 10px; padding: 10px 15px; background: #fff; transition: 0.2s; }
    .role-checkbox-wrapper:hover { border-color: var(--shop-brand); }
    .role-checkbox-wrapper .form-check-input:checked { background-color: var(--shop-brand); border-color: var(--shop-brand); }
</style>

<div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
    <div>
        <h6 class="text-secondary fw-bold text-uppercase mb-1" style="font-size: 11px; letter-spacing: 1px;">System Configuration</h6>
        <h3 class="fw-bold mb-0" style="color: var(--shop-brand);">Platform Settings</h3>
    </div>
    
    <a href="change-password.php" class="btn btn-white shadow-sm fw-bold border bg-white" style="border-radius: 12px; color: var(--shop-brand);">
        <i class="bi bi-shield-lock me-2"></i> Security Settings
    </a>
</div>

<?php if(isset($_GET["saved"])): ?>
    <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 fw-bold alert-dismissible fade show">
        <i class="bi bi-check-circle-fill me-2"></i> Settings Saved & Notifications Processed ✅
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="glass-card mb-4">
    <form method="POST" enctype="multipart/form-data" onsubmit="return handleMaintenanceWarning();">
        <div class="row g-4">
            
            <div class="col-md-6">
                <label class="form-label">Marketplace Name</label>
                <input type="text" name="site_name" class="form-control-custom w-100" value="<?= htmlspecialchars($settings["site_name"] ?? '') ?>" required>
            </div>
            
            <div class="col-md-6">
                <label class="form-label">System Currency (Base)</label>
                <input type="text" name="currency" class="form-control-custom w-100" value="<?= htmlspecialchars($settings["currency"] ?? 'GHS') ?>" required>
                <small class="text-muted mt-1 d-block"><i class="bi bi-info-circle text-primary"></i> The root currency for accounting.</small>
            </div>
            
            <div class="col-md-6">
                <label class="form-label">Support Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted border-top border-bottom border-start" style="border-radius: 12px 0 0 12px;"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="support_email" class="form-control-custom w-100 border-start-0" style="border-radius: 0 12px 12px 0; width: calc(100% - 45px) !important;" value="<?= htmlspecialchars($settings["support_email"] ?? '') ?>" required>
                </div>
            </div>
            
            <div class="col-md-6">
                <label class="form-label">Support Helpline</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted border-top border-bottom border-start" style="border-radius: 12px 0 0 12px;"><i class="bi bi-telephone"></i></span>
                    <input type="text" name="support_phone" class="form-control-custom w-100 border-start-0" style="border-radius: 0 12px 12px 0; width: calc(100% - 45px) !important;" value="<?= htmlspecialchars($settings["support_phone"] ?? '') ?>" required>
                </div>
            </div>
            
            <div class="col-md-6">
                <label class="form-label">Global Commission Rate (%)</label>
                <div class="input-group">
                    <input type="number" name="commission_percent" class="form-control-custom w-100 border-end-0" style="border-radius: 12px 0 0 12px; width: calc(100% - 45px) !important;" value="<?= htmlspecialchars($settings["commission_percent"] ?? 10) ?>" required>
                    <span class="input-group-text bg-light border-start-0 text-muted border-top border-bottom border-end fw-bold" style="border-radius: 0 12px 12px 0;">%</span>
                </div>
                <small class="text-muted mt-2 d-block"><i class="bi bi-info-circle text-primary"></i> Default rate applied after grace period expires.</small>
            </div>

            <div class="col-md-6">
                <label class="form-label text-success">Zero-Commission Grace Period (New Vendors)</label>
                <div class="input-group">
                    <select name="vendor_grace_period_months" class="form-control-custom w-100 border-end-0" style="border-radius: 12px 0 0 12px; width: calc(100% - 80px) !important;">
                        <option value="0" <?= ($settings["vendor_grace_period_months"] == 0) ? 'selected' : '' ?>>Off (0 Months)</option>
                        <option value="1" <?= ($settings["vendor_grace_period_months"] == 1) ? 'selected' : '' ?>>1 Month</option>
                        <option value="3" <?= ($settings["vendor_grace_period_months"] == 3) ? 'selected' : '' ?>>3 Months (Recommended)</option>
                        <option value="6" <?= ($settings["vendor_grace_period_months"] == 6) ? 'selected' : '' ?>>6 Months</option>
                    </select>
                    <span class="input-group-text bg-light border-start-0 text-muted border-top border-bottom border-end fw-bold" style="border-radius: 0 12px 12px 0;">Months</span>
                </div>
                <small class="text-muted mt-2 d-block"><i class="bi bi-stars text-warning"></i> Starts exactly when their account is approved.</small>
            </div>

        </div>

        <hr class="my-5 opacity-10">
        <h5 class="fw-bold mb-4 text-dark"><i class="bi bi-megaphone-fill me-2 text-warning"></i> Marketing & Promotions</h5>
        
        <div class="p-4 border rounded-4 bg-white shadow-sm mb-5">
            <label class="switch-card p-3 d-flex flex-row align-items-center mb-0 w-100 border-0 bg-transparent" for="promoActive" style="cursor: pointer;">
                <div class="form-check form-switch m-0 p-0 d-flex align-items-center w-100">
                    <input class="form-check-input ms-0 me-3" type="checkbox" role="switch" id="promoActive" name="promo_active" <?= !empty($settings["promo_active"]) ? "checked" : "" ?> style="width: 3em; height: 1.5em; cursor: pointer;">
                    <div>
                        <span class="fw-bold text-dark d-block" style="font-size: 1.1rem;">Enable Storewide Promotion</span>
                        <span class="small text-muted fw-normal">Turns on the Top Banner and replaces the main Hero Image on the homepage.</span>
                    </div>
                </div>
            </label>

            <div id="promoSettings" style="display: <?= !empty($settings["promo_active"]) ? 'block' : 'none' ?>;" class="ps-2 pe-2 pb-2 mt-3 border-top pt-4">
                <div class="row g-4">
                    <div class="col-md-12">
                        <label class="form-label">Hero Banner Images (Select multiple)</label>
                        <input type="file" name="promo_images[]" class="form-control-custom w-100" accept="image/*" multiple>
                        <small class="text-muted mt-1 d-block">Hold CTRL (or CMD on Mac) to select multiple images. Uploading new images will replace the current ones.</small>
                        
                        <?php if(!empty($settings['promo_image'])): 
                            $current_images = explode(',', $settings['promo_image']);
                        ?>
                            <div class="mt-3 p-3 bg-light rounded border">
                                <small class="text-success fw-bold d-block mb-2"><i class="bi bi-images"></i> <?= count($current_images) ?> Active Images</small>
                                <div class="d-flex gap-2 flex-wrap">
                                    <?php foreach($current_images as $img): ?>
                                        <div style="width: 80px; height: 50px; overflow: hidden; border-radius: 6px; border: 1px solid #ccc;">
                                            <img src="../../public/uploads/banners/<?= htmlspecialchars($img) ?>" alt="promo" style="width: 100%; height: 100%; object-fit: cover;">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-12">
                        <label class="form-label">Banner Text (HTML allowed)</label>
                        <input type="text" name="promo_text" class="form-control-custom w-100" value="<?= htmlspecialchars($settings["promo_text"] ?? '') ?>" placeholder="e.g. 🎉 BLACK FRIDAY: Get 10% off your entire order!">
                        <small class="text-muted mt-1 d-block">You can use <code>&lt;strong&gt;</code> tags to make words bold.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Promo Code</label>
                        <input type="text" name="promo_code" class="form-control-custom w-100 text-uppercase" value="<?= htmlspecialchars($settings["promo_code"] ?? '') ?>" placeholder="e.g. BLACKFRI26">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Countdown End Date & Time</label>
                        <input type="datetime-local" name="promo_end_date" class="form-control-custom w-100" value="<?= $promoEndDateHTML ?>">
                    </div>
                </div>
            </div>
        </div>

        <hr class="my-5 opacity-10">
        <h5 class="fw-bold mb-4 text-dark"><i class="bi bi-tools me-2 text-danger"></i> System Maintenance</h5>

        <div class="p-4 border rounded-4 bg-white shadow-sm">
            <label class="switch-card p-3 d-flex flex-row align-items-center mb-3 w-100 border-0 bg-transparent" for="maint" style="cursor: pointer;">
                <div class="form-check form-switch m-0 p-0 d-flex align-items-center w-100">
                    <input class="form-check-input ms-0 me-3" type="checkbox" role="switch" id="maint" name="maintenance_mode" data-initial="<?= $oldMaintMode ?>" <?= $oldMaintMode ? "checked" : "" ?> style="width: 3em; height: 1.5em; cursor: pointer;">
                    <div>
                        <span class="fw-bold text-dark d-block" style="font-size: 1.1rem;">Enable Maintenance Mode</span>
                        <span class="small text-muted fw-normal">Temporarily disable storefront access. Choose exactly who gets locked out below.</span>
                    </div>
                </div>
            </label>

            <div id="rolesSelection" style="display: <?= $oldMaintMode ? 'block' : 'none' ?>;" class="ps-2 pe-2 pb-2">
                <hr class="opacity-10 mb-4">
                <label class="form-label mb-3 text-primary"><i class="bi bi-person-x-fill me-2"></i>Select Accounts to Block:</label>
                
                <div class="d-flex flex-wrap gap-3">
                    <div class="role-checkbox-wrapper form-check flex-grow-1">
                        <input class="form-check-input fs-5 mt-1" type="checkbox" name="blocked_roles[]" value="user" id="blockUser" <?= in_array('user', $blockedRoles) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold ms-2 pt-1" style="cursor: pointer;" for="blockUser">Buyers / Customers</label>
                    </div>
                    
                    <div class="role-checkbox-wrapper form-check flex-grow-1">
                        <input class="form-check-input fs-5 mt-1" type="checkbox" name="blocked_roles[]" value="vendor" id="blockVendor" <?= in_array('vendor', $blockedRoles) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold ms-2 pt-1" style="cursor: pointer;" for="blockVendor">Store Vendors</label>
                    </div>
                    
                    <div class="role-checkbox-wrapper form-check flex-grow-1">
                        <input class="form-check-input fs-5 mt-1" type="checkbox" name="blocked_roles[]" value="support" id="blockSupport" <?= in_array('support', $blockedRoles) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold ms-2 pt-1" style="cursor: pointer;" for="blockSupport">Support Agents</label>
                    </div>

                    <div class="role-checkbox-wrapper form-check flex-grow-1">
                        <input class="form-check-input fs-5 mt-1" type="checkbox" name="blocked_roles[]" value="country_agent" id="blockAgent" <?= in_array('country_agent', $blockedRoles) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold ms-2 pt-1" style="cursor: pointer;" for="blockAgent">Country Agents</label>
                    </div>
                </div>
                
                <div class="alert alert-warning mt-4 mb-0 border-0 rounded-3 d-flex align-items-center">
                    <i class="bi bi-shield-fill-check fs-4 me-3"></i>
                    <div>
                        <strong class="d-block">Super Admins are Immune</strong>
                        <small>Your account will never be locked out by this setting.</small>
                    </div>
                </div>
            </div>
        </div>
        
        <hr class="my-5 opacity-25">
        
        <div class="text-end">
            <button type="submit" class="btn btn-brand shadow-sm" id="saveBtn">
                <span id="btnText"><i class="bi bi-save2 me-2"></i> Save Configuration</span>
                <span id="btnSpinner" class="spinner-border spinner-border-sm d-none"></span>
            </button>
        </div>
    </form>
</div>

<script>
    document.getElementById('maint').addEventListener('change', function() {
        const rolesDiv = document.getElementById('rolesSelection');
        if (this.checked) {
            rolesDiv.style.display = 'block';
            const checkboxes = document.querySelectorAll('input[name="blocked_roles[]"]:checked');
            if(checkboxes.length === 0) document.getElementById('blockUser').checked = true;
        } else {
            rolesDiv.style.display = 'none';
        }
    });

    document.getElementById('promoActive').addEventListener('change', function() {
        const promoDiv = document.getElementById('promoSettings');
        if (this.checked) {
            promoDiv.style.display = 'block';
        } else {
            promoDiv.style.display = 'none';
        }
    });

    function handleMaintenanceWarning() {
        const maintSwitch = document.getElementById('maint');
        const initialState = maintSwitch.getAttribute('data-initial') === '1';
        const currentState = maintSwitch.checked;

        if (currentState !== initialState) {
            let msg = currentState 
                ? "WARNING: You are turning Maintenance Mode ON. This will immediately email all selected roles to notify them they are locked out. Proceed?"
                : "You are turning Maintenance Mode OFF. This will email all previously locked users to notify them the site is back online. Proceed?";
            
            if (!confirm(msg)) {
                return false; 
            }
        }
        
        document.getElementById('btnText').classList.add('d-none');
        document.getElementById('btnSpinner').classList.remove('d-none');
        document.getElementById('saveBtn').style.pointerEvents = 'none';
        return true;
    }
</script>

<?php require_once __DIR__ . "/includes/footer.php"; ?>