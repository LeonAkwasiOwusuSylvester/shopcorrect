<?php
// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . "/../../app/config/db.php";

// Strict Role Check - Allows supadmin, country_agent, and support
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['supadmin', 'country_agent', 'support'])) {
    header("Location: ../login.php");
    exit;
}

$userRole = $_SESSION['role'];
$managedCountry = $_SESSION['managed_country'] ?? null;

// =========================================================
// 2. DATA FETCHING (DYNAMIC BASED ON ROLE)
// =========================================================

// Base Queries
$qUsers = "SELECT COUNT(*) FROM users";
$qVendors = "SELECT COUNT(*) FROM vendors";
$qPendingVendors = "SELECT COUNT(*) FROM vendors WHERE status = 'pending'";

$qRevenue = "
    SELECT SUM(oi.price * oi.quantity) 
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
";

$qRecentOrders = "
    SELECT o.id, u.name, o.created_at, o.status, 
    (SELECT COALESCE(SUM(oi.price * oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.id) as total
    FROM orders o 
    JOIN users u ON o.user_id = u.id
";

// Apply Country Filters for Country Agents
if ($userRole === 'country_agent') {
    $qRevenue .= " WHERE o.shipping_country = ?";
    $qRecentOrders .= " WHERE o.shipping_country = ?";
}

$qRecentOrders .= " ORDER BY o.created_at DESC LIMIT 6";

// Fetch Standard Global Metrics
$totalUsers     = $pdo->query($qUsers)->fetchColumn();
$totalVendors   = $pdo->query($qVendors)->fetchColumn();
$pendingVendors = $pdo->query($qPendingVendors)->fetchColumn();

// Fetch Revenue (Only for Supadmin and Country Agents)
$totalRevenue = 0;
if ($userRole === 'country_agent') {
    $revStmt = $pdo->prepare($qRevenue);
    $revStmt->execute([$managedCountry]);
    $totalRevenue = $revStmt->fetchColumn() ?? 0;
} elseif ($userRole === 'supadmin') {
    $totalRevenue = $pdo->query($qRevenue)->fetchColumn() ?? 0;
}

// Fetch Support-Specific Metrics
$totalOrders = 0; $processingOrders = 0; $deliveredOrders = 0;
if ($userRole === 'support') {
    $totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $processingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'processing'")->fetchColumn();
    $deliveredOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered'")->fetchColumn();
}

// 3. RECENT ORDERS (Role Aware)
if ($userRole === 'country_agent') {
    $roStmt = $pdo->prepare($qRecentOrders);
    $roStmt->execute([$managedCountry]);
    $recentOrders = $roStmt->fetchAll();
} else {
    // Supadmin and Support see global recent orders
    $recentOrders = $pdo->query($qRecentOrders)->fetchAll();
}

// 4. CHART DATA (Role Aware for Orders)
$chartLabels = []; $userData = []; $orderData = [];
for ($i = 5; $i >= 0; $i--) {
    $monthKey = date("Y-m", strtotime("-$i months"));
    $chartLabels[] = date("M", strtotime("-$i months"));
    
    $stmtU = $pdo->prepare("SELECT COUNT(*) FROM users WHERE DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmtU->execute([$monthKey]);
    $userData[] = (int)$stmtU->fetchColumn();

    $qChartOrders = "SELECT COUNT(*) FROM orders WHERE DATE_FORMAT(created_at, '%Y-%m') = ?";
    $chartParams = [$monthKey];
    
    if ($userRole === 'country_agent') {
        $qChartOrders .= " AND shipping_country = ?";
        $chartParams[] = $managedCountry;
    }

    $stmtO = $pdo->prepare($qChartOrders);
    $stmtO->execute($chartParams);
    $orderData[] = (int)$stmtO->fetchColumn();
}

// 5. INCLUDE HEADER 
require_once __DIR__ . '/includes/header.php';
?>

<div class="row g-4 mb-4">
    
    <?php if ($userRole === 'supadmin' || $userRole === 'country_agent'): ?>
        <div class="col-sm-6 col-xl-3">
            <div class="glass-card stat-card">
                <div class="icon-box"><i class="bi bi-wallet2"></i></div>
                <div class="flex-grow-1">
                    <p class="text-secondary small mb-0 fw-bold">
                        <?= $userRole === 'country_agent' ? htmlspecialchars($managedCountry) . ' Revenue' : 'Total Revenue' ?>
                    </p>
                    <div class="fw-bold mb-0 notranslate" style="color: var(--shop-brand); font-size: clamp(1.1rem, 1.5vw, 1.5rem);">
                        <?= formatPrice($totalRevenue) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="glass-card stat-card">
                <div class="icon-box" style="color: #4318ff; background: #eff4fb;"><i class="bi bi-people-fill"></i></div>
                <div class="flex-grow-1">
                    <p class="text-secondary small mb-0 fw-bold">Active Users</p>
                    <div class="fw-bold mb-0" style="color: var(--shop-brand); font-size: clamp(1.2rem, 1.5vw, 1.5rem);">
                        <?= number_format($totalUsers) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="glass-card stat-card">
                <div class="icon-box" style="color: #05cd99; background: #e6faf5;"><i class="bi bi-shop-window"></i></div>
                <div class="flex-grow-1">
                    <p class="text-secondary small mb-0 fw-bold">Verified Vendors</p>
                    <div class="fw-bold mb-0" style="color: var(--shop-brand); font-size: clamp(1.2rem, 1.5vw, 1.5rem);">
                        <?= number_format($totalVendors) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="glass-card stat-card" <?= $pendingVendors > 0 ? 'style="border: 2px solid #ffb547;"' : '' ?>>
                <div class="icon-box" style="color: #ffb547; background: #fff9e6;"><i class="bi bi-exclamation-diamond-fill"></i></div>
                <div class="flex-grow-1">
                    <p class="text-secondary small mb-0 fw-bold">Pending Audits</p>
                    <div class="fw-bold mb-0" style="color: var(--shop-brand); font-size: clamp(1.2rem, 1.5vw, 1.5rem);">
                        <?= number_format($pendingVendors) ?>
                    </div>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <div class="col-sm-6 col-xl-3">
            <div class="glass-card stat-card">
                <div class="icon-box" style="color: #19376D; background: #eff4fb;"><i class="bi bi-cart-check-fill"></i></div>
                <div class="flex-grow-1">
                    <p class="text-secondary small mb-0 fw-bold">Total Orders</p>
                    <div class="fw-bold mb-0" style="color: var(--shop-brand); font-size: clamp(1.2rem, 1.5vw, 1.5rem);">
                        <?= number_format($totalOrders) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="glass-card stat-card">
                <div class="icon-box" style="color: #4318ff; background: #eff4fb;"><i class="bi bi-people-fill"></i></div>
                <div class="flex-grow-1">
                    <p class="text-secondary small mb-0 fw-bold">Active Users</p>
                    <div class="fw-bold mb-0" style="color: var(--shop-brand); font-size: clamp(1.2rem, 1.5vw, 1.5rem);">
                        <?= number_format($totalUsers) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="glass-card stat-card">
                <div class="icon-box" style="color: #ffb547; background: #fff9e6;"><i class="bi bi-box-seam"></i></div>
                <div class="flex-grow-1">
                    <p class="text-secondary small mb-0 fw-bold">Processing Orders</p>
                    <div class="fw-bold mb-0" style="color: var(--shop-brand); font-size: clamp(1.2rem, 1.5vw, 1.5rem);">
                        <?= number_format($processingOrders) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="glass-card stat-card">
                <div class="icon-box" style="color: #05cd99; background: #e6faf5;"><i class="bi bi-check-circle-fill"></i></div>
                <div class="flex-grow-1">
                    <p class="text-secondary small mb-0 fw-bold">Delivered Orders</p>
                    <div class="fw-bold mb-0" style="color: var(--shop-brand); font-size: clamp(1.2rem, 1.5vw, 1.5rem);">
                        <?= number_format($deliveredOrders) ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="glass-card">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <h5 class="fw-bold mb-0" style="color: var(--shop-brand);">
                    <?= $userRole === 'country_agent' ? htmlspecialchars($managedCountry) . ' Growth' : 'Growth Performance' ?>
                </h5>
                <span class="badge bg-light text-dark border small px-3 py-2 rounded-pill">
                    <i class="bi bi-calendar3 me-1"></i> Past 6 Months
                </span>
            </div>
            <div style="height: 350px; width: 100%; position: relative;">
                <canvas id="growthChart"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="glass-card d-flex flex-column">
            <h5 class="fw-bold mb-4" style="color: var(--shop-brand);">Recent Orders</h5>
            
            <div class="flex-grow-1 overflow-auto" style="max-height: 300px;">
                <?php if (empty($recentOrders)): ?>
                    <div class="text-center py-5 opacity-50">
                        <i class="bi bi-inbox fs-1"></i>
                        <p class="small mt-2">No orders found.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentOrders as $ro): ?>
                    <div class="d-flex justify-content-between align-items-center border-bottom py-3">
                        <div class="d-flex align-items-center" style="min-width: 0;">
                            <div class="bg-light rounded-circle p-2 me-3 flex-shrink-0" style="width: 35px; height: 35px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-bag-check" style="color: var(--shop-brand);"></i>
                            </div>
                            <div class="flex-grow-1 text-truncate pe-2" style="min-width: 0;">
                                <span class="d-block fw-bold small text-dark text-truncate">#<?= $ro['id'] ?></span>
                                <span class="text-secondary text-truncate d-block" style="font-size: 11px;" title="<?= htmlspecialchars($ro['name']) ?>">
                                    <?= htmlspecialchars($ro['name']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="text-end flex-shrink-0">
                            <span class="d-block fw-bold small notranslate"><?= formatPrice($ro['total']) ?></span>
                            <span class="badge-pill status-<?= strtolower($ro['status']) ?>"><?= $ro['status'] ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <a href="orders.php" class="btn btn-light w-100 mt-3 fw-bold rounded-pill border hover-shadow" style="color: var(--shop-brand);">
                View All Orders <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</div>

<?php
// 6. INCLUDE FOOTER
require_once __DIR__ . '/includes/footer.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('growthChart').getContext('2d');
    const gradientFill = ctx.createLinearGradient(0, 0, 0, 400);
    gradientFill.addColorStop(0, 'rgba(11, 36, 71, 0.2)');
    gradientFill.addColorStop(1, 'rgba(11, 36, 71, 0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [
                {
                    label: 'New Users',
                    data: <?= json_encode($userData) ?>,
                    borderColor: '#0B2447',
                    borderWidth: 3,
                    backgroundColor: gradientFill,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#0B2447',
                },
                {
                    label: 'Orders',
                    data: <?= json_encode($orderData) ?>,
                    borderColor: '#05cd99',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    fill: false,
                    tension: 0.4,
                    pointRadius: 0
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 8 } }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f1f4f9', borderDash: [5, 5] } },
                x: { grid: { display: false } }
            }
        }
    });
</script>