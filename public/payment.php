<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/../app/helpers/currency.php";

$paystackPublicKey = "pk_test_4fe8ce38868a9163e8037f9528776965c07a9412"; 

if (!isset($_GET['order_id']) || !isset($_SESSION['user_id'])) { 
    header("Location: index.php"); 
    exit; 
}

$orderId = (int) $_GET['order_id'];
$userId  = $_SESSION['user_id'];

/* |--------------------------------------------------------------------------
   | 1. FETCH ORDER DETAILS
   |-------------------------------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT 
        o.id,
        o.total_amount, 
        o.discount_amount, 
        o.promo_code,
        o.shipping_cost,
        o.shipping_phone, 
        o.payment_method, 
        o.shipping_method,
        u.email 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.id = ? AND o.user_id = ?
    LIMIT 1
");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Invalid Order Session. Please return to your cart.");
}

/* |--------------------------------------------------------------------------
   | 2. FETCH ORDER ITEMS (To display variations)
   |-------------------------------------------------------------------------- */
$itemsStmt = $pdo->prepare("
    SELECT oi.quantity, oi.price, oi.selected_color, oi.selected_size, p.name, p.image
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$itemsStmt->execute([$orderId]);
$orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

function getImagePath($imageName) {
    $defaultImage = 'assets/images/no-image.png';
    if (empty($imageName)) return $defaultImage;
    $paths = ['uploads/products/' . $imageName, 'uploads/' . $imageName];
    foreach ($paths as $path) {
        if (file_exists(__DIR__ . '/../public/' . $path)) return $path; 
    }
    return $defaultImage;
}

/* |--------------------------------------------------------------------------
   | 3. CALCULATE TOTALS
   |-------------------------------------------------------------------------- */
$grandTotal      = (float) $order['total_amount'];
$shippingCost    = (float) ($order['shipping_cost'] ?? 0.00);
$discountAmount  = (float) ($order['discount_amount'] ?? 0.00);
$promoCode       = $order['promo_code'];

$productSubtotal = ($grandTotal + $discountAmount) - $shippingCost;

$shippingMethod = strtolower(trim($order['shipping_method'] ?? ''));
if (str_contains($shippingMethod, 'express')) {
    $deliveryLabel = "Express Delivery";
} elseif (str_contains($shippingMethod, 'standard')) {
    $deliveryLabel = "Standard Delivery";
} elseif (str_contains($shippingMethod, 'pickup')) {
    $deliveryLabel = "Pickup Station";
} else {
    $deliveryLabel = "Delivery Included";
}

$paystackCurrency  = 'GHS';
$amountInSubunits  = (int) round($grandTotal * 100);

/* |--------------------------------------------------------------------------
   | 4. UI CONFIGURATION
   |-------------------------------------------------------------------------- */
$userEmail      = $order['email'] ?? 'customer@example.com';
$selectedMethod = $order['payment_method'];

if ($selectedMethod === 'momo') {
    $channels    = ['mobile_money'];
    $methodLabel = "Mobile Money";
    $methodIcon  = "bi-phone-fill";
    $methodColor = "#fff3cd"; 
    $methodText  = "#856404";
} else {
    $channels    = ['card'];
    $methodLabel = "Credit / Debit Card";
    $methodIcon  = "bi-credit-card-2-front-fill";
    $methodColor = "#cff4fc"; 
    $methodText  = "#055160";
}

require_once __DIR__ . "/partials/navbar.php"; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment</title>
</head>
<body>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    :root {
        --sc-navy: #0B2447;
        --sc-blue: #19376D;
    }
    
    .payment-section {
        position: relative;
        background-color: var(--sc-navy);
        background-image: 
            linear-gradient(135deg, rgba(11, 36, 71, 0.92), rgba(25, 55, 109, 0.85)),
            url('https://images.unsplash.com/photo-1563013544-824ae1b704d3?q=80&w=1000&auto=format&fit=crop');
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        min-height: 85vh;
        display: flex;
        align-items: center;
        padding: clamp(24px, 5vw, 40px) 0;
    }

    .payment-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.1);
        position: relative;
        z-index: 2;
    }

    .payment-header {
        background: var(--sc-navy);
        padding: clamp(1.25rem, 4vw, 2rem);
        color: white;
        text-align: center;
        position: relative;
    }
    
    .payment-header::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 0;
        right: 0;
        height: 20px;
        background: white;
        border-radius: 20px 20px 0 0;
    }

    .amount-display {
        font-size: clamp(1.8rem, 6vw, 2.5rem);
        font-weight: 800;
        margin: 0.5rem 0;
    }

    .payment-body {
        padding: clamp(1.25rem, 4vw, 2rem);
    }

    .method-badge {
        display: flex;
        align-items: center;
        padding: 12px 16px;
        border-radius: 12px;
        margin-bottom: 20px;
        font-weight: 600;
        font-size: clamp(0.85rem, 2.5vw, 0.95rem);
    }

    .order-items-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 20px;
        max-height: 180px;
        overflow-y: auto;
        scrollbar-width: thin;
    }
    .order-items-box::-webkit-scrollbar { width: 6px; }
    .order-items-box::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .mini-item {
        display: flex;
        align-items: center;
        padding-bottom: 10px;
        margin-bottom: 10px;
        border-bottom: 1px dashed #e2e8f0;
        gap: 12px;
    }
    .mini-item:last-child {
        padding-bottom: 0;
        margin-bottom: 0;
        border-bottom: none;
    }
    .mini-item img {
        width: 40px;
        height: 40px;
        border-radius: 6px;
        object-fit: cover;
        border: 1px solid #cbd5e1;
        flex-shrink: 0;
    }

    .info-list {
        margin-bottom: 2rem;
    }

    .info-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        font-size: clamp(0.82rem, 2.5vw, 0.95rem);
        color: #64748b;
        border-bottom: 1px dashed #f1f5f9;
        padding-bottom: 8px;
        gap: 8px;
    }
    .info-item:last-child { border-bottom: none; }
    .info-item span:last-child { color: #334155; font-weight: 600; text-align: right; }

    .btn-pay {
        width: 100%;
        padding: clamp(12px, 3vw, 16px);
        border-radius: 12px;
        background: var(--sc-navy);
        color: white;
        font-weight: 700;
        font-size: clamp(0.95rem, 3vw, 1.1rem);
        border: none;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        cursor: pointer;
    }
    .btn-pay:hover {
        background: var(--sc-blue);
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(11, 36, 71, 0.15);
    }
    .btn-pay:disabled {
        background: #94a3b8;
        cursor: not-allowed;
        transform: none;
    }

    .secure-badge {
        text-align: center;
        margin-top: 1.5rem;
        font-size: 0.85rem;
        color: #94a3b8;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    @media (max-width: 768px) {
        .payment-section {
            background-attachment: scroll;
        }
    }
</style>

<div class="payment-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-11 col-sm-9 col-md-8 col-lg-5">
                
                <div class="payment-card">
                    <div class="payment-header">
                        <div class="text-uppercase small opacity-75 fw-bold">Total Amount</div>
                        <div class="amount-display"><?= formatPrice($grandTotal) ?></div>
                        
                        <?php 
                        $activeCurrency = $_SESSION['currency'] ?? 'GHS';
                        if ($activeCurrency !== 'GHS'): 
                        ?>
                            <div class="small text-warning mt-1" style="font-size: 0.8rem; background: rgba(255,255,255,0.1); padding: 5px 10px; border-radius: 6px; display: inline-block;">
                                <i class="bi bi-info-circle"></i> Billed as <strong>GHS <?= number_format($grandTotal, 2) ?></strong>
                            </div>
                        <?php else: ?>
                            <div class="small opacity-75">Order #<?= str_pad($orderId, 6, '0', STR_PAD_LEFT) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="payment-body">
                        
                        <div class="method-badge" style="background-color: <?= $methodColor ?>; color: <?= $methodText ?>;">
                            <i class="bi <?= $methodIcon ?> fs-5 me-3"></i>
                            <div>
                                <div class="small opacity-75 text-uppercase" style="font-size: 0.7rem;">Paying with</div>
                                <div><?= $methodLabel ?></div>
                            </div>
                            <i class="bi bi-check-circle-fill ms-auto fs-5 opacity-75"></i>
                        </div>

                        <div class="order-items-box">
                            <?php foreach ($orderItems as $item): 
                                $imgSrc = getImagePath($item['image']);
                                $lineTotal = $item['price'] * $item['quantity'];
                            ?>
                            <div class="mini-item">
                                <img src="<?= htmlspecialchars($imgSrc) ?>" alt="Item">
                                <div class="flex-grow-1 min-w-0">
                                    <div class="text-truncate fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($item['name']) ?></div>
                                    <div class="text-muted" style="font-size: 0.7rem;">
                                        Qty: <?= $item['quantity'] ?> 
                                        <?php if(!empty($item['selected_color'])): ?> | <?= htmlspecialchars($item['selected_color']) ?><?php endif; ?>
                                        <?php if(!empty($item['selected_size'])): ?> | <?= htmlspecialchars($item['selected_size']) ?><?php endif; ?>
                                    </div>
                                </div>
                                <div class="fw-bold" style="font-size: 0.85rem; color: var(--sc-navy);">
                                    <?= formatPrice($lineTotal) ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="info-list">
                            <div class="info-item">
                                <span>Subtotal</span>
                                <span><?= formatPrice($productSubtotal) ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span>Shipping</span>
                                <span class="text-success">
                                    <?php if($shippingCost > 0): ?>
                                        +<?= formatPrice($shippingCost) ?>
                                    <?php else: ?>
                                        Free
                                    <?php endif; ?>
                                </span>
                            </div>

                            <?php if ($discountAmount > 0): ?>
                            <div class="info-item">
                                <span>Discount <span class="badge bg-success bg-opacity-10 text-success ms-1 border border-success" style="font-size: 0.65rem; padding: 3px 6px;"><?= htmlspecialchars($promoCode) ?></span></span>
                                <span class="text-danger fw-bold">-<?= formatPrice($discountAmount) ?></span>
                            </div>
                            <?php endif; ?>

                            <div class="info-item">
                                <span>Email</span>
                                <span class="text-truncate" style="max-width: 150px;"><?= htmlspecialchars($userEmail) ?></span>
                            </div>
                        </div>

                        <button onclick="payWithPaystack()" id="payBtn" class="btn-pay">
                            Pay Now <i class="bi bi-lock-fill"></i>
                        </button>

                        <div id="status-msg" class="text-center mt-3 small fw-bold"></div>

                        <div class="secure-badge">
                            <i class="bi bi-shield-lock-fill"></i>
                            Secured by Paystack 
                        </div>

                        <div class="text-center mt-4 pt-3 border-top">
                            <form action="../routes/checkout.php" method="POST" class="d-inline">
                                <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                <button type="submit" name="edit_order" class="btn btn-link text-decoration-none text-muted small p-0 me-3">
                                    Edit Order
                                </button>
                            </form>
                            <span class="text-muted small">|</span>
                            <a href="index.php" class="btn btn-link text-decoration-none text-danger small p-0 ms-3">
                                Cancel Payment
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
    function payWithPaystack() {
        const btn = document.getElementById('payBtn');
        const statusMsg = document.getElementById('status-msg');
        const originalText = btn.innerHTML;
        
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Processing...';
        statusMsg.innerText = "Initializing secure gateway...";
        statusMsg.className = "text-center mt-3 small fw-bold text-muted";

        const customerEmail = <?= json_encode($userEmail) ?>;
        if (!customerEmail || !customerEmail.includes('@')) {
            alert("🔴 Error: Your account email is invalid. Paystack requires a valid email address.");
            btn.disabled = false; btn.innerHTML = originalText; statusMsg.innerText = ""; return;
        }

        if (typeof PaystackPop === 'undefined') {
            alert("🔴 Error: Payment gateway blocked! Please click the Lion icon in your URL bar and turn off Brave Shields.");
            btn.disabled = false; btn.innerHTML = originalText; statusMsg.innerText = ""; return;
        }

        try {
            let handler = PaystackPop.setup({
                key: <?= json_encode($paystackPublicKey) ?>,
                email: customerEmail,
                amount: <?= $amountInSubunits ?>,
                currency: <?= json_encode($paystackCurrency) ?>,
                ref: "SC_" + Date.now() + "_" + Math.floor(Math.random() * 1000),
                /* ✅ I completely removed the strict 'channels' filter here so it won't crash! */
                metadata: {
                    order_id: <?= json_encode($orderId) ?>,
                    custom_fields: [{ 
                        display_name: "Mobile Number", 
                        variable_name: "mobile_number", 
                        value: <?= json_encode((string)$order['shipping_phone']) ?>
                    }]
                },
                onClose: function(){
                    statusMsg.innerText = "Transaction Cancelled";
                    statusMsg.className = "text-center mt-3 small fw-bold text-danger";
                    btn.disabled = false; 
                    btn.innerHTML = originalText;
                },
                callback: function(response){
                    statusMsg.innerText = "Verifying payment...";
                    statusMsg.className = "text-center mt-3 small fw-bold text-success";
                    window.location.href = "../routes/verify_transaction.php?reference=" + response.reference;
                }
            });
            
            handler.openIframe();
            
            // Safety check for brave browser
            setTimeout(() => {
                if (document.querySelector('iframe[name="paystack-checkout"]') == null) {
                    statusMsg.innerHTML = "<span class='text-danger'>Gateway blocked! Turn off Brave Shields.</span>";
                    btn.disabled = false; btn.innerHTML = originalText;
                }
            }, 3000);

        } catch (error) {
            console.error("Paystack Error:", error);
            alert("Oops! Something went wrong loading the payment gateway.");
            btn.disabled = false; btn.innerHTML = originalText; statusMsg.innerText = "";
        }
    }
</script>


<?php require_once __DIR__ . "/partials/footer.php"; ?>
</body>
</html>