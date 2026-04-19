<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/../app/helpers/currency.php";
require_once __DIR__ . "/partials/navbar.php";

// Extract active currency symbol for JS
$fmt0 = formatPrice(0);
preg_match('/^[^\d]*/', $fmt0, $preMatch);
preg_match('/[^\d]*$/', $fmt0, $sufMatch);
$activeSymbol = trim($preMatch[0] . $sufMatch[0]); 
if(empty($activeSymbol)) $activeSymbol = '₵'; 

/* ----------------------------------------------------------
| 1. Validate & Fetch Main Product
|---------------------------------------------------------- */
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    die('<div class="container py-5 text-center"><h3>Product not found</h3><a href="index.php" class="btn btn-dark mt-3">Return Home</a></div>');
}

$productId = (int) $_GET["id"];

$stmt = $pdo->prepare("
    SELECT p.*, v.shop_name, v.id AS vendor_id
    FROM products p
    JOIN vendors v ON p.vendor_id = v.id
    WHERE p.id = ? AND p.status != 'is_deleted'
    LIMIT 1
");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    die('<div class="container py-5 text-center"><h3>Product unavailable</h3><a href="index.php" class="btn btn-dark mt-3">Return Home</a></div>');
}

/* ----------------------------------------------------------
| 2. Fetch Product Variants & Legacy Fallback
|---------------------------------------------------------- */
$varStmt = $pdo->prepare("SELECT id, color, size, price, stock FROM product_variants WHERE product_id = ?");
$varStmt->execute([$productId]);
$variants = $varStmt->fetchAll(PDO::FETCH_ASSOC);

$hasNewVariants = count($variants) > 0;

$basePrice  = (float) $product['price'];
$salePrice  = (float) $product['sale_price'];
$discount   = (int)   $product['discount_percent'];
$isOnSale   = ($discount > 0 && $salePrice > 0 && $salePrice < $basePrice);
$finalPrice = $isOnSale ? $salePrice : $basePrice;

$uniqueColors = [];
$uniqueSizes = [];
$totalVariantStock = 0;

if ($hasNewVariants) {
    foreach ($variants as $v) {
        $totalVariantStock += (int)$v['stock'];
    }
    $displayStock = $totalVariantStock;
} else {
    $displayStock = (int)$product['stock'];
    if (!empty($product['colors'])) {
        $uniqueColors = array_values(array_filter(array_map('trim', explode(',', $product['colors']))));
    }
    if (!empty($product['sizes'])) {
        $uniqueSizes = array_values(array_filter(array_map('trim', explode(',', $product['sizes']))));
    }
}

/* ----------------------------------------------------------
| 3. Helper Function for Image Paths & URL
|---------------------------------------------------------- */
function getImagePath($imageName) {
    if (empty($imageName)) return 'assets/images/no-image.png';
    $path1 = 'uploads/products/' . $imageName;
    if (file_exists(__DIR__ . '/../public/' . $path1)) return $path1;
    $path2 = 'uploads/' . $imageName;
    if (file_exists(__DIR__ . '/../public/' . $path2)) return $path2;
    return 'assets/images/no-image.png';
}

function getSiteBaseUrl() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'];
    $script = dirname($_SERVER['SCRIPT_NAME']); 
    return $scheme . '://' . $host . rtrim($script, '/');
}
$siteBaseUrl = getSiteBaseUrl(); 

/* ----------------------------------------------------------
| 4. Process Product Data
|---------------------------------------------------------- */
$mainImage  = getImagePath($product['image']);
$galleryRaw = !empty($product['gallery']) ? json_decode($product['gallery'], true) : [];
$gallery    = [];

if (empty($galleryRaw) && !empty($product['image'])) {
    $gallery[] = $mainImage;
} else {
    foreach ($galleryRaw as $imgName) $gallery[] = getImagePath($imgName);
}

// Specs decoder
$specRawData = $product['specifications'] ?? '';
$tempSpecs   = [];

if (!empty($specRawData)) {
    $decoded = json_decode($specRawData, true);
    if (is_string($decoded)) $decoded = json_decode($decoded, true);
    if (!is_array($decoded)) $decoded = @unserialize($specRawData);

    if (is_array($decoded)) {
        if ((isset($decoded['key']) || isset($decoded['keys'])) && (isset($decoded['value']) || isset($decoded['values']))) {
            $keys = array_values((array)($decoded['key'] ?? $decoded['keys']));
            $vals = array_values((array)($decoded['value'] ?? $decoded['values']));
            foreach ($keys as $i => $k) {
                $v = $vals[$i] ?? '';
                if (!empty(trim((string)$k)) && trim((string)$v) !== '') $tempSpecs[trim((string)$k)] = trim((string)$v);
            }
        } else {
            foreach ($decoded as $k => $v) {
                if (is_array($v)) {
                    $subKey = $v['key'] ?? $v['name'] ?? ($v[0] ?? null);
                    $subVal = $v['value'] ?? $v['val'] ?? ($v[1] ?? null);
                    if ($subKey !== null && $subVal !== null) {
                        if (!empty(trim((string)$subKey)) && trim((string)$subVal) !== '') $tempSpecs[trim((string)$subKey)] = trim((string)$subVal);
                    } else {
                        foreach ($v as $sk => $sv) {
                            if (!is_numeric($sk) && is_scalar($sv) && trim((string)$sv) !== '') $tempSpecs[trim((string)$sk)] = trim((string)$sv);
                        }
                    }
                } elseif (is_scalar($v)) {
                    if (!is_numeric($k)) {
                        if (!empty(trim((string)$k)) && trim((string)$v) !== '') $tempSpecs[trim((string)$k)] = trim((string)$v);
                    } else {
                        if (is_string($v) && strpos($v, ':') !== false) {
                            $parts = explode(':', $v, 2);
                            if (!empty(trim($parts[0])) && trim($parts[1]) !== '') $tempSpecs[trim($parts[0])] = trim($parts[1]);
                        }
                    }
                }
            }
        }
    }
}

