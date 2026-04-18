<?php
// Path: shopcorrect/public/my-orders.php

require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/../app/helpers/currency.php"; // Added currency helper

/* -------------------------------------------------
   1. LOGIC & AUTH
------------------------------------------------- */
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION["user_id"];

// A. HANDLE ORDER DELETION (Hide from history)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order_id'])) {
    $orderId = (int) $_POST['delete_order_id'];

    try {
        $pdo->beginTransaction();
        $checkStmt = $pdo->prepare("SELECT id, status FROM orders WHERE id = ? AND user_id = ?");
        $checkStmt->execute([$orderId, $userId]);
        $order = $checkStmt->fetch();
        $status = strtolower(trim($order['status'] ?? ''));

        if ($order && in_array($status, ['delivered', 'cancelled', 'failed', 'refunded'])) {
            $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$orderId]);
            $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$orderId]);
            $pdo->commit();
            $_SESSION['success'] = "Order #{$orderId} removed from history.";
        } else {
            $pdo->rollBack();
            $_SESSION['error'] = "Order cannot be deleted. It must be Delivered, Cancelled, or Refunded first.";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// B. HANDLE ORDER CANCELLATION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order_id'])) {
    $orderId = (int) $_POST['cancel_order_id'];

    try {
        $pdo->beginTransaction();
        
        $checkStmt = $pdo->prepare("SELECT id, status FROM orders WHERE id = ? AND user_id = ?");
        $checkStmt->execute([$orderId, $userId]);
        $orderToCancel = $checkStmt->fetch();
        
        if ($orderToCancel) {
            $currentStatus = strtolower(trim($orderToCancel['status'] ?? ''));
            
            if (in_array($currentStatus, ['processing', 'pending'])) {
                // Fetch items to restore stock
                $itemsStmt = $pdo->prepare("SELECT product_id, quantity, selected_color, selected_size FROM order_items WHERE order_id = ?");
                $itemsStmt->execute([$orderId]);
                $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $restoreVariantStock = $pdo->prepare("UPDATE product_variants SET stock = stock + ? WHERE product_id = ? AND COALESCE(color, '') = COALESCE(?, '') AND COALESCE(size, '') = COALESCE(?, '')");
                $restoreBaseStock = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");

                foreach ($orderItems as $item) {
                    if (!empty($item['selected_color']) || !empty($item['selected_size'])) {
                        $restoreVariantStock->execute([$item['quantity'], $item['product_id'], $item['selected_color'] ?? '', $item['selected_size'] ?? '']);
                    } else {
                        $restoreBaseStock->execute([$item['quantity'], $item['product_id']]);
                    }
                }

                $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$orderId]);
                $pdo->commit();
                
                $_SESSION['success'] = "Order #{$orderId} has been successfully cancelled.";
            } else {
                $pdo->rollBack();
                $_SESSION['error'] = "This order is currently marked as '" . ucfirst($currentStatus) . "' and can no longer be cancelled.";
            }
        } else {
            $pdo->rollBack();
            $_SESSION['error'] = "We could not find this order to cancel. Please try again.";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error'] = "System Error: " . $e->getMessage();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// C. HANDLE REFUND REQUEST (7-DAY POLICY APPLIED)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refund_order_id'])) {
    $orderId = (int) $_POST['refund_order_id'];
    $reason = trim($_POST['refund_reason']);

    try {
        // Fetch order along with the updated_at timestamp
        $checkStmt = $pdo->prepare("SELECT id, status, updated_at, created_at FROM orders WHERE id = ? AND user_id = ?");
        $checkStmt->execute([$orderId, $userId]);
        $orderToRefund = $checkStmt->fetch();
        
        if ($orderToRefund) {
            $currentStatus = strtolower(trim($orderToRefund['status'] ?? ''));
            
            if ($currentStatus === 'delivered') {
                
                // Safely calculate days passed
                $timestampToUse = !empty($orderToRefund['updated_at']) ? $orderToRefund['updated_at'] : $orderToRefund['created_at'];
                $daysPassed = (time() - strtotime($timestampToUse)) / 86400; 
                
                if ($daysPassed <= 7) {
                    // Append the refund reason to the order notes and change status
                    $refundNote = "\n[REFUND REQUESTED]: " . $reason;
                    $stmt = $pdo->prepare("UPDATE orders SET status = 'refund requested', notes = CONCAT(IFNULL(notes, ''), ?) WHERE id = ?");
                    $stmt->execute([$refundNote, $orderId]);
                    
                    $_SESSION['success'] = "Refund request for Order #{$orderId} has been submitted to support.";
                } else {
                    $_SESSION['error'] = "The 7-day return window for this order has expired.";
                }
                
            } else {
                $_SESSION['error'] = "You can only request a refund for items that have been delivered.";
            }
        } else {
            $_SESSION['error'] = "We could not locate this order.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "System Error: " . $e->getMessage();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

/* -------------------------------------------------
   2. FETCH ORDERS
------------------------------------------------- */
$sql = "SELECT * FROM orders WHERE user_id = ? 
        AND (
            LOWER(payment_status) = 'paid' 
            OR LOWER(payment_method) LIKE '%cod%' 
            OR LOWER(payment_method) LIKE '%cash%'
        )
        ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

require_once __DIR__ . "/partials/navbar.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Orders | ShopCorrect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root { 
            --sc-primary: #0B2447; 
            --sc-primary-hover: #1e3a8a;
            --sc-bg: #f8fafc; 
            --sc-border: #e2e8f0;
            --sc-text-muted: #64748b;
        }
        body { 
            background-color: var(--sc-bg); 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            color: #334155; 
        }
        
        /* Header Area */
        .page-header { 
            background: white; 
            padding: 2.5rem 0; 
            border-bottom: 1px solid var(--sc-border); 
            margin-bottom: 2.5rem; 
        }
        .page-title { 
            color: var(--sc-primary); 
            font-weight: 800; 
            letter-spacing: -0.5px; 
            font-size: clamp(1.5rem, 4vw, 2rem);
        }

        /* Order Card */
        .order-card { 
            background: white; 
            border: 1px solid var(--sc-border);
            border-radius: 16px; 
            margin-bottom: 2rem; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            overflow: hidden;
        }
        .order-card:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 10px 15px -3px rgba(11, 36, 71, 0.08); 
            border-color: #cbd5e1;
        }

        /* Order Header Grid */
        .order-header { 
            background: #f8fafc; 
            padding: 1.25rem 1.5rem; 
            border-bottom: 1px solid var(--sc-border); 
        }
        .header-label { 
            font-size: 0.72rem; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
            color: var(--sc-text-muted); 
            font-weight: 700; 
            margin-bottom: 4px; 
            display: block;
        }
        .header-value { 
            font-size: 0.95rem; 
            font-weight: 700; 
            color: var(--sc-primary); 
        }

        /* Status Badges */
        .status-badge { 
            display: inline-flex; 
            align-items: center; 
            gap: 6px; 
            padding: 6px 14px; 
            border-radius: 50px; 
            font-size: 0.75rem; 
            font-weight: 800; 
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-dot { width: 6px; height: 6px; border-radius: 50%; }
        
        .status-delivered { background: #dcfce7; color: #166534; }
        .status-delivered .status-dot { background: #166534; }
        
        .status-processing { background: #fff7ed; color: #9a3412; }
        .status-processing .status-dot { background: #9a3412; }
        
        .status-shipped { background: #eff6ff; color: #1e40af; }
        .status-shipped .status-dot { background: #1e40af; }
        
        .status-cancelled { background: #fef2f2; color: #991b1b; }
        .status-cancelled .status-dot { background: #991b1b; }

        .status-refund-requested { background: #f3e8ff; color: #6b21a8; }
        .status-refund-requested .status-dot { background: #6b21a8; }

        /* Products List */
        .product-item { 
            padding: 1.5rem; 
            border-bottom: 1px dashed var(--sc-border); 
        }
        .product-item:last-child { border-bottom: none; }
        .img-box { 
            width: 80px; 
            height: 80px; 
            border-radius: 12px; 
            background: #f1f5f9; 
            border: 1px solid var(--sc-border); 
            overflow: hidden; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            flex-shrink: 0;
        }
        .img-box img { 
            width: 100%; 
            height: 100%; 
            object-fit: contain; 
            transition: transform 0.3s ease; 
            mix-blend-mode: multiply;
        }
        .img-box:hover img { transform: scale(1.1); }
        .spec-pill { 
            background: #f1f5f9; 
            color: #475569; 
            font-size: 0.75rem; 
            padding: 4px 10px; 
            border-radius: 8px; 
            font-weight: 600; 
            border: 1px solid #e2e8f0;
        }

        /* Footer Actions */
        .card-footer-custom { 
            background: white; 
            padding: 1.25rem 1.5rem; 
            border-top: 1px solid var(--sc-border); 
            display: flex; 
            justify-content: flex-end; 
            align-items: center;
            flex-wrap: wrap; 
            gap: 12px;
        }
        
        /* Custom Buttons */
        .btn-action { 
            font-size: 0.85rem; 
            font-weight: 700; 
            border-radius: 50px;
            padding: 0.6rem 1.2rem;
            display: inline-flex; 
            align-items: center; 
            gap: 6px; 
            transition: all 0.2s; 
            text-decoration: none;
            cursor: pointer;
        }
        .btn-outline-custom {
            border: 1px solid #cbd5e1;
            color: var(--sc-primary);
            background: white;
        }
        .btn-outline-custom:hover {
            border-color: var(--sc-primary);
            background: #f0f9ff;
        }
        .btn-primary-custom {
            border: 1px solid var(--sc-primary);
            background: var(--sc-primary);
            color: white;
        }
        .btn-primary-custom:hover {
            background: var(--sc-primary-hover);
            color: white;
        }
        .btn-outline-danger-custom {
            border: 1px solid #fecaca;
            color: #dc2626;
            background: white;
        }
        .btn-outline-danger-custom:hover {
            border-color: #f87171;
            background: #fef2f2;
        }
        
        @media (max-width: 768px) {
            .order-header .col-6 { margin-bottom: 1rem; }
            .order-header .col-6:last-child { margin-bottom: 0; }
            .card-footer-custom { justify-content: stretch; }
            .btn-action { flex: 1; justify-content: center; }
        }
    </style>
</head>
<body>

<div class="page-header">
    <div class="container d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h2 class="page-title mb-1">Purchase History</h2>
            <p class="text-muted mb-0 fw-medium">Track, manage, and review your recent orders.</p>
        </div>
        <a href="index.php" class="btn btn-dark rounded-pill px-4 fw-bold py-2" style="background: var(--sc-primary);">
            <i class="bi bi-arrow-left me-2"></i>Continue Shopping
        </a>
    </div>
</div>

<div class="container pb-5" style="max-width: 900px;">
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success border border-success bg-success-subtle rounded-3 mb-4 d-flex align-items-center gap-3 fw-bold">
            <i class="bi bi-check-circle-fill fs-4 text-success"></i>
            <div class="text-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger border border-danger bg-danger-subtle rounded-3 mb-4 d-flex align-items-center gap-3 fw-bold">
            <i class="bi bi-exclamation-circle-fill fs-4 text-danger"></i>
            <div class="text-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        </div>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
        <div class="text-center py-5 my-4">
            <div class="mb-4">
                <div class="bg-white p-4 rounded-circle shadow-sm d-inline-block border" style="border-color: #cbd5e1 !important;">
                    <i class="bi bi-bag-x display-4 text-muted opacity-50"></i>
                </div>
            </div>
            <h4 class="fw-bold text-dark mb-2">No orders found</h4>
            <p class="text-muted">It looks like you haven't placed any orders yet.</p>
            <a href="index.php" class="btn btn-dark rounded-pill px-4 mt-3 fw-bold" style="background: var(--sc-primary);">Start Shopping</a>
        </div>
    <?php else: ?>
        
        <?php foreach ($orders as $o): ?>
            <?php
                // Fetch Items Safely
                $itemStmt = $pdo->prepare("SELECT oi.quantity, oi.selected_color, oi.selected_size, p.name, p.image, oi.price 
                                           FROM order_items oi 
                                           JOIN products p ON oi.product_id = p.id 
                                           WHERE oi.order_id = ?");
                $itemStmt->execute([$o['id']]);
                $items = $itemStmt->fetchAll();
                
                // Status Logic
                $statusLower = strtolower(trim($o['status'] ?? ''));
                $statusClass = match($statusLower) {
                    'delivered' => 'status-delivered',
                    'shipped' => 'status-shipped',
                    'cancelled' => 'status-cancelled',
                    'refund requested' => 'status-refund-requested',
                    default => 'status-processing'
                };

                // Safely calculate days passed since the order was updated to 'delivered'
                $safeUpdateDate = !empty($o['updated_at']) ? $o['updated_at'] : $o['created_at'];
                $daysPassed = (time() - strtotime($safeUpdateDate)) / 86400;
            ?>

            <div class="order-card">
                <div class="order-header">
                    <div class="row align-items-center">
                        <div class="col-6 col-md-3">
                            <span class="header-label">Order Placed</span>
                            <span class="header-value"><?= date("M d, Y", strtotime($o["created_at"])) ?></span>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="header-label">Total Amount</span>
                            <span class="header-value"><?= formatPrice($o['total_amount']) ?></span>
                        </div>
                        <div class="col-6 col-md-3 mt-3 mt-md-0">
                            <span class="header-label">Order Number</span>
                            <span class="header-value">#<?= str_pad($o["id"], 6, '0', STR_PAD_LEFT) ?></span>
                        </div>
                        <div class="col-6 col-md-3 mt-3 mt-md-0 text-start text-md-end">
                            <span class="status-badge <?= $statusClass ?>">
                                <span class="status-dot"></span>
                                <?= ucwords($statusLower ?: 'Processing') ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="order-body">
                    <?php if (empty($items)): ?>
                        <div class="p-4 text-center text-muted small fw-semibold">
                            <i class="bi bi-box-seam me-1"></i> Order items are currently being processed.
                        </div>
                    <?php else: ?>
                        <?php foreach ($items as $i): ?>
                            <div class="product-item d-flex align-items-center gap-3">
                                <div class="img-box">
                                    <?php 
                                        $imgName = $i['image'] ?? '';
                                        $imgPath = 'assets/img/placeholder.png'; 
                                        
                                        if (!empty($imgName)) {
                                            $checkPaths = [
                                                'uploads/products/' . $imgName,
                                                '../uploads/products/' . $imgName,
                                                'uploads/' . $imgName
                                            ];
                                            foreach ($checkPaths as $path) {
                                                if (file_exists(__DIR__ . '/' . $path)) {
                                                    $imgPath = $path;
                                                    break;
                                                }
                                            }
                                        }
                                    ?>
                                    <img src="<?= htmlspecialchars($imgPath) ?>" alt="<?= htmlspecialchars($i['name'] ?? 'Product') ?>" onerror="this.src='assets/img/placeholder.png'">
                                </div>
                                <div class="flex-grow-1 min-w-0">
                                    <h6 class="fw-bold text-dark mb-1 text-truncate"><?= htmlspecialchars($i['name'] ?? 'Unknown Item') ?></h6>
                                    
                                    <?php if(!empty($i['selected_color']) || !empty($i['selected_size'])): ?>
                                    <div class="d-flex flex-wrap gap-2 mb-2 mt-2">
                                        <?php if(!empty($i['selected_color'])): ?>
                                            <span class="spec-pill"><i class="bi bi-palette-fill me-1 text-muted"></i> <?= htmlspecialchars($i['selected_color']) ?></span>
                                        <?php endif; ?>
                                        <?php if(!empty($i['selected_size'])): ?>
                                            <span class="spec-pill"><i class="bi bi-arrows-angle-expand me-1 text-muted"></i> <?= htmlspecialchars($i['selected_size']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>

                                    <div class="text-muted small fw-semibold mt-1">
                                        Qty: <strong class="text-dark"><?= $i['quantity'] ?? 1 ?></strong> <span class="mx-1">&bull;</span> <?= formatPrice($i['price'] ?? 0) ?> each
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="card-footer-custom">
                    
                    <?php if ($statusLower === 'processing' || $statusLower === 'pending'): ?>
                        <button type="button" class="btn-action btn-outline-custom" onclick="confirmCancel(<?= $o['id'] ?>)">
                            <i class="bi bi-x-circle"></i> Cancel Order
                        </button>
                    <?php endif; ?>

                    <?php if ($statusLower === 'delivered'): ?>
                        <?php if ($daysPassed <= 7): ?>
                            <button type="button" class="btn-action btn-outline-custom" onclick="confirmRefund(<?= $o['id'] ?>)">
                                <i class="bi bi-arrow-return-left"></i> Request Refund
                            </button>
                        <?php else: ?>
                            <span class="btn-action text-muted border border-light bg-light" style="cursor: not-allowed;" title="The 7-day return window has expired.">
                                <i class="bi bi-calendar-x"></i> Return Expired
                            </span>
                        <?php endif; ?>

                        <a href="review.php?order_id=<?= $o['id'] ?>" class="btn-action btn-outline-custom">
                            <i class="bi bi-star"></i> Write Review
                        </a>
                    <?php endif; ?>

                    <?php if (in_array($statusLower, ['delivered', 'cancelled', 'failed', 'refunded'])): ?>
                        <button type="button" class="btn-action btn-outline-danger-custom" onclick="confirmDelete(<?= $o['id'] ?>)">
                            <i class="bi bi-trash3"></i> Delete
                        </button>
                    <?php endif; ?>

                    <a href="invoice.php?id=<?= $o['id'] ?>" class="btn-action btn-primary-custom ms-md-auto">
                        <i class="bi bi-receipt"></i> View Invoice
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-body text-center p-4">
                <div class="mb-3 text-danger bg-danger-subtle d-inline-flex p-3 rounded-circle">
                    <i class="bi bi-trash3-fill fs-4"></i>
                </div>
                <h5 class="fw-bold mb-2">Delete Order?</h5>
                <p class="text-muted small mb-4">This will permanently remove Order #<span id="displayOrderId" class="fw-bold text-dark"></span> from your history.</p>
                <div class="d-grid gap-2">
                    <form method="POST">
                        <input type="hidden" name="delete_order_id" id="hiddenDeleteOrderId">
                        <button type="submit" class="btn btn-danger w-100 rounded-pill fw-semibold mb-2">Yes, Delete It</button>
                    </form>
                    <button type="button" class="btn btn-light w-100 rounded-pill fw-semibold text-muted" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="cancelConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-body text-center p-4">
                <div class="mb-3 text-warning bg-warning-subtle d-inline-flex p-3 rounded-circle">
                    <i class="bi bi-x-circle-fill fs-4"></i>
                </div>
                <h5 class="fw-bold mb-2">Cancel Order?</h5>
                <p class="text-muted small mb-4">Are you sure you want to cancel Order #<span id="displayCancelId" class="fw-bold text-dark"></span>? This action cannot be undone.</p>
                <div class="d-grid gap-2">
                    <form method="POST">
                        <input type="hidden" name="cancel_order_id" id="hiddenCancelOrderId">
                        <button type="submit" class="btn btn-warning w-100 rounded-pill fw-bold text-dark mb-2">Yes, Cancel Order</button>
                    </form>
                    <button type="button" class="btn btn-light w-100 rounded-pill fw-semibold text-muted" data-bs-dismiss="modal">Keep Order</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="refundConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Request a Refund</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-4">Please provide a reason for returning Order #<span id="displayRefundId" class="fw-bold text-dark"></span>. Our support team will review your request shortly.</p>
                <form method="POST">
                    <input type="hidden" name="refund_order_id" id="hiddenRefundOrderId">
                    <div class="mb-4">
                        <label class="form-label fw-semibold small text-dark">Reason for Refund</label>
                        <textarea name="refund_reason" class="form-control bg-light" rows="3" placeholder="E.g. Item arrived damaged, wrong size..." required></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light w-50 rounded-pill fw-semibold text-muted" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-dark w-50 rounded-pill fw-bold" style="background: var(--sc-primary);">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function confirmDelete(id) {
        document.getElementById('displayOrderId').innerText = id.toString().padStart(6, '0');
        document.getElementById('hiddenDeleteOrderId').value = id;
        new bootstrap.Modal(document.getElementById('deleteConfirmModal')).show();
    }
    
    function confirmCancel(id) {
        document.getElementById('displayCancelId').innerText = id.toString().padStart(6, '0');
        document.getElementById('hiddenCancelOrderId').value = id;
        new bootstrap.Modal(document.getElementById('cancelConfirmModal')).show();
    }

    function confirmRefund(id) {
        document.getElementById('displayRefundId').innerText = id.toString().padStart(6, '0');
        document.getElementById('hiddenRefundOrderId').value = id;
        new bootstrap.Modal(document.getElementById('refundConfirmModal')).show();
    }
</script>

<?php require_once __DIR__ . "/partials/footer.php"; ?>

</body>
</html>