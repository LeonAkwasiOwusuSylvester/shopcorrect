<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/helpers/currency.php";

// Get the product ID from the URL
$productId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : null;
$product   = null;
$isValid   = false;

if ($productId) {
    $stmt = $pdo->prepare("
        SELECT p.*, v.shop_name
        FROM products p
        JOIN vendors v ON p.vendor_id = v.id
        WHERE p.id = ? AND p.status != 'is_deleted'
        LIMIT 1
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        $isValid = true;
    }
}

// ── Resolve product image (checks both upload paths) ────────────────
function getVerifyImagePath($imageName) {
    if (empty($imageName)) return 'assets/images/no-image.png';
    $p1 = 'uploads/products/' . $imageName;
    if (file_exists(__DIR__ . '/' . $p1)) return $p1;
    $p2 = 'uploads/' . $imageName;
    if (file_exists(__DIR__ . '/' . $p2)) return $p2;
    return 'assets/images/no-image.png';
}

$productImage = $product ? getVerifyImagePath($product['image']) : '';

// ── Pricing Logic ──────────────────────────────────────────────────────────
$displayPrice = '0.00';
$isOnSale = false;
$discount = 0;

if ($product) {
    $basePrice  = (float) $product['price'];
    $salePrice  = (float) $product['sale_price'];
    $discount   = (int)   $product['discount_percent'];
    $isOnSale   = ($discount > 0 && $salePrice > 0 && $salePrice < $basePrice);
    $finalPrice = $isOnSale ? $salePrice : $basePrice;

    // Check for variations to determine if we need a price range
    $varStmt = $pdo->prepare("SELECT price FROM product_variants WHERE product_id = ?");
    $varStmt->execute([$productId]);
    $variants = $varStmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($variants) > 0) {
        $prices = [];
        foreach ($variants as $v) {
            $prices[] = !empty($v['price']) ? (float)$v['price'] : $finalPrice;
        }
        $minPrice = min($prices);
        $maxPrice = max($prices);

        if ($minPrice != $maxPrice) {
            $displayPrice = formatPrice($minPrice) . ' - ' . formatPrice($maxPrice);
            $isOnSale = false; // Usually hide sale badges on ranges to prevent confusion
        } else {
            $displayPrice = formatPrice($finalPrice);
        }
    } else {
        $displayPrice = formatPrice($finalPrice);
    }
}

// Include your public navbar
require_once __DIR__ . "/partials/navbar.php";
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">

