<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/../app/helpers/maintenance.php";
require_once __DIR__ . "/../app/helpers/currency.php";

/* -----------------------------------------------------------
    FETCH CATEGORIES
----------------------------------------------------------- */
$category_error = "";
try {
    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    $category_error = $e->getMessage();
    $categories = [];
}

/* -----------------------------------------------------------
    FETCH PROMO BANNER (HERO)
----------------------------------------------------------- */
$promoBanner    = null;
$showHeroBanner = false;
try {
    $promoStmt   = $pdo->query("SELECT promo_active, promo_image, promo_code FROM settings LIMIT 1");
    $promoBanner = $promoStmt->fetch(PDO::FETCH_ASSOC);
    if ($promoBanner && $promoBanner['promo_active'] == 1 && !empty($promoBanner['promo_image'])) {
        $showHeroBanner = true;
    }
} catch (PDOException $e) { }

/* -----------------------------------------------------------
    FILTER LOGIC & PAGINATION
----------------------------------------------------------- */
$items_per_page = 20;
$page   = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

$where  = "(p.status = 'active' OR p.status = 'out_of_stock') AND p.is_deleted = 0";
$params = [];

if (!empty($_GET["category"])) {
    $where    .= " AND p.category_id = ?";
    $params[] = (int)$_GET["category"];
}
if (!empty($_GET["q"])) {
    $where    .= " AND p.name LIKE ?";
    $params[] = "%" . $_GET["q"] . "%";
}

$min_price = !empty($_GET['min_price']) ? (float)$_GET['min_price'] : null;
$max_price = !empty($_GET['max_price']) ? (float)$_GET['max_price'] : null;

