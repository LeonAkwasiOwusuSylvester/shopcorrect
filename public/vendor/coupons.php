<?php
session_start();
require_once __DIR__ . "/../../app/config/db.php";

// 1. Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// 2. Get the specific Vendor ID
$vStmt = $pdo->prepare("SELECT id, shop_name FROM vendors WHERE user_id = ?");
$vStmt->execute([$_SESSION['user_id']]);
$vendor = $vStmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    die("Vendor profile not found.");
}
$realVendorId = $vendor['id'];
$shopName = $vendor['shop_name'];

$successMsg = '';
$errorMsg = '';

// 3. Handle Form Submission to Create Coupon
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_coupon'])) {
    $code = strtoupper(trim($_POST['code']));
    $type = $_POST['type'];
    $value = (float)$_POST['value'];
    
    // Handle Optional Fields gracefully
    $min_order = empty($_POST['min_order_amount']) ? 0.00 : (float)$_POST['min_order_amount'];
    $max_discount = empty($_POST['max_discount_amount']) ? null : (float)$_POST['max_discount_amount'];
    $usage_limit = empty($_POST['usage_limit']) ? null : (int)$_POST['usage_limit'];
    
    // Format dates for MySQL
    $starts_at = empty($_POST['starts_at']) ? null : date('Y-m-d H:i:s', strtotime($_POST['starts_at']));
    $expires_at = empty($_POST['expires_at']) ? null : date('Y-m-d H:i:s', strtotime($_POST['expires_at']));

    if (empty($code) || $value <= 0) {
        $errorMsg = "Coupon code and a valid discount value are required.";
    } else {
        // Prevent duplicate codes
        $checkStmt = $pdo->prepare("SELECT id FROM coupons WHERE code = ?");
        $checkStmt->execute([$code]);
        if ($checkStmt->fetch()) {
            $errorMsg = "The code '$code' already exists. Please choose a unique code.";
        } else {
            try {
                $insertStmt = $pdo->prepare("
                    INSERT INTO coupons 
                    (code, type, value, min_order_amount, max_discount_amount, usage_limit, vendor_id, status, starts_at, expires_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)
                ");
                $insertStmt->execute([
                    $code, $type, $value, $min_order, $max_discount, $usage_limit, $realVendorId, $starts_at, $expires_at
                ]);
                $successMsg = "Promo code '$code' created successfully!";
            } catch (PDOException $e) {
                $errorMsg = "Error creating coupon. Please try again.";
                error_log("Coupon Insert Error: " . $e->getMessage());
            }
        }
    }
}

// 4. Fetch Existing Coupons for this Vendor
$couponsStmt = $pdo->prepare("SELECT * FROM coupons WHERE vendor_id = ? ORDER BY created_at DESC");
$couponsStmt->execute([$realVendorId]);
$coupons = $couponsStmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Load the Header
require_once __DIR__ . "/includes/header.php";
?>