<style>
    body { font-family: 'Inter', sans-serif; background: #F0F4F8; }

    .verify-wrapper {
        min-height: calc(100vh - 80px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 32px 16px;
    }

    .verify-card {
        background: #fff;
        border-radius: 24px;
        max-width: 460px;
        width: 100%;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0,0,0,0.10);
    }

    /* ── Status banner ── */
    .status-banner {
        padding: 28px 24px 20px;
        text-align: center;
    }
    .status-banner.authentic { background: linear-gradient(135deg, #0B2447 0%, #1e3a8a 100%); }
    .status-banner.invalid   { background: linear-gradient(135deg, #7f1d1d 0%, #dc2626 100%); }

    .status-icon-wrap {
        width: 68px;
        height: 68px;
        border-radius: 50%;
        background: rgba(255,255,255,0.15);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        margin-bottom: 12px;
    }
    .status-icon-wrap.ok  { color: #4ade80; }
    .status-icon-wrap.err { color: #fca5a5; }

    .status-title { color: white; font-weight: 800; font-size: 1.25rem; margin: 0 0 4px; }
    .status-sub   { color: rgba(255,255,255,0.72); font-size: 0.82rem; margin: 0; }

    /* ── Product block ── */
    .product-section {
        padding: 20px 24px;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        gap: 16px;
        align-items: center;
    }
    .product-img-wrap {
        width: 88px;
        height: 88px;
        border-radius: 12px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        overflow: hidden;
        padding: 6px;
    }
    .product-img-wrap img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .product-name  { font-weight: 700; color: #0f172a; font-size: 1rem; margin: 0 0 4px; line-height: 1.35; }
    .product-shop  { font-size: 0.78rem; color: #64748b; font-weight: 600; margin: 0 0 8px; }
    .product-price { font-weight: 800; color: #0B2447; font-size: 1.1rem; }

    /* ── Detail rows ── */
    .detail-section { padding: 16px 24px; }
    .detail-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 9px 0;
        border-bottom: 1px solid #f8fafc;
        font-size: 0.88rem;
    }
    .detail-row:last-child { border-bottom: none; }
    .detail-label { color: #94a3b8; font-weight: 600; }
    .detail-value { color: #0f172a; font-weight: 700; text-align: right; }
    .badge-verified    { background: #dcfce7; color: #15803d; padding: 3px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; }
    .badge-unavailable { background: #f1f5f9; color: #64748b;  padding: 3px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; }

    /* ── CTA ── */
    .cta-section { padding: 4px 24px 24px; display: flex; flex-direction: column; gap: 10px; }
    .btn-cta-primary {
        display: block; width: 100%; background: #0B2447; color: white;
        font-weight: 700; padding: 14px; border-radius: 50px;
        text-decoration: none; text-align: center; font-size: 0.95rem;
        border: none; cursor: pointer; transition: background 0.2s, transform 0.2s;
    }
    .btn-cta-primary:hover { background: #1e3a8a; color: white; transform: translateY(-1px); }
    .btn-cta-light {
        display: block; width: 100%; background: #f1f5f9; color: #334155;
        font-weight: 700; padding: 14px; border-radius: 50px;
        text-decoration: none; text-align: center; font-size: 0.88rem;
        border: none; cursor: pointer; transition: background 0.2s;
    }
    .btn-cta-light:hover { background: #e2e8f0; color: #334155; }
    .btn-cta-danger {
        display: block; width: 100%; background: #fff0f0; color: #dc2626;
        font-weight: 700; padding: 12px; border-radius: 50px;
        text-decoration: none; text-align: center; font-size: 0.85rem;
        border: 1.5px solid #fecaca; cursor: pointer; transition: background 0.2s;
    }
    .btn-cta-danger:hover { background: #fee2e2; color: #b91c1c; }

    /* ── Warning box (invalid) ── */
    .warning-box {
        margin: 0 24px 20px;
        background: #fff7ed;
        border: 1px solid #fed7aa;
        border-radius: 12px;
        padding: 14px 16px;
        font-size: 0.85rem;
    }
</style>

<div class="verify-wrapper">
    <div class="verify-card">

        <?php if ($isValid): ?>

            <div class="status-banner authentic">
                <div class="status-icon-wrap ok">
                    <i class="bi bi-patch-check-fill"></i>
                </div>
                <p class="status-title"><?= $lang['verified_authentic'] ?? 'Product Verified ✓' ?></p>
                <p class="status-sub"><?= $lang['verified_desc'] ?? 'This product is registered and verified on ShopCorrect.' ?></p>
            </div>

            <div class="product-section">
                <div class="product-img-wrap">
                    <img src="<?= htmlspecialchars($productImage) ?>"
                         alt="<?= htmlspecialchars($product['name']) ?>">
                </div>
                <div>
                    <p class="product-name"><?= htmlspecialchars($product['name']) ?></p>
                    <p class="product-shop">
                        <i class="bi bi-shop me-1"></i><?= htmlspecialchars($product['shop_name']) ?>
                        <i class="bi bi-patch-check-fill text-primary ms-1" style="font-size:0.8em;"></i>
                    </p>
                    <span class="product-price"><?= $displayPrice ?></span>
                    <?php if ($isOnSale): ?>
                        <span class="ms-2 badge bg-danger" style="font-size:0.72rem;">-<?= $discount ?>% OFF</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="detail-section">
                <div class="detail-row">
                    <span class="detail-label"><?= $lang['sold_by'] ?? 'Sold By' ?></span>
                    <span class="detail-value"><?= htmlspecialchars($product['shop_name']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><?= $lang['price'] ?? 'Price' ?></span>
                    <span class="detail-value"><?= $displayPrice ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Product ID</span>
                    <span class="detail-value">#<?= $product['id'] ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><?= $lang['status'] ?? 'Status' ?></span>
                    <span class="detail-value">
                        <?php if ($product['status'] === 'active'): ?>
                            <span class="badge-verified">
                                <i class="bi bi-check-circle-fill me-1"></i><?= $lang['in_stock'] ?? 'In Stock' ?>
                            </span>
                        <?php else: ?>
                            <span class="badge-unavailable"><?= $lang['unavailable'] ?? 'Unavailable' ?></span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Platform</span>
                    <span class="detail-value">ShopCorrect Marketplace</span>
                </div>
            </div>

            <div class="cta-section">
                <a href="product.php?id=<?= $product['id'] ?>" class="btn-cta-primary">
                    <i class="bi bi-bag me-2"></i><?= $lang['view_product'] ?? 'View Product Page' ?>
                </a>
                <a href="index.php" class="btn-cta-light">
                    <i class="bi bi-house me-2"></i><?= $lang['return_home'] ?? 'Return to Homepage' ?>
                </a>
                <button type="button" class="btn-cta-danger" data-bs-toggle="modal" data-bs-target="#reportModal">
                    <i class="bi bi-flag me-2"></i><?= $lang['report_item'] ?? 'Report Suspicious Item' ?>
                </button>
            </div>

        <?php else: ?>

            <div class="status-banner invalid">
                <div class="status-icon-wrap err">
                    <i class="bi bi-shield-x-fill"></i>
                </div>
                <p class="status-title"><?= $lang['unverified_product'] ?? 'Verification Failed' ?></p>
                <p class="status-sub"><?= $lang['unverified_desc'] ?? 'This product could not be verified on ShopCorrect.' ?></p>
            </div>

            <div class="detail-section">
                <div class="detail-row">
                    <span class="detail-label">Product ID</span>
                    <span class="detail-value"><?= $productId ? '#' . $productId : 'Invalid' ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><?= $lang['status'] ?? 'Status' ?></span>
                    <span class="detail-value">
                        <span style="background:#fee2e2;color:#dc2626;padding:3px 10px;border-radius:50px;font-size:0.75rem;font-weight:700;">
                            <i class="bi bi-x-circle-fill me-1"></i>Not Found
                        </span>
                    </span>
                </div>
            </div>

            <div class="warning-box">
                <p class="mb-1 fw-bold text-dark">
                    <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i><?= $lang['warning'] ?? 'Warning' ?>
                </p>
                <p class="mb-0 text-muted">
                    <?= $lang['warning_desc'] ?? 'Do not purchase this item if the seller claims it is verified by ShopCorrect. The QR code may be fake or the item has been removed.' ?>
                </p>
            </div>

            <div class="cta-section">
                <a href="index.php" class="btn-cta-primary">
                    <i class="bi bi-house me-2"></i><?= $lang['return_home'] ?? 'Return to Homepage' ?>
                </a>
                <button type="button" class="btn-cta-danger" data-bs-toggle="modal" data-bs-target="#reportModal">
                    <i class="bi bi-flag me-2"></i><?= $lang['report_item'] ?? 'Report Suspicious Item' ?>
                </button>
            </div>

        <?php endif; ?>

    </div>
</div>

<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="reportModalLabel">
                    <i class="bi bi-flag text-danger me-2"></i><?= $lang['report_product'] ?? 'Report Product' ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-4">
                    <?= $lang['report_help_text'] ?? 'Help us keep ShopCorrect safe. Please let us know why you are reporting this item.' ?>
                </p>

                <form action="../routes/report.php" method="POST">
                    <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['id'] ?? '') ?>">

                    <div class="mb-3 text-start">
                        <label class="form-label fw-bold small text-dark">
                            <?= $lang['report_reason'] ?? 'Reason for reporting' ?> <span class="text-danger">*</span>
                        </label>
                        <select name="reason" class="form-select bg-light" required>
                            <option value="" disabled selected><?= $lang['select_reason'] ?? 'Select a reason...' ?></option>
                            <option value="fake"><?= $lang['reason_fake'] ?? 'Suspected Fake / Counterfeit' ?></option>
                            <option value="misleading"><?= $lang['reason_misleading'] ?? 'Misleading Description' ?></option>
                            <option value="offensive"><?= $lang['reason_offensive'] ?? 'Offensive or Inappropriate Content' ?></option>
                            <option value="damaged"><?= $lang['reason_damaged'] ?? 'Item appears damaged in photos' ?></option>
                            <option value="other"><?= $lang['reason_other'] ?? 'Other' ?></option>
                        </select>
                    </div>

                    <div class="mb-4 text-start">
                        <label class="form-label fw-bold small text-dark">
                            <?= $lang['report_details'] ?? 'Additional Details (Optional)' ?>
                        </label>
                        <textarea name="details" class="form-control bg-light" rows="3"
                                  placeholder="<?= $lang['report_placeholder'] ?? 'Provide any extra details here...' ?>"></textarea>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-danger fw-bold rounded-pill">
                            <?= $lang['submit_report'] ?? 'Submit Report' ?>
                        </button>
                        <button type="button" class="btn btn-light fw-bold rounded-pill text-muted" data-bs-dismiss="modal">
                            <?= $lang['cancel'] ?? 'Cancel' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>