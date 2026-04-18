<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/../app/helpers/currency.php"; // Load currency helper for dynamic pricing

/* -----------------------------------------------------------
   1. LOGIC: HANDLE AJAX POST TO ADD/REMOVE FROM WISHLIST
----------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Please login to save items']);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $product_id = (int)$_POST['product_id'];

    try {
        // Check if already in wishlist
        $check = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
        $check->execute([$user_id, $product_id]);
        
        if ($check->fetch()) {
            // Already exists? Remove it (toggle behavior)
            $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$user_id, $product_id]);
            echo json_encode(['status' => 'success', 'message' => 'Removed from wishlist', 'action' => 'removed']);
        } else {
            // Add to wishlist
            $stmt = $pdo->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $product_id]);
            echo json_encode(['status' => 'success', 'message' => 'Added to wishlist', 'action' => 'added']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
    exit;
}

/* -----------------------------------------------------------
   2. DISPLAY: FETCH WISHLIST ITEMS FOR THE LOGGED-IN USER
----------------------------------------------------------- */
$user_id = $_SESSION['user_id'] ?? 0;

try {
    $sql = "
        SELECT p.id, p.name, p.price, p.sale_price, p.discount_percent,
               p.stock, p.image, v.shop_name
        FROM wishlist w
        JOIN products p ON w.product_id = p.id
        JOIN vendors v ON p.vendor_id = v.id
        WHERE w.user_id = ?
        ORDER BY w.created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $wishlist_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $wishlist_items = [];
}

/* -----------------------------------------------------------
   3. SMART RECOMMENDATION LOGIC
----------------------------------------------------------- */
// Get IDs currently in wishlist to exclude them
$wishlistIds = array_column($wishlist_items, 'id');
$excludeIds = !empty($wishlistIds) ? implode(',', $wishlistIds) : '0';

// Fetch random active products NOT in the wishlist
$recSql = "
    SELECT p.*, v.shop_name 
    FROM products p 
    JOIN vendors v ON p.vendor_id = v.id
    WHERE p.id NOT IN ($excludeIds) 
    AND p.status = 'active'
    AND p.is_deleted = 0
    ORDER BY RAND() LIMIT 8
";
$recStmt = $pdo->query($recSql);
$recommendations = $recStmt->fetchAll(PDO::FETCH_ASSOC);

