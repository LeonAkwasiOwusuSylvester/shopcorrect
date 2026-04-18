<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/../app/helpers/currency.php";

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

// ✅ REMOVED THE INSECURE GET PARAMETER OVERRIDE HERE! 
// The routes/verify_transaction.php securely handles DB updates now.

$order = [];
$items = [];
$secureToken = '';

if ($orderId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        // Generate the secure token so the user can easily view their invoice
        $secureToken = hash('sha256', $order['id'] . $order['created_at'] . 'ShopCorrectSecureInvoice2026');

        $itemStmt = $pdo->prepare("
            SELECT oi.*, p.name 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = ?
        ");
        $itemStmt->execute([$orderId]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!$order) {
    header("Location: index.php");
    exit;
}

require_once __DIR__ . "/partials/navbar.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed</title>
</head>
<body>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    body {
        background: linear-gradient(rgba(11, 36, 71, 0.85), rgba(11, 36, 71, 0.85)), 
                    url('https://images.unsplash.com/photo-1557821552-17105176677c?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');
        background-size: cover;
        background-position: center;
        background-attachment: scroll; 
        font-family: 'Plus Jakarta Sans', sans-serif;
        min-height: 100vh;
        color: white;
        overflow-x: hidden;
        margin: 0;
    }

    body::before {
        content: "SHOPCORRECT"; 
        position: fixed;
        top: 50%; 
        left: 50%;
        transform: translate(-50%, -50%) rotate(-30deg); 
        font-size: 15vw; 
        font-weight: 900;
        color: rgba(255, 255, 255, 0.03); 
        white-space: nowrap; 
        pointer-events: none; 
        letter-spacing: 2rem; 
        z-index: 0;
    }
    
    .success-icon-box {
        width: clamp(75px, 15vw, 100px);
        height: clamp(75px, 15vw, 100px);
        background: #10B981;
        color: white;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 2rem;
        box-shadow: 0 0 0 10px rgba(16, 185, 129, 0.2);
        animation: popIn 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        position: relative;
        z-index: 1;
    }
    .success-icon-box i { font-size: clamp(2rem, 6vw, 3.5rem); }
    @keyframes popIn { 0% { transform: scale(0); } 100% { transform: scale(1); } }
    
    .receipt-card {
        background: rgba(255, 255, 255, 0.98); 
        backdrop-filter: blur(10px);
        border-radius: 24px; 
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        border: none;
        overflow: hidden;
        position: relative;
        z-index: 1;
    }
    .receipt-header {
        background: #0B2447;
        color: white;
        padding: clamp(1.25rem, 4vw, 2rem);
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    .receipt-body {
        padding: clamp(1rem, 4vw, 2rem);
        color: #1e293b;
    } 
    
    .item-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px dashed #e2e8f0;
        gap: 12px;
    }
    .item-row:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .item-row .fw-bold { font-size: clamp(0.85rem, 2.5vw, 1rem); }
    .item-row > div:last-child { flex-shrink: 0; }
    
    .summary-row { display: flex; justify-content: space-between; font-size: clamp(0.82rem, 2.5vw, 0.9rem); margin-bottom: 0.5rem; color: #64748b; }
    .total-row { display: flex; justify-content: space-between; font-size: clamp(1rem, 3vw, 1.25rem); font-weight: 800; color: #0B2447; margin-top: 1rem; padding-top: 1rem; border-top: 2px solid #0B2447; }

    #confetti-canvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 9999; }

    /* Action buttons */
    .action-btns { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
    .action-btns .btn { font-size: clamp(0.82rem, 2.5vw, 1rem); }
</style>

<canvas id="confetti-canvas"></canvas>

<div class="container py-4 py-md-5" style="position: relative; z-index: 2;">
    <div class="row justify-content-center">
        <div class="col-11 col-md-8 col-lg-6">
            
            <div class="text-center mb-4 mb-md-5">
                <div class="success-icon-box">
                    <i class="bi bi-check-lg"></i>
                </div>
                <h1 class="fw-bold mb-2" style="font-size: clamp(1.5rem, 5vw, 2.2rem);">Order Confirmed!</h1>
                <p class="text-white-50" style="font-size: clamp(0.95rem, 3vw, 1.2rem);">Thanks for your purchase, <?= htmlspecialchars($_SESSION['name'] ?? 'Customer') ?>.</p>
                <div class="d-inline-block bg-white bg-opacity-10 px-4 py-1 rounded-pill border border-light border-opacity-25 small mt-2">
                    Order #<?= str_pad($orderId, 6, '0', STR_PAD_LEFT) ?>
                </div>
            </div>

            <div class="receipt-card mb-4">
                <div class="receipt-header">
                    <h5 class="mb-0 fw-bold">Order Receipt</h5>
                    <div class="opacity-75 small"><?= date('F j, Y, g:i a') ?></div>
                </div>
                
                <div class="receipt-body">
                    <?php if (!empty($items)): ?>
                        <div class="mb-4">
                            <h6 class="text-uppercase text-muted small fw-bold mb-3">Items Ordered</h6>
                            <?php foreach($items as $item): ?>
                            <div class="item-row">
                                <div>
                                    <span class="fw-bold text-dark"><?= htmlspecialchars($item['name']) ?></span>
                                    <?php 
                                        $specs = [];
                                        if (!empty($item['selected_size'])) $specs[] = "Size: " . htmlspecialchars($item['selected_size']);
                                        if (!empty($item['selected_color'])) $specs[] = "Color: " . htmlspecialchars($item['selected_color']);
                                    ?>
                                    <?php if (!empty($specs)): ?>
                                        <div class="small text-muted mt-1" style="font-size: 0.8rem;">
                                            <?= implode(' | ', $specs) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="text-muted small mt-1">Qty: x<?= $item['quantity'] ?></div>
                                </div>
                                <div class="fw-bold text-dark"><?= formatPrice($item['price'] * $item['quantity']) ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($order): ?>
                    <div class="row mb-4 g-3">
                        <div class="col-6">
                            <h6 class="text-uppercase text-muted small fw-bold mb-2">Shipping To</h6>
                            <div class="small text-dark" style="line-height: 1.6;">
                                <strong><?= htmlspecialchars($order['shipping_name'] ?? 'N/A') ?></strong><br>
                                <?= htmlspecialchars($order['shipping_address'] ?? '') ?><br>
                                <?= htmlspecialchars($order['shipping_city'] ?? '') ?>, <?= htmlspecialchars($order['shipping_region'] ?? '') ?><br>
                                <?= htmlspecialchars($order['shipping_phone'] ?? '') ?>
                            </div>
                        </div>
                        <div class="col-6 text-end">
                            <h6 class="text-uppercase text-muted small fw-bold mb-2">Payment</h6>
                            <div class="small text-dark">
                                <span class="badge bg-light text-dark border">
                                    <?= strtoupper($order['payment_method'] ?? 'COD') ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <?php 
                        $grandTotal     = (float) ($order['total_amount'] ?? 0);
                        $shippingCost   = (float) ($order['shipping_cost'] ?? 0);
                        $discountAmount = (float) ($order['discount_amount'] ?? 0);
                        $promoCode      = $order['promo_code'] ?? '';
                        $subtotal       = ($grandTotal + $discountAmount) - $shippingCost;
                    ?>

                    <div class="bg-light p-3 rounded">
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span><?= formatPrice($subtotal) ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Shipping</span>
                            <span class="text-success">
                                <?= $shippingCost > 0 ? '+' . formatPrice($shippingCost) : 'Free' ?>
                            </span>
                        </div>
                        <?php if ($discountAmount > 0): ?>
                        <div class="summary-row">
                            <span>Discount <span class="badge bg-success bg-opacity-10 text-success ms-1 border border-success" style="font-size: 0.65rem; padding: 3px 6px;"><?= htmlspecialchars($promoCode) ?></span></span>
                            <span class="text-danger fw-bold">-<?= formatPrice($discountAmount) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="total-row">
                            <span>Total Paid</span>
                            <span><?= formatPrice($grandTotal) ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="action-btns">
                <a href="index.php" class="btn btn-outline-light rounded-pill px-4 fw-bold">
                    <i class="bi bi-cart3"></i> Continue Shopping
                </a>
                <a href="invoice.php?id=<?= $orderId ?>&token=<?= $secureToken ?>" target="_blank" class="btn btn-warning rounded-pill px-4 fw-bold text-dark">
                    <i class="bi bi-receipt"></i> View Invoice
                </a>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
<script>
    window.addEventListener('load', () => {
        var duration = 3 * 1000;
        var animationEnd = Date.now() + duration;
        var defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 0 };

        function randomInRange(min, max) { return Math.random() * (max - min) + min; }

        var interval = setInterval(function() {
            var timeLeft = animationEnd - Date.now();
            if (timeLeft <= 0) { return clearInterval(interval); }
            var particleCount = 50 * (timeLeft / duration);
            confetti(Object.assign({}, defaults, { particleCount, origin: { x: randomInRange(0.1, 0.3), y: Math.random() - 0.2 } }));
            confetti(Object.assign({}, defaults, { particleCount, origin: { x: randomInRange(0.7, 0.9), y: Math.random() - 0.2 } }));
        }, 250);
    });
</script>

<?php require_once __DIR__ . "/partials/footer.php"; ?>
</body>
</html>