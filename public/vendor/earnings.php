<?php
ob_start(); // Prevent header errors
require_once __DIR__ . "/../../app/helpers/vendor_guard.php";
require_once __DIR__ . "/../../app/config/db.php";
require_once __DIR__ . "/../../app/helpers/currency.php"; // Included currency helper

/* -------------------------------------------------
   1. Get Vendor Details
------------------------------------------------- */
$stmt = $pdo->prepare("SELECT id, shop_name FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION["user_id"]]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    header("Location: /shopcorrect/public/create-shop.php");
    exit;
}

$vendorId = $vendor['id'];
$shopName = $vendor['shop_name'];

// Extract current active currency symbol for inputs/text
$fmt0 = formatPrice(0);
preg_match('/^[^\d]*/', $fmt0, $preMatch);
preg_match('/[^\d]*$/', $fmt0, $sufMatch);
$activeSymbol = trim($preMatch[0] . $sufMatch[0]); 
if(empty($activeSymbol)) $activeSymbol = '₵'; 

// 2. Create Payout Table (Safe Check)
$pdo->exec("CREATE TABLE IF NOT EXISTS payout_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

/* -------------------------------------------------
   3. FINANCIAL CALCULATIONS (Updated)
------------------------------------------------- */

// A. Gross Product Sales
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(oi.price * oi.quantity), 0) 
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.vendor_id = ? AND o.status = 'delivered'
");
$stmt->execute([$vendorId]);
$grossProductSales = (float) $stmt->fetchColumn();

// B. Total Commission Deducted (Read from DB)
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(oi.commission_fee), 0)
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.vendor_id = ? AND o.status = 'delivered'
");
$stmt->execute([$vendorId]);
$totalCommission = (float) $stmt->fetchColumn();

// C. Net Product Sales
$netProductSales = $grossProductSales - $totalCommission;

// D. Total Shipping Reimbursement
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(o.shipping_cost), 0)
    FROM orders o
    JOIN (SELECT DISTINCT order_id FROM order_items WHERE vendor_id = ?) vo ON o.id = vo.order_id
    WHERE o.status = 'delivered'
");
$stmt->execute([$vendorId]);
$totalShippingReimbursement = (float) $stmt->fetchColumn();

// E. Total Net Revenue
$totalNetRevenue = $netProductSales + $totalShippingReimbursement;

// F. Total Payouts
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payout_requests WHERE vendor_id = ? AND status IN ('approved', 'pending')");
$stmt->execute([$vendorId]);
$totalWithdrawnOrPending = (float) $stmt->fetchColumn();

// G. Available Balance
$availableBalance = $totalNetRevenue - $totalWithdrawnOrPending;
if ($availableBalance < 0) $availableBalance = 0;

// --- DYNAMIC PERCENTAGE WITH FALLBACK ---
$stmtSettings = $pdo->query("SELECT commission_percent FROM settings LIMIT 1");
$globalCommission = (int) $stmtSettings->fetchColumn();
$displayPercentage = ($grossProductSales > 0) ? round(($totalCommission / $grossProductSales) * 100) : $globalCommission;


/* -------------------------------------------------
   4. LOGIC: EXPORT TO CSV
------------------------------------------------- */
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    ob_end_clean();
    $filename = "earnings_report_" . date('Y-m-d') . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Order Ref', 'Date', 'Product', 'Specs', 'Qty', 'Unit Price', 'Gross Subtotal', "Commission Fee", 'Net (Inc. Shipping)']);
    
    $csvStmt = $pdo->prepare("
        SELECT 
            o.id as order_ref,
            o.created_at,
            o.shipping_cost,
            p.name as product_name,
            oi.quantity,
            oi.selected_color,
            oi.selected_size,
            oi.price as unit_price,
            oi.commission_fee,
            (SELECT COUNT(*) FROM order_items WHERE order_id = o.id AND vendor_id = ?) as items_count,
            ROW_NUMBER() OVER (PARTITION BY o.id ORDER BY oi.id) as row_num
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        WHERE oi.vendor_id = ? AND o.status = 'delivered'
        ORDER BY o.created_at DESC
    ");
    $csvStmt->execute([$vendorId, $vendorId]);
    
    while ($row = $csvStmt->fetch(PDO::FETCH_ASSOC)) {
        $subtotal = $row['unit_price'] * $row['quantity'];
        $fee = (float)$row['commission_fee'];
        $netItem = $subtotal - $fee;
        
        if ($row['row_num'] == 1) {
            $netItem += $row['shipping_cost'];
        }
        
        $specs = "";
        if($row['selected_color']) $specs .= "Color: " . $row['selected_color'];
        if($row['selected_size']) $specs .= ($specs ? " | " : "") . "Size: " . $row['selected_size'];

        fputcsv($output, [
            $row['order_ref'],
            $row['created_at'],
            $row['product_name'],
            $specs,
            $row['quantity'],
            $row['unit_price'],
            $subtotal,
            '-'.$fee,
            $netItem
        ]);
    }
    fclose($output);
    exit;
}

