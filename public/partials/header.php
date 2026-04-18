<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/../../app/config/db.php";

// 1. Get IDs and Name
$userId     = $_SESSION['user_id'] ?? null;
$vendorName = $_SESSION['vendor_name'] ?? $_SESSION['name'] ?? 'Vendor';

// 2. INITIAL NOTIFICATION COUNT LOGIC (For Page Load)
$totalAlerts = 0;

if ($userId) {
    // A. Get actual Vendor ID for this user
    $vStmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
    $vStmt->execute([$userId]);
    $vRow = $vStmt->fetch();
    $realVendorId = $vRow['id'] ?? 0;

    if ($realVendorId) {
        // B. NEW LOGIC: Count Unread Notifications from the Table
        // We only count items that are NOT read and NOT deleted
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) FROM notifications 
            WHERE vendor_id = ? AND is_read = 0 AND is_deleted = 0
        ");
        $countStmt->execute([$realVendorId]);
        $totalAlerts = (int)$countStmt->fetchColumn();
    }
}

// Helper for active state
function isVendorActive($path) {
    return (strpos($_SERVER['REQUEST_URI'], $path) !== false) ? 'active' : '';
}
?>

<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root { 
            --sc-vendor-bg: #0f172a; 
            --sc-gold: #ffc107; 
            --sc-hover: rgba(255, 255, 255, 0.1); 
        }
        body { font-family: 'Inter', sans-serif; }
        
        .vendor-nav { 
            background: linear-gradient(90deg, var(--sc-vendor-bg) 0%, #1e293b 100%); 
            border-bottom: 1px solid rgba(255,255,255,0.08); 
            padding: 0.6rem 0; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        
        .nav-main-link { 
            color: rgba(255,255,255,0.7) !important; 
            font-weight: 500; font-size: 0.9rem; 
            transition: all 0.2s ease; 
            padding: 0.6rem 1rem !important;
            border-radius: 8px;
            display: flex; align-items: center; gap: 8px;
        }
        .nav-main-link:hover, .nav-main-link.active { color: #fff !important; background: rgba(255, 255, 255, 0.15); }

        .nav-action {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            color: rgba(255, 255, 255, 0.85); text-decoration: none;
            padding: 6px 12px; border-radius: 8px; transition: all 0.2s;
            min-width: 64px;
        }
        .nav-action:hover { background: var(--sc-hover); color: var(--sc-gold); transform: translateY(-2px); }
        
        .nav-action i { font-size: 1.25rem; line-height: 1; margin-bottom: 4px; }
        .nav-action span { font-size: 0.7rem; font-weight: 600; letter-spacing: 0.3px; }

        /* 🔔 LIVE BADGE STYLE */
        .icon-wrapper { position: relative; display: inline-block; }
        .alert-badge { 
            position: absolute; top: -4px; right: -8px; 
            background: #ef4444; color: white; 
            font-size: 0.65rem; font-weight: 800;
            height: 18px; min-width: 18px; padding: 0 5px;
            border-radius: 10px; display: flex; 
            align-items: center; justify-content: center;
            border: 2px solid #1e293b;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .user-pill {
            background: rgba(255, 255, 255, 0.05); border-radius: 50px; 
            padding: 4px 14px 4px 4px; border: 1px solid rgba(255, 255, 255, 0.15); 
            display: flex; align-items: center; gap: 10px;
            color: white; text-decoration: none; transition: 0.3s;
        }
    </style>
</head>

<nav class="navbar navbar-expand-lg navbar-dark vendor-nav sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2 me-5" href="/shopcorrect/public/vendor/index.php">
            <img src="/assets/images/shopcorrect-logo.png" height="34" alt="SC">
            <div class="d-flex flex-column" style="line-height: 1;">
                <span class="d-none d-sm-inline text-white">ShopCorrect</span>
                <span style="font-size: 0.6rem; font-weight: 600; opacity: 0.6; letter-spacing: 1.5px; text-transform: uppercase; color: var(--sc-gold);">Seller Center</span>
            </div>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#vendorNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="vendorNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-main-link <?= isVendorActive('index.php') ?>" href="/shopcorrect/public/vendor/index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-main-link <?= isVendorActive('products.php') ?>" href="/shopcorrect/public/vendor/products.php"><i class="bi bi-box-seam"></i> Products</a></li>
                <li class="nav-item"><a class="nav-main-link <?= isVendorActive('orders.php') ?>" href="/shopcorrect/public/vendor/orders.php"><i class="bi bi-receipt"></i> Orders</a></li>
                <li class="nav-item"><a class="nav-main-link <?= isVendorActive('earnings.php') ?>" href="/shopcorrect/public/vendor/earnings.php"><i class="bi bi-wallet2"></i> Earnings</a></li>
                <li class="nav-item"><a class="nav-main-link <?= isVendorActive('payouts.php') ?>" href="/shopcorrect/public/vendor/payouts.php"><i class="bi bi-cash-stack"></i> Payouts</a></li>
            </ul>

            <div class="d-flex align-items-center justify-content-lg-end gap-1 flex-wrap mt-3 mt-lg-0">
                <a href="/shopcorrect/public/vendor/notifications.php" class="nav-action">
                     <div class="icon-wrapper">
                        <i class="bi bi-bell"></i>
                        <span class="alert-badge <?= ($totalAlerts > 0) ? '' : 'd-none' ?>">
                            <?= $totalAlerts > 99 ? '99+' : $totalAlerts ?>
                        </span>
                    </div>
                    <span>Alerts</span>
                </a>

                <a href="/shopcorrect/public/vendor/vprofile.php" class="nav-action">
                    <i class="bi bi-shop-window"></i>
                    <span>My Shop</span>
                </a>

                <div class="dropdown">
                    <a href="#" class="user-pill dropdown-toggle" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="avatar-box" style="width:34px; height:34px; border-radius:50%; background:white; color:#0f172a; display:flex; align-items:center; justify-content:center; font-weight:800;">
                            <?= strtoupper(substr($vendorName, 0, 1)) ?>
                        </div>
                        <div class="d-flex flex-column lh-1 d-none d-md-block text-start">
                            <span style="font-size: 0.65rem; opacity: 0.7; text-transform: uppercase; color: white;">Vendor</span>
                            <span style="font-size: 0.85rem; font-weight: 600; color: white;"><?= htmlspecialchars(explode(' ', $vendorName)[0]) ?></span>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                        <li><a class="dropdown-item" href="/shopcorrect/public/vendor/vprofile.php"><i class="bi bi-person-gear me-2"></i> Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-primary" href="/shopcorrect/public/index.php"><i class="bi bi-cart3 me-2"></i> Switch to Buyer</a></li>
                        <li><a class="dropdown-item text-danger" href="/shopcorrect/public/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Log Out</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
    <div id="liveToast" class="toast align-items-center text-white bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi bi-bell-fill me-2 text-warning"></i>
                <span id="toastMessage">New shop activity detected!</span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// --- LIVE NOTIFICATION AJAX LOGIC ---
let previousCount = <?= $totalAlerts ?>; // Set initial count from PHP

function updateNotificationBadge() {
    // Note: We use the `no-cache` header in the PHP API, but adding a timestamp here ensures the browser never serves a cached version
    const timestamp = new Date().getTime();
    fetch('/shopcorrect/public/vendor/api-notifications.php?_=' + timestamp)
        .then(response => response.json())
        .then(data => {
            const badge = document.querySelector('.alert-badge');
            const count = parseInt(data.total);

            // 1. Update the Badge UI
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }

            // 2. Check for NEW Alerts (Increase in count) to trigger Toast
            // We only show toast if the count INCREASES
            if (previousCount !== -1 && count > previousCount) {
                const toastEl = document.getElementById('liveToast');
                const toast = new bootstrap.Toast(toastEl);
                toast.show();
            }

            previousCount = count; // Save current count for next cycle
        })
        .catch(err => console.warn('Notification Sync Error:', err));
}

// Refresh every 10 seconds (faster updates for better UX)
setInterval(updateNotificationBadge, 10000);
</script>