/* -----------------------------------------------------------
   4. HELPER FUNCTION: PRODUCT IMAGES
----------------------------------------------------------- */
function getImagePath($imageName) {
    $defaultImage = 'assets/images/no-image.png';

    if (empty($imageName)) {
        return $defaultImage;
    }

    // Define priority paths to check
    $paths = [
        'uploads/products/' . $imageName,
        'uploads/' . $imageName
    ];

    foreach ($paths as $path) {
        if (file_exists(__DIR__ . '/' . $path)) {
            return $path; 
        }
    }

    return $defaultImage;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Wishlist | ShopCorrect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --sc-primary: #0B2447; --sc-bg: #F8FAFC; --sc-card-bg: #FFFFFF; }
        
        body { background: var(--sc-bg); font-family: 'Inter', sans-serif; color: #334155; }
        
        /* Header */
        .wishlist-header { 
            background: white; 
            padding: 3rem 0; 
            border-bottom: 1px solid #E2E8F0; 
            margin-bottom: 3rem; 
        }
        
        /* Wishlist Card Styles */
        .product-card { 
            background: var(--sc-card-bg); 
            border-radius: 12px; 
            border: 1px solid white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            transition: all 0.25s ease; 
            height: 100%; 
            position: relative; 
            display: flex; 
            flex-direction: column; 
            overflow: hidden;
        }
        .product-card:hover { 
            transform: translateY(-4px); 
            box-shadow: 0 15px 30px -5px rgba(0, 0, 0, 0.08); 
        }
        
        .card-img { 
            height: 220px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 20px; 
            background: #F8FAFC; /* Subtle gray background for images */
            position: relative;
        }
        .card-img img { 
            max-height: 100%; 
            max-width: 100%; 
            object-fit: contain; 
            mix-blend-mode: multiply; /* Helps transparent PNGs look better */
            transition: transform 0.3s ease;
        }
        .product-card:hover .card-img img { transform: scale(1.08); }
        
        /* Remove Button */
        .remove-wishlist { 
            position: absolute; 
            top: 12px; 
            right: 12px; 
            background: white; 
            color: #ef4444; 
            border: 1px solid #fee2e2; 
            width: 32px; height: 32px; 
            border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; 
            z-index: 10; 
            transition: 0.2s; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .remove-wishlist:hover { background: #ef4444; color: white; border-color: #ef4444; }

        /* Typography */
        .vendor-name { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #64748B; letter-spacing: 0.5px; }
        .product-title { 
            font-size: 0.95rem; font-weight: 600; color: #0f172a; text-decoration: none; 
            line-height: 1.4; margin-bottom: 0.5rem; display: block;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }
        .product-title:hover { color: var(--sc-primary); }
        
        .price-current { font-weight: 800; font-size: 1.1rem; color: var(--sc-primary); }
        .price-old { text-decoration: line-through; color: #94a3b8; font-size: 0.85rem; margin-left: 6px; }

        /* Add to Cart Button */
        .btn-add-cart-custom {
            background-color: var(--sc-primary); 
            color: #fff; 
            border: none;
            border-radius: 8px;
            padding: 10px 0;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.2s;
            width: 100%;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-add-cart-custom:hover {
            background-color: #1e3a8a; 
            transform: translateY(-1px);
            color: white;
        }

        /* --- UPDATED RECOMMENDATION STYLES --- */
        .rec-section { margin-top: 4rem; padding-top: 3rem; border-top: 1px dashed #E2E8F0; }
        .rec-scroll-container { display: flex; gap: 1rem; overflow-x: auto; padding: 10px 5px 20px 5px; scroll-behavior: smooth; }
        .rec-card { min-width: 240px; max-width: 240px; background: #fff; border: 1px solid #eef2f7; border-radius: 16px; overflow: hidden; transition: 0.2s; position: relative; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .rec-card:hover { transform: translateY(-4px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .rec-img { height: 160px; display: flex; align-items: center; justify-content: center; padding: 15px; background: #f8fafc; border-bottom: 1px solid #f1f5f9; }
        .rec-img img { max-height: 100%; max-width: 100%; object-fit: contain; mix-blend-mode: multiply; }
        .rec-body { padding: 1.25rem 1rem; }
        
        .rec-vendor { font-size: 0.75rem; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 6px; display: flex; align-items: center; gap: 4px; }
        .rec-vendor i.bi-patch-check-fill { color: #3b82f6; font-size: 0.85rem; }
        .rec-vendor i.bi-shop { color: #94a3b8; }
        
        .rec-title { font-size: 1rem; font-weight: 700; color: #0f172a; display: block; text-decoration: none; margin-bottom: 0.75rem; height: 2.8em; overflow: hidden; line-height: 1.4; }
        .rec-title:hover { color: #3b82f6; text-decoration: underline; }
        
        .rec-price { font-weight: 800; color: #0f172a; font-size: 1.2rem; }
        .btn-view { font-size: 0.85rem; padding: 6px 18px; border-radius: 50px; background: #f1f5f9; color: #0f172a; text-decoration: none; font-weight: 700; transition: 0.2s; }
        .btn-view:hover { background: #e2e8f0; }
        
        .badge-discount { position: absolute; top: 12px; left: 12px; background: #ef4444; color: white; font-size: 0.75rem; font-weight: 700; padding: 4px 8px; border-radius: 6px; z-index: 2; letter-spacing: 0.5px; }
    </style>
</head>
<body>

<?php require_once __DIR__ . "/partials/navbar.php"; ?>

<header class="wishlist-header text-center">
    <div class="container">
        <h2 class="fw-bold mb-1" style="color: var(--sc-primary);">My Wishlist</h2>
        <p class="text-muted mb-0">Your curated collection of favorites</p>
    </div>
</header>

<div class="container mb-5" style="max-width: 1100px;">
    
    <?php if (empty($wishlist_items)): ?>
        <div class="text-center py-5">
            <div class="bg-white p-4 rounded-circle shadow-sm d-inline-block mb-3">
                <i class="bi bi-heart display-4 text-danger opacity-50"></i>
            </div>
            <h4 class="fw-bold text-dark">Your wishlist is empty</h4>
            <p class="text-muted mb-4">You haven't saved any items yet.</p>
            <a href="index.php" class="btn btn-dark rounded-pill px-5 py-2 fw-semibold" style="background: #0B2447;">Start Shopping</a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($wishlist_items as $p): 
                $isSale = ($p['sale_price'] > 0 && $p['sale_price'] < $p['price']);
                $current = $isSale ? $p['sale_price'] : $p['price'];
                $displayImg = getImagePath($p['image']);
            ?>
            <div class="col-6 col-md-4 col-lg-3" id="wish-item-<?= $p['id'] ?>">
                <div class="product-card">
                    <button class="remove-wishlist" onclick="removeFromWishlist(<?= $p['id'] ?>)" title="Remove">
                        <i class="bi bi-x-lg" style="font-size: 0.8rem;"></i>
                    </button>

                    <?php if($isSale): ?>
                        <div class="badge-discount" style="top: 12px; left: 12px;">-<?= $p['discount_percent'] ?>%</div>
                    <?php endif; ?>

                    <div class="card-img">
                        <img src="<?= htmlspecialchars($displayImg) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                    </div>

                    <div class="p-3 d-flex flex-column flex-grow-1">
                        <div class="vendor-name mb-1">
                            <i class="bi bi-shop me-1"></i><?= htmlspecialchars($p['shop_name']) ?>
                        </div>

                        <a href="product.php?id=<?= $p['id'] ?>" class="product-title">
                            <?= htmlspecialchars($p['name']) ?>
                        </a>
                        
                        <div class="mt-auto pt-3">
                            <div class="mb-3 d-flex align-items-baseline">
                                <span class="price-current"><?= formatPrice($current) ?></span>
                                <?php if($isSale): ?>
                                    <span class="price-old"><?= formatPrice($p['price']) ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if($p['stock'] > 0): ?>
                                <a href="product.php?id=<?= $p['id'] ?>" class="btn-add-cart-custom text-decoration-none">
                                    <i class="bi bi-bag-plus"></i> View Item
                                </a>
                            <?php else: ?>
                                <button class="btn btn-secondary w-100 btn-sm rounded-3" disabled>Out of Stock</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if(!empty($recommendations)): ?>
    <div class="rec-section">
        <div class="d-flex justify-content-between align-items-end mb-4 px-2">
            <h3 class="fw-bold mb-0 text-dark" style="font-size: 1.5rem;">You might also like</h3>
            <span class="text-muted small fw-medium d-none d-sm-block"><i class="bi bi-arrow-left-right me-1"></i> Swipe to see more</span>
        </div>
        
        <div class="rec-scroll-container">
            <?php foreach($recommendations as $rec): 
                $rImg = getImagePath($rec['image']);
                $recPriceVal = ($rec['sale_price'] > 0 && $rec['sale_price'] < $rec['price']) ? $rec['sale_price'] : $rec['price'];
                
                // Logic to fetch discount if explicit percentage doesn't exist but sale price does
                $recDiscount = isset($rec['discount_percent']) && $rec['discount_percent'] > 0 ? (int)$rec['discount_percent'] : 0;
                if($recDiscount == 0 && $rec['sale_price'] > 0 && $rec['sale_price'] < $rec['price']) {
                    $recDiscount = round((($rec['price'] - $rec['sale_price']) / $rec['price']) * 100);
                }
            ?>
            <div class="rec-card">
                <?php if($recDiscount > 0): ?>
                    <span class="badge-discount">-<?= $recDiscount ?>%</span>
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

</div>

<script>
function removeFromWishlist(productId) {
    if(!confirm('Are you sure you want to remove this item?')) return;

    fetch('wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'product_id=' + productId
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            const item = document.getElementById('wish-item-' + productId);
            // Smooth fade out effect
            item.style.transition = "all 0.3s ease";
            item.style.opacity = "0";
            item.style.transform = "scale(0.9)";
            
            setTimeout(() => {
                item.remove();
                // Check if wishlist is now empty
                if(document.querySelectorAll('.col-lg-3').length === 0) {
                    location.reload(); 
                }
            }, 300);
        }
    })
    .catch(err => alert('Error communicating with server'));
}
</script>

<?php require_once __DIR__ . "/partials/footer.php"; ?>
</body>
</html>