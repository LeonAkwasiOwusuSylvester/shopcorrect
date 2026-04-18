<?php
require_once __DIR__ . "/../../app/config/db.php";

// 1. SESSION & SECURITY (Updated to allow Agents and Support)
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['supadmin', 'country_agent', 'support'])) {
    header("Location: ../login.php");
    exit;
}

$userRole = $_SESSION['role'];
$managedCountry = $_SESSION['managed_country'] ?? null;

// =========================================================
// 2. ACTION HANDLERS
// =========================================================

// A. HANDLE EXPORT (CSV) - Support agents cannot export data
if (isset($_GET['action']) && $_GET['action'] === 'export' && $userRole !== 'support') {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=ShopCorrect_Orders_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Order ID', 'Customer Name', 'Gross Total', 'Promo Code', 'Discount', 'Admin Commission', 'Status', 'Date Created']);
    
    // Dynamic Query based on Role for Export
    $exportQuery = "
        SELECT o.id, o.shipping_name, o.total_amount, 
        o.promo_code, o.discount_amount,
        (SELECT SUM(commission_fee) FROM order_items WHERE order_id = o.id) as admin_comm,
        CASE 
            WHEN o.notes LIKE '%[REFUND REQUESTED]%' THEN 'Refund Requested'
            ELSE COALESCE(o.status, 'Pending')
        END as status, 
        o.created_at 
        FROM orders o 
    ";
    
    $exportParams = [];
    if ($userRole === 'country_agent') {
        $exportQuery .= " WHERE o.shipping_country = ? ";
        $exportParams[] = $managedCountry;
    }
    
    $exportQuery .= " ORDER BY o.created_at DESC";
    
    $stmt = $pdo->prepare($exportQuery);
    $stmt->execute($exportParams);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { fputcsv($output, $row); }
    fclose($output);
    exit;
}

// B. HANDLE DELETE (Only Super Admin can delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && $userRole === 'supadmin') {
    try {
        $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$_POST['delete_id']]);
        $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$_POST['delete_id']]);
        header("Location: " . $_SERVER['PHP_SELF'] . "?msg=deleted");
        exit;
    } catch (PDOException $e) {
        die("Error deleting order: " . $e->getMessage());
    }
}

// C. HANDLE UPDATE (Super Admin & Country Agents)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_id']) && $userRole !== 'support') {
    try {
        $oid = $_POST['update_order_id'];
        
        // If it's a country agent, double check they own this order before updating!
        if ($userRole === 'country_agent') {
            $checkStmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND shipping_country = ?");
            $checkStmt->execute([$oid, $managedCountry]);
            if (!$checkStmt->fetch()) {
                die("Unauthorized action. You can only update orders in your assigned country.");
            }
        }

        $stmt = $pdo->prepare("UPDATE orders SET status=?, carrier=?, tracking_number=?, admin_notes=? WHERE id=?");
        $stmt->execute([$_POST['status'], $_POST['carrier']??'', $_POST['tracking_number']??'', $_POST['admin_notes']??'', $oid]);
        header("Location: " . $_SERVER['PHP_SELF'] . "?msg=updated");
        exit;
    } catch (PDOException $e) {
        die("Error updating order: " . $e->getMessage());
    }
}

// =========================================================
// 3. FETCH DATA (DYNAMIC BASED ON ROLE)
// =========================================================
$orders = [];
$pendingRefunds = 0;

