<?php
require_once __DIR__ . "/../../app/config/db.php";

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) session_start();

// Updated Bouncer: Allow Super Admin AND Country Agent
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['supadmin', 'country_agent'])) {
    header("Location: ../login.php");
    exit;
}

$userRole = $_SESSION['role'];
$managedCountry = $_SESSION['managed_country'] ?? null;

// 2. DATE FILTER LOGIC
$startDate = $_GET['start_date'] ?? date('Y-m-01'); 
$endDate   = $_GET['end_date'] ?? date('Y-m-d');    

// =========================================================
// 3. DATA FETCHING (DYNAMIC BASED ON ROLE)
// =========================================================

// A. Financial Summary
$finSql = "
    SELECT 
        SUM(o.total_amount) as gross_revenue,
        SUM(oi.commission_fee) as total_commission
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    WHERE o.status IN ('paid', 'shipped', 'delivered') 
    AND o.created_at BETWEEN ? AND ?
";
$finParams = ["$startDate 00:00:00", "$endDate 23:59:59"];

if ($userRole === 'country_agent') {
    $finSql .= " AND o.shipping_country = ?";
    $finParams[] = $managedCountry;
}

$finStmt = $pdo->prepare($finSql);
$finStmt->execute($finParams);
$finance = $finStmt->fetch(PDO::FETCH_ASSOC);

$grossRevenue = $finance['gross_revenue'] ?: 0;
$adminProfit  = $finance['total_commission'] ?: 0;


// B. Sales Trend Chart Data
$chartSql = "
    SELECT DATE(o.created_at) as date, SUM(oi.commission_fee) as total
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    WHERE o.status IN ('paid', 'shipped', 'delivered')
    AND o.created_at BETWEEN ? AND ?
";
$chartParams = ["$startDate 00:00:00", "$endDate 23:59:59"];

if ($userRole === 'country_agent') {
    $chartSql .= " AND o.shipping_country = ?";
    $chartParams[] = $managedCountry;
}

$chartSql .= " GROUP BY DATE(o.created_at) ORDER BY date ASC";

$chartStmt = $pdo->prepare($chartSql);
$chartStmt->execute($chartParams);
$chartData = $chartStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$period = new DatePeriod(new DateTime($startDate), new DateInterval('P1D'), (new DateTime($endDate))->modify('+1 day'));
$finalChartData = [];
foreach ($period as $dt) {
    $d = $dt->format('Y-m-d');
    $rate = $_SESSION['exchange_rate'] ?? 1;
    $finalChartData[$d] = isset($chartData[$d]) ? round($chartData[$d] * $rate, 2) : 0;
}


// C. Top Selling Products
$topProdSql = "
    SELECT p.name, p.image, SUM(oi.quantity) as sold_qty, SUM(oi.commission_fee) as admin_earned
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.status IN ('paid', 'shipped', 'delivered')
    AND o.created_at BETWEEN ? AND ?
";
$topProdParams = ["$startDate 00:00:00", "$endDate 23:59:59"];

if ($userRole === 'country_agent') {
    $topProdSql .= " AND o.shipping_country = ?";
    $topProdParams[] = $managedCountry;
}

$topProdSql .= " GROUP BY p.id, p.name, p.image ORDER BY sold_qty DESC LIMIT 5";

$topProdStmt = $pdo->prepare($topProdSql);
$topProdStmt->execute($topProdParams);
$topProducts = $topProdStmt->fetchAll(PDO::FETCH_ASSOC);


// D. Top Performing Vendors
$topVendorSql = "
    SELECT v.shop_name, COUNT(DISTINCT o.id) as orders_count, SUM(oi.commission_fee) as total_commission
    FROM order_items oi
    JOIN vendors v ON oi.vendor_id = v.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.status IN ('paid', 'shipped', 'delivered')
    AND o.created_at BETWEEN ? AND ?
";
$topVendorParams = ["$startDate 00:00:00", "$endDate 23:59:59"];

if ($userRole === 'country_agent') {
    $topVendorSql .= " AND o.shipping_country = ?";
    $topVendorParams[] = $managedCountry;
}

$topVendorSql .= " GROUP BY v.id, v.shop_name ORDER BY total_commission DESC LIMIT 5";

