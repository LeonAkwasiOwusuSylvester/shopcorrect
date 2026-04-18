<?php
// 1. Initialize Session & DB
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db_path = __DIR__ . "/../../app/config/db.php";
if (file_exists($db_path)) {
    require_once $db_path;
} else {
    die("Error: Database configuration not found.");
}

require_once __DIR__ . "/../../app/helpers/currency.php"; // Added currency helper for header compatibility

// 2. Security Check
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id || (isset($_SESSION['role']) && $_SESSION['role'] !== 'vendor')) {
    header("Location: ../login.php");
    exit;
}

$message = "";
$error   = "";

// 3. Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name         = trim($_POST['name']);
    $shop_name    = trim($_POST['shop_name']);
    $phone        = trim($_POST['phone']);
    $address      = trim($_POST['address']);

    if (empty($name) || empty($shop_name)) {
        $error = "Both Merchant Name and Shop Name are required.";
    } else {
        try {
            $pdo->beginTransaction();

            // A. Update User Table
            $stmtUser = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
            $stmtUser->execute([$name, $user_id]);

            // B. Update Vendors Table
            $check = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
            $check->execute([$user_id]);
            
            if ($check->fetch()) {
                $stmtVendor = $pdo->prepare("
                    UPDATE vendors 
                    SET shop_name = ?, business_phone = ?, business_address = ? 
                    WHERE user_id = ?
                ");
                $stmtVendor->execute([$shop_name, $phone, $address, $user_id]);
            } else {
                $stmtVendor = $pdo->prepare("
                    INSERT INTO vendors (user_id, shop_name, business_phone, business_address, status) 
                    VALUES (?, ?, ?, ?, 'pending')
                ");
                $stmtVendor->execute([$user_id, $shop_name, $phone, $address]);
            }

            $pdo->commit();
            
            $_SESSION['name'] = $name; 
            $_SESSION['vendor_name'] = $shop_name; 
            
            $message = "Business profile updated successfully!";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "System Error: " . $e->getMessage();
        }
    }
}

// 4. Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'];
    $new_pass     = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if ($new_pass !== $confirm_pass) {
        $error = "New passwords do not match.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $db_pass = $user['password_hash'];
                $verified = false;

                if (password_verify($current_pass, $db_pass)) {
                    $verified = true;
                } elseif ($current_pass === $db_pass) {
                    $verified = true;
                }

                if ($verified) {
                    $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                    $upd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $upd->execute([$new_hash, $user_id]);
                    $message = "Security credentials updated successfully.";
                } else {
                    $error = "Current password is incorrect.";
                }
            } else {
                $error = "User account not found.";
            }
        } catch (PDOException $e) {
            $error = "System Error: " . $e->getMessage();
        }
    }
}

// 5. Fetch Data
$stmt = $pdo->prepare("
    SELECT 
        u.name, 
        u.email, 
        v.shop_name, 
        v.business_phone as phone, 
        v.business_address as address
    FROM users u
    LEFT JOIN vendors v ON u.id = v.user_id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

$shopName = $vendor['shop_name'] ?? $vendor['name']; // Fallback for the header variable

// --------------------------------------------------
// 6. Include Header
// --------------------------------------------------
require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* PAGE SPECIFIC STYLES */
    .settings-card { background: white; border-radius: 16px; border: 1px solid var(--card-border); overflow: hidden; margin-bottom: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
    .settings-header { padding: 1.5rem 2rem; border-bottom: 1px solid var(--card-border); display: flex; align-items: center; gap: 1rem; background: #F8FAFC; }
    .header-icon { width: 40px; height: 40px; background: white; border: 1px solid var(--card-border); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--primary-accent); font-size: 1.2rem; }
    .settings-body { padding: 2rem; }
    .form-label { font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 0.5rem; }
    .form-control { padding: 0.7rem 1rem; border-radius: 8px; border-color: #CBD5E1; font-size: 0.95rem; }
    .form-control:focus { border-color: var(--primary-accent); box-shadow: 0 0 0 4px rgba(11, 36, 71, 0.1); }
    .form-text { font-size: 0.75rem; color: #94A3B8; margin-top: 0.3rem; }
    
    .btn-save { background: var(--primary-accent); color: white; border: none; padding: 0.7rem 2rem; border-radius: 8px; font-weight: 600; transition: 0.2s; }
    .btn-save:hover { background: #1E293B; transform: translateY(-1px); }
    
    .input-group-text { background-color: white; border-color: #CBD5E1; cursor: pointer; }
    .input-group-text:hover { background-color: #f8f9fa; }
</style>

<div class="container-fluid px-4 py-4">

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4 justify-content-center">
        <div class="col-lg-10 col-xl-9">
            
            <div class="settings-card">
                <div class="settings-header">
                    <div class="header-icon"><i class="bi bi-shop-window"></i></div>
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Store Profile</h5>
                        <p class="text-muted small mb-0">Manage your business identification and contact details.</p>
                    </div>
                </div>
                <div class="settings-body">
                    <form method="POST">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">Shop / Business Name</label>
                                <input type="text" name="shop_name" class="form-control" value="<?= htmlspecialchars($vendor['shop_name'] ?? '') ?>" placeholder="e.g. ShopCorrect Electronics" required>
                                <div class="form-text">Visible to your customers on invoices.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Merchant Full Name</label>
                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($vendor['name']) ?>" required>
                                <div class="form-text">Account owner's legal name.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Business Email</label>
                                <input type="email" class="form-control bg-light" value="<?= htmlspecialchars($vendor['email']) ?>" readonly disabled>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Contact Phone Number</label>
                                <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($vendor['phone'] ?? '') ?>" placeholder="e.g. 055 000 0000">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Primary Business Address</label>
                                <textarea name="address" class="form-control" rows="3" placeholder="Street name, City, Region..."><?= htmlspecialchars($vendor['address'] ?? '') ?></textarea>
                            </div>

                            <div class="col-12 text-end">
                                <button type="submit" name="update_profile" class="btn-save">
                                    Update Business Profile
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="settings-card">
                <div class="settings-header">
                    <div class="header-icon"><i class="bi bi-shield-lock"></i></div>
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Security & Access</h5>
                        <p class="text-muted small mb-0">Update your credentials to keep your shop secure.</p>
                    </div>
                </div>
                <div class="settings-body">
                    <form method="POST">
                        <div class="row g-4">
                            <div class="col-md-4">
                                <label class="form-label">Current Password</label>
                                <div class="input-group">
                                    <input type="password" name="current_password" class="form-control" placeholder="••••••••" required>
                                    <span class="input-group-text toggle-password">
                                        <i class="bi bi-eye"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">New Password</label>
                                <div class="input-group">
                                    <input type="password" name="new_password" class="form-control" minlength="6" placeholder="Min 6 chars" required>
                                    <span class="input-group-text toggle-password">
                                        <i class="bi bi-eye"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Confirm New Password</label>
                                <div class="input-group">
                                    <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter new password" required>
                                    <span class="input-group-text toggle-password">
                                        <i class="bi bi-eye"></i>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="col-12 text-end">
                                <button type="submit" name="change_password" class="btn btn-outline-dark px-4 py-2 fw-bold" style="border-radius: 8px;">
                                    Change Security Keys
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

</div> 

<script>
    document.querySelectorAll('.toggle-password').forEach(item => {
        item.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
    });
</script>

<?php
// --------------------------------------------------
// 7. Include Footer
// --------------------------------------------------
require_once __DIR__ . '/includes/footer.php';
?>