try {
    $mainQuery = "
        SELECT o.id, 
               CASE 
                   WHEN o.notes LIKE '%[REFUND REQUESTED]%' THEN 'Refund Requested'
                   ELSE COALESCE(o.status, 'Pending')
               END as status, 
               o.created_at, o.total_amount, o.shipping_name, u.email,
               o.promo_code, o.discount_amount,
               (SELECT SUM(commission_fee) FROM order_items WHERE order_id = o.id) as total_admin_commission
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
    ";
    
    $badgeQuery = "
        SELECT COUNT(*) FROM orders o
        WHERE (LOWER(TRIM(o.status)) = 'refund requested' 
           OR o.notes LIKE '%[REFUND REQUESTED]%'
           OR (LOWER(TRIM(o.status)) = 'cancelled' AND LOWER(TRIM(o.payment_status)) = 'paid'))
    ";

    $queryParams = [];
    
    // Apply Country Filter for Agents
    if ($userRole === 'country_agent') {
        $mainQuery .= " WHERE o.shipping_country = ? ";
        $badgeQuery .= " AND o.shipping_country = ? ";
        $queryParams[] = $managedCountry;
    }

    $mainQuery .= " ORDER BY o.created_at DESC";

    // Execute Main Order Query
    $stmt = $pdo->prepare($mainQuery);
    $stmt->execute($queryParams);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Execute Badge Query
    $badgeStmt = $pdo->prepare($badgeQuery);
    $badgeStmt->execute($queryParams);
    $pendingRefunds = $badgeStmt->fetchColumn();

} catch (PDOException $e) {
    die("<div style='color:red; padding:20px; font-family:sans-serif;'><strong>Database Error:</strong> " . $e->getMessage() . "</div>");
}

// 4. INCLUDE HEADER
require_once __DIR__ . "/includes/header.php";
?>