$specs = [];
foreach ($tempSpecs as $k => $v) $specs[ucwords(str_replace(['_', '-'], ' ', $k))] = $v;

/* ----------------------------------------------------------
| 5. Reviews & Pricing
|---------------------------------------------------------- */
$reviewsStmt = $pdo->prepare("SELECT r.rating, r.comment, r.created_at, u.name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? ORDER BY r.created_at DESC");
$reviewsStmt->execute([$productId]);
$reviews = $reviewsStmt->fetchAll();

$ratingStmt = $pdo->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS total FROM reviews WHERE product_id = ?");
$ratingStmt->execute([$productId]);
$rating = $ratingStmt->fetch();

/* ----------------------------------------------------------
| 6. Smart Recommendations
|---------------------------------------------------------- */
$recStmt = $pdo->prepare("SELECT p.*, v.shop_name FROM products p JOIN vendors v ON p.vendor_id = v.id WHERE p.id != ? AND p.category_id = ? AND p.status = 'active' AND p.is_deleted = 0 ORDER BY RAND() LIMIT 4");
$recStmt->execute([$productId, $product['category_id']]);
$recommendations = $recStmt->fetchAll(PDO::FETCH_ASSOC);

if (count($recommendations) < 4) {
    $needed     = 4 - count($recommendations);
    $excludeIds = [$productId];
    foreach ($recommendations as $r) $excludeIds[] = $r['id'];
    $excludeStr = implode(',', $excludeIds);
    $fbStmt     = $pdo->query("SELECT p.*, v.shop_name FROM products p JOIN vendors v ON p.vendor_id = v.id WHERE p.id NOT IN ($excludeStr) AND p.status = 'active' AND p.is_deleted = 0 ORDER BY RAND() LIMIT $needed");
    $recommendations = array_merge($recommendations, $fbStmt->fetchAll(PDO::FETCH_ASSOC));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?></title>
</head>
<body>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1055;">
    <div id="cartToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-bold"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<style>
    /* ── Base ── */
    body { font-family: 'Inter', sans-serif; background-color: #F8FAFC; color: #334155; }

    /* ── Breadcrumb ── */
    .breadcrumb-item a       { color: #64748b; text-decoration: none; font-size: clamp(0.78rem, 1.5vw, 0.9rem); }
    .breadcrumb-item.active  { color: #94a3b8; font-size: clamp(0.78rem, 1.5vw, 0.9rem); }

    /* ══════════════════════════════
       GALLERY
    ══════════════════════════════ */
    .gallery-container {
        background: #fff;
        border-radius: 16px;
        padding: clamp(12px, 2vw, 20px);
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
    }

    .main-image-frame {
        height: clamp(220px, 40vw, 400px);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 16px;
        overflow: hidden;
    }
    .main-image-frame img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        transition: transform 0.3s ease;
    }

    .thumb-grid {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding-bottom: 4px;
        scrollbar-width: none;
    }
    .thumb-grid::-webkit-scrollbar { display: none; }
    .thumb-item {
        width: clamp(52px, 8vw, 70px);
        height: clamp(52px, 8vw, 70px);
        border-radius: 8px;
        border: 2px solid transparent;
        cursor: pointer;
        overflow: hidden;
        opacity: 0.7;
        transition: 0.2s;
        flex-shrink: 0;
    }
    .thumb-item img { width: 100%; height: 100%; object-fit: cover; }
    .thumb-item:hover, .thumb-item.active { border-color: #0B2447; opacity: 1; }

    /* ══════════════════════════════
       TABS
    ══════════════════════════════ */
    .nav-tabs {
        border-bottom: 1px solid #e2e8f0;
        gap: 4px;
        flex-wrap: nowrap;
        overflow-x: auto;
        scrollbar-width: none;
        -webkit-overflow-scrolling: touch;
    }
    .nav-tabs::-webkit-scrollbar { display: none; }
    .nav-tabs .nav-link {
        color: #64748b;
        font-weight: 600;
        font-size: clamp(0.82rem, 1.5vw, 1rem);
        border: none;
        padding: 10px 16px;
        background: transparent;
        transition: 0.2s;
        white-space: nowrap;
    }
    .nav-tabs .nav-link:hover { color: #0f172a; }
    .nav-tabs .nav-link.active {
        color: #0B2447 !important;
        background-color: #F8FAFC !important;
        border: 3px solid #bfdbfe !important;
        border-bottom-color: #F8FAFC !important;
        border-radius: 8px 8px 0 0;
        margin-bottom: -1px;
    }

    /* ══════════════════════════════
       SPECS TABLE
    ══════════════════════════════ */
    .specs-wrapper {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
    }
    .specs-table {
        width: 100%;
        min-width: 400px;
        font-size: clamp(0.82rem, 1.4vw, 0.95rem);
        border-collapse: collapse;
        background: #fff;
    }
    .specs-table th {
        width: 35%;
        color: #64748b;
        background-color: #f8fafc;
        font-weight: 600;
        padding: clamp(10px, 2vw, 16px) clamp(14px, 2vw, 24px);
        text-align: left;
        border-bottom: 1px solid #e2e8f0;
        border-right: 1px solid #f1f5f9;
    }
    .specs-table td {
        color: #0f172a;
        background-color: #fff;
        font-weight: 500;
        padding: clamp(10px, 2vw, 16px) clamp(14px, 2vw, 24px);
        border-bottom: 1px solid #e2e8f0;
    }
    .specs-table tr:last-child th,
    .specs-table tr:last-child td { border-bottom: none; }
    .specs-table tr:hover td { background-color: #f8fafc; }

    /* ══════════════════════════════
       STICKY SIDEBAR
    ══════════════════════════════ */
    @media (min-width: 992px) {
        .sticky-sidebar { position: sticky; top: 100px; }
    }

    /* ══════════════════════════════
       PURCHASE BOX
    ══════════════════════════════ */
    .purchase-container {
        background: white;
        padding: clamp(18px, 3vw, 30px);
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);
    }

    .product-title {
        font-size: clamp(1.2rem, 3vw, 1.75rem);
        font-weight: 800;
        color: #0f172a;
        line-height: 1.3;
    }

    .price-main  { font-size: clamp(1.4rem, 4vw, 2rem); font-weight: 800; color: #0B2447; line-height: 1; transition: 0.3s; }
    .price-old   { text-decoration: line-through; color: #94a3b8; font-size: clamp(0.85rem, 2vw, 1rem); }
    .option-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b; display: block; }

    /* --- COMPACT VARIANT GRID CARDS --- */
    .variants-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
        gap: 10px;
    }
    .variant-list-card {
        display: block;
        cursor: pointer;
        user-select: none;
        height: 100%;
        margin: 0;
    }
    .variant-list-radio { display: none; }
    .v-card-inner {
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        padding: 10px 12px;
        background: #fff;
        transition: all 0.2s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
        text-align: center;
    }
    .v-card-inner:hover {
        border-color: #cbd5e1;
        background: #f8fafc;
    }
    .variant-list-radio:checked + .v-card-inner {
        border-color: #0B2447;
        background: #f0f9ff;
        box-shadow: 0 4px 10px rgba(11,36,71,0.08);
    }
    
    .v-card-title { font-size: 0.85rem; font-weight: 700; color: #1e293b; line-height: 1.2; margin-bottom: 4px; }
    .v-card-price { font-size: 0.95rem; font-weight: 800; color: #0B2447; margin-bottom: 4px; }
    .v-card-stock { font-size: 0.7rem; font-weight: 700; }
    
    .variant-list-card.out-of-stock { opacity: 0.5; cursor: not-allowed; }
    .variant-list-card.out-of-stock .v-card-inner:hover { border-color: #e2e8f0; background: #fff; }

    /* Visual Color Dots */
    .color-dot-small {
        display: inline-block;
        width: 12px; height: 12px;
        border-radius: 50%;
        border: 1px solid rgba(0,0,0,0.15);
        vertical-align: middle;
        box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
    }

    /* --- LEGACY FALLBACK SELECTORS --- */
    .legacy-radio { display: none; }
    .legacy-label {
        border: 1px solid #cbd5e1;
        padding: clamp(6px, 1.5vw, 8px) clamp(12px, 2vw, 16px);
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        font-size: clamp(0.78rem, 1.5vw, 0.85rem);
        color: #334155;
        min-width: 44px;
        text-align: center;
        background: #fff;
        transition: 0.2s;
        display: inline-block;
    }
    .legacy-radio:checked + .legacy-label { background-color: #0B2447; color: white; border-color: #0B2447; }
    .color-swatch {
        width: clamp(26px, 4vw, 32px);
        height: clamp(26px, 4vw, 32px);
        border-radius: 50%;
        cursor: pointer;
        border: 2px solid #e2e8f0;
        display: inline-block;
        position: relative;
        transition: all 0.2s;
    }
    .legacy-radio:checked + .color-swatch { border-color: #0B2447; transform: scale(1.1); box-shadow: 0 0 0 2px white inset; }

    @keyframes shake {
        0% { transform: translateX(0); } 25% { transform: translateX(-5px); }
        50% { transform: translateX(5px); } 75% { transform: translateX(-5px); }
        100% { transform: translateX(0); }
    }
    .validation-error { border: 2px solid #dc3545 !important; animation: shake 0.3s ease-in-out; border-radius: 12px; }

    .btn-add-cart {
        background-color: #0B2447;
        color: #fff;
        font-weight: 700;
        height: 50px;
        min-height: 50px;
        border-radius: 50px;
        border: none;
        font-size: clamp(0.88rem, 2vw, 1rem);
        width: 100%;
        transition: 0.2s;
    }
    .btn-add-cart:hover { background-color: #1e3a8a; transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(11,36,71,0.3); }
    .btn-add-cart:disabled { background-color: #cbd5e1; color: #64748b; cursor: not-allowed; transform: none; box-shadow: none; }

    .cart-row {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    .cart-row .qty-input {
        width: 80px;
        flex-shrink: 0;
        height: 50px;
        text-align: center;
        font-weight: 700;
    }
    @media (max-width: 360px) {
        .cart-row { flex-direction: column; }
        .cart-row .qty-input { width: 100%; }
    }

    /* ── QR Box ── */
    .qr-verification-box {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #fff;
        padding: clamp(14px, 2vw, 24px);
        text-align: center;
    }
    .qr-verification-box .qr-image-wrap {
        display: inline-block;
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 8px;
        margin: 10px 0;
    }
    .qr-verification-box .qr-image-wrap img {
        width: clamp(90px, 20vw, 120px);
        height: clamp(90px, 20vw, 120px);
        display: block;
    }

    /* ══════════════════════════════
       RECOMMENDATIONS
    ══════════════════════════════ */
    .rec-scroll-container {
        display: flex;
        gap: 1rem;
        overflow-x: auto;
        padding: 8px 4px 20px;
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .rec-scroll-container::-webkit-scrollbar { display: none; }

    .rec-card {
        min-width: clamp(180px, 45vw, 240px);
        max-width: clamp(180px, 45vw, 240px);
        background: #fff;
        border: 1px solid #eef2f7;
        border-radius: 16px;
        overflow: hidden;
        transition: 0.2s;
        position: relative;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        flex-shrink: 0;
    }
    .rec-card:hover { transform: translateY(-4px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }

    .rec-img {
        height: clamp(120px, 20vw, 160px);
        display: flex; align-items: center; justify-content: center;
        padding: 12px;
        background: #f8fafc;
        border-bottom: 1px solid #f1f5f9;
    }
    .rec-img img { max-height: 100%; max-width: 100%; object-fit: contain; mix-blend-mode: multiply; }

    .rec-body { padding: clamp(10px, 2vw, 20px) clamp(10px, 2vw, 16px); }
    .rec-vendor { font-size: 0.72rem; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 5px; display: flex; align-items: center; gap: 4px; }
    .rec-vendor i.bi-patch-check-fill { color: #3b82f6; }
    .rec-title { font-size: clamp(0.85rem, 1.8vw, 1rem); font-weight: 700; color: #0f172a; display: block; text-decoration: none; margin-bottom: 10px; height: 2.8em; overflow: hidden; line-height: 1.4; }
    .rec-title:hover { color: #3b82f6; }
    .rec-price { font-weight: 800; color: #0f172a; font-size: clamp(1rem, 2vw, 1.2rem); }
    .btn-view { font-size: 0.82rem; padding: 5px 14px; border-radius: 50px; background: #f1f5f9; color: #0f172a; text-decoration: none; font-weight: 700; transition: 0.2s; white-space: nowrap; }
    .btn-view:hover { background: #e2e8f0; }
    .badge-discount { position: absolute; top: 10px; left: 10px; background: #ef4444; color: white; font-size: 0.72rem; font-weight: 700; padding: 3px 8px; border-radius: 6px; z-index: 2; }
</style>

<main>
<div class="container py-4 mb-5">

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="index.php">Products</a></li>
            <li class="breadcrumb-item active text-truncate" style="max-width:200px;">
                <?= htmlspecialchars($product['name']) ?>
            </li>
        </ol>
    </nav>

    <div class="row g-3 g-lg-5">

        <div class="col-lg-7">

            <div class="gallery-container mb-4">
                <div class="main-image-frame">
                    <img id="mainDisplayImage"
                         src="<?= htmlspecialchars($mainImage) ?>"
                         alt="<?= htmlspecialchars($product['name']) ?>">
                </div>
                <?php if (!empty($gallery)): ?>
                <div class="thumb-grid">
                    <?php
                    $firstImg = reset($gallery);
                    $counter  = 0;
                    ?>
                    <div class="thumb-item active" onclick="changeImage(this,'<?= htmlspecialchars($firstImg) ?>')">
                        <img src="<?= htmlspecialchars($firstImg) ?>">
                    </div>
                    <?php foreach ($gallery as $img):
                        if ($img === $firstImg && $counter === 0) { $counter++; continue; } ?>
                        <div class="thumb-item" onclick="changeImage(this,'<?= htmlspecialchars($img) ?>')">
                            <img src="<?= htmlspecialchars($img) ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#desc" type="button">Description</button>
                </li>
                <?php if (!empty($specs)): ?>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#specs" type="button">Specifications</button>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#reviews" type="button">
                        Reviews (<?= count($reviews) ?>)
                    </button>
                </li>
            </ul>

            <div class="tab-content py-3" id="myTabContent">

                <div class="tab-pane fade show active" id="desc">
                    <div class="text-secondary" style="line-height:1.8;font-size:clamp(0.88rem,1.5vw,1rem);">
                        <?php
                        foreach (explode("\n", $product['description']) as $line) {
                            $line = trim($line);
                            if (empty($line)) continue;
                            $fc = mb_substr($line, 0, 1);
                            if (in_array($fc, ['-', '*', '•'])) {
                                $content = trim(mb_substr($line, 1));
                                echo '<div class="d-flex align-items-start mb-2"><i class="bi bi-check2-circle text-primary me-2 flex-shrink-0" style="font-size:1.1em;margin-top:2px;"></i><span>' . htmlspecialchars($content) . '</span></div>';
                            } else {
                                echo '<p class="mb-3">' . htmlspecialchars($line) . '</p>';
                            }
                        }
                        ?>
                    </div>
                </div>

                <?php if (!empty($specs)): ?>
                <div class="tab-pane fade" id="specs">
                    <div class="specs-wrapper">
                        <table class="specs-table mb-0">
                            <tbody>
                                <?php foreach ($specs as $key => $val): ?>
                                <tr>
                                    <th><?= htmlspecialchars($key) ?></th>
                                    <td><?= htmlspecialchars($val) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <div class="tab-pane fade" id="reviews">
                    <?php if (!$reviews): ?>
                        <div class="p-4 bg-light rounded-3 text-center">
                            <i class="bi bi-chat-square-text fs-1 text-muted opacity-25"></i>
                            <p class="text-muted mt-3 mb-0">No reviews yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($reviews as $r): ?>
                        <div class="mb-4 pb-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-1">
                                <span class="fw-bold text-dark"><?= htmlspecialchars($r['name']) ?></span>
                                <small class="text-muted"><?= date('M d, Y', strtotime($r['created_at'])) ?></small>
                            </div>
                            <div class="text-warning small">
                               <?php for ($i=1; $i<=5; $i++) echo $i<=round((float)($rating['avg_rating'] ?? 0)) ? '<i class="bi bi-star-fill"></i> ' : '<i class="bi bi-star text-muted opacity-25"></i> '; ?>
                            </div>
                            <p class="text-secondary small mb-0"><?= htmlspecialchars($r['comment']) ?></p>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div></div><div class="col-lg-5">
            <div class="sticky-sidebar">

                <div class="purchase-container mb-4">

                    <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                        <div class="badge bg-light text-dark border d-inline-flex align-items-center">
                            <i class="bi bi-shop me-1"></i><?= htmlspecialchars($product['shop_name']) ?>
                            <i class="bi bi-patch-check-fill text-primary ms-1" style="font-size:0.85em;"></i>
                        </div>
                        <?php if ($isOnSale && !$hasNewVariants): ?><div class="badge bg-danger">Sale</div><?php endif; ?>
                    </div>

                    <h1 class="product-title mb-2"><?= htmlspecialchars($product['name']) ?></h1>

                    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                        <div class="text-warning small">
                            <?php for ($i=1; $i<=5; $i++) echo $i<=round($rating['avg_rating']) ? '<i class="bi bi-star-fill"></i> ' : '<i class="bi bi-star text-muted opacity-25"></i> '; ?>
                        </div>
                        <span class="text-muted small fw-semibold">
                            <?= number_format((float)$rating['avg_rating'], 1) ?> (<?= $rating['total'] ?> Reviews)
                        </span>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex align-items-baseline gap-2 flex-wrap">
                            <div class="price-main" id="mainPriceDisplay">
                                <?php 
                                if ($hasNewVariants) {
                                    echo '<span style="font-size: clamp(1rem, 2.5vw, 1.25rem); color: #94a3b8; font-weight: 600;">Select an option to see price</span>';
                                } else {
                                    echo formatPrice($finalPrice);
                                }
                                ?>
                            </div>
                            
                            <div id="discountContainer" class="d-flex align-items-baseline gap-2 <?= (!$hasNewVariants && $isOnSale) ? '' : 'd-none' ?>">
                                <div class="price-old" id="oldPriceDisplay"><?= formatPrice($basePrice) ?></div>
                                <span class="badge bg-danger-subtle text-danger fw-bold rounded px-2" id="discountBadgeDisplay">-<?= $discount ?>%</span>
                            </div>
                        </div>
                    </div>

                    <form id="addToCartForm" onsubmit="handleAddToCart(event)">
                        <input type="hidden" name="product_id" value="<?= $productId ?>">

                        <?php if ($hasNewVariants): ?>
                            <div class="mb-4" id="variantCardContainer">
                                <span class="option-label mb-3">Available Options <span class="text-danger">*</span></span>
                                <div class="variants-grid">
                                    <?php foreach ($variants as $index => $v): 
                                        $hasColor = !empty($v['color']);
                                        $hasSize  = !empty($v['size']);
                                        
                                        $vPrice = !empty($v['price']) ? (float)$v['price'] : $finalPrice;
                                        $vStock = (int)$v['stock'];
                                        
                                        // ✅ NEW: Strict 4-item Stock Thresholds for Variant Cards
                                        $stockText = "Out of Stock";
                                        $stockClass = "text-danger";
                                        if ($vStock > 0 && $vStock <= 4) {
                                            $stockText = "Only {$vStock} left!";
                                            $stockClass = "text-warning";
                                        } elseif ($vStock > 4) {
                                            $stockText = "In Stock ({$vStock})";
                                            $stockClass = "text-success";
                                        }
                                    ?>
                                    <label class="variant-list-card <?= $vStock <= 0 ? 'out-of-stock' : '' ?>">
                                        <input type="radio" name="variant_selection" value="<?= $index ?>" class="variant-list-radio" <?= $vStock <= 0 ? 'disabled' : '' ?>>
                                        <div class="v-card-inner">
                                            <div class="v-card-title d-flex align-items-center justify-content-center flex-wrap gap-1">
                                                <?php if($hasColor): ?>
                                                    <span class="color-dot-small" style="background-color: <?= htmlspecialchars($v['color']) ?>;" title="<?= htmlspecialchars($v['color']) ?>"></span>
                                                    <?= htmlspecialchars($v['color']) ?>
                                                <?php endif; ?>
                                                
                                                <?php if($hasColor && $hasSize): ?> <span class="text-muted mx-1">|</span> <?php endif; ?>
                                                
                                                <?php if($hasSize): ?>
                                                    <?= htmlspecialchars($v['size']) ?>
                                                <?php endif; ?>
                                                
                                                <?php if(!$hasColor && !$hasSize): ?>
                                                    Standard
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="v-card-price"><?= formatPrice($vPrice) ?></div>
                                            <div class="v-card-stock <?= $stockClass ?>">
                                                <?= $stockText ?>
                                            </div>
                                        </div>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="color" id="selectedColor" value="">
                                <input type="hidden" name="size" id="selectedSize" value="">
                            </div>

                        <?php else: ?>
                            <?php if (!empty($uniqueColors)): ?>
                            <div class="mb-3" id="colorSection">
                                <span class="option-label mb-2">Select Color <span class="text-danger">*</span></span>
                                <div class="d-flex flex-wrap gap-2 p-1 rounded" id="colorContainer">
                                    <?php foreach ($uniqueColors as $col): ?>
                                        <label>
                                            <input type="radio" name="color" value="<?= htmlspecialchars($col) ?>" class="legacy-radio">
                                            <span class="color-swatch" style="background-color:<?= htmlspecialchars($col) ?>;" title="<?= htmlspecialchars($col) ?>"></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($uniqueSizes)): ?>
                            <div class="mb-3" id="sizeSection">
                                <span class="option-label mb-2">Select Size <span class="text-danger">*</span></span>
                                <div class="d-flex flex-wrap gap-2 p-1 rounded" id="sizeContainer">
                                    <?php foreach ($uniqueSizes as $size): ?>
                                        <label>
                                            <input type="radio" name="size" value="<?= htmlspecialchars($size) ?>" class="legacy-radio">
                                            <span class="legacy-label"><?= htmlspecialchars($size) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php
                            $btnText = $displayStock <= 0 ? 'Out of Stock' : ($hasNewVariants ? 'Select an Option' : 'Add to Cart');
                            $btnDisabled = ($displayStock <= 0 || $hasNewVariants) ? 'disabled' : '';
                        ?>

                        <div class="cart-row mb-3">
                            <input type="number" id="qtyInput" name="quantity" value="1" min="1"
                                   max="<?= $displayStock ?>"
                                   class="form-control qty-input"
                                   <?= $displayStock <= 0 ? 'disabled' : '' ?>>
                            <button type="submit" id="addToCartBtn" class="btn btn-add-cart flex-grow-1" <?= $btnDisabled ?>>
                                <i class="bi bi-bag-plus me-2"></i> <?= $btnText ?>
                            </button>
                        </div>
                        
                        <?php if (!$hasNewVariants): ?>
                            <div id="stockStatusDisplay" class="<?= $displayStock > 0 ? ($displayStock <= 4 ? 'text-warning' : 'text-success') : 'text-danger' ?> small fw-bold mt-2">
                                <?php if ($displayStock > 0): ?>
                                    <?php if ($displayStock <= 4): ?>
                                        <i class="bi bi-exclamation-triangle-fill" style="font-size:12px;"></i> Only <?= $displayStock ?> left in stock!
                                    <?php else: ?>
                                        <i class="bi bi-check-circle-fill" style="font-size:12px;"></i> In Stock (<?= $displayStock ?> available)
                                    <?php endif; ?>
                                <?php else: ?>
                                    <i class="bi bi-x-circle-fill" style="font-size:12px;"></i> Out of Stock
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div id="stockStatusDisplay" class="text-secondary small fw-bold mt-2">
                                <i class="bi bi-info-circle-fill" style="font-size:12px;"></i> Please select an option above
                            </div>
                        <?php endif; ?>

                    </form>
                </div><?php if (!empty($product['qr_path'])): ?>
                <div class="qr-verification-box mt-3">
                    <h6 class="fw-bold text-dark mb-1">
                        <i class="bi bi-shield-check text-success fs-5 align-middle"></i>
                        <?= $lang['scan_to_verify'] ?? 'Scan to Verify' ?>
                    </h6>
                    <p class="small text-muted mb-1">
                        <?= $lang['scan_desc'] ?? 'Use your phone camera to confirm this product is authentic.' ?>
                    </p>

                    <?php
                        $qrImageSrc = $siteBaseUrl . '/uploads/qrcodes/' . rawurlencode($product['qr_path']);
                        $qrVerifyUrl = $siteBaseUrl . '/verify.php?id=' . $productId;
                    ?>

                    <div class="qr-image-wrap">
                        <img src="<?= htmlspecialchars($qrImageSrc) ?>"
                             alt="QR Code for <?= htmlspecialchars($product['name']) ?>">
                    </div>

                    <div class="small text-muted mt-1 mb-2" style="font-size:0.7rem; word-break:break-all;">
                        <i class="bi bi-link-45deg"></i>
                        <a href="<?= htmlspecialchars($qrVerifyUrl) ?>" target="_blank" class="text-muted">
                            <?= htmlspecialchars($qrVerifyUrl) ?>
                        </a>
                    </div>

                    <div class="small text-success fw-bold">
                        <i class="bi bi-patch-check-fill"></i> ShopCorrect Secured
                    </div>
                </div>
                <?php endif; ?>

            </div></div></div><?php if (!empty($recommendations)): ?>
    <div class="mt-5 pt-4 border-top">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h3 class="fw-bold mb-0 text-dark" style="font-size:clamp(1.1rem,3vw,1.5rem);">
                Recommended for You
            </h3>
            <span class="text-muted small fw-medium">
                <i class="bi bi-arrow-left-right me-1"></i> Swipe to see more
            </span>
        </div>

        <div class="rec-scroll-container">
            <?php foreach ($recommendations as $rec):
                $rImg        = getImagePath($rec['image']);
                $recPriceVal = ($rec['sale_price'] > 0 && $rec['sale_price'] < $rec['price']) ? $rec['sale_price'] : $rec['price'];
                $recDiscount = isset($rec['discount_percent']) && $rec['discount_percent'] > 0 ? (int)$rec['discount_percent'] : 0;
                if ($recDiscount == 0 && $rec['sale_price'] > 0 && $rec['sale_price'] < $rec['price'])
                    $recDiscount = round((($rec['price'] - $rec['sale_price']) / $rec['price']) * 100);
            ?>
            <div class="rec-card">
                <?php if ($recDiscount > 0): ?>
                    <span class="badge-discount">-<?= $recDiscount ?>%</span>
                <?php endif; ?>
                <div class="rec-img">
                    <img src="<?= htmlspecialchars($rImg) ?>" alt="<?= htmlspecialchars($rec['name']) ?>">
                </div>
                <div class="rec-body">
                    <div class="rec-vendor">
                        <i class="bi bi-shop"></i> <?= htmlspecialchars($rec['shop_name']) ?>
                        <i class="bi bi-patch-check-fill"></i>
                    </div>
                    <a href="product.php?id=<?= $rec['id'] ?>" class="rec-title">
                        <?= htmlspecialchars($rec['name']) ?>
                    </a>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="rec-price"><?= formatPrice($recPriceVal) ?></span>
                        <a href="product.php?id=<?= $rec['id'] ?>" class="btn-view">View</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div></main>

<script>
function changeImage(element, src) {
    document.getElementById('mainDisplayImage').src = src;
    document.querySelectorAll('.thumb-item').forEach(el => el.classList.remove('active'));
    element.classList.add('active');
}

function showToast(message, type = 'success') {
    const toastEl   = document.getElementById('cartToast');
    const toastBody = toastEl.querySelector('.toast-body');
    toastEl.classList.remove('bg-dark', 'bg-danger');
    toastEl.classList.add(type === 'error' ? 'bg-danger' : 'bg-dark');
    toastBody.innerHTML = type === 'error'
        ? `<i class="bi bi-exclamation-circle-fill me-2"></i>${message}`
        : `<i class="bi bi-check-circle-fill me-2"></i>${message}`;
    new bootstrap.Toast(toastEl).show();
}

/* --- Dynamic Variant Logic --- */
const hasNewVariants = <?= $hasNewVariants ? 'true' : 'false' ?>;
const dbVariants = <?= json_encode($variants) ?>;
const basePrice = <?= $finalPrice ?>;
const globalDiscountPercent = <?= $discount ?>; // ✅ JS sync for strikethrough math
const currencySymbol = '<?= $activeSymbol ?>';

function formatPriceJS(amount) {
    return currencySymbol + parseFloat(amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

if (hasNewVariants) {
    document.querySelectorAll('.variant-list-radio').forEach(radio => {
        radio.addEventListener('change', function() {
            const variantIndex = this.value;
            const variant = dbVariants[variantIndex];
            
            if (variant) {
                document.getElementById('selectedColor').value = variant.color || '';
                document.getElementById('selectedSize').value = variant.size || '';
                
                const priceDisplay = document.getElementById('mainPriceDisplay');
                const discContainer = document.getElementById('discountContainer');
                const oldPriceDisplay = document.getElementById('oldPriceDisplay');
                const stockDisplay = document.getElementById('stockStatusDisplay');
                const qtyInput = document.getElementById('qtyInput');
                const addBtn = document.getElementById('addToCartBtn');

                // Update Price
                let vPrice = parseFloat(variant.price);
                if (!vPrice || isNaN(vPrice)) vPrice = basePrice; 
                
                priceDisplay.style.opacity = '0';
                setTimeout(() => {
                    priceDisplay.innerHTML = formatPriceJS(vPrice);
                    priceDisplay.style.color = '#0B2447';
                    priceDisplay.style.fontSize = 'clamp(1.4rem, 4vw, 2rem)';
                    priceDisplay.style.opacity = '1';
                }, 150);

                // ✅ Dynamically calculate and show the Strikethrough Price
                if (globalDiscountPercent > 0) {
                    let calculatedOldPrice = vPrice / (1 - (globalDiscountPercent / 100));
                    oldPriceDisplay.innerHTML = formatPriceJS(calculatedOldPrice);
                    discContainer.classList.remove('d-none');
                } else {
                    discContainer.classList.add('d-none');
                }

                // ✅ NEW: Update Stock & Button Logic (Strict 4-item threshold)
                let vStock = parseInt(variant.stock);
                qtyInput.max = vStock;
                qtyInput.value = 1;
                
                if (vStock > 0 && vStock <= 4) {
                    stockDisplay.innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i> Only ${vStock} left in this option!`;
                    stockDisplay.className = "text-warning small fw-bold mt-2";
                    addBtn.disabled = false;
                    addBtn.innerHTML = '<i class="bi bi-bag-plus me-2"></i> Add to Cart';
                } else if (vStock > 4) {
                    stockDisplay.innerHTML = `<i class="bi bi-check-circle-fill"></i> In Stock (${vStock} available)`;
                    stockDisplay.className = "text-success small fw-bold mt-2";
                    addBtn.disabled = false;
                    addBtn.innerHTML = '<i class="bi bi-bag-plus me-2"></i> Add to Cart';
                } else {
                    stockDisplay.innerHTML = `<i class="bi bi-x-circle-fill" style="font-size:8px;"></i> Out of Stock for this option`;
                    stockDisplay.className = "text-danger small fw-bold mt-2";
                    addBtn.disabled = true;
                    addBtn.innerHTML = 'Out of Stock';
                }

                // Clear validation error if any
                const vc = document.getElementById('variantCardContainer');
                if(vc) vc.classList.remove('validation-error');
            }
        });
    });
}

function handleAddToCart(event) {
    event.preventDefault();
    const form  = event.target;
    let isValid = true;

    if (hasNewVariants) {
        if (!form.querySelector('input[name="variant_selection"]:checked')) {
            isValid = false;
            const vc = document.getElementById('variantCardContainer');
            if(vc) {
                vc.classList.add('validation-error');
                setTimeout(() => vc.classList.remove('validation-error'), 500);
            }
        }
    } else {
        const colorSection = document.getElementById('colorSection');
        if (colorSection && !form.querySelector('input[name="color"]:checked')) {
            isValid = false;
            const cc = document.getElementById('colorContainer');
            cc.classList.add('validation-error');
            setTimeout(() => cc.classList.remove('validation-error'), 500);
        }

        const sizeSection = document.getElementById('sizeSection');
        if (sizeSection && isValid && !form.querySelector('input[name="size"]:checked')) {
            isValid = false;
            const sc = document.getElementById('sizeContainer');
            sc.classList.add('validation-error');
            setTimeout(() => sc.classList.remove('validation-error'), 500);
        }
    }

    if (!isValid) {
        showToast("Please select your preferred options first.", "error");
        return;
    }

    const btn          = form.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.disabled       = true;
    btn.innerHTML      = '<span class="spinner-border spinner-border-sm me-2"></span>Adding...';

    fetch('../routes/cart.php', { method: 'POST', body: new FormData(form) })
        .then(r => {
            // Check for login required before parsing JSON
            if(r.status === 401) {
                window.location.href = 'login.php';
                throw new Error('Not logged in');
            }
            return r.json();
        })
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message || 'Item added to cart!');
                
                // ✅ THIS IS THE FIX: Tell the Navbar to update the badge immediately!
                if (data.cart_count !== undefined && typeof window.updateCartBadge === 'function') {
                    window.updateCartBadge(data.cart_count);
                }
                
                btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Added!';
                setTimeout(() => { btn.innerHTML = originalText; btn.disabled = false; }, 1500);
            } else {
                showToast(data.message || 'Could not add to cart.', 'error');
                btn.disabled = false; 
                btn.innerHTML = originalText;
            }
        })
        .catch((err) => {
            if(err.message !== 'Not logged in') {
                showToast('Error connecting to server', 'error');
                btn.disabled = false; 
                btn.innerHTML = originalText;
            }
        });
}
</script>

<?php require_once __DIR__ . "/partials/footer.php"; ?>
</body>
</html>