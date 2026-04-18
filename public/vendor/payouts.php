<?php
require_once __DIR__ . "/../../app/helpers/vendor_guard.php";
require_once __DIR__ . "/../../app/config/db.php";
require_once __DIR__ . "/../../app/helpers/currency.php"; // Currency helper included

$userId = $_SESSION['user_id'];
$commissionPercentage = 10; // Config: Must match Admin logic

// ---------------------------------------------------------
// 1. GET VENDOR ID & PHONE
// ---------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT v.id, v.shop_name, u.phone 
    FROM vendors v
    JOIN users u ON v.user_id = u.id
    WHERE v.user_id = ?
");
$stmt->execute([$userId]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    // If no vendor profile, redirect to create one
    header("Location: /shopcorrect/public/create-shop.php");
    exit;
}

$vendorId = $vendor['id'];
$shopName = $vendor['shop_name'];

// Extract current active currency symbol for inputs
$fmt0 = formatPrice(0);
preg_match('/^[^\d]*/', $fmt0, $preMatch);
preg_match('/[^\d]*$/', $fmt0, $sufMatch);
$activeSymbol = trim($preMatch[0] . $sufMatch[0]); 
if(empty($activeSymbol)) $activeSymbol = '₵'; 

// ---------------------------------------------------------
// 2. CALCULATE WALLET BALANCE (Real-time)
// ---------------------------------------------------------