if ($min_price !== null) {
    $where    .= " AND (CASE WHEN p.sale_price > 0 AND p.sale_price < p.price THEN p.sale_price ELSE p.price END) >= ?";
    $params[] = $min_price;
}
if ($max_price !== null) {
    $where    .= " AND (CASE WHEN p.sale_price > 0 AND p.sale_price < p.price THEN p.sale_price ELSE p.price END) <= ?";
    $params[] = $max_price;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $where");
$countStmt->execute($params);
$total_products = (int)$countStmt->fetchColumn();
$total_pages    = max(1, ceil($total_products / $items_per_page));

$sql = "
    SELECT p.id, p.name, p.price, p.sale_price, p.discount_percent,
           p.stock as base_stock, p.image, v.shop_name,
           (SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE product_id = p.id) as avg_rating,
           (SELECT COUNT(id) FROM reviews WHERE product_id = p.id) as review_count,
           (SELECT COUNT(id) FROM product_variants WHERE product_id = p.id) as variant_count,
           (SELECT SUM(stock) FROM product_variants WHERE product_id = p.id) as variant_stock
    FROM products p
    JOIN vendors v ON p.vendor_id = v.id
    WHERE $where
    ORDER BY p.created_at DESC
    LIMIT $items_per_page OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$queryParams = $_GET;
unset($queryParams['min_price'], $queryParams['max_price']);
$clearFilterUrl = 'index.php?' . http_build_query($queryParams);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ShopCorrect | The Global Marketplace Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        *, *::before, *::after { box-sizing: border-box; }

        html { height: 100%; }
        body {
            background: #F0F2F5;
            font-family: Inter, sans-serif;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }
        main   { flex: 1 0 auto; }
        footer { flex-shrink: 0; }

        /* ══ HERO — Default ══ */
        .hero-default {
            background-color: #0B2447;
            background-repeat: no-repeat;
            background-size: 150% auto;
            position: relative;
            border-radius: 0 0 30px 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            padding: clamp(24px, 5vw, 60px) 20px clamp(30px, 6vw, 70px);
            text-align: center;
            animation: panAndSwap 30s infinite;
        }
        .hero-default::before {
            content: "";
            position: absolute; inset: 0;
            background: rgba(11,36,71,0.6);
            border-radius: 0 0 30px 30px;
        }
        @keyframes panAndSwap {
            0%   { background-image: url('https://images.unsplash.com/photo-1441984904996-e0b6ba687e04?q=80&w=2500&auto=format&fit=crop'); background-position: 0% 50%; }
            30%  { background-image: url('https://images.unsplash.com/photo-1441984904996-e0b6ba687e04?q=80&w=2500&auto=format&fit=crop'); background-position: 100% 50%; }
            33%  { background-image: url('https://images.unsplash.com/photo-1472851294608-062f824d29cc?q=80&w=2500&auto=format&fit=crop'); background-position: 0% 50%; }
            63%  { background-image: url('https://images.unsplash.com/photo-1472851294608-062f824d29cc?q=80&w=2500&auto=format&fit=crop'); background-position: 100% 50%; }
            66%  { background-image: url('https://images.unsplash.com/photo-1607082349566-187342175e2f?q=80&w=2500&auto=format&fit=crop'); background-position: 0% 50%; }
            96%  { background-image: url('https://images.unsplash.com/photo-1607082349566-187342175e2f?q=80&w=2500&auto=format&fit=crop'); background-position: 100% 50%; }
            100% { background-image: url('https://images.unsplash.com/photo-1441984904996-e0b6ba687e04?q=80&w=2500&auto=format&fit=crop'); background-position: 0% 50%; }
        }

        .glass-panel {
            position: relative; z-index: 1;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 24px;
            padding: clamp(20px,4vw,40px) clamp(16px,4vw,30px);
            max-width: 700px; width: 100%;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            color: white;
        }
        .glass-panel h1 { font-weight:800; font-size:clamp(1.5rem,5vw,2.8rem); margin-bottom:10px; letter-spacing:-1px; }
        .glass-panel p  { color:rgba(255,255,255,0.9); font-size:clamp(0.9rem,2vw,1.1rem); max-width:600px; margin:0 auto 20px; }

        .btn-shop-now {
            background:#FFD700; color:#0B2447; font-weight:700;
            padding:11px 30px; border-radius:50px; text-decoration:none;
            display:inline-block; margin-bottom:25px; transition:0.3s; border:none;
            font-size:clamp(0.85rem,2vw,1rem);
        }
        .btn-shop-now:hover { background:#FFF; transform:translateY(-2px); box-shadow:0 4px 15px rgba(255,215,0,0.4); }

        .trust-bar { display:flex; justify-content:center; gap:10px; flex-wrap:wrap; }
        .trust-item {
            display:inline-flex; align-items:center; gap:6px;
            background:rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.1);
            padding:7px 14px; border-radius:100px; color:#f8fafc;
            font-size:clamp(0.75rem,1.8vw,0.85rem); font-weight:600;
        }
        .trust-item i { color:#FFD700; font-size:1rem; }

        /* ══ HERO — Promo Banner ══ */
        .hero-promo-wrapper { display:block; width:100%; height:clamp(180px,40vw,450px); overflow:hidden; }
        .hero-promo-bg {
            width:100%; height:100%;
            background-repeat:no-repeat; background-position:center; background-size:cover;
            animation:smoothPromoPan 20s ease-in-out infinite alternate;
        }
        @keyframes smoothPromoPan {
            0%   { transform:scale(1.05) translateX(-2%); }
            100% { transform:scale(1.05) translateX(2%); }
        }

        /* ══ CATEGORY PILLS ══ */
        .category-wrapper { margin-top:20px; position:relative; z-index:10; margin-bottom:28px; }
        .category-scroll {
            display:flex; gap:10px; overflow-x:auto;
            padding:10px 4%; scrollbar-width:none; white-space:nowrap;
        }
        .category-scroll::-webkit-scrollbar { display:none; }
        .cat-pill {
            background:white; border:1px solid #eef2f7;
            padding:9px 18px; border-radius:100px; white-space:nowrap;
            text-decoration:none; color:#475569; font-weight:600;
            font-size:clamp(0.78rem,1.5vw,0.88rem);
            box-shadow:0 2px 8px rgba(0,0,0,0.05);
            transition:all 0.2s ease; display:flex; align-items:center; gap:6px; flex-shrink:0;
        }
        .cat-pill:hover  { transform:translateY(-2px); box-shadow:0 8px 16px rgba(0,0,0,0.08); color:#0B2447; }
        .cat-pill.active { background:#0B2447; color:white; border-color:#0B2447; }

        /* ══ FILTER SIDEBAR ══ */
        .filter-sidebar {
            background:white; padding:20px;
            border-radius:16px; border:1px solid #e2e8f0;
            box-shadow:0 2px 8px rgba(0,0,0,0.04);
        }
        @media (min-width:992px) {
            .filter-sidebar { position:sticky; top:100px; z-index:5; }
        }
        .filter-toggle-btn {
            display:none; width:100%; background:white;
            border:1px solid #e2e8f0; border-radius:12px;
            padding:12px 16px; font-weight:600; color:#0B2447;
            text-align:left; margin-bottom:10px;
            box-shadow:0 2px 8px rgba(0,0,0,0.04);
        }
        @media (max-width:991px) {
            .filter-toggle-btn { display:flex; align-items:center; justify-content:space-between; }
            .filter-sidebar-body { display:none; }
            .filter-sidebar-body.show { display:block; }
            .filter-col { width:100%; margin-bottom:8px; }
        }

        /* ══ PRODUCT CARDS ══ */
        .product-card {
            background:white; border-radius:16px; border:1px solid #e8edf5;
            transition:.25s; height:100%; display:flex; flex-direction:column;
            position:relative; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.04);
        }
        .product-card:hover { transform:translateY(-4px); box-shadow:0 16px 32px rgba(0,0,0,0.1); border-color:#d1dce8; }

        .wishlist-btn {
            position:absolute; top:10px; right:10px; background:white; border:none;
            width:30px; height:30px; border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            box-shadow:0 2px 8px rgba(0,0,0,0.12);
            color:#94a3b8; z-index:5; transition:0.2s; cursor:pointer;
        }
        .wishlist-btn:hover, .wishlist-btn.active { color:#ef4444; transform:scale(1.1); }

        /* ✅ THE FIX: Vibrant, Crisp Discount Badge */
        .badge-sale {
            position:absolute; top:10px; left:10px;
            background:linear-gradient(135deg, #e11d48, #be123c); 
            color:white; padding:4px 9px; border-radius:6px;
            font-size:0.7rem; font-weight:800; z-index:4; letter-spacing:0.5px;
            box-shadow: 0 4px 10px rgba(225, 29, 72, 0.3); 
        }

        /* --- DYNAMIC EDGE-TO-EDGE IMAGE FIX --- */
        .card-img {
            width: 100%; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            padding: 0;
            background: #fff;
            border-bottom: 1px solid #f1f5f9;
            border-radius: 16px 16px 0 0;
            overflow: hidden; 
        }
        .card-img img { 
            width: 100%;
            height: auto;
            max-height: 180px;
            object-fit: contain; 
            display: block;
            transition: transform 0.3s ease; 
        }
        .product-card:hover .card-img img { transform:scale(1.04); }

        /* ✅ THE FIX: Improved Spacing and Grid Alignment */
        .card-body-inner { 
            padding: 14px 12px; 
            display:flex; flex-direction:column; flex:1; gap:8px; 
        }

        .vendor-label {
            font-size:0.67rem; font-weight:700; text-transform:uppercase; color:#94a3b8;
            display:flex; align-items:center; gap:4px;
            white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex-shrink:0;
        }

        .product-name {
            font-size:clamp(0.85rem,1.8vw,0.92rem); font-weight:600; color:#1e293b;
            text-decoration:none; line-height:1.3;
            /* Force exact height for 2 lines so buttons align perfectly */
            min-height: 2.6em; 
            display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;
            word-break:break-word; overflow-wrap:break-word; hyphens:auto;
        }
        .product-name:hover { color:#0B2447; }

        .star-row { display:flex; align-items:center; gap:4px; flex-shrink:0; }
        .stars { font-size:0.72rem; color:#f59e0b; display:flex; gap:1px; }
        .review-count { font-size:0.68rem; color:#94a3b8; font-weight:700; }

        .stock-badge { font-size:0.72rem; font-weight:700; flex-shrink:0; }

        /* ✅ THE FIX: Wrap Prices Safely on Mobile */
        .price-row { 
            display:flex; align-items:baseline; gap:6px; flex-wrap:wrap; flex-shrink:0; 
        }
        .price-main { font-weight:800; font-size:clamp(0.95rem,2.5vw,1.15rem); color:#0B2447; line-height: 1; }
        .price-old  { text-decoration:line-through; font-size:clamp(0.75rem,1.5vw,0.85rem); color:#94a3b8; font-weight:600; line-height: 1; }

        .card-footer-btn { margin-top:auto; padding-top:6px; flex-shrink:0; }

        /* ✅ THE FIX: Larger Tap Target for Mobile Thumbs */
        .btn-add-cart-index, .btn-apply-custom {
            background-color:#0B2447 !important; color:#fff !important; border:none !important;
            transition:all 0.2s; border-radius:50px !important; font-weight:700;
            font-size:clamp(0.8rem,1.8vw,0.85rem) !important;
            padding: 8px 14px !important; width:100%;
        }
        .btn-add-cart-index:hover    { background-color:#1e3a8a !important; transform:translateY(-1px); }
        .btn-add-cart-index:disabled { background-color:#e2e8f0 !important; color:#94a3b8 !important; cursor:not-allowed; transform:none; }

        @media (max-width:576px) { .pagination-wrap .btn { padding:7px 12px; font-size:0.82rem; } }

        .section-heading { font-size:clamp(1rem,2.5vw,1.15rem); font-weight:800; color:#0f172a; }

        .toast-container { z-index:10500; }
    </style>
</head>
<body>

<?php require_once __DIR__ . "/partials/navbar.php"; ?>

<main>

    <?php if ($showHeroBanner):
        $promo_images = array_filter(array_map('trim', explode(',', $promoBanner['promo_image'])));
    ?>
        <div id="promoHeroCarousel" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-inner" style="border-radius:0 0 30px 30px; background-color:#0B2447;">
                <?php foreach ($promo_images as $index => $img):
                    $bannerImgPath = 'uploads/banners/' . $img;
                    if (!file_exists(__DIR__ . '/' . $bannerImgPath)) $bannerImgPath = 'assets/images/no-image.png';
                ?>
                <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>" data-bs-interval="6000">
                    <a href="index.php?q=<?= htmlspecialchars($promoBanner['promo_code'] ?? '') ?>" class="d-block text-decoration-none hero-promo-wrapper">
                        <div class="hero-promo-bg" style="background-image:url('<?= htmlspecialchars($bannerImgPath) ?>');"></div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($promo_images) > 1): ?>
                <button class="carousel-control-prev" type="button" data-bs-target="#promoHeroCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon"></span><span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#promoHeroCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon"></span><span class="visually-hidden">Next</span>
                </button>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <section class="hero-default">
            <div class="glass-panel">
                <h1>Discover the Best Deals</h1>
                <p>Shop top brands and exclusive items from verified sellers across Ghana.</p>
                <a href="register.php" class="btn-shop-now"><i class="bi bi-shop me-2"></i>Sell on <span class="notranslate">ShopCorrect</span></a>
                <div class="trust-bar">
                    <span class="trust-item"><i class="bi bi-shield-check"></i> Secure Payments</span>
                    <span class="trust-item"><i class="bi bi-patch-check"></i> Verified Vendors</span>
                    <span class="trust-item"><i class="bi bi-truck"></i> Fast Delivery</span>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <div class="container-fluid p-0 category-wrapper">
        <div class="category-scroll">
            <a href="index.php" class="cat-pill <?= empty($_GET['category']) ? 'active' : '' ?>">
                <i class="bi bi-grid-fill" style="font-size:0.8rem;"></i> All Categories
            </a>
            <?php foreach($categories as $c): ?>
                <a href="index.php?category=<?= $c['id'] ?>"
                   class="cat-pill <?= (($_GET['category'] ?? null) == $c['id']) ? 'active' : '' ?>">
                    <?= htmlspecialchars($c['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="products" class="container-fluid px-3 px-lg-5 mb-5">
        <div class="row g-3">

            <div class="col-lg-3 col-xl-2 filter-col">

                <button class="filter-toggle-btn" id="filterToggle">
                    <span><i class="bi bi-sliders me-2"></i> Filters</span>
                    <i class="bi bi-chevron-down" id="filterChevron"></i>
                </button>

                <div class="filter-sidebar">
                    <h6 class="fw-bold mb-3 border-bottom pb-2 d-none d-lg-block" style="color:#0B2447;">
                        <i class="bi bi-sliders me-2"></i> Filters
                    </h6>
                    <div class="filter-sidebar-body" id="filterBody">
                        <form action="index.php" method="GET">
                            <?php if(!empty($_GET['category'])): ?>
                                <input type="hidden" name="category" value="<?= htmlspecialchars($_GET['category']) ?>">
                            <?php endif; ?>
                            <?php if(!empty($_GET['q'])): ?>
                                <input type="hidden" name="q" value="<?= htmlspecialchars($_GET['q']) ?>">
                            <?php endif; ?>
                            
                            <label class="form-label small fw-bold text-muted text-uppercase mb-1" style="font-size:0.7rem; letter-spacing:0.5px;">Price Range</label>
                            
                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text bg-light border-end-0 text-muted">₵</span>
                                <input type="number" name="min_price" class="form-control border-start-0 ps-0 bg-light" placeholder="Min" value="<?= htmlspecialchars($min_price ?? '') ?>">
                            </div>
                            
                            <div class="input-group input-group-sm mb-3">
                                <span class="input-group-text bg-light border-end-0 text-muted">₵</span>
                                <input type="number" name="max_price" class="form-control border-start-0 ps-0 bg-light" placeholder="Max" value="<?= htmlspecialchars($max_price ?? '') ?>">
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-sm btn-apply-custom py-2">Apply Filters</button>
                                <?php if($min_price !== null || $max_price !== null): ?>
                                    <a href="<?= $clearFilterUrl ?>" class="btn btn-link btn-sm text-decoration-none text-danger mt-1">Clear Filters</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-9 col-xl-10">

                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <span class="section-heading">
                        <?php if(!empty($_GET['q'])): ?>
                            Results for "<em><?= htmlspecialchars($_GET['q']) ?></em>"
                        <?php elseif(!empty($_GET['category'])): ?>
                            <?php foreach($categories as $c) if($c['id'] == $_GET['category']) echo htmlspecialchars($c['name']); ?>
                        <?php else: ?>
                            All Products
                        <?php endif; ?>
                    </span>
                    <span class="text-muted small fw-semibold"><?= number_format($total_products) ?> items</span>
                </div>

                <div class="row g-3 row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-xl-4 row-cols-xxl-5">

                    <?php if(empty($products)): ?>
                        <div class="col-12 text-center py-5">
                            <i class="bi bi-search" style="font-size:3rem; color:#cbd5e1;"></i>
                            <h5 class="text-muted mt-3">No products found.</h5>
                            <a href="index.php" class="btn btn-dark mt-3 rounded-pill px-4">Clear All Filters</a>
                        </div>
                    <?php endif; ?>

                    <?php foreach($products as $p):
                        $hasVariants = (int)$p['variant_count'] > 0;
                        $stock       = $hasVariants ? (int)$p['variant_stock'] : (int)$p['base_stock'];
                        
                        $isSale      = ($p['sale_price'] > 0 && $p['sale_price'] < $p['price']);
                        $current     = $isSale ? $p['sale_price'] : $p['price'];
                        
                        $displayImg = 'assets/images/no-image.png';
                        if (!empty($p['image'])) {
                            if (file_exists(__DIR__ . '/../public/uploads/products/' . $p['image']))
                                $displayImg = 'uploads/products/' . $p['image'];
                            elseif (file_exists(__DIR__ . '/../public/uploads/' . $p['image']))
                                $displayImg = 'uploads/' . $p['image'];
                        }
                        $avgRating   = round((float)$p['avg_rating'], 1);
                        $reviewCount = (int)$p['review_count'];
                    ?>
                    <div class="col">
                        <div class="product-card">

                            <button class="wishlist-btn add-to-wishlist" data-id="<?= $p['id'] ?>" title="Add to Wishlist">
                                <i class="bi bi-heart-fill" style="font-size:0.8rem;"></i>
                            </button>

                            <?php if($isSale): ?>
                                <div class="badge-sale">-<?= (int)$p['discount_percent'] ?>%</div>
                            <?php endif; ?>

                            <a href="product.php?id=<?= $p['id'] ?>" class="card-img d-block">
                                <img src="<?= htmlspecialchars($displayImg) ?>"
                                     alt="<?= htmlspecialchars($p['name']) ?>"
                                     loading="lazy">
                            </a>

                            <div class="card-body-inner">

                                <div class="vendor-label">
                                    <i class="bi bi-shop" style="font-size:0.7rem;"></i>
                                    <span class="text-truncate"><?= htmlspecialchars($p['shop_name']) ?></span>
                                    <i class="bi bi-patch-check-fill text-primary" style="font-size:0.72rem; flex-shrink:0;"></i>
                                </div>

                                <a href="product.php?id=<?= $p['id'] ?>" class="product-name"
                                   title="<?= htmlspecialchars($p['name']) ?>">
                                    <?= htmlspecialchars($p['name']) ?>
                                </a>

                                <div class="star-row">
                                    <div class="stars">
                                        <?php
                                        if ($reviewCount > 0) {
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= floor($avgRating))
                                                    echo '<i class="bi bi-star-fill"></i>';
                                                elseif ($i == ceil($avgRating) && $avgRating - floor($avgRating) > 0)
                                                    echo '<i class="bi bi-star-half"></i>';
                                                else
                                                    echo '<i class="bi bi-star" style="color:#e2e8f0;"></i>';
                                            }
                                        } else {
                                            echo str_repeat('<i class="bi bi-star" style="color:#e2e8f0;"></i>', 5);
                                        }
                                        ?>
                                    </div>
                                    <span class="review-count">(<?= $reviewCount ?>)</span>
                                </div>

                                <div class="stock-badge <?= $stock > 0 ? ($stock <= 5 ? 'text-danger' : 'text-success') : 'text-muted' ?>">
                                    <i class="bi <?= $stock > 0 ? ($stock <= 5 ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill') : 'bi-x-circle-fill' ?>" style="font-size:0.65rem;"></i>
                                    <?= $stock > 0 ? ($stock <= 5 ? "Only $stock left" : "In Stock") : "Out of Stock" ?>
                                </div>

                                <div class="card-footer-btn">
                                    <div class="price-row mb-2">
                                        <span class="price-main"><?= formatPrice($current) ?></span>
                                        <?php if($isSale): ?>
                                            <span class="price-old"><?= formatPrice($p['price']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if($hasVariants): ?>
                                        <a href="product.php?id=<?= $p['id'] ?>" class="btn btn-add-cart-index text-center text-decoration-none d-block">
                                            <i class="bi bi-list-ul me-1"></i> View Options
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-add-cart-index add-to-cart"
                                                data-id="<?= $p['id'] ?>"
                                                <?= $stock <= 0 ? 'disabled' : '' ?>>
                                            <i class="bi bi-cart-plus me-1"></i>
                                            <?= $stock <= 0 ? 'Out of Stock' : 'Add to Cart' ?>
                                        </button>
                                    <?php endif; ?>
                                    
                                </div>

                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                </div><?php if($total_pages > 1): ?>
                <div class="mt-5 text-center pagination-wrap">
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <?php if($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
                               class="btn btn-outline-dark fw-bold px-3">
                               <i class="bi bi-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        <?php
                        $range = 2;
                        for($i = 1; $i <= $total_pages; $i++):
                            if ($i == 1 || $i == $total_pages || abs($i - $page) <= $range):
                        ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                               class="btn fw-bold px-3 <?= $page === $i ? 'btn-dark' : 'btn-outline-dark' ?>">
                                <?= $i ?>
                            </a>
                        <?php
                            elseif (abs($i - $page) == $range + 1):
                                echo '<span class="btn btn-outline-secondary disabled px-2">…</span>';
                            endif;
                        endfor;
                        ?>
                        <?php if($page < $total_pages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
                               class="btn btn-outline-dark fw-bold px-3">
                               <i class="bi bi-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="mt-2 text-muted small">Page <?= $page ?> of <?= $total_pages ?></div>
                </div>
                <?php endif; ?>

            </div></div></div></main>

<div class="toast-container position-fixed top-0 end-0 p-3">
    <div id="actionToast" class="toast align-items-center text-white bg-dark border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-bold">Success!</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>

<script>
    // ── Toast helper ──
    const actionToast = new bootstrap.Toast(document.getElementById('actionToast'));
    const toastBody   = document.querySelector('.toast-body');
    function notify(msg) { toastBody.innerText = msg; actionToast.show(); }

    // ── Wishlist ──
    document.querySelectorAll('.add-to-wishlist').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            fetch('wishlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'product_id=' + this.dataset.id
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') { this.classList.toggle('active'); notify(data.message); }
                else notify(data.message || 'Please login first');
            })
            .catch(() => notify('Error connecting to server'));
        });
    });

    // ── Add to Cart ──
    document.querySelectorAll('.add-to-cart').forEach(btn => {
        btn.addEventListener('click', function() {
            const originalHTML = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Adding...';
            
            fetch('../routes/cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'product_id=' + this.dataset.id + '&quantity=1'
            })
            .then(r => {
                // If not logged in, cart.php returns 401
                if(r.status === 401) {
                    window.location.href = 'login.php';
                    throw new Error('Not logged in');
                }
                return r.json();
            })
            .then(data => {
                notify(data.message || 'Added to cart');
                this.innerHTML = '<i class="bi bi-check-lg me-1"></i> Added!';
                
                // ✅ BULLETPROOF BADGE UPDATE
                if (data.cart_count !== undefined) {
                    // Update the badge text
                    const badge = document.getElementById('navCartBadge');
                    if (badge) {
                        badge.innerText = data.cart_count;
                        if (parseInt(data.cart_count) > 0) {
                            badge.classList.remove('d-none');
                            // Add a fun little pop animation!
                            badge.style.transition = 'transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
                            badge.style.transform = 'scale(1.4)';
                            setTimeout(() => { badge.style.transform = 'scale(1)'; }, 200);
                        } else {
                            badge.classList.add('d-none');
                        }
                    }
                }

                setTimeout(() => { this.innerHTML = originalHTML; this.disabled = false; }, 1500);
            })
            .catch((err) => {
                if(err.message !== 'Not logged in') {
                    notify('Error connecting to server');
                    this.innerHTML = originalHTML;
                    this.disabled = false;
                }
            });
        });
    });

    // ── Mobile filter toggle ──
    const filterToggle  = document.getElementById('filterToggle');
    const filterBody    = document.getElementById('filterBody');
    const filterChevron = document.getElementById('filterChevron');

    if (filterToggle) {
        filterToggle.addEventListener('click', function() {
            filterBody.classList.toggle('show');
            filterChevron.classList.toggle('bi-chevron-down');
            filterChevron.classList.toggle('bi-chevron-up');
        });
    }

    function checkFilterDisplay() {
        if (window.innerWidth >= 992) filterBody.classList.add('show');
    }
    checkFilterDisplay();
    window.addEventListener('resize', checkFilterDisplay);
</script>
</body>
</html>