/* -------------------------------------------------
   5. HANDLE PAYOUT REQUESTS
------------------------------------------------- */
$payoutMsg = "";
$payoutError = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_payout') {
    $amount = (float)$_POST['amount'];
    $checkPending = $pdo->prepare("SELECT id FROM payout_requests WHERE vendor_id = ? AND status = 'pending'");
    $checkPending->execute([$vendorId]);
    
    if ($checkPending->rowCount() > 0) {
        $payoutError = "You already have a pending request. Please wait for approval.";
    } elseif ($amount <= 0) {
        $payoutError = "Invalid amount.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO payout_requests (vendor_id, amount) VALUES (?, ?)");
        if ($stmt->execute([$vendorId, $amount])) {
            $payoutMsg = "Request for " . formatPrice($amount) . " submitted.";
        } else {
            $payoutError = "Database error.";
        }
    }
}

/* -------------------------------------------------
   6. FETCH RECENT SALES
------------------------------------------------- */
$listStmt = $pdo->prepare("
    SELECT 
        p.name as product_name,
        p.image as product_image,
        oi.price as unit_price,
        oi.quantity,
        oi.selected_color,
        oi.selected_size,
        oi.commission_fee,
        o.created_at,
        o.id as order_ref,
        o.shipping_cost,
        (SELECT COUNT(*) FROM order_items WHERE order_id = o.id AND vendor_id = ?) as items_in_order,
        ROW_NUMBER() OVER (PARTITION BY o.id ORDER BY oi.id) as row_num
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.vendor_id = ? AND o.status = 'delivered'
    ORDER BY o.created_at DESC
    LIMIT 20
");
$listStmt->execute([$vendorId, $vendorId]);
$recentSales = $listStmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// 7. Include Header
// --------------------------------------------------
require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* Metric Cards Custom */
    .metric-card { background: white; border-radius: 12px; border: 1px solid var(--card-border); padding: 1.5rem; height: 100%; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .card-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748B; letter-spacing: 0.5px; margin-bottom: 0.5rem; }
    .card-value { font-size: 1.75rem; font-weight: 800; color: var(--primary-accent); margin-bottom: 0.25rem; }
    .card-sub { font-size: 0.8rem; color: #94a3b8; }

    /* Table & Components */
    .table-container { background: white; border: 1px solid var(--card-border); border-radius: 12px; overflow: hidden; }
    .product-thumb { width: 40px; height: 40px; border-radius: 6px; object-fit: cover; border: 1px solid #f1f5f9; }
    
    .spec-badge { font-size: 0.7rem; background: #f1f5f9; color: #475569; padding: 2px 6px; border-radius: 4px; font-weight: 600; margin-right: 4px; border: 1px solid #e2e8f0; }
    
    .btn-export { background: white; border: 1px solid #cbd5e1; color: #475569; font-weight: 600; padding: 8px 16px; border-radius: 8px; font-size: 0.9rem; transition: 0.2s; text-decoration: none; }
    .btn-export:hover { background: #f8fafc; color: var(--primary-accent); border-color: #94a3b8; }
    .btn-payout { background: var(--primary-accent); border: none; color: white; font-weight: 600; padding: 8px 16px; border-radius: 8px; font-size: 0.9rem; transition: 0.2s; }
    .btn-payout:hover { background: #1e3a8a; }
    .btn-payout:disabled { background: #94a3b8; opacity: 0.7; }
</style>

<div class="container-fluid px-4 py-4">

    <?php if ($payoutMsg): ?>
        <div class="alert alert-success border-0 shadow-sm mb-4"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($payoutMsg) ?></div>
    <?php endif; ?>
    <?php if ($payoutError): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-4"><i class="bi bi-exclamation-octagon me-2"></i><?= htmlspecialchars($payoutError) ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">Financial Overview</h4>
            <p class="text-secondary mb-0 small">Track settled funds and request withdrawals.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="?action=export" class="btn-export"><i class="bi bi-download me-1"></i> CSV</a>
            <button class="btn-payout shadow-sm" data-bs-toggle="modal" data-bs-target="#payoutModal" <?= $availableBalance <= 0 ? 'disabled' : '' ?>>
                Request Payout
            </button>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-6 col-lg-3">
            <div class="metric-card border-start border-4 border-primary">
                <div class="card-label text-primary">Total Net Revenue</div>
                <div class="card-value"><?= formatPrice($totalNetRevenue) ?></div>
                <div class="card-sub">Net Sales + Shipping Fees</div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-3">
            <div class="metric-card">
                <div class="card-label">Shipping Collected</div>
                <div class="card-value text-secondary"><?= formatPrice($totalShippingReimbursement) ?></div>
                <div class="card-sub">Included in total revenue</div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="metric-card">
                <div class="card-label">Platform Fees</div>
                <div class="card-value text-danger">-<?= formatPrice($totalCommission) ?></div>
                <div class="card-sub"><?= $displayPercentage ?>% Deducted from sales</div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="metric-card bg-success-subtle border-success-subtle">
                <div class="card-label text-success-emphasis">Available Balance</div>
                <div class="card-value text-success"><?= formatPrice($availableBalance) ?></div>
                <div class="card-sub text-success-emphasis">Revenue minus Withdrawals</div>
            </div>
        </div>
    </div>

    <div class="table-container">
        <div class="p-3 border-bottom bg-light">
            <h6 class="fw-bold m-0 text-dark">Recent Line Items (Delivered)</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Item</th>
                        <th>Order Ref</th>
                        <th>Gross</th>
                        <th class="text-danger">Fee</th>
                        <th class="text-end pe-4">Net Earning</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentSales)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No completed sales yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentSales as $sale): ?>
                            <?php 
                                $prodTotal = $sale['unit_price'] * $sale['quantity'];
                                $fee = (float)$sale['commission_fee'];
                                $netItem = $prodTotal - $fee;
                                $img = !empty($sale['product_image']) ? "../uploads/products/".$sale['product_image'] : "";
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <?php if($img && file_exists(__DIR__ . "/../../public/uploads/products/" . $sale['product_image'])): ?>
                                            <img src="<?= htmlspecialchars($img) ?>" class="product-thumb">
                                        <?php else: ?>
                                            <div class="product-thumb bg-light d-flex align-items-center justify-content-center text-muted"><i class="bi bi-box"></i></div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($sale['product_name']) ?></div>
                                            <div class="d-flex align-items-center mt-1">
                                                <span class="text-muted small me-2">Qty: <?= $sale['quantity'] ?></span>
                                                <?php if($sale['selected_color']): ?>
                                                    <span class="spec-badge">Color: <?= htmlspecialchars($sale['selected_color']) ?></span>
                                                <?php endif; ?>
                                                <?php if($sale['selected_size']): ?>
                                                    <span class="spec-badge">Size: <?= htmlspecialchars($sale['selected_size']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">#<?= $sale['order_ref'] ?></span>
                                    <div class="text-muted small mt-1"><?= date('M d', strtotime($sale['created_at'])) ?></div>
                                </td>
                                <td><?= formatPrice($prodTotal) ?></td>
                                <td class="text-danger">-<?= formatPrice($fee) ?></td>
                                <td class="text-end pe-4 fw-bold text-success">
                                    <?php 
                                        // Add shipping cost only to the first row of the order so it isn't duplicated
                                        if($sale['row_num'] == 1) {
                                            $finalRowTotal = $netItem + $sale['shipping_cost'];
                                            echo formatPrice($finalRowTotal);
                                            echo "<div class='text-secondary small' style='font-size:0.75rem;'>(Inc. ".formatPrice($sale['shipping_cost'])." ship)</div>";
                                        } else {
                                            echo formatPrice($netItem);
                                            echo "<div class='text-muted small' style='font-size:0.7rem;'>Item only</div>";
                                        }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div> 

<div class="modal fade" id="payoutModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold">Withdraw Funds</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <form method="POST">
            <input type="hidden" name="action" value="request_payout">
            <div class="text-center mb-4">
                <small class="text-muted text-uppercase fw-bold">Available to Withdraw</small>
                <h1 class="fw-bold text-success display-6"><?= formatPrice($availableBalance) ?></h1>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold small">Enter Amount (<?= $activeSymbol ?>)</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><?= $activeSymbol ?></span>
                    <input type="number" name="amount" class="form-control form-control-lg" max="<?= $availableBalance ?>" min="1" step="0.01" value="<?= $availableBalance ?>" required>
                </div>
            </div>
            <button type="submit" class="btn btn-payout w-100 py-3">Submit Request</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php
// --------------------------------------------------
// 8. Include Footer
// --------------------------------------------------
require_once __DIR__ . '/includes/footer.php';
?>