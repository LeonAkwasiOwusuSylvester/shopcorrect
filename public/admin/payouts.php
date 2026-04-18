<?php
require_once __DIR__ . "/../../app/config/db.php";

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'supadmin') {
    header("Location: ../login.php");
    exit;
}

// CONFIG
$commissionPercentage = 10; 

// ---------------------------------------------------------
// 2. ACTION HANDLER (Approve/Reject/Delete)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // ACTION: UPDATE STATUS
    if ($_POST['action'] === 'update_status') {
        $payoutId = $_POST['payout_id'];
        $status = $_POST['status']; 
        $notes = $_POST['admin_notes'] ?? '';

        // Update database
        $stmt = $pdo->prepare("UPDATE payout_requests SET status = ?, admin_notes = ? WHERE id = ?");
        $result = $stmt->execute([$status, $notes, $payoutId]);
        
        if ($result) {
            $msg = ($status === 'approved') ? 'processed' : 'rejected';
            header("Location: " . $_SERVER['PHP_SELF'] . "?msg=" . $msg);
            exit;
        } else {
            // Database error fallback
            header("Location: " . $_SERVER['PHP_SELF'] . "?msg=error");
            exit;
        }
    }

    // ACTION: DELETE PAYOUT
    if ($_POST['action'] === 'delete_payout') {
        $payoutId = $_POST['payout_id'];
        $stmt = $pdo->prepare("DELETE FROM payout_requests WHERE id = ?");
        $stmt->execute([$payoutId]);
        header("Location: " . $_SERVER['PHP_SELF'] . "?msg=deleted");
        exit;
    }
}

// ---------------------------------------------------------
// 3. FETCH PAYOUT REQUESTS
// ---------------------------------------------------------
$query = "
    SELECT 
        pr.*, 
        v.shop_name, 
        u.name as owner_name,
        u.email as owner_email,
        u.phone as owner_phone,
        v.id as vendor_id
    FROM payout_requests pr
    JOIN vendors v ON pr.vendor_id = v.id
    JOIN users u ON v.user_id = u.id
    ORDER BY CASE WHEN pr.status = 'pending' THEN 1 ELSE 2 END, pr.created_at DESC
";
$requests = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// 4. INCLUDE HEADER (Brings in Sidebar, CSS, Google Translate, and Currency Helper)
require_once __DIR__ . "/includes/header.php";
?>