<div class="container-fluid px-4 py-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-0 text-dark">Promo Codes</h3>
            <p class="text-muted small mb-0">Create and manage discount codes for your shop.</p>
        </div>
        <button class="btn btn-primary fw-bold px-4 rounded-pill" style="background-color: var(--primary-accent); border: none;" data-bs-toggle="modal" data-bs-target="#createCouponModal">
            <i class="bi bi-plus-lg me-1"></i> Create Coupon
        </button>
    </div>

    <?php if ($successMsg): ?>
        <div class="alert alert-success alert-dismissible fade show fw-bold" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($successMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="alert alert-danger alert-dismissible fade show fw-bold" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($errorMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4 py-3">Code</th>
                            <th class="py-3">Discount</th>
                            <th class="py-3">Min. Spend</th>
                            <th class="py-3">Usage</th>
                            <th class="py-3">Status</th>
                            <th class="py-3">Expires</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($coupons)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-ticket-detailed display-4 d-block mb-3 opacity-25"></i>
                                    You have not created any promo codes yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($coupons as $c): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark">
                                        <span class="bg-light border px-2 py-1 rounded text-monospace" style="letter-spacing: 1px;">
                                            <?= htmlspecialchars($c['code']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-success">
                                            <?= $c['type'] === 'percentage' ? (float)$c['value'] . '%' : '₵' . number_format($c['value'], 2) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small">
                                        <?= $c['min_order_amount'] > 0 ? '₵' . number_format($c['min_order_amount'], 2) : 'None' ?>
                                    </td>
                                    <td class="text-muted small">
                                        <?= (int)$c['used_count'] ?> / <?= $c['usage_limit'] ? (int)$c['usage_limit'] : '∞' ?>
                                    </td>
                                    <td>
                                        <?php if ($c['status'] === 'active'): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small">
                                        <?= $c['expires_at'] ? date('M j, Y', strtotime($c['expires_at'])) : 'Never' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<div class="modal fade" id="createCouponModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold">Create New Promo Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="coupons.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="create_coupon" value="1">
                    
                    <div class="row g-4">
                        <div class="col-md-12">
                            <label class="form-label fw-bold text-dark small text-uppercase mb-1">Coupon Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control fw-bold text-uppercase" placeholder="e.g. FLASH20" required style="letter-spacing: 1px;">
                            <div class="form-text">Customers will enter this at checkout. Keep it short and memorable.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark small text-uppercase mb-1">Discount Type <span class="text-danger">*</span></label>
                            <select name="type" id="discountType" class="form-select fw-bold" required>
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (₵)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark small text-uppercase mb-1">Discount Value <span class="text-danger">*</span></label>
                            <input type="number" name="value" class="form-control fw-bold" placeholder="e.g. 20" step="0.01" min="0.01" required>
                        </div>

                        <div class="col-12 mt-4">
                            <h6 class="fw-bold border-bottom pb-2 mb-3">Restrictions (Optional)</h6>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark small text-uppercase mb-1">Minimum Spend (₵)</label>
                            <input type="number" name="min_order_amount" class="form-control" placeholder="0.00" step="0.01" min="0">
                            <div class="form-text">Cart must meet this total.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark small text-uppercase mb-1">Maximum Discount Cap (₵)</label>
                            <input type="number" name="max_discount_amount" id="maxDiscount" class="form-control" placeholder="No limit" step="0.01" min="0">
                            <div class="form-text">Limit how much money a percentage code can take off.</div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-bold text-dark small text-uppercase mb-1">Total Usage Limit</label>
                            <input type="number" name="usage_limit" class="form-control" placeholder="e.g. 50" min="1">
                            <div class="form-text">How many times can this code be used in total across all customers? Leave blank for unlimited.</div>
                        </div>

                        <div class="col-12 mt-4">
                            <h6 class="fw-bold border-bottom pb-2 mb-3">Scheduling (Optional)</h6>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark small text-uppercase mb-1">Start Date</label>
                            <input type="datetime-local" name="starts_at" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark small text-uppercase mb-1">Expiration Date</label>
                            <input type="datetime-local" name="expires_at" class="form-control">
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-top-0 px-4 pb-4 pt-0">
                    <button type="button" class="btn btn-light fw-bold px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4 rounded-pill" style="background-color: var(--primary-accent); border: none;">Save Promo Code</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Disable the 'Max Discount Cap' field if the vendor chooses a Fixed Amount discount
document.getElementById('discountType').addEventListener('change', function() {
    const maxDiscountInput = document.getElementById('maxDiscount');
    if (this.value === 'fixed') {
        maxDiscountInput.value = '';
        maxDiscountInput.disabled = true;
    } else {
        maxDiscountInput.disabled = false;
    }
});
</script>

<?php 
// Assuming you have a footer partial for the vendor dashboard
if (file_exists(__DIR__ . "/includes/footer.php")) {
    require_once __DIR__ . "/includes/footer.php"; 
} else {
    echo "</div></div><script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'></script></body></html>";
}
?>