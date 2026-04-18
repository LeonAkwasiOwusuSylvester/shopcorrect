<?php
require_once __DIR__ . "/../../app/helpers/vendor_guard.php";
require_once __DIR__ . "/../../app/config/db.php";
require_once __DIR__ . "/../../app/helpers/maintenance.php";
require_once __DIR__ . "/../../app/helpers/currency.php"; // Currency helper

// --------------------------------------------------
// 1. Get Vendor Details & Grace Period
// --------------------------------------------------
$stmt = $pdo->prepare("SELECT id, shop_name, commission_free_until FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION["user_id"]]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    echo "<script>window.location.href='../create-shop.php';</script>";
    exit;
}

$vendorId = $vendor['id'];
$shopName = $vendor['shop_name'];

// ✅ CHECK ZERO-COMMISSION STATUS
$isGracePeriodActive = false;
$gracePeriodEnd = '';
$gracePeriodMs = 0;

if (!empty($vendor['commission_free_until']) && strtotime($vendor['commission_free_until']) > time()) {
    $isGracePeriodActive = true;
    $gracePeriodEnd = date('F jS, Y', strtotime($vendor['commission_free_until']));
    $gracePeriodMs = strtotime($vendor['commission_free_until']) * 1000; // Convert to JS milliseconds
}

// --------------------------------------------------
// 2. Statistics Logic
// --------------------------------------------------
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(oi.price * oi.quantity), 0)
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.vendor_id = ? AND o.status = 'delivered'
");
$stmt->execute([$vendorId]);
$grossProductSales = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(o.shipping_cost), 0)
    FROM orders o
    JOIN (SELECT DISTINCT order_id FROM order_items WHERE vendor_id = ?) vo ON o.id = vo.order_id
    WHERE o.status = 'delivered'
");
$stmt->execute([$vendorId]);
$totalShipping = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(oi.commission_fee), 0)
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.vendor_id = ? AND o.status = 'delivered'
");
$stmt->execute([$vendorId]);
$commissionAmount = (float) $stmt->fetchColumn();

$netEarnings = ($grossProductSales - $commissionAmount) + $totalShipping;

$stmtSettings = $pdo->query("SELECT commission_percent FROM settings LIMIT 1");
$globalCommission = (int) $stmtSettings->fetchColumn();
$displayPercentage = ($grossProductSales > 0) ? round(($commissionAmount / $grossProductSales) * 100) : $globalCommission;

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT oi.order_id) FROM order_items oi WHERE oi.vendor_id = ?");
$stmt->execute([$vendorId]);
$totalOrders = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(oi.quantity), 0)
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.vendor_id = ? AND o.status = 'delivered'
");
$stmt->execute([$vendorId]);
$totalUnitsSold = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = ? AND status = 'active' AND is_deleted = 0");
$stmt->execute([$vendorId]);
$totalProducts = $stmt->fetchColumn();

