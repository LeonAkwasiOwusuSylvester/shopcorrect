<?php
require_once __DIR__ . "/../../app/helpers/vendor_guard.php";
require_once __DIR__ . "/../../app/config/db.php";
require_once __DIR__ . "/../../app/helpers/currency.php"; // Included for header compatibility

/* --------------------------------------------------
   1. Fetch Vendor & User Data
   -------------------------------------------------- */
$stmt = $pdo->prepare(
    "SELECT v.id, v.shop_name, u.name, u.email
     FROM vendors v
     JOIN users u ON v.user_id = u.id
     WHERE v.user_id = ?"
);
$stmt->execute([$_SESSION["user_id"]]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    die("Vendor profile not found.");
}

$vendorId = $vendor['id'];
$shopName = $vendor['shop_name']; // For display in Navbar

/* --------------------------------------------------
   2. Handle Form Submission
   -------------------------------------------------- */
$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $newShopName = trim($_POST["shop_name"] ?? "");
    $ownerName = trim($_POST["name"] ?? "");

    if (empty($newShopName) || empty($ownerName)) {
        $error = "Shop name and Owner name are required.";
    } else {
        try {
            $pdo->beginTransaction();

            // Update Vendor Table
            $stmt = $pdo->prepare("UPDATE vendors SET shop_name = ? WHERE id = ?");
            $stmt->execute([$newShopName, $vendor["id"]]);

            // Update Users Table
            $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
            $stmt->execute([$ownerName, $_SESSION["user_id"]]);

            $pdo->commit();
            $success = "Settings updated successfully.";
            
            // Refresh displayed data
            $vendor["shop_name"] = $newShopName;
            $vendor["name"] = $ownerName;
            $shopName = $newShopName; // Update navbar var

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "An error occurred while saving settings.";
        }
    }
}

// --------------------------------------------------
// 3. Include Header
// --------------------------------------------------
require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* Settings Card Specific Styles */
    .card-settings { background: white; border: 1px solid var(--card-border); border-radius: 12px; margin-bottom: 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .card-header-settings { padding: 1.2rem 1.5rem; border-bottom: 1px solid var(--card-border); background: #F8FAFC; border-radius: 12px 12px 0 0; }
    
    .form-label { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #64748B; letter-spacing: 0.5px; margin-bottom: 0.5rem; }
    .form-control { padding: 0.7rem 1rem; font-size: 0.95rem; border-color: #CBD5E1; border-radius: 8px; }
    .form-control:focus { border-color: var(--primary-accent); box-shadow: 0 0 0 3px rgba(11, 36, 71, 0.1); }
    
    .btn-brand { background: var(--primary-accent); color: white; border: none; padding: 0.7rem 1.5rem; border-radius: 8px; font-weight: 600; transition: 0.2s; }
    .btn-brand:hover { background: #1e3a8a; transform: translateY(-1px); }
</style>

<div class="container-fluid px-4 py-4">

    <?php if ($success): ?>
        <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success d-flex align-items-center mb-4 rounded-3 shadow-sm">
            <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger d-flex align-items-center mb-4 rounded-3 shadow-sm">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="row g-4">
            
            <div class="col-lg-8">
                <div class="card-settings">
                    <div class="card-header-settings">
                        <h6 class="fw-bold text-dark mb-0">General Information</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-4">
                            <label class="form-label">Shop Name</label>
                            <input type="text" name="shop_name" class="form-control form-control-lg" value="<?= htmlspecialchars($vendor["shop_name"]) ?>" required>
                            <div class="form-text text-muted mt-2">This is the public name customers will see on your product pages.</div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">Owner Full Name</label>
                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($vendor["name"]) ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-lock-fill"></i></span>
                                    <input type="email" class="form-control bg-light text-muted border-start-0 ps-0" value="<?= htmlspecialchars($vendor["email"]) ?>" readonly>
                                </div>
                                <div class="form-text mt-1" style="font-size: 0.75rem;">Contact support to update your email address.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-3">
                    <a href="index.php" class="btn btn-light border text-secondary px-4 fw-medium">Cancel</a>
                    <button type="submit" class="btn btn-brand px-4 shadow-sm">
                        <i class="bi bi-save me-2"></i>Save Changes
                    </button>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card-settings">
                    <div class="card-header-settings">
                        <h6 class="fw-bold text-dark mb-0">Account Status</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-success bg-opacity-10 text-success rounded-circle p-2 me-3" style="width: 42px; height: 42px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-shield-check fs-5"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0">Verified Vendor</h6>
                                <small class="text-secondary">Your shop is active.</small>
                            </div>
                        </div>
                        <hr class="opacity-10 my-3">
                        <a href="vprofile.php" class="text-decoration-none small fw-bold text-primary">
                            View Public Shop Profile <i class="bi bi-box-arrow-up-right ms-1"></i>
                        </a>
                    </div>
                </div>

                <div class="card-settings">
                    <div class="card-header-settings">
                        <h6 class="fw-bold text-dark mb-0">Security</h6>
                    </div>
                    <div class="card-body p-4">
                        <p class="small text-secondary mb-3">
                            To ensure your account stays secure, we recommend updating your password every 90 days.
                        </p>
                        <a href="../../public/forgot-password.php" class="btn btn-outline-dark w-100 btn-sm fw-medium">
                            Change Password
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </form>

</div> 

<?php
// --------------------------------------------------
// 4. Include Footer
// --------------------------------------------------
require_once __DIR__ . '/includes/footer.php';
?>