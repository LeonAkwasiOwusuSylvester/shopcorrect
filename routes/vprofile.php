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

// 2. Security Check
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id || (isset($_SESSION['role']) && $_SESSION['role'] !== 'vendor')) {
    header("Location: ../login.php");
    exit;
}

$message = "";
$error   = "";

// 3. Handle Profile Update (Fixed Logic)
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

            // A. Update User Table (Personal Name)
            $stmtUser = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
            $stmtUser->execute([$name, $user_id]);

            // B. Update Vendors Table (Shop Details)
            // Check if vendor record exists
            $check = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
            $check->execute([$user_id]);
            
            if ($check->fetch()) {
                // Update
                $stmtVendor = $pdo->prepare("
                    UPDATE vendors 
                    SET shop_name = ?, business_phone = ?, business_address = ? 
                    WHERE user_id = ?
                ");
                $stmtVendor->execute([$shop_name, $phone, $address, $user_id]);
            } else {
                // Insert if missing (fail-safe)
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

// 4. Handle Password Change (Fixed Column Check)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'];
    $new_pass     = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if ($new_pass !== $confirm_pass) {
        $error = "New passwords do not match.";
    } else {
        // Query users table for password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($current_pass, $user['password'])) {
            $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upd->execute([$new_hash, $user_id]);
            $message = "Security credentials updated.";
        } else {
            $error = "Current password is incorrect.";
        }
    }
}

// 5. Fetch Data (Fixed JOIN to get correct columns)
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
    // If no user found, force logout
    session_destroy();
    header("Location: ../login.php");
    exit;
}