// --------------------------------------------------
// 3. Include Header
// --------------------------------------------------
require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* Dashboard Specific Styles */
    .metric-card {
        background-color: #ffffff;
        border: 1px solid var(--card-border, #E2E8F0);
        border-radius: 16px;
        padding: 1.5rem;
        height: 100%;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        display: flex;
        flex-direction: column;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .metric-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }

    .metric-label {
        font-size: 0.85rem;
        font-weight: 700;
        color: #64748B;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .metric-value {
        font-size: 1.75rem;
        font-weight: 800;
        color: #0F172A;
        margin-top: 0.25rem;
    }

    .icon-square {
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    .finance-list {
        margin-top: auto; 
        padding-top: 1.25rem;
        border-top: 1px dashed #E2E8F0;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .finance-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.9rem;
        color: #475569;
    }
    
    .finance-row strong {
        font-weight: 700;
    }

    .hover-shadow {
        transition: all 0.3s ease;
        border-radius: 12px;
    }
    .hover-shadow:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important;
        border-color: var(--primary-accent, #0B2447) !important;
    }
    
    .action-icon-box {
        width: 42px;
        height: 42px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .grace-countdown-box {
        background: rgba(255,255,255,0.9);
        border-radius: 12px;
        padding: 10px 15px;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        font-variant-numeric: tabular-nums;
    }
    .grace-time-unit {
        display: flex;
        flex-direction: column;
        align-items: center;
        line-height: 1;
    }
    .grace-time-unit span { font-size: 1.2rem; font-weight: 800; color: #166534; }
    .grace-time-unit small { font-size: 0.6rem; font-weight: 700; text-transform: uppercase; color: #166534; opacity: 0.7; margin-top: 3px; }
    .grace-colon { font-size: 1.2rem; font-weight: 800; color: #166534; opacity: 0.5; padding-bottom: 8px; }
</style>

<div class="container-fluid px-4 py-4">
    
    <div class="row mb-4">
        <div class="col-12">
            <div class="bg-primary bg-opacity-10 p-4 rounded-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 border border-primary border-opacity-10">
                <div>
                    <h3 class="fw-bold text-primary mb-1">Welcome back, <?= htmlspecialchars($shopName) ?>!</h3>
                    <p class="text-secondary mb-0">Here's what's happening in your store today.</p>
                </div>
                <a href="add-product.php" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm fw-bold">
                    <i class="bi bi-plus-lg me-1"></i> Add Product
                </a>
            </div>
        </div>
    </div>

    <?php if ($isGracePeriodActive): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="bg-success bg-opacity-10 border border-success border-opacity-25 rounded-4 p-4 d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-4 shadow-sm position-relative overflow-hidden">
                <div class="position-absolute end-0 top-0 opacity-10" style="font-size: 8rem; line-height: 0; transform: translate(20%, -20%); pointer-events: none;">🎁</div>
                
                <div class="d-flex align-items-center gap-3 position-relative z-1">
                    <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 shadow-sm" style="width: 60px; height: 60px; font-size: 1.5rem;">
                        <i class="bi bi-stars"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold text-success mb-1">Zero-Commission Period Active!</h4>
                        <p class="text-success text-opacity-75 mb-0 fw-medium" style="font-size: 0.95rem;">
                            You are keeping <strong>100% of your profits</strong> until <strong><?= $gracePeriodEnd ?></strong>. Add more inventory now!
                        </p>
                    </div>
                </div>

                <div class="grace-countdown-box position-relative z-1 border border-success border-opacity-25 flex-shrink-0">
                    <div class="grace-time-unit"><span id="g-days">00</span><small>Days</small></div>
                    <div class="grace-colon">:</div>
                    <div class="grace-time-unit"><span id="g-hours">00</span><small>Hrs</small></div>
                    <div class="grace-colon">:</div>
                    <div class="grace-time-unit"><span id="g-mins">00</span><small>Min</small></div>
                    <div class="grace-colon">:</div>
                    <div class="grace-time-unit"><span id="g-secs">00</span><small>Sec</small></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ✅ JS to animate the Vendor Countdown
        const graceEndDate = <?= $gracePeriodMs ?>;
        
        const graceTimer = setInterval(() => {
            const distance = graceEndDate - new Date().getTime();

            if (distance < 0) {
                clearInterval(graceTimer);
                document.querySelector('.grace-countdown-box').innerHTML = "<span class='text-danger fw-bold'>Offer Expired</span>";
                return;
            }

            document.getElementById('g-days').textContent  = String(Math.floor(distance / (1000 * 60 * 60 * 24))).padStart(2, '0');
            document.getElementById('g-hours').textContent = String(Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))).padStart(2, '0');
            document.getElementById('g-mins').textContent  = String(Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60))).padStart(2, '0');
            document.getElementById('g-secs').textContent  = String(Math.floor((distance % (1000 * 60)) / 1000)).padStart(2, '0');
        }, 1000);
    </script>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        
        <div class="col-lg-4">
            <div class="metric-card">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="metric-label">Net Earnings</div>
                        <div class="metric-value notranslate"><?= formatPrice($netEarnings) ?></div>
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill mt-2 px-3 py-2 fw-semibold border border-success border-opacity-25">
                            <i class="bi bi-check-circle-fill me-1"></i> Paid
                        </span>
                    </div>
                    <div class="icon-square bg-success bg-opacity-10 text-success">
                        <i class="bi bi-wallet2"></i>
                    </div>
                </div>
                
                <div class="finance-list">
                    <div class="finance-row">
                        <span>Gross Sales</span>
                        <strong class="text-dark notranslate"><?= formatPrice($grossProductSales) ?></strong>
                    </div>
                    <div class="finance-row">
                        <span>Commission (<?= $isGracePeriodActive ? '0' : $displayPercentage ?>%)</span>
                        <strong class="text-danger notranslate">-<?= formatPrice($commissionAmount) ?></strong>
                    </div>
                    <div class="finance-row">
                        <span>Shipping Credits</span>
                        <strong class="text-success notranslate">+<?= formatPrice($totalShipping) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-4">
            <div class="metric-card">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <div class="metric-label">Total Orders</div>
                        <div class="metric-value mt-1"><?= number_format($totalOrders) ?></div>
                    </div>
                    <div class="icon-square bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-bag-check-fill"></i>
                    </div>
                </div>
                <div class="mt-auto pt-3 border-top border-light">
                    <p class="text-muted small fw-medium mb-0 d-flex align-items-center">
                        <i class="bi bi-box-seam me-2 text-primary fs-6"></i> 
                        <span><?= number_format($totalUnitsSold) ?> items sold in total</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-4">
            <div class="metric-card">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <div class="metric-label">Active Products</div>
                        <div class="metric-value mt-1"><?= number_format($totalProducts) ?></div>
                    </div>
                    <div class="icon-square bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-grid-fill"></i>
                    </div>
                </div>
                <div class="mt-auto pt-3 border-top border-light">
                    <p class="text-muted small fw-medium mb-0 d-flex align-items-center">
                        <i class="bi bi-eye me-2 text-warning fs-6"></i> 
                        <span>Currently live on your store</span>
                    </p>
                </div>
            </div>
        </div>
        
    </div>
    
    <h5 class="fw-bold text-dark mb-3 mt-2">Quick Actions</h5>
    <div class="row g-3">
        <div class="col-6 col-md-3">
            <a href="products.php" class="card text-decoration-none h-100 border-0 shadow-sm p-3 hover-shadow">
                <div class="d-flex align-items-center gap-3">
                    <div class="action-icon-box bg-primary bg-opacity-10 text-primary rounded-circle">
                        <i class="bi bi-tags-fill fs-5"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Inventory</h6>
                        <small class="text-muted">Manage items</small>
                    </div>
                </div>
            </a>
        </div>
        
        <div class="col-6 col-md-3">
            <a href="orders.php" class="card text-decoration-none h-100 border-0 shadow-sm p-3 hover-shadow">
                <div class="d-flex align-items-center gap-3">
                    <div class="action-icon-box bg-success bg-opacity-10 text-success rounded-circle">
                        <i class="bi bi-truck fs-5"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Orders</h6>
                        <small class="text-muted">Ship items</small>
                    </div>
                </div>
            </a>
        </div>
        
        <div class="col-6 col-md-3">
            <a href="earnings.php" class="card text-decoration-none h-100 border-0 shadow-sm p-3 hover-shadow">
                <div class="d-flex align-items-center gap-3">
                    <div class="action-icon-box bg-info bg-opacity-10 text-info rounded-circle">
                        <i class="bi bi-pie-chart-fill fs-5"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Reports</h6>
                        <small class="text-muted">View analytics</small>
                    </div>
                </div>
            </a>
        </div>
        
        <div class="col-6 col-md-3">
            <a href="settings.php" class="card text-decoration-none h-100 border-0 shadow-sm p-3 hover-shadow">
                <div class="d-flex align-items-center gap-3">
                    <div class="action-icon-box bg-secondary bg-opacity-10 text-secondary rounded-circle">
                        <i class="bi bi-sliders fs-5"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Settings</h6>
                        <small class="text-muted">Shop profile</small>
                    </div>
                </div>
            </a>
        </div>
    </div>

</div> 

<?php
// --------------------------------------------------
// 4. Include Footer
// --------------------------------------------------
require_once __DIR__ . '/includes/footer.php';
?>