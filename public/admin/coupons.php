<?php
require_once __DIR__ . "/../../app/config/db.php";

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'supadmin') {
    header("Location: ../login.php");
    exit;
}

// 2. ACTION HANDLERS (Create, Toggle, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // ADD NEW COUPON
    if ($_POST['action'] === 'add') {
        $code = strtoupper(trim($_POST['code']));
        $type = $_POST['type'];
        $value = (float)$_POST['value'];
        $min_order = !empty($_POST['min_order_amount']) ? (float)$_POST['min_order_amount'] : 0.00;
        $max_discount = !empty($_POST['max_discount_amount']) ? (float)$_POST['max_discount_amount'] : null;
        $usage_limit = !empty($_POST['usage_limit']) ? (int)$_POST['usage_limit'] : null;
        $starts_at = !empty($_POST['starts_at']) ? $_POST['starts_at'] : null;
        $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

        try {
            $stmt = $pdo->prepare("
                INSERT INTO coupons (code, type, value, min_order_amount, max_discount_amount, usage_limit, starts_at, expires_at, vendor_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL)
            ");
            $stmt->execute([$code, $type, $value, $min_order, $max_discount, $usage_limit, $starts_at, $expires_at]);
            header("Location: coupons.php?msg=added");
            exit;
        } catch(PDOException $e) {
            // Usually triggers if the promo code already exists (UNIQUE constraint)
            header("Location: coupons.php?msg=error");
            exit;
        }
    }

    // TOGGLE STATUS (Active/Inactive)
    if ($_POST['action'] === 'toggle') {
        $id = (int)$_POST['coupon_id'];
        $newStatus = $_POST['current_status'] === 'active' ? 'inactive' : 'active';
        $pdo->prepare("UPDATE coupons SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
        header("Location: coupons.php?msg=updated");
        exit;
    }

    // DELETE COUPON
    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['coupon_id'];
        $pdo->prepare("DELETE FROM coupons WHERE id = ?")->execute([$id]);
        header("Location: coupons.php?msg=deleted");
        exit;
    }
}

