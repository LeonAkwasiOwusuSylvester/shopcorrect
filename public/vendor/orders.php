<?php
require_once __DIR__ . "/../../app/helpers/vendor_guard.php";
require_once __DIR__ . "/../../app/config/db.php";
require_once __DIR__ . "/../../app/helpers/currency.php"; 

/* --------------------------------------------------
   1. Get Vendor ID & Details
   -------------------------------------------------- */
$stmt = $pdo->prepare("SELECT id, shop_name FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION["user_id"]]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    die("Vendor profile not found.");
}

$vendorId = $vendor['id'];
$shopName = $vendor['shop_name'];

/* --------------------------------------------------
   2. Handle Delete Logic
   -------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_order') {
    $orderIdToDelete = $_POST['order_id'];

    // Security Check: Verify Vendor Ownership AND closed status
    $checkStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM order_items oi 
        JOIN orders o ON oi.order_id = o.id 
        WHERE oi.order_id = ? AND oi.vendor_id = ? AND o.status IN ('delivered', 'cancelled', 'refunded')
    ");
    $checkStmt->execute([$orderIdToDelete, $vendorId]);

    if ($checkStmt->fetchColumn() > 0) {
        $deleteStmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ? AND vendor_id = ?");
        $deleteStmt->execute([$orderIdToDelete, $vendorId]);
        $_SESSION['success'] = "Order record removed successfully.";
    } else {
        $_SESSION['error'] = "You can only delete orders that are delivered, cancelled, or refunded.";
    }
    
    // ✅ FIXED: Safe relative redirect prevents 404 errors on live servers
    header("Location: orders.php");
    exit;
}

/* --------------------------------------------------
   3. Fetch Orders Logic
   -------------------------------------------------- */
$search = $_GET['search'] ?? '';
$filterStatus = $_GET['status_filter'] ?? '';
$orders = []; 

try {
    $query = "
        SELECT 
            o.id AS order_id, 
            o.status, 
            o.created_at, 
            o.shipping_name, 
            o.shipping_phone, 
            o.shipping_address, 
            o.shipping_city, 
            o.shipping_region, 
            o.payment_method,
            o.payment_status,
            o.shipping_cost,
            o.shipping_method,
            o.notes,
            u.name AS account_name, 
            oi.quantity, 
            oi.price AS unit_price_sold, 
            oi.promo_code,
            oi.discount_amount,
            oi.commission_fee,
            oi.vendor_earning,
            oi.selected_color, 
            oi.selected_size, 
            p.name AS product_name, 
            p.image
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        JOIN users u ON o.user_id = u.id
        WHERE oi.vendor_id = ?
        AND (o.payment_method = 'cod' OR o.payment_status = 'paid')
    ";

    $params = [$vendorId];

    if ($search) {
        $query .= " AND (o.id LIKE ? OR o.shipping_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($filterStatus) {
        $query .= " AND o.status = ?";
        $params[] = $filterStatus;
    }

    $query .= " ORDER BY o.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []; 
} catch (PDOException $e) {
    $_SESSION['error'] = "Data load error: " . $e->getMessage();
}

/* --------------------------------------------------
   4. Include Header
   -------------------------------------------------- */
