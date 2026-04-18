<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/../app/helpers/currency.php";

/* 🛡️ Force login */
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php?redirect=cart");
    exit;
}

/* 📦 Fetch Cart Items & Variant Pricing */
$stmt = $pdo->prepare("
    SELECT 
        ci.id, ci.quantity, ci.selected_color, ci.selected_size,
        p.id as product_id, p.name, p.price as base_price, p.sale_price, p.discount_percent, p.image, 
        p.category_id, 
        v.shop_name,
        pv.price as variant_price
    FROM carts c
    JOIN cart_items ci ON c.id = ci.cart_id
    JOIN products p ON ci.product_id = p.id
    JOIN vendors v ON p.vendor_id = v.id
    LEFT JOIN product_variants pv 
        ON p.id = pv.product_id 
        AND COALESCE(ci.selected_color, '') = COALESCE(pv.color, '')
        AND COALESCE(ci.selected_size, '') = COALESCE(pv.size, '')
    WHERE c.user_id = ?
    ORDER BY ci.id DESC
");
$stmt->execute([$_SESSION["user_id"]]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cartTotal = 0;

// --- SMART RECOMMENDATION LOGIC ---
$cartProductIds = array_column($items, 'product_id');
$excludeIds = !empty($cartProductIds) ? implode(',', $cartProductIds) : '0';
$cartCategoryIds = array_unique(array_column($items, 'category_id'));
$catIdsStr = !empty($cartCategoryIds) ? implode(',', $cartCategoryIds) : '0';

$recSql = "
    SELECT p.*, v.shop_name 
    FROM products p 
    JOIN vendors v ON p.vendor_id = v.id
    WHERE p.id NOT IN ($excludeIds) 
    AND p.status = 'active'
    AND p.is_deleted = 0
    AND p.category_id IN ($catIdsStr) 
    ORDER BY RAND() LIMIT 8
";

if(empty($cartCategoryIds) || $pdo->query($recSql)->rowCount() < 4) {
    $recSql = "
        SELECT p.*, v.shop_name 
        FROM products p 
        JOIN vendors v ON p.vendor_id = v.id
        WHERE p.id NOT IN ($excludeIds) 
        AND p.status = 'active'
        AND p.is_deleted = 0
        ORDER BY RAND() LIMIT 8
    ";
}

$recStmt = $pdo->query($recSql);
$recommendations = $recStmt->fetchAll(PDO::FETCH_ASSOC);

function getImagePath($imageName) {
    $defaultImage = 'assets/images/no-image.png';
    if (empty($imageName)) return $defaultImage;
    $paths = ['uploads/products/' . $imageName, 'uploads/' . $imageName];
    foreach ($paths as $path) {
        if (file_exists(__DIR__ . '/' . $path)) return $path; 
    }
    return $defaultImage;
}

require_once __DIR__ . "/partials/navbar.php"; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart</title>
</head>
<body>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<style>
    :root {
        --c-primary: #0B2447; 
        --c-primary-hover: #1e3a8a; 
        --c-bg: #f8fafc;
        --c-surface: #ffffff;
        --c-text-main: #334155;
        --c-text-muted: #64748b;
        --c-border: #e2e8f0;
        --radius-lg: 16px;
        --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
    }
    body { background-color: var(--c-bg); font-family: 'Plus Jakarta Sans', sans-serif; color: var(--c-text-main); }
    .cart-header { margin-bottom: 2rem; }
    .cart-title { font-weight: 800; color: var(--c-primary); font-size: clamp(1.5rem, 5vw, 2rem); }
    .cart-grid { display: grid; grid-template-columns: 1fr 380px; gap: 2rem; align-items: start; }
    @media (max-width: 992px) { .cart-grid { grid-template-columns: 1fr; } }

    .cart-list { display: flex; flex-direction: column; gap: 1rem; }

    .cart-item {
        background: var(--c-surface);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--c-border);
        padding: 1.25rem;
        display: grid;
        grid-template-columns: auto 1fr auto;
        gap: 1.5rem;
        position: relative;
    }
    @media (max-width: 576px) {
        .cart-item {
            grid-template-columns: auto 1fr;
            grid-template-areas: "img info" "actions actions";
        }
        .item-actions-col {
            grid-area: actions;
            flex-direction: row !important;
            justify-content: space-between;
            border-top: 1px dashed var(--c-border);
            padding-top: 1rem;
            margin-top: 0.5rem;
        }
    }

    .item-img { width: 90px; height: 90px; background: #f1f5f9; border-radius: 12px; border: 1px solid var(--c-border); display: flex; align-items: center; justify-content: center; padding: 5px; position: relative; flex-shrink: 0; }
    .item-img img { max-width: 100%; max-height: 100%; object-fit: contain; mix-blend-mode: multiply; }
    .item-info { display: flex; flex-direction: column; justify-content: center; min-width: 0; }
    .shop-name { font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--c-text-muted); margin-bottom: 4px; display: flex; align-items: center; }
    .product-name { font-size: clamp(0.9rem, 2.5vw, 1.1rem); font-weight: 700; color: var(--c-primary); margin-bottom: 0.5rem; text-decoration: none; line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .product-name:hover { color: var(--c-primary-hover); text-decoration: underline; }
    .specs-container { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 5px; }
    .spec-badge { display: inline-flex; align-items: center; gap: 6px; font-size: 0.8rem; font-weight: 600; background: #f1f5f9; color: var(--c-text-main); padding: 4px 10px; border-radius: 6px; border: 1px solid var(--c-border); }
    .item-actions-col { display: flex; flex-direction: column; justify-content: space-between; align-items: flex-end; gap: 1rem; flex-shrink: 0; }
    .item-price { font-size: clamp(1rem, 3vw, 1.2rem); font-weight: 800; color: var(--c-primary); }
    .item-price small { font-size: 0.85rem; text-decoration: line-through; color: var(--c-text-muted); font-weight: 500; margin-right: 5px; }
    
    /* ✅ UPGRADED LARGE QUANTITY BUTTONS */
    .qty-group { display: inline-flex; align-items: center; border: 1px solid var(--c-border); border-radius: 50px; background: #fff; padding: 4px; }
    .qty-control { border: none; background: #f1f5f9; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; color: var(--c-text-main); cursor: pointer; transition: 0.2s; flex-shrink: 0; }
    .qty-control:hover { background: #e2e8f0; color: var(--c-primary); }
    .qty-input { width: 45px; border: none; text-align: center; font-weight: 800; font-size: 1.1rem; color: var(--c-primary); margin: 0 5px; -moz-appearance: textfield; }
    /* Hide the annoying default browser up/down arrows */
    .qty-input::-webkit-outer-spin-button, .qty-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .qty-btn { border: none; background: var(--c-primary); color: white; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; margin-left: 8px; flex-shrink: 0; box-shadow: 0 2px 5px rgba(11,36,71,0.25); }
    .qty-btn:hover { background: var(--c-primary-hover); transform: scale(1.05) rotate(45deg); }

    .btn-remove { color: #ef4444; background: none; border: none; font-size: 0.85rem; font-weight: 600; display: flex; align-items: center; gap: 5px; cursor: pointer; opacity: 0.8; transition: 0.2s; white-space: nowrap; }
    .btn-remove:hover { opacity: 1; text-decoration: underline; }

    .summary-card { background: var(--c-surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); border: 1px solid var(--c-border); padding: 2rem; position: sticky; top: 2rem; }
    .summary-row { display: flex; justify-content: space-between; margin-bottom: 1rem; font-size: 0.95rem; color: var(--c-text-muted); }
    .summary-row.total { border-top: 2px dashed var(--c-border); padding-top: 1.5rem; margin-top: 1.5rem; color: var(--c-primary); font-weight: 800; font-size: 1.25rem; align-items: center; }
    .btn-checkout { display: block; width: 100%; text-align: center; background: var(--c-primary); color: white; padding: 1rem; border-radius: 50px; font-weight: 700; text-decoration: none; margin-top: 2rem; transition: 0.2s; }
    .btn-checkout:hover { background: var(--c-primary-hover); transform: translateY(-2px); color: white; box-shadow: 0 4px 12px rgba(11, 36, 71, 0.2); }
    .empty-cart { text-align: center; padding: 4rem 1rem; background: white; border-radius: var(--radius-lg); border: 1px dashed var(--c-border); }

    /* --- RECOMMENDATION STYLES --- */
    .rec-scroll-container { display: flex; gap: 1rem; overflow-x: auto; padding: 10px 5px 20px 5px; scroll-behavior: smooth; scrollbar-width: none; -webkit-overflow-scrolling: touch; }
    .rec-scroll-container::-webkit-scrollbar { display: none; }
    .rec-card { min-width: clamp(180px, 45vw, 240px); max-width: clamp(180px, 45vw, 240px); background: #fff; border: 1px solid #eef2f7; border-radius: 16px; overflow: hidden; transition: 0.2s; position: relative; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); flex-shrink: 0; }
    .rec-card:hover { transform: translateY(-4px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
    .rec-img { height: clamp(120px, 20vw, 160px); display: flex; align-items: center; justify-content: center; padding: 15px; background: #f8fafc; border-bottom: 1px solid #f1f5f9; }
    .rec-img img { max-height: 100%; max-width: 100%; object-fit: contain; mix-blend-mode: multiply; }
    .rec-body { padding: clamp(10px, 2vw, 20px) clamp(10px, 2vw, 16px); }
    .rec-vendor { font-size: 0.75rem; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 6px; display: flex; align-items: center; gap: 4px; }
    .rec-vendor i.bi-patch-check-fill { color: #3b82f6; font-size: 0.85rem; }
    .rec-vendor i.bi-shop { color: #94a3b8; }
    .rec-title { font-size: clamp(0.85rem, 1.8vw, 1rem); font-weight: 700; color: #0f172a; display: block; text-decoration: none; margin-bottom: 0.75rem; height: 2.8em; overflow: hidden; line-height: 1.4; }
    .rec-title:hover { color: #3b82f6; text-decoration: underline; }
    .rec-price { font-weight: 800; color: #0f172a; font-size: clamp(1rem, 2vw, 1.2rem); }
    .btn-view { font-size: 0.85rem; padding: 6px 18px; border-radius: 50px; background: #f1f5f9; color: #0f172a; text-decoration: none; font-weight: 700; transition: 0.2s; white-space: nowrap; }
    .btn-view:hover { background: #e2e8f0; }
    .badge-discount { position: absolute; top: 8px; left: 8px; background: #ef4444; color: white; font-size: 0.7rem; font-weight: 700; padding: 3px 6px; border-radius: 6px; z-index: 2; letter-spacing: 0.5px; }
</style>

<div class="container py-4 py-md-5">
    <div class="d-flex justify-content-between align-items-end cart-header flex-wrap gap-2">
        <div>
            <h1 class="cart-title mb-1">Shopping Cart</h1>
            <p class="text-muted mb-0"><?= count($items) ?> items ready for checkout</p>
        </div>
        <a href="index.php" class="text-decoration-none fw-bold text-muted small"><i class="bi bi-arrow-left"></i> Continue Shopping</a>
    </div>

    <?php if (empty($items)): ?>
        <div class="empty-cart">
            <i class="bi bi-cart-x empty-icon" style="font-size: 3rem;"></i>
            <h3>Your cart is empty</h3>
            <p class="text-muted">Looks like you haven't made your choice yet.</p>
            <a href="index.php" class="btn btn-dark rounded-pill mt-3 px-4 fw-bold" style="background: var(--c-primary);">Start Shopping</a>
        </div>
    <?php else: ?>
        <div class="cart-grid">
            <div class="cart-list">
                <?php foreach ($items as $item): ?>
                    <?php
                        $globalBase  = (float) $item['base_price'];
                        $globalSale  = (float) $item['sale_price'];
                        $discount    = (int) $item['discount_percent'];
                        $isGlobalSale = ($discount > 0 && $globalSale > 0);
                        $globalFinal = $isGlobalSale ? $globalSale : $globalBase;

                        // Use variant specific price if it exists, otherwise fall back to global price
                        $unitPrice   = !empty($item['variant_price']) ? (float) $item['variant_price'] : $globalFinal;
                        
                        $lineTotal   = $unitPrice * $item['quantity'];
                        $cartTotal  += $lineTotal;
                        $imagePath   = getImagePath($item['image']);
                        
                        $showDiscountBadge = empty($item['variant_price']) && $isGlobalSale;
                    ?>

                    <div class="cart-item">
                        <div class="item-img">
                            <img src="<?= htmlspecialchars($imagePath) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                        </div>

                        <div class="item-info">
                            <div class="shop-name"><i class="bi bi-shop me-1"></i> <?= htmlspecialchars($item['shop_name']) ?> <i class="bi bi-patch-check-fill text-primary ms-1" style="font-size: 0.9em;"></i></div>
                            <a href="product.php?id=<?= $item['product_id'] ?>" class="product-name"><?= htmlspecialchars($item['name']) ?></a>
                            
                            <?php if(!empty($item['selected_color']) || !empty($item['selected_size'])): ?>
                            <div class="specs-container">
                                <?php if(!empty($item['selected_color'])): ?>
                                    <span class="spec-badge"><i class="bi bi-palette-fill small text-muted me-1"></i> <?= htmlspecialchars($item['selected_color']) ?></span>
                                <?php endif; ?>
                                <?php if(!empty($item['selected_size'])): ?>
                                    <span class="spec-badge"><i class="bi bi-tag-fill small text-muted me-1"></i> <?= htmlspecialchars($item['selected_size']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="item-actions-col">
                            <div class="text-end">
                                <div class="item-price d-flex align-items-center justify-content-end gap-1 mb-1">
                                    <?php if($showDiscountBadge): ?>
                                        <span style="background: #fee2e2; color: #ef4444; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: 800;">-<?= $discount ?>%</span>
                                        <small><?= formatPrice($globalBase * $item['quantity']) ?></small>
                                    <?php endif; ?>
                                    <span><?= formatPrice($lineTotal) ?></span>
                                </div>
                                <div class="small text-muted text-end"><?= formatPrice($unitPrice) ?> each</div>
                            </div>

                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                <form method="POST" action="../routes/cart-update.php" class="m-0">
                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                    <div class="qty-group shadow-sm">
                                        <button type="button" class="qty-control" onclick="this.parentNode.querySelector('.qty-input').stepDown();"><i class="bi bi-dash"></i></button>
                                        <input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="1" class="qty-input">
                                        <button type="button" class="qty-control" onclick="this.parentNode.querySelector('.qty-input').stepUp();"><i class="bi bi-plus"></i></button>
                                        <button type="submit" name="update_qty" class="qty-btn" title="Update Quantity"><i class="bi bi-arrow-clockwise"></i></button>
                                    </div>
                                </form>
                                <button type="button" class="btn-remove" onclick="openRemoveModal(<?= $item['id'] ?>, <?= $item['product_id'] ?>, '<?= addslashes($item['name']) ?>')">
                                    <i class="bi bi-trash3"></i> Remove
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="summary-wrapper">
                <div class="summary-card">
                    <h4 class="fw-bold mb-4" style="color: var(--c-primary);">Order Summary</h4>
                    <div class="summary-row"><span>Subtotal</span><span class="fw-bold text-dark"><?= formatPrice($cartTotal) ?></span></div>
                    <div class="summary-row"><span>Tax Estimate</span><span><?= formatPrice(0) ?></span></div>
                    <div class="summary-row"><span>Shipping</span><span class="fst-italic small">Calculated at checkout</span></div>
                    <div class="summary-row total"><span>Total</span><span><?= formatPrice($cartTotal) ?></span></div>
                    <a href="checkout.php" class="btn-checkout">Proceed to Checkout <i class="bi bi-chevron-right small"></i></a>
                </div>
            </div>
        </div>

        <?php if(!empty($recommendations)): ?>
        <div class="recommendations mt-5 pt-5 border-top">
            <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
                <h3 class="fw-bold mb-0" style="color: #0f172a; font-size: clamp(1.1rem, 3vw, 1.5rem);">Recommended for You</h3>
                <span class="text-muted small fw-medium"><i class="bi bi-arrow-left-right me-1"></i> Swipe to see more</span>
            </div>
            
            <div class="rec-scroll-container">
                <?php foreach($recommendations as $rec): 
                    $rImg = getImagePath($rec['image']);
                    $recPriceVal = ($rec['sale_price'] > 0 && $rec['sale_price'] < $rec['price']) ? $rec['sale_price'] : $rec['price'];
                    $discount = isset($rec['discount_percent']) && $rec['discount_percent'] > 0 ? $rec['discount_percent'] : 0;
                    if($discount == 0 && $rec['sale_price'] > 0 && $rec['sale_price'] < $rec['price']) {
                        $discount = round((($rec['price'] - $rec['sale_price']) / $rec['price']) * 100);
                    }
                ?>
                <div class="rec-card">
                    <?php if($discount > 0): ?>
                        <span class="badge-discount">-<?= $discount ?>%</span>
                    <?php endif; ?>
                    <div class="rec-img"><img src="<?= htmlspecialchars($rImg) ?>" alt="<?= htmlspecialchars($rec['name']) ?>"></div>
                    <div class="rec-body">
                        <div class="rec-vendor">
                            <i class="bi bi-shop"></i> <?= htmlspecialchars($rec['shop_name']) ?> <i class="bi bi-patch-check-fill"></i>
                        </div>
                        <a href="product.php?id=<?= $rec['id'] ?>" class="rec-title"><?= htmlspecialchars($rec['name']) ?></a>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <span class="rec-price"><?= formatPrice($recPriceVal) ?></span>
                            <a href="product.php?id=<?= $rec['id'] ?>" class="btn-view">View</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="modal fade" id="removeChoiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; border: none;">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Remove Item?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-4 text-center">
                <p class="text-muted mb-0">Would you like to move <strong id="modalItemName" class="text-dark"></strong> to your wishlist for later, or remove it entirely?</p>
            </div>
            <div class="modal-footer flex-column align-items-stretch" style="border-top: none; gap: 10px;">
                <button type="button" onclick="handleSaveLater(this)" class="btn btn-primary py-3" style="background:var(--c-primary); border:none;">Save for Later</button>
                <form action="../routes/cart-update.php" method="POST" class="m-0">
                    <input type="hidden" name="item_id" id="modalRemoveItemId">
                    <input type="hidden" name="remove_item" value="1">
                    <button type="submit" class="btn btn-outline-danger w-100 py-3 mt-2">Remove from Cart</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let currentProductId = null;
let currentCartItemId = null;
function openRemoveModal(cartItemId, productId, productName) {
    currentProductId = productId; currentCartItemId = cartItemId;
    document.getElementById('modalItemName').textContent = productName;
    document.getElementById('modalRemoveItemId').value = cartItemId;
    new bootstrap.Modal(document.getElementById('removeChoiceModal')).show();
}
function handleSaveLater(btn) {
    const originalContent = btn.innerHTML; btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Moving...';
    fetch('wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({'product_id': currentProductId})
    }).then(res => res.json()).then(data => {
        if(data.status === 'success') {
            btn.innerHTML = 'Saved!';
            setTimeout(() => {
                const f = document.createElement('form'); f.method = 'POST'; f.action = '../routes/cart-update.php';
                const inputs = { 'item_id': currentCartItemId, 'remove_item': '1' };
                for (const [k, v] of Object.entries(inputs)) { const i = document.createElement('input'); i.type = 'hidden'; i.name = k; i.value = v; f.appendChild(i); }
                document.body.appendChild(f); f.submit();
            }, 800);
        } else { alert(data.message); btn.disabled = false; btn.innerHTML = originalContent; }
    });
}
</script>

<?php require_once __DIR__ . "/partials/footer.php"; ?>
</body>
</html>