// A. Gross Sales (Delivered Orders Only)
$s1 = $pdo->prepare("
    SELECT COALESCE(SUM(oi.price * oi.quantity), 0) 
    FROM order_items oi 
    JOIN orders o ON oi.order_id = o.id 
    WHERE oi.vendor_id = ? AND o.status = 'delivered'
");
$s1->execute([$vendorId]);
$grossSales = $s1->fetchColumn();

// B. Commission Deduction
$commission = $grossSales * ($commissionPercentage / 100);

// C. Shipping Credit (If vendor handles shipping)
$s2 = $pdo->prepare("
    SELECT COALESCE(SUM(o.shipping_cost), 0) 
    FROM orders o 
    JOIN (SELECT DISTINCT order_id FROM order_items WHERE vendor_id = ?) vo 
    ON o.id = vo.order_id 
    WHERE o.status = 'delivered'
");
$s2->execute([$vendorId]);
$shipping = $s2->fetchColumn();

// D. Total Payouts (Approved Only)
$s3 = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payout_requests WHERE vendor_id = ? AND status = 'approved'");
$s3->execute([$vendorId]);
$totalPaid = $s3->fetchColumn();

// E. Pending Payouts (Locked Funds)
$s4 = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payout_requests WHERE vendor_id = ? AND status = 'pending'");
$s4->execute([$vendorId]);
$pendingAmount = $s4->fetchColumn();

// FINAL CALCULATION
$netEarnings = ($grossSales - $commission + $shipping);
$availableBalance = $netEarnings - $totalPaid - $pendingAmount;
if ($availableBalance < 0) $availableBalance = 0;

// ---------------------------------------------------------
// 3. HANDLE PAYOUT REQUEST
// ---------------------------------------------------------
$requestError = "";
$requestSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_amount'])) {
    $amount = filter_var($_POST['request_amount'], FILTER_VALIDATE_FLOAT);

    if ($amount && $amount > 0 && $amount <= $availableBalance) {
        $stmt = $pdo->prepare("INSERT INTO payout_requests (vendor_id, amount, status, created_at) VALUES (?, ?, 'pending', NOW())");
        if ($stmt->execute([$vendorId, $amount])) {
            $requestSuccess = true;
            // Update balance for display immediately
            $pendingAmount += $amount; 
            $availableBalance -= $amount;
        }
    } else {
        $requestError = "Invalid amount. You cannot request more than your available balance.";
    }
}

// 4. FETCH HISTORY
$stmt = $pdo->prepare("SELECT * FROM payout_requests WHERE vendor_id = ? ORDER BY created_at DESC");
$stmt->execute([$vendorId]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// 5. Include Header
// --------------------------------------------------
require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* Wallet Cards */
    .wallet-card { border: none; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); height: 100%; overflow: hidden; }
    .wallet-card-body { padding: 1.5rem; display: flex; flex-direction: column; justify-content: center; height: 100%; }
    
    /* Table */
    .table-container { background: white; border: 1px solid var(--card-border); border-radius: 12px; overflow: hidden; }
    
    .btn-payout { background: white; color: #0F172A; border: none; font-weight: 600; padding: 10px 20px; border-radius: 8px; width: 100%; transition: 0.2s; }
    .btn-payout:hover { background: #f1f5f9; }
</style>

<div class="container-fluid px-4 py-4">

    <?php if ($requestSuccess): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            Payout request submitted successfully! Admin will review shortly.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($requestError): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
            <i class="bi bi-exclamation-circle-fill me-2"></i>
            <?= $requestError ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-5">
        <div class="col-lg-4">
            <div class="wallet-card text-white" style="background: linear-gradient(135deg, #0B1727 0%, #1E293B 100%);">
                <div class="wallet-card-body">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <div class="text-white-50 small text-uppercase fw-bold mb-1">Available Balance</div>
                            <h2 class="mb-0 fw-bold"><?= formatPrice($availableBalance) ?></h2>
                        </div>
                        <div class="bg-white bg-opacity-10 p-2 rounded-3">
                            <i class="bi bi-wallet2 fs-4 text-white"></i>
                        </div>
                    </div>
                    
                    <?php if ($availableBalance >= 10): ?>
                        <button type="button" class="btn-payout shadow-sm" data-bs-toggle="modal" data-bs-target="#requestModal">
                            <i class="bi bi-box-arrow-up-right me-2"></i> Request Payout
                        </button>
                    <?php else: ?>
                        <button class="btn btn-light bg-opacity-10 text-white w-100 disabled border-white border-opacity-25" style="opacity: 0.6;">
                            Min. Withdrawal <?= formatPrice(10) ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-4">
            <div class="wallet-card bg-white border">
                <div class="wallet-card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rounded-circle bg-warning bg-opacity-10 p-2 me-3 text-warning d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="bi bi-hourglass-split fs-5"></i>
                        </div>
                        <h6 class="mb-0 text-muted text-uppercase small fw-bold">Pending Requests</h6>
                    </div>
                    <h3 class="mb-1 fw-bold text-dark"><?= formatPrice($pendingAmount) ?></h3>
                    <small class="text-muted">Funds currently locked for review</small>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-4">
            <div class="wallet-card bg-white border">
                <div class="wallet-card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rounded-circle bg-success bg-opacity-10 p-2 me-3 text-success d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="bi bi-check-lg fs-5"></i>
                        </div>
                        <h6 class="mb-0 text-muted text-uppercase small fw-bold">Total Withdrawn</h6>
                    </div>
                    <h3 class="mb-1 fw-bold text-dark"><?= formatPrice($totalPaid) ?></h3>
                    <small class="text-muted">Lifetime approved payouts</small>
                </div>
            </div>
        </div>
    </div>

    <div class="table-container shadow-sm">
        <div class="p-3 border-bottom bg-light">
            <h6 class="fw-bold m-0 text-dark">Payout History</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 text-secondary small text-uppercase fw-bold">Ref ID</th>
                        <th class="text-secondary small text-uppercase fw-bold">Date Requested</th>
                        <th class="text-secondary small text-uppercase fw-bold">Amount</th>
                        <th class="text-secondary small text-uppercase fw-bold">Status</th>
                        <th class="text-secondary small text-uppercase fw-bold pe-4">Note</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
                                You have no payout history yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($history as $row): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-dark">#<?= $row['id'] ?></td>
                                <td class="text-muted"><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                <td><span class="fw-bold text-dark"><?= formatPrice($row['amount']) ?></span></td>
                                <td>
                                    <?php 
                                        $statusConfig = match($row['status']) {
                                            'approved' => ['bg-success', 'Processed'],
                                            'rejected' => ['bg-danger', 'Rejected'],
                                            default => ['bg-warning text-dark', 'Pending']
                                        };
                                    ?>
                                    <span class="badge rounded-pill <?= $statusConfig[0] ?> bg-opacity-75 px-3 py-2 fw-normal">
                                        <?= $statusConfig[1] ?>
                                    </span>
                                </td>
                                <td class="pe-4 text-muted small">
                                    <?= !empty($row['admin_notes']) ? htmlspecialchars($row['admin_notes']) : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div> 

<div class="modal fade" id="requestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-light rounded-top-4">
                <h5 class="modal-title fw-bold">Withdraw Funds</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-cash-stack fs-2"></i>
                    </div>
                    <p class="text-muted mb-0">Available to withdraw: <strong class="text-dark"><?= formatPrice($availableBalance) ?></strong></p>
                </div>

                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label small text-muted fw-bold">ENTER AMOUNT (<?= $activeSymbol ?>)</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-white text-muted border-end-0"><?= $activeSymbol ?></span>
                            <input type="number" step="0.01" name="request_amount" class="form-control border-start-0 ps-0 shadow-none" 
                                   max="<?= $availableBalance ?>" min="1" placeholder="0.00" required>
                        </div>
                    </div>
                    
                    <div class="alert alert-light border d-flex align-items-start small text-muted mb-4">
                        <i class="bi bi-info-circle-fill text-primary mt-1 me-2"></i>
                        <div>
                            Funds will be sent to your registered number: <br>
                            <strong class="text-dark"><?= htmlspecialchars($vendor['phone']) ?></strong>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg fw-bold" style="background-color: var(--primary-accent); border: none;">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// --------------------------------------------------
// 6. Include Footer
// --------------------------------------------------
require_once __DIR__ . '/includes/footer.php';
?>