require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* --- ORDER CARD SPECIFIC STYLES --- */
    .order-card { background: white; border: 1px solid var(--card-border); border-radius: 12px; overflow: hidden; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: transform 0.2s ease; }
    .card-header-custom { background: white; border-bottom: 1px solid var(--card-border); padding: 16px 20px; }
    
    .product-thumb { width: 70px; height: 70px; border-radius: 8px; object-fit: cover; border: 1px solid #f1f5f9; background: #f8fafc; }
    .label-xs { font-size: 0.7rem; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 10px; display: block; }
    
    .financial-box { background: #f8fafc; border-radius: 8px; padding: 14px; border: 1px solid #f1f5f9; }
    .fin-row { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 0.85rem; color: #64748b; }
    .fin-row.total { border-top: 1px dashed #cbd5e1; margin-top: 8px; padding-top: 8px; margin-bottom: 0; font-size: 0.95rem; color: var(--primary-accent); font-weight: 700; }
    .text-net { color: #059669; } 
    .text-fee { color: #dc2626; }
    .text-discount { color: #10B981; }

    .customer-message-box { background-color: #f0f9ff; border-left: 4px solid #0ea5e9; padding: 10px 14px; border-radius: 4px; margin-top: 12px; font-size: 0.85rem; color: #0c4a6e; }

    /* Status Badges */
    .badge-status { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .status-processing { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }
    .status-shipped { background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe; }
    .status-delivered { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
    .status-cancelled { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
    .status-refund-requested { background: #FFFBEB; color: #B45309; border: 1px solid #FEF3C7; }
    .status-refunded { background: #F3E8FF; color: #6B21A8; border: 1px solid #E9D5FF; }
    
    .text-truncate-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
</style>

<div class="container-fluid px-4 py-4">

    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
        <div>
            <h4 class="fw-bold text-dark mb-1">Orders</h4>
            <p class="text-secondary mb-0 small">Overview of your sales and financial breakdowns.</p>
        </div>
        
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search ID or Name..." value="<?= htmlspecialchars($search) ?>" style="min-width: 200px;">
            <select name="status_filter" class="form-select form-select-sm" style="width: 150px;">
                <option value="">All Statuses</option>
                <option value="processing" <?= $filterStatus=='processing'?'selected':'' ?>>Processing</option>
                <option value="shipped" <?= $filterStatus=='shipped'?'selected':'' ?>>Shipped</option>
                <option value="delivered" <?= $filterStatus=='delivered'?'selected':'' ?>>Delivered</option>
                <option value="cancelled" <?= $filterStatus=='cancelled'?'selected':'' ?>>Cancelled</option>
            </select>
            <button type="submit" class="btn btn-sm btn-dark"><i class="bi bi-search"></i></button>
            <a href="orders.php" class="btn btn-sm btn-light border" title="Refresh"><i class="bi bi-arrow-counterclockwise"></i></a>
        </form>
    </div>

    <?php if (empty($orders)): ?>
        <div class="text-center py-5 bg-white rounded-3 shadow-sm border">
            <div class="mb-3"><i class="bi bi-inbox text-muted opacity-25 display-1"></i></div>
            <h5 class="fw-bold text-secondary">No orders found</h5>
        </div>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
            <?php 
                $displayName = !empty($order['shipping_name']) ? $order['shipping_name'] : $order['account_name']; 
                
                $productSubtotal = $order['unit_price_sold'] * $order['quantity'];
                
                $itemDiscount = (float)($order['discount_amount'] ?? 0);
                $promoCodeUsed = $order['promo_code'] ?? null;
                $discountedSubtotal = $productSubtotal - $itemDiscount; 
                
                $shippingCost = (float)($order['shipping_cost'] ?? 0);
                $commissionFee = (float)($order['commission_fee'] ?? 0);
                $vendorNetPayout = (float)($order['vendor_earning'] ?? 0) + $shippingCost;
                
                $actualRate = ($discountedSubtotal > 0) ? round(($commissionFee / $discountedSubtotal) * 100) : 0;

                $statusLower = strtolower(trim($order['status']));
                $badgeClass = match($statusLower) {
                    'processing', 'pending' => 'status-processing',
                    'shipped' => 'status-shipped',
                    'delivered' => 'status-delivered',
                    'cancelled' => 'status-cancelled',
                    'refund requested' => 'status-refund-requested',
                    'refunded' => 'status-refunded',
                    default => 'bg-light border text-dark'
                };

                $dbImage = $order['image'];
                $imagePath = ''; 
                if (!empty($dbImage)) {
                    $serverPathNew = __DIR__ . '/../uploads/products/' . $dbImage;
                    if (file_exists($serverPathNew)) $imagePath = '../uploads/products/' . $dbImage;
                    else $imagePath = '../uploads/' . $dbImage;
                }
            ?>
            
            <div class="order-card <?= in_array($statusLower, ['cancelled', 'refunded']) ? 'opacity-75' : '' ?>">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold text-dark">#<?= $order['order_id'] ?></span>
                        <span class="text-secondary small ms-2 ps-2 border-start">
                             <?= date("M d, Y • h:i A", strtotime($order['created_at'])) ?>
                        </span>
                    </div>
                    <span class="badge-status <?= $badgeClass ?>"><?= ucwords($statusLower) ?></span>
                </div>

                <div class="p-3 p-md-4">
                    <div class="row g-4">
                        
                        <div class="col-lg-4 border-end-lg">
                            <span class="label-xs">Item Details</span>
                            <div class="d-flex gap-3">
                                <div class="flex-shrink-0">
                                    <?php if ($imagePath): ?>
                                        <img src="<?= htmlspecialchars($imagePath) ?>" class="product-thumb">
                                    <?php else: ?>
                                        <div class="product-thumb d-flex align-items-center justify-content-center text-muted opacity-25">
                                            <i class="bi bi-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark text-truncate-2 mb-1" style="line-height: 1.3;">
                                        <?= htmlspecialchars($order['product_name']) ?>
                                    </div>
                                    
                                    <?php if(!empty($order['selected_color']) || !empty($order['selected_size'])): ?>
                                        <div class="mb-1 text-dark" style="font-size: 0.8rem;">
                                            <?php if(!empty($order['selected_color'])): ?>
                                                <span class="me-1 text-secondary">Color:</span><span class="fw-semibold"><?= htmlspecialchars($order['selected_color']) ?></span>
                                            <?php endif; ?>
                                            
                                            <?php if(!empty($order['selected_color']) && !empty($order['selected_size'])): ?>
                                                <span class="mx-1 text-muted">•</span>
                                            <?php endif; ?>

                                            <?php if(!empty($order['selected_size'])): ?>
                                                <span class="me-1 text-secondary">Size:</span><span class="fw-semibold"><?= htmlspecialchars($order['selected_size']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="text-secondary small mt-1">
                                        Qty: <span class="text-dark fw-bold"><?= $order['quantity'] ?></span> &times; <?= formatPrice($order['unit_price_sold']) ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4 border-end-lg">
                            <span class="label-xs">Delivery Details</span>
                            
                            <div class="mb-3">
                                <div class="fw-bold text-dark small mb-1">
                                    <i class="bi bi-person me-1 text-secondary"></i> <?= htmlspecialchars($displayName) ?>
                                </div>
                                <div class="text-secondary small mb-1">
                                    <i class="bi bi-telephone me-1"></i> <?= htmlspecialchars($order['shipping_phone']) ?>
                                </div>
                                
                                <div class="text-secondary small mb-1">
                                    <i class="bi bi-truck me-1"></i> 
                                    Method: <span class="text-dark fw-semibold">
                                        <?= ucwords(str_replace(['_', '-'], ' ', htmlspecialchars($order['shipping_method'] ?? 'Standard'))) ?>
                                    </span>
                                </div>
                                <div class="text-secondary small lh-sm">
                                    <i class="bi bi-geo-alt me-1"></i> 
                                    <?= htmlspecialchars($order['shipping_address']) ?>, <?= htmlspecialchars($order['shipping_city']) ?>
                                </div>
                            </div>

                            <?php if(!empty($order['notes'])): ?>
                                <div class="customer-message-box">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <i class="bi bi-chat-quote-fill"></i> 
                                        <strong style="font-size: 0.8rem;">Customer Message:</strong>
                                    </div>
                                    <div style="font-style: italic;">
                                        "<?= htmlspecialchars($order['notes']) ?>"
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-lg-4">
                            <span class="label-xs">Financial Breakdown</span>
                            
                            <div class="financial-box mb-3">
                                <div class="fin-row">
                                    <span>Product Sales</span>
                                    <span class="fw-bold text-dark"><?= formatPrice($productSubtotal) ?></span>
                                </div>
                                
                                <?php if ($itemDiscount > 0): ?>
                                    <div class="fin-row text-discount">
                                        <span>Promo (<span class="badge bg-success bg-opacity-25 text-success ms-1" style="font-size:0.6rem;"><?= htmlspecialchars($promoCodeUsed) ?></span>)</span>
                                        <span>-<?= formatPrice($itemDiscount) ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="fin-row text-fee">
                                    <span>Less Commission (<?= $actualRate ?>%)</span>
                                    <span>-<?= formatPrice($commissionFee) ?></span>
                                </div>
                                
                                <div class="fin-row text-secondary">
                                    <span>Plus Shipping Refund</span>
                                    <span>+<?= formatPrice($shippingCost) ?></span>
                                </div>
                                <div class="fin-row total">
                                    <span>Net Payout</span>
                                    <span class="text-net <?= in_array($statusLower, ['cancelled', 'refunded']) ? 'text-muted text-decoration-line-through' : '' ?>">
                                        <?= formatPrice($vendorNetPayout) ?>
                                    </span>
                                </div>
                            </div>

                            <div class="d-flex flex-column gap-2">
                                <?php if (in_array($statusLower, ['cancelled', 'refund requested', 'refunded'])): ?>
                                    <div class="alert alert-danger p-2 mb-0 text-center small fw-bold border-0 shadow-sm">
                                        <?php if($statusLower === 'cancelled'): ?>
                                            <i class="bi bi-x-circle-fill me-1"></i> Buyer Cancelled Order
                                        <?php elseif($statusLower === 'refund requested'): ?>
                                            <i class="bi bi-exclamation-triangle-fill me-1"></i> Return Requested
                                        <?php else: ?>
                                            <i class="bi bi-arrow-return-left me-1"></i> Order Refunded
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <form method="POST" action="../../routes/vendor.php" class="d-flex gap-2">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                        
                                        <select name="status" class="form-select form-select-sm border-secondary-subtle">
                                            <option value="processing" <?= $statusLower == 'processing' || $statusLower == 'pending' ? 'selected' : '' ?>>Processing</option>
                                            <option value="shipped" <?= $statusLower == 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                            <option value="delivered" <?= $statusLower == 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                        </select>
                                        
                                        <button type="submit" class="btn btn-dark btn-sm shadow-sm px-3">
                                            Update
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if(in_array($statusLower, ['delivered', 'cancelled', 'refunded'])): ?>
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to remove this record from your view?');">
                                        <input type="hidden" name="action" value="delete_order">
                                        <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm w-100 border-0" style="font-size: 0.8rem;">
                                            <i class="bi bi-trash me-1"></i> Delete Record
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div> 

<?php
// --------------------------------------------------
// 5. Include Footer
// --------------------------------------------------
require_once __DIR__ . '/includes/footer.php';
?>