$shopDisplayName = $vendor['shop_name'] ?? $vendor['name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Settings | ShopCorrect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* [SAME CSS AS BEFORE - KEPT 100% VISUALS] */
        :root {
            --sidebar-bg: #0B2447;
            --sidebar-text: #8FA5B8;
            --sidebar-hover: #FFFFFF;
            --sidebar-active-text: #FFFFFF;
            --logout-color: #FF6B6B;
            --main-bg: #F5F7FA;
            --text-dark: #1E293B;
            --card-border: #E2E8F0;
            --primary-accent: #0B2447;
        }
        body { background-color: var(--main-bg); font-family: 'Plus Jakarta Sans', sans-serif; color: var(--text-dark); overflow-x: hidden; }
        #wrapper { display: flex; width: 100%; height: 100vh; overflow: hidden; }
        #sidebar-wrapper { min-height: 100vh; width: 260px; margin-left: -260px; background-color: var(--sidebar-bg); display: flex; flex-direction: column; transition: margin 0.25s ease-out; z-index: 1000; padding: 1.5rem 1rem; overflow-y: auto; }
        .sidebar-brand { display: flex; align-items: center; gap: 12px; margin-bottom: 2rem; padding-left: 0.5rem; flex-shrink: 0; }
        .brand-logo-box { width: 45px; height: 45px; border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: rgba(255,255,255,0.05); }
        .logo-img { width: 100%; height: 100%; object-fit: contain; }
        .brand-text { color: #fff; font-size: 1.35rem; font-weight: 700; letter-spacing: -0.5px; }
        .sidebar-nav { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.25rem; flex-grow: 1; }
        .nav-link { display: flex; align-items: center; gap: 16px; padding: 12px 16px; color: var(--sidebar-text); text-decoration: none; border-radius: 8px; font-weight: 500; font-size: 0.95rem; transition: all 0.2s; position: relative; }
        .nav-link:hover { color: var(--sidebar-hover); background-color: rgba(255, 255, 255, 0.05); }
        .nav-link.active { color: var(--sidebar-active-text); font-weight: 600; background-color: transparent; }
        .nav-link.active::after { content: ''; position: absolute; right: -16px; top: 50%; transform: translateY(-50%); height: 24px; width: 4px; background-color: #3B82F6; border-top-left-radius: 4px; border-bottom-left-radius: 4px; }
        .nav-label { font-size: 0.75rem; text-transform: uppercase; color: #4B5563; font-weight: 700; margin-top: 1.5rem; margin-bottom: 0.5rem; padding-left: 1rem; }
        .logout-section { margin-top: auto; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 1rem; padding-bottom: 1rem; flex-shrink: 0; }
        .nav-link.logout-link { color: var(--logout-color); }
        .nav-link.logout-link:hover { background-color: rgba(255, 107, 107, 0.1); color: #ff8787; }
        #page-content-wrapper { width: 100%; display: flex; flex-direction: column; overflow-y: auto; }
        .top-navbar { background: white; padding: 1.2rem 2.5rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--card-border); }
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
        .admin-footer { margin-top: auto; padding: 1.5rem 2.5rem; background: white; font-size: 0.875rem; color: #64748B; }
        @media (min-width: 768px) { #sidebar-wrapper { margin-left: 0; } #wrapper.toggled #sidebar-wrapper { margin-left: -260px; } }
    </style>
</head>
<body>

<div class="d-flex" id="wrapper">

    <div id="sidebar-wrapper">
        <div class="sidebar-brand">
            <div class="brand-logo-box">
                <img src="../assets/images/shopcorrect-logo.png" alt="SC Logo" class="logo-img">
            </div>
            <div class="brand-text">ShopCorrect</div>
        </div>
        
        <nav class="sidebar-nav">
            <a href="index.php" class="nav-link">
                <i class="bi bi-grid-fill"></i> <span>Dashboard</span>
            </a>
            <a href="products.php" class="nav-link">
                <i class="bi bi-box-seam"></i> <span>My Inventory</span>
            </a>
            <a href="orders.php" class="nav-link">
                <i class="bi bi-cart3"></i> <span>Orders</span>
            </a>
            <a href="notifications.php" class="nav-link">
                <i class="bi bi-bell"></i> <span>Messages</span>
            </a>

            <div class="nav-label">Finance & Shop</div>

            <a href="earnings.php" class="nav-link">
                <i class="bi bi-wallet2"></i> <span>Earnings</span>
            </a>
            <a href="payouts.php" class="nav-link">
                <i class="bi bi-cash-stack"></i> <span>Payouts</span>
            </a>
            
            <a href="vprofile.php" class="nav-link active">
                <i class="bi bi-shop"></i> <span>My Shop</span>
            </a>
        </nav>

        <div class="logout-section">
            <a href="settings.php" class="nav-link">
                <i class="bi bi-gear"></i> <span>Settings</span>
            </a>
            <a href="../../public/logout.php" class="nav-link logout-link">
                <i class="bi bi-box-arrow-right"></i> <span>Log Out</span>
            </a>
        </div>
    </div>

    <div id="page-content-wrapper">

        <nav class="top-navbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-outline-secondary d-md-none" id="menu-toggle"><i class="bi bi-list"></i></button>
                <h5 class="m-0 fw-bold text-dark">Shop Settings</h5>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="d-none d-md-block text-end">
                    <div class="fw-bold small text-dark"><?= htmlspecialchars($shopDisplayName) ?></div>
                    <div class="text-muted small" style="font-size: 0.75rem;">Vendor Account</div>
                </div>
                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                     <?= strtoupper(substr($shopDisplayName, 0, 1)) ?>
                </div>
            </div>
        </nav>

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
                                        <input type="password" name="current_password" class="form-control" placeholder="••••••••" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">New Password</label>
                                        <input type="password" name="new_password" class="form-control" minlength="6" placeholder="Min 6 chars" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Confirm New Password</label>
                                        <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter new password" required>
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

        </div> <footer class="admin-footer d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <div>
                &copy; <?= date('Y') ?> <strong>ShopCorrect</strong>. All rights reserved.
            </div>
            <div class="d-flex gap-3">
                <a href="#" class="text-decoration-none text-muted">Privacy Policy</a>
                <a href="#" class="text-decoration-none text-muted">Terms of Service</a>
            </div>
        </footer>

    </div> </div> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    var el = document.getElementById("wrapper");
    var toggleButton = document.getElementById("menu-toggle");
    toggleButton.onclick = function () { el.classList.toggle("toggled"); };
</script>

</body>
</html>