$topVendorStmt = $pdo->prepare($topVendorSql);
$topVendorStmt->execute($topVendorParams);
$topVendors = $topVendorStmt->fetchAll(PDO::FETCH_ASSOC);


// 4. INCLUDE HEADER (Brings in Sidebar, CSS, Google Translate, and Currency Helper)
require_once __DIR__ . "/includes/header.php";
?>

<style>
    /* Filter Section */
    .filter-bar { background: #fff; border-radius: 20px; padding: 20px; box-shadow: 0px 10px 30px rgba(112, 144, 176, 0.1); margin-bottom: 30px; }
    .form-control-custom { border: 1px solid #e0e5f2; border-radius: 10px; padding: 10px 15px; font-weight: 600; font-size: 0.9rem; }
    .btn-update { background: var(--shop-brand); color: white; border-radius: 10px; font-weight: 700; padding: 10px 20px; border:none; transition: 0.2s; }
    .btn-update:hover { background: var(--shop-accent); color: white; transform: translateY(-2px); }

    /* Tables */
    .table thead th { background: #FAFCFF; color: #A3AED0; font-size: 0.75rem; text-transform: uppercase; border-bottom: 1px solid #E2E8F0; padding: 1rem; }
    .table td { padding: 1rem; vertical-align: middle; font-size: 0.9rem; border-bottom: 1px solid #F4F7FE; }
    
    .rank-badge { 
        width: 24px; height: 24px; 
        border-radius: 6px; 
        display: flex; align-items: center; justify-content: center; 
        font-weight: 800; font-size: 0.7rem;
    }
    .rank-1 { background: #FFFBEB; color: #F59E0B; border: 1px solid #FDE68A; }
    .rank-other { background: #F3F4F6; color: #6B7280; }

    @media print {
        .header-dropdowns, .filter-bar, .no-print, #sidebarToggle { display: none !important; }
        .main-content { margin: 0; padding: 0; }
        .glass-card { box-shadow: none; border: 1px solid #ddd; }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
    <div>
        <h6 class="text-secondary fw-bold text-uppercase mb-1" style="font-size: 11px; letter-spacing: 1px;">Analytics Center</h6>
        <h3 class="fw-bold mb-0" style="color: var(--shop-brand);">
            <?= $userRole === 'country_agent' ? htmlspecialchars($managedCountry) . ' Performance Reports' : 'Global Performance Reports' ?>
        </h3>
    </div>
    <button onclick="window.print()" class="btn btn-white border px-4 rounded-3 shadow-sm fw-bold text-secondary bg-white no-print">
        <i class="bi bi-printer me-2"></i> Print Report
    </button>
</div>

<div class="filter-bar no-print">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label small fw-bold text-muted text-uppercase">Start Date</label>
            <input type="date" name="start_date" class="form-control form-control-custom" value="<?= $startDate ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-bold text-muted text-uppercase">End Date</label>
            <input type="date" name="end_date" class="form-control form-control-custom" value="<?= $endDate ?>">
        </div>
        <div class="col-md-4">
            <button type="submit" class="btn btn-update w-100 shadow-sm">
                <i class="bi bi-arrow-repeat me-2"></i> Synchronize Data
            </button>
        </div>
    </form>
</div>

<div class="row g-4 mb-5">
    <div class="col-md-6">
        <div class="glass-card stat-card">
            <div>
                <p class="text-secondary small fw-bold mb-1 text-uppercase">Total Gross Revenue</p>
                <h2 class="fw-bold mb-0 text-dark notranslate"><?= formatPrice($grossRevenue) ?></h2>
                <small class="text-muted">Total value of completed orders</small>
            </div>
            <div class="icon-box bg-light text-primary">
                <i class="bi bi-wallet2"></i>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="glass-card stat-card" style="border: 1px solid #05CD99;">
            <div>
                <p class="text-secondary small fw-bold mb-1 text-uppercase" style="color: #05CD99 !important;">Admin Commission</p>
                <h2 class="fw-bold mb-0 notranslate" style="color: #05CD99;"><?= formatPrice($adminProfit) ?></h2>
                <small class="text-muted">Net Platform Earnings</small>
            </div>
            <div class="icon-box" style="background: #E6FAF5; color: #05CD99;">
                <i class="bi bi-graph-up-arrow"></i>
            </div>
        </div>
    </div>
</div>

<div class="row mb-5">
    <div class="col-12">
        <div class="glass-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold text-dark mb-0">Commission Growth Trend</h5>
                <span class="badge bg-light text-secondary border px-3">Daily Performance</span>
            </div>
            <div style="height: 350px;">
                <canvas id="salesChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="glass-card h-100">
            <div class="p-4 border-bottom">
                <h6 class="fw-bold text-dark mb-0">High Velocity Products</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Rank</th>
                            <th>Product</th>
                            <th class="text-center">Sold</th>
                            <th class="text-end pe-4">Earnings</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($topProducts)): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted small">No data available for this period.</td></tr>
                        <?php else: ?>
                            <?php foreach($topProducts as $idx => $prod): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="rank-badge <?= $idx == 0 ? 'rank-1' : 'rank-other' ?>"><?= $idx+1 ?></div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-light rounded border me-3" style="width: 35px; height: 35px; overflow: hidden;">
                                            <img src="../../uploads/products/<?= $prod['image'] ?>" style="width:100%; height:100%; object-fit:cover;" onerror="this.src='https://via.placeholder.com/35'">
                                        </div>
                                        <span class="text-dark fw-bold small"><?= htmlspecialchars($prod['name']) ?></span>
                                    </div>
                                </td>
                                <td class="text-center fw-bold text-secondary"><?= $prod['sold_qty'] ?></td>
                                <td class="text-end pe-4 text-success fw-bold notranslate"><?= formatPrice($prod['admin_earned']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="glass-card h-100">
            <div class="p-4 border-bottom">
                <h6 class="fw-bold text-dark mb-0">Top Producing Merchants</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Rank</th>
                            <th>Shop Name</th>
                            <th class="text-center">Orders</th>
                            <th class="text-end pe-4">Commission</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($topVendors)): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted small">No data available for this period.</td></tr>
                        <?php else: ?>
                            <?php foreach($topVendors as $idx => $v): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="rank-badge <?= $idx == 0 ? 'rank-1' : 'rank-other' ?>"><?= $idx+1 ?></div>
                                </td>
                                <td class="text-dark fw-bold small notranslate"><?= htmlspecialchars($v['shop_name']) ?></td>
                                <td class="text-center fw-bold text-secondary"><?= $v['orders_count'] ?></td>
                                <td class="text-end pe-4 text-primary fw-bold notranslate"><?= formatPrice($v['total_commission']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php 
// 5. INCLUDE FOOTER
require_once __DIR__ . "/includes/footer.php"; 

// Safely determine the active currency symbol based on the session code
$sysCurrency = $_SESSION['currency'] ?? 'GHS';
$currencySymbols = [
    'GHS' => '₵', 'USD' => '$', 'EUR' => '€', 'GBP' => '£',
    'NGN' => '₦', 'KES' => 'KSh ', 'ZAR' => 'R ', 'CAD' => 'C$',
    'AUD' => 'A$', 'XOF' => 'CFA ', 'JPY' => '¥', 'CNY' => '¥',
    'INR' => '₹', 'AED' => 'د.إ '
];
$chartSymbol = $currencySymbols[$sysCurrency] ?? $sysCurrency . ' ';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('salesChart').getContext('2d');
    
    // Use the strictly mapped PHP symbol
    const currencySymbol = '<?= $chartSymbol ?>';
    
    // Create Gradient for the chart
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(11, 36, 71, 0.15)'); // Brand Navy transparent
    gradient.addColorStop(1, 'rgba(11, 36, 71, 0.0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_keys($finalChartData)) ?>,
            datasets: [{
                label: 'Commission Earned',
                data: <?= json_encode(array_values($finalChartData)) ?>,
                borderColor: '#0B2447',
                backgroundColor: gradient,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#0B2447',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        // Updates the tooltip when you hover over a dot
                        label: function(context) {
                            return 'Commission: ' + currencySymbol + context.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                x: { 
                    grid: { display: false },
                    ticks: { font: { family: 'Plus Jakarta Sans', size: 11 } }
                },
                y: { 
                    beginAtZero: true,
                    grid: { color: '#F1F5F9' },
                    ticks: { 
                        font: { family: 'Plus Jakarta Sans', size: 11 },
                        // Updates the Y-Axis labels
                        callback: function(value) { return currencySymbol + value; } 
                    }
                }
            }
        }
    });
</script>