// 3. FETCH GLOBAL COUPONS (vendor_id IS NULL)
$coupons = $pdo->query("SELECT * FROM coupons WHERE vendor_id IS NULL ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// 4. INCLUDE HEADER
require_once __DIR__ . "/includes/header.php";
?>

<style>
    .table thead th { background-color: #FAFCFF; color: #A3AED0; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; padding: 1.2rem 1.5rem; border-bottom: 1px solid #E2E8F0; }
    .table tbody td { padding: 1rem 1.5rem; vertical-align: middle; font-size: 0.9rem; color: var(--text-color); border-bottom: 1px solid #F4F7FE; }
    
    .badge-status { padding: 6px 12px; border-radius: 30px; font-size: 0.7rem; font-weight: 800; text-transform: capitalize; }
    .status-active { background: #E6FAF5; color: #05CD99; }
    .status-inactive { background: #FEE2E2; color: #E31A1A; }
    
    .promo-code-box { background: #F8FAFC; border: 1px dashed #cbd5e1; padding: 4px 10px; border-radius: 8px; font-family: monospace; font-weight: 800; color: var(--shop-brand); font-size: 1.1rem; letter-spacing: 1px; display: inline-block; }
</style>

<div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
    <div>
        <h6 class="text-secondary fw-bold text-uppercase mb-1" style="font-size: 11px; letter-spacing: 1px;">Marketing</h6>
        <h3 class="fw-bold mb-0" style="color: var(--shop-brand);">Promo Codes</h3>
    </div>
    <button type="button" class="btn fw-bold text-white shadow-sm" style="background: var(--shop-brand); border-radius: 12px; padding: 10px 20px;" data-bs-toggle="modal" data-bs-target="#addCouponModal">
        <i class="bi bi-plus-lg me-2"></i> Create Global Code
    </button>
</div>

<?php if (isset($_GET['msg'])): ?>
    <?php 
        $msgs = [
            'added' => ['success', 'Promo code created successfully!'],
            'updated' => ['success', 'Coupon status updated.'],
            'deleted' => ['danger', 'Coupon permanently deleted.'],
            'error' => ['warning', 'Error: That promo code already exists.']
        ];
        $type = $msgs[$_GET['msg']][0] ?? 'info';
        $text = $msgs[$_GET['msg']][1] ?? 'Action completed.';
    ?>
    <div class="alert alert-<?= $type ?> border-0 shadow-sm rounded-4 mb-4 alert-dismissible fade show fw-bold">
        <?= $text ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="glass-card p-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-4">Promo Code</th>
                    <th>Discount Value</th>
                    <th>Usage Limit</th>
                    <th>Expiration</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($coupons)): ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted small"><i class="bi bi-ticket-perforated fs-1 d-block mb-2 opacity-25"></i> No promo codes created yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($coupons as $c): ?>
                        <tr>
                            <td class="ps-4"><span class="promo-code-box notranslate"><?= htmlspecialchars($c['code']) ?></span></td>
                            <td>
                                <div class="fw-bold text-dark notranslate">
                                    <?= $c['type'] === 'percentage' ? rtrim(rtrim($c['value'], '0'), '.') . '%' : formatPrice($c['value']) ?> OFF
                                </div>
                                <?php if($c['min_order_amount'] > 0): ?>
                                    <div class="text-muted small">Min Spend: <span class="notranslate"><?= formatPrice($c['min_order_amount']) ?></span></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold text-secondary">
                                    <?= $c['used_count'] ?> / <?= $c['usage_limit'] ? $c['usage_limit'] : '∞' ?>
                                </div>
                            </td>
                            <td>
                                <?php if($c['expires_at']): ?>
                                    <div class="small fw-bold <?= strtotime($c['expires_at']) < time() ? 'text-danger' : 'text-success' ?>">
                                        <?= date('M d, Y', strtotime($c['expires_at'])) ?>
                                    </div>
                                <?php else: ?>
                                    <span class="small text-muted fw-bold">Never</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge-status status-<?= $c['status'] ?>"><?= $c['status'] ?></span></td>
                            <td class="text-end pe-4">
                                <form method="POST" class="d-inline-flex gap-2">
                                    <input type="hidden" name="coupon_id" value="<?= $c['id'] ?>">
                                    <input type="hidden" name="current_status" value="<?= $c['status'] ?>">
                                    
                                    <button type="submit" name="action" value="toggle" class="btn btn-sm <?= $c['status'] === 'active' ? 'btn-outline-warning' : 'btn-outline-success' ?> shadow-sm fw-bold">
                                        <?= $c['status'] === 'active' ? '<i class="bi bi-pause-fill"></i> Disable' : '<i class="bi bi-play-fill"></i> Enable' ?>
                                    </button>
                                    
                                    <button type="submit" name="action" value="delete" class="btn btn-sm btn-outline-danger shadow-sm" onclick="return confirm('Permanently delete this code?');" title="Delete">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addCouponModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-header border-0 pt-4 px-4 bg-light">
                    <h5 class="modal-title fw-bold text-dark">Create Promo Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body px-4 py-3">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Promo Code (e.g. LAUNCH20)</label>
                        <input type="text" name="code" class="form-control fw-bold" style="text-transform: uppercase;" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Discount Type</label>
                            <select name="type" class="form-select fw-bold" required>
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Discount Value</label>
                            <input type="number" step="0.01" name="value" class="form-control fw-bold" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Minimum Spend Required (Optional)</label>
                        <input type="number" step="0.01" name="min_order_amount" class="form-control" placeholder="0.00">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Usage Limit</label>
                            <input type="number" name="usage_limit" class="form-control" placeholder="Unlimited if blank">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Expiration Date</label>
                            <input type="datetime-local" name="expires_at" class="form-control text-muted">
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-0 pb-4 px-4">
                    <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white px-4 fw-bold shadow-sm" style="background: var(--shop-brand);">Save Campaign</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/includes/footer.php"; ?>