<style>
    /* Table Overrides */
    .table-responsive { overflow: visible !important; }
    .table thead th { 
        background-color: #FAFCFF; 
        border-bottom: 1px solid #E2E8F0; 
        color: #A3AED0; 
        font-weight: 700; 
        text-transform: uppercase; 
        font-size: 0.75rem; 
        letter-spacing: 0.05em; 
        padding: 1.2rem 1.5rem; 
    }
    .table tbody td { 
        padding: 1.2rem 1.5rem; 
        vertical-align: middle; 
        font-size: 0.9rem;
        color: var(--text-color);
        border-bottom: 1px solid #F4F7FE;
    }

    /* Components */
    .status-badge { padding: 6px 12px; border-radius: 30px; font-size: 0.7rem; font-weight: 800; text-transform: capitalize; }
    .bg-pending { background: #FFF9E6; color: #FFB547; border: 1px solid #fef08a; }
    .bg-approved { background: #E6FAF5; color: #05CD99; border: 1px solid #bbf7d0; }
    .bg-rejected { background: #FEE2E2; color: #E31A1A; border: 1px solid #fecaca; }

    .btn-process { background: #1B2559; color: white; font-weight: 600; border-radius: 10px; padding: 6px 16px; border: none; transition: 0.2s; }
    .btn-process:hover { background: #4318FF; color: white; }
    
    .modal-content { border-radius: 24px; border: none; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
</style>

<div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
    <div>
        <h6 class="text-secondary fw-bold text-uppercase mb-1" style="font-size: 11px; letter-spacing: 1px;">Finance & Accounts</h6>
        <h3 class="fw-bold mb-0" style="color: var(--shop-brand);">Payout Management</h3>
    </div>
    <div class="bg-white px-3 py-2 rounded-4 shadow-sm border d-inline-flex align-items-center">
        <div class="bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center me-2" style="width:24px;height:24px;">
            <i class="bi bi-cash-coin small"></i>
        </div>
        <span class="fw-bold small text-muted">Review & Process Earnings</span>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <?php if ($_GET['msg'] === 'processed'): ?>
        <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show rounded-4 mb-4">
            <i class="bi bi-check-circle-fill me-2"></i> <strong>Success!</strong> Payout processed and funds deducted.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($_GET['msg'] === 'rejected'): ?>
        <div class="alert alert-warning border-0 shadow-sm alert-dismissible fade show rounded-4 mb-4">
            <i class="bi bi-x-circle-fill me-2"></i> <strong>Rejected.</strong> The request was declined.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($_GET['msg'] === 'deleted'): ?>
        <div class="alert alert-danger border-0 shadow-sm alert-dismissible fade show rounded-4 mb-4">
            <i class="bi bi-trash-fill me-2"></i> <strong>Deleted.</strong> Record removed permanently.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($_GET['msg'] === 'error'): ?>
        <div class="alert alert-danger border-0 shadow-sm alert-dismissible fade show rounded-4 mb-4">
            <i class="bi bi-exclamation-octagon-fill me-2"></i> <strong>System Error.</strong> Could not update the record.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div class="glass-card p-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-4">Ref ID</th>
                    <th>Vendor Details</th>
                    <th>Request Amount</th>
                    <th>System Verification</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted small">No payout requests found.</td></tr>
                <?php else: ?>
                    <?php foreach ($requests as $pr): ?>
                        <?php 
                            // --- LOGIC START (PRESERVED) ---
                            // VERIFY VENDOR EARNINGS
                            $vid = $pr['vendor_id'];
                            
                            // A. Gross Sales
                            $s1 = $pdo->prepare("SELECT COALESCE(SUM(oi.price * oi.quantity), 0) FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE oi.vendor_id = ? AND o.status = 'delivered'");
                            $s1->execute([$vid]);
                            $gross = $s1->fetchColumn();

                            // B. Commission
                            $comm = $gross * ($commissionPercentage / 100);

                            // C. Shipping
                            $s2 = $pdo->prepare("SELECT COALESCE(SUM(o.shipping_cost), 0) FROM orders o JOIN (SELECT DISTINCT order_id FROM order_items WHERE vendor_id = ?) vo ON o.id = vo.order_id WHERE o.status = 'delivered'");
                            $s2->execute([$vid]);
                            $ship = $s2->fetchColumn();

                            // D. Previous Approved Payouts
                            $s3 = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payout_requests WHERE vendor_id = ? AND status = 'approved'");
                            $s3->execute([$vid]);
                            $paid = $s3->fetchColumn();

                            // CALC: Actual Balance Available
                            $actualBalance = ($gross - $comm + $ship) - $paid;
                            $isSafe = $actualBalance >= $pr['amount'];
                            // --- LOGIC END ---
                        ?>
                        <tr>
                            <td class="ps-4 fw-bold text-muted">#<?= $pr['id'] ?></td>
                            
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($pr['shop_name']) ?></div>
                                <div class="small text-muted">
                                    <i class="bi bi-person me-1"></i><?= htmlspecialchars($pr['owner_name']) ?> <br>
                                    <i class="bi bi-phone me-1"></i><span class="notranslate"><?= htmlspecialchars($pr['owner_phone']) ?></span>
                                </div>
                            </td>
                            
                            <td>
                                <h5 class="fw-bold text-dark mb-0 notranslate"><?= formatPrice($pr['amount']) ?></h5>
                                <small class="text-muted"><?= date('M d, Y', strtotime($pr['created_at'])) ?></small>
                            </td>

                            <td>
                                <?php if(strtolower($pr['status']) == 'pending'): ?>
                                    <div class="small mb-1 text-muted">Balance: <strong class="notranslate"><?= formatPrice($actualBalance) ?></strong></div>
                                    <?php if ($isSafe): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2">
                                            Funds Available
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-pill px-2">
                                            Insufficient Funds
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">Processed</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="status-badge bg-<?= strtolower($pr['status']) ?>">
                                    <?= strtoupper($pr['status']) ?>
                                </span>
                            </td>

                            <td class="text-end pe-4">
                                <div class="d-flex justify-content-end gap-2">
                                    <?php if (strtolower($pr['status']) === 'pending'): ?>
                                        <?php if ($isSafe): ?>
                                            <button class="btn btn-sm btn-process shadow-sm" 
                                                data-id="<?= $pr['id'] ?>"
                                                data-shop="<?= htmlspecialchars($pr['shop_name']) ?>"
                                                data-amount-formatted="<?= formatPrice($pr['amount']) ?>"
                                                data-phone="<?= htmlspecialchars($pr['owner_phone']) ?>"
                                                data-name="<?= htmlspecialchars($pr['owner_name']) ?>"
                                                onclick="openPayoutModal(this)">
                                                Process
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-secondary fw-bold px-3 shadow-sm" disabled title="Insufficient funds">Process</button>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <form method="POST" onsubmit="return confirm('Permanently delete this record?');">
                                        <input type="hidden" name="action" value="delete_payout">
                                        <input type="hidden" name="payout_id" value="<?= $pr['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-light text-danger border">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="payoutActionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <form method="POST">
                <input type="hidden" name="payout_id" id="modalPayoutId">
                
                <div class="modal-header border-0 pt-4 px-4 bg-light">
                    <div>
                        <h5 class="modal-title fw-bold text-dark">Confirm Transaction</h5>
                        <p class="mb-0 text-muted small">Process payment to vendor via Mobile Money</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body px-4 py-3">
                    <div class="bg-white border rounded-3 p-3 mb-3 shadow-sm">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-uppercase fw-bold small text-muted">Amount</span>
                            <span class="fw-bold text-success fs-4 notranslate" id="modalAmountDisplay">...</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center bg-light p-2 rounded">
                            <div>
                                <div class="fw-bold text-dark notranslate" id="modalPhoneDisplay">...</div>
                                <div class="small text-secondary" id="modalNameDisplay">...</div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-dark fw-bold" id="copyBtn" onclick="copyNumber()">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Action</label>
                        <select name="status" class="form-select form-select-lg fw-bold" required>
                            <option value="approved">✅ Approve (Funds Sent)</option>
                            <option value="rejected">❌ Reject Request</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Transaction ID / Notes</label>
                        <textarea name="admin_notes" class="form-control" rows="2" placeholder="e.g. MTN Transaction ID: 12345678"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer border-0 pb-4 px-4">
                    <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="action" value="update_status" class="btn btn-dark px-4 fw-bold" style="background: var(--shop-brand);">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
// 5. INCLUDE FOOTER
require_once __DIR__ . "/includes/footer.php"; 
?>

<script>
    const payoutModal = new bootstrap.Modal(document.getElementById('payoutActionModal'));

    function openPayoutModal(btn) {
        document.getElementById('modalPayoutId').value = btn.getAttribute('data-id');
        // Fetch pre-formatted currency string instead of building it in JS
        document.getElementById('modalAmountDisplay').textContent = btn.getAttribute('data-amount-formatted');
        document.getElementById('modalPhoneDisplay').textContent = btn.getAttribute('data-phone');
        document.getElementById('modalNameDisplay').textContent = btn.getAttribute('data-name');
        
        // Setup copy logic
        document.getElementById('copyBtn').setAttribute('data-phone', btn.getAttribute('data-phone'));
        
        payoutModal.show();
    }

    function copyNumber() {
        const phone = document.getElementById('copyBtn').getAttribute('data-phone');
        navigator.clipboard.writeText(phone).then(() => {
            const btn = document.getElementById('copyBtn');
            btn.innerHTML = '<i class="bi bi-check"></i> Copied';
            setTimeout(() => { btn.innerHTML = '<i class="bi bi-clipboard"></i> Copy'; }, 2000);
        });
    }
</script>