<style>
    /* Table & Status UI overrides */
    .table-responsive { overflow: visible !important; }
    .table thead th { background-color: #FAFCFF; color: #A3AED0; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; padding: 1.2rem 1.5rem; border-bottom: 1px solid #E2E8F0; }
    .table tbody td { padding: 1.2rem 1.5rem; vertical-align: middle; font-size: 0.9rem; color: var(--text-color); border-bottom: 1px solid #F4F7FE; }

    .badge-status { padding: 6px 14px; border-radius: 30px; font-size: 0.7rem; font-weight: 800; text-transform: capitalize; }
    .status-paid, .status-delivered { background: #E6FAF5; color: #05CD99; border: 1px solid #bbf7d0; }
    .status-processing, .status-pending { background: #FFF9E6; color: #FFB547; border: 1px solid #fef08a; }
    .status-shipped { background: #E9EDFE; color: #4318FF; border: 1px solid #bfdbfe; }
    .status-cancelled, .status-failed { background: #FEE2E2; color: #E31A1A; border: 1px solid #fecaca; }
    .status-refund-requested { background: #FFFBEB; color: #B45309; border: 1px solid #fde68a; }
    .status-refunded { background: #F3E8FF; color: #6B21A8; border: 1px solid #e9d5ff; }

    .modal-content { border-radius: 24px; border: none; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
    .summary-box { background: #F8FAFC; padding: 20px; border-radius: 16px; height: 100%; border: 1px solid #F1F5F9; }
</style>

<div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
    <div>
        <h6 class="text-secondary fw-bold text-uppercase mb-1" style="font-size: 11px; letter-spacing: 1px;">Logistics & Sales</h6>
        <h3 class="fw-bold mb-0" style="color: var(--shop-brand);">Order Directory</h3>
    </div>
    
    <?php if ($userRole !== 'support'): ?>
        <a href="?action=export" class="btn btn-white shadow-sm fw-bold border bg-white" style="border-radius: 12px; color: var(--shop-brand);">
            <i class="bi bi-cloud-arrow-down-fill me-2"></i> Export CSV
        </a>
    <?php endif; ?>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 alert-dismissible fade show">
        <i class="bi bi-check-circle-fill me-2"></i> Action completed successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (empty($orders)): ?>
    <div class="glass-card text-center py-5">
        <i class="bi bi-inbox text-muted opacity-25" style="font-size: 4rem;"></i>
        <h5 class="fw-bold text-secondary mt-3">No orders yet</h5>
        <p class="text-muted">When customers place orders in your region, they will appear here.</p>
    </div>
<?php else: ?>
    <div class="glass-card p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Order ID</th>
                        <th>Customer / Date</th>
                        <th>Gross Amount</th>
                        <th>Discount</th> 
                        <th>Commission</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <?php 
                        $statusClean = str_replace(' ', '-', strtolower(trim($o['status']))); 
                        $discountAmt = (float)($o['discount_amount'] ?? 0);
                    ?>
                    <tr>
                        <td class="ps-4 fw-bold notranslate" style="color: var(--shop-accent);">#<?= $o["id"] ?></td>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($o["shipping_name"] ?? 'Guest') ?></div>
                            <div class="text-muted small"><?= date("M d, Y • h:i A", strtotime($o["created_at"])) ?></div>
                        </td>
                        <td class="fw-bold notranslate"><?= formatPrice($o["total_amount"]) ?></td>
                        
                        <td>
                            <?php if($discountAmt > 0): ?>
                                <div class="text-success fw-bold notranslate">-<?= formatPrice($discountAmt) ?></div>
                                <div class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size:0.65rem;"><?= htmlspecialchars($o['promo_code']) ?></div>
                            <?php else: ?>
                                <span class="text-muted small">None</span>
                            <?php endif; ?>
                        </td>

                        <td class="text-primary fw-bold notranslate"><?= formatPrice($o["total_admin_commission"] ?? 0) ?></td>
                        <td><span class="badge-status status-<?= $statusClean ?>"><?= ucwords($o['status']) ?></span></td>
                        <td class="text-end pe-4">
                            
                            <?php if ($userRole === 'support'): ?>
                                <button class="btn btn-light btn-sm fw-bold border" onclick="openOrderModal(<?= $o['id'] ?>, 'view', '<?= htmlspecialchars($o['status']) ?>')">
                                    <i class="bi bi-eye"></i> View
                                </button>
                            <?php else: ?>
                                <div class="dropdown">
                                    <button class="btn btn-light border-0 btn-sm rounded-circle p-2" type="button" data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                        <li><button type="button" class="dropdown-item" onclick="openOrderModal(<?= $o['id'] ?>, 'view', '<?= htmlspecialchars($o['status']) ?>')"><i class="bi bi-eye me-2 text-primary"></i> View Details</button></li>
                                        <li><button type="button" class="dropdown-item" onclick="openOrderModal(<?= $o['id'] ?>, 'edit', '<?= htmlspecialchars($o['status']) ?>')"><i class="bi bi-pencil-square me-2 text-warning"></i> Update Status</button></li>
                                        
                                        <?php if ($userRole === 'supadmin'): ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" onsubmit="return confirm('Delete permanently?');">
                                                    <input type="hidden" name="delete_id" value="<?= $o['id'] ?>">
                                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash3 me-2"></i> Delete Order</button>
                                                </form>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<div class="modal fade" id="orderModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0 align-items-center">
                <h5 class="modal-title fw-bold mb-0" style="color: var(--shop-brand);">Order Intelligence</h5>
                <span id="modalStatusBadge" class="badge-status ms-3"></span>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <ul class="nav nav-pills nav-fill mb-4 p-1 rounded-3" style="background: #F1F5F9;">
                    <li class="nav-item"><button class="nav-link active fw-bold py-2 rounded-3" id="view-tab" data-bs-toggle="tab" data-bs-target="#view-pane">Overview</button></li>
                    <?php if ($userRole !== 'support'): ?>
                        <li class="nav-item"><button class="nav-link fw-bold py-2 rounded-3" id="edit-tab" data-bs-toggle="tab" data-bs-target="#edit-pane">Edit Logistics</button></li>
                    <?php endif; ?>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="view-pane">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="summary-box">
                                    <span class="text-muted small fw-bold text-uppercase d-block mb-2">Shipping To</span>
                                    <h5 class="fw-bold mb-1" id="viewName">Loading...</h5>
                                    <p class="small text-muted mb-2" id="viewAddress">---</p>
                                    <div class="small fw-bold"><i class="bi bi-telephone me-1"></i> <span id="viewPhone">---</span></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="summary-box" style="background: var(--shop-brand); color: white; border: none;">
                                    <span class="text-white-50 small fw-bold text-uppercase d-block mb-3">Financials</span>
                                    <div class="d-flex justify-content-between mb-1"><span class="small opacity-75">Customer Paid</span><span class="fw-bold notranslate" id="viewTotal">---</span></div>
                                    
                                    <div class="d-flex justify-content-between mb-1 d-none" id="modalDiscountRow">
                                        <span class="small text-success">Promo Discount</span>
                                        <span class="fw-bold text-success notranslate" id="viewTotalDiscount">---</span>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between mb-3 border-bottom border-white border-opacity-25 pb-2"><span class="small text-warning">Admin Comm.</span><span class="fw-bold text-warning notranslate" id="viewAdminCommission">---</span></div>
                                    <div class="d-flex justify-content-between align-items-center"><span class="small opacity-75">Vendor Payout</span><h4 class="fw-bold mb-0 notranslate" id="viewVendorEarning">---</h4></div>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive border rounded-4">
                            <table class="table table-sm mb-0">
                                <thead class="bg-light"><tr class="small text-uppercase text-muted"><th class="ps-3 py-2">Item</th><th>Price</th><th>Qty</th><th class="text-end pe-3">Net</th></tr></thead>
                                <tbody id="itemsTableBody" class="small fw-bold">
                                    <tr><td colspan="4" class="text-center py-3 text-muted">Fetching items...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <?php if ($userRole !== 'support'): ?>
                    <div class="tab-pane fade" id="edit-pane">
                        <form method="POST">
                            <input type="hidden" name="update_order_id" id="editOrderId">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-muted">Order Status</label>
                                    <select class="form-select form-select-lg fw-bold" name="status" id="editStatus">
                                        <option value="pending">Pending</option>
                                        <option value="paid">Paid</option>
                                        <option value="processing">Processing</option>
                                        <option value="shipped">Shipped</option>
                                        <option value="delivered">Delivered</option>
                                        <option value="cancelled">Cancelled</option>
                                        <option value="refund requested">Refund Requested</option>
                                        <option value="refunded">Refunded</option>
                                    </select>
                                </div>
                                <div class="col-md-6"><label class="form-label small fw-bold text-muted">Carrier</label><input type="text" class="form-control" name="carrier" id="editCarrier"></div>
                                <div class="col-md-6"><label class="form-label small fw-bold text-muted">Tracking #</label><input type="text" class="form-control" name="tracking_number" id="editTracking"></div>
                                <div class="col-12"><label class="form-label small fw-bold text-muted">Admin Notes</label><textarea class="form-control" name="admin_notes" id="editNotes" rows="3"></textarea></div>
                                <div class="col-12 mt-4"><button type="submit" class="btn btn-primary w-100 py-3 rounded-3 fw-bold" style="background: var(--shop-brand); border:none;">Update Order Status</button></div>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/includes/footer.php"; ?>

<script>
const fxRate = <?= $_SESSION['exchange_rate'] ?? 1 ?>;
const fxSymbol = '<?= $_SESSION['currency_symbol'] ?? '₵' ?>';

function formatJsPrice(baseAmount) {
    return fxSymbol + (parseFloat(baseAmount || 0) * fxRate).toFixed(2);
}

function openOrderModal(orderId, mode, currentStatus) {
    const modalEl = document.getElementById('orderModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    const tabToTrigger = mode === 'view' ? 'view-tab' : 'edit-tab';
    const triggerEl = document.getElementById(tabToTrigger);
    if (triggerEl) {
        const tabTrigger = new bootstrap.Tab(triggerEl);
        tabTrigger.show();
    }

    document.getElementById('viewName').textContent = 'Loading...';
    document.getElementById('itemsTableBody').innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">Fetching items...</td></tr>';
    document.getElementById('modalDiscountRow').classList.add('d-none');
    
    const statusClean = currentStatus.toLowerCase().trim().replace(/\s+/g, '-');
    const headerBadge = document.getElementById('modalStatusBadge');
    headerBadge.textContent = currentStatus.toUpperCase();
    headerBadge.className = 'badge-status ms-3 status-' + statusClean;

    fetch(`api-get-order.php?id=${orderId}`)
        .then(res => res.json())
        .then(data => {
            if(data.error) {
                document.getElementById('viewName').textContent = 'Error loading data';
                return;
            }
            const o = data.order;
            const items = data.items;

            document.getElementById('viewName').textContent = o.shipping_name || 'Guest';
            document.getElementById('viewAddress').textContent = (o.shipping_address || '') + ', ' + (o.shipping_city || '');
            document.getElementById('viewPhone').textContent = o.shipping_phone || 'N/A';

            let totalComm = 0, totalVendor = 0, itemsHtml = '';
            
            if (items.length === 0) {
                itemsHtml = '<tr><td colspan="4" class="text-center py-3 text-muted">No items found for this order.</td></tr>';
            } else {
                items.forEach(i => {
                    totalComm += parseFloat(i.commission_fee || 0);
                    totalVendor += parseFloat(i.vendor_earning || 0);
                    
                    let itemDiscountBadge = '';
                    if (i.discount_amount > 0) {
                        itemDiscountBadge = `<span class="badge bg-success bg-opacity-10 text-success ms-2" style="font-size:0.6rem;">-${formatJsPrice(i.discount_amount)}</span>`;
                    }

                    // ADDED: Extract and format size/color specs
                    let specHtml = '';
                    let specs = [];
                    if (i.selected_size && i.selected_size !== '') specs.push('Size: ' + i.selected_size);
                    if (i.selected_color && i.selected_color !== '') specs.push('Color: ' + i.selected_color);
                    
                    if (specs.length > 0) {
                        specHtml = `<div class="small text-secondary fw-normal mt-1" style="font-size: 0.75rem;">${specs.join(' | ')}</div>`;
                    }

                    itemsHtml += `
                        <tr>
                            <td class="ps-3 py-3 fw-bold" style="color:var(--shop-brand)">
                                ${i.product_name} ${itemDiscountBadge}
                                ${specHtml}
                            </td>
                            <td class="notranslate">${formatJsPrice(i.price)}</td>
                            <td>${i.quantity}</td>
                            <td class="text-end pe-3 fw-bold text-success notranslate">${formatJsPrice(i.vendor_earning)}</td>
                        </tr>`;
                });
            }

            document.getElementById('viewTotal').textContent = formatJsPrice(o.total_amount);
            document.getElementById('viewAdminCommission').textContent = formatJsPrice(totalComm);
            document.getElementById('viewVendorEarning').textContent = formatJsPrice(totalVendor);
            
            if (parseFloat(o.discount_amount) > 0) {
                document.getElementById('modalDiscountRow').classList.remove('d-none');
                document.getElementById('viewTotalDiscount').textContent = '-' + formatJsPrice(o.discount_amount);
            }

            document.getElementById('itemsTableBody').innerHTML = itemsHtml;

            const editOrderId = document.getElementById('editOrderId');
            if (editOrderId) {
                editOrderId.value = o.id;
                let statusSelect = document.getElementById('editStatus');
                statusSelect.value = currentStatus.toLowerCase();
                if(statusSelect.selectedIndex === -1) { statusSelect.value = 'pending'; } 

                document.getElementById('editCarrier').value = o.carrier || '';
                document.getElementById('editTracking').value = o.tracking_number || '';
                document.getElementById('editNotes').value = o.admin_notes || '';
            }
        })
        .catch(err => {
            document.getElementById('viewName').textContent = 'Connection Error';
            document.getElementById('itemsTableBody').innerHTML = '<tr><td colspan="4" class="text-center py-3 text-danger">Failed to load items.</td></tr>';
        });
}
</script>