<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";

// 1. Validate Product ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$productId = (int) $_GET['id'];

try {
    // 2. Fetch Product & Vendor Details
    // We join the vendors table to show who is selling it
    $stmt = $pdo->prepare("
        SELECT p.*, v.shop_name, v.phone_number 
        FROM products p 
        JOIN vendors v ON p.vendor_id = v.id 
        WHERE p.id = ? AND p.status = 'active' AND p.is_deleted = 0
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        die("Product not found or has been removed.");
    }

    // 3. Fetch Product Specifications (Optional)
    $specStmt = $pdo->prepare("SELECT spec_key, spec_value FROM product_specs WHERE product_id = ?");
    $specStmt->execute([$productId]);
    $specs = $specStmt->fetchAll();

    // 4. Fetch Variants/Sizes (Optional)
    $varStmt = $pdo->prepare("SELECT size, stock FROM product_variants WHERE product_id = ? AND stock > 0");
    $varStmt->execute([$productId]);
    $variants = $varStmt->fetchAll();

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// --- SMART IMAGE LOGIC ---
// This ensures we find the image whether it's an 'Old' upload or a 'New' one.
$imagePath = 'assets/images/no-image.png'; // Fallback
$dbImage = $product['image'];

if (!empty($dbImage)) {
    // 1. Check 'products' subfolder (The Professional Standard)
    if (file_exists("uploads/products/" . $dbImage)) {
        $imagePath = "uploads/products/" . $dbImage;
    } 
    // 2. Check main 'uploads' folder (Legacy Support)
    elseif (file_exists("uploads/" . $dbImage)) {
        $imagePath = "uploads/" . $dbImage;
    }
    // 3. Check if DB already has full path
    elseif (strpos($dbImage, 'uploads/') !== false) {
        $imagePath = $dbImage;
    }
}

// Calculate Discount
$basePrice = (float) $product['price'];
$salePrice = (float) $product['sale_price'];
$isOnSale = ($salePrice > 0 && $salePrice < $basePrice);
$currentPrice = $isOnSale ? $salePrice : $basePrice;
$discountPercent = $isOnSale ? round((($basePrice - $salePrice) / $basePrice) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($product['name']) ?> | ShopCorrect</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; color: #334155; }
        
        .product-image-container {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            height: 500px; /* Fixed height for professional look */
        }
        
        .product-img-main {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain; /* Prevents stretching */
            transition: transform 0.3s ease;
        }
        .product-image-container:hover .product-img-main { transform: scale(1.02); }

        .details-card { background: white; border-radius: 16px; padding: 2rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); height: 100%; }
        
        .price-tag { font-size: 2rem; font-weight: 800; color: #0F172A; }
        .old-price { font-size: 1.2rem; color: #94a3b8; text-decoration: line-through; }
        .discount-badge { background: #ef4444; color: white; padding: 4px 12px; border-radius: 100px; font-weight: 600; font-size: 0.9rem; }
        
        .spec-row { display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid #f1f5f9; }
        .spec-label { color: #64748b; font-weight: 500; }
        .spec-value { font-weight: 600; color: #334155; }
    </style>
</head>
<body>

<?php require_once __DIR__ . "/partials/navbar.php"; ?>

<div class="container py-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($product['name']) ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="product-image-container position-relative">
                <?php if ($isOnSale): ?>
                    <div class="position-absolute top-0 start-0 m-4 discount-badge">
                        -<?= $discountPercent ?>% OFF
                    </div>
                <?php endif; ?>
                
                <img src="<?= htmlspecialchars($imagePath) ?>" 
                     alt="<?= htmlspecialchars($product['name']) ?>" 
                     class="product-img-main">
            </div>
        </div>

        <div class="col-lg-6">
            <div class="details-card">
                <small class="text-uppercase text-primary fw-bold tracking-wide">
                    <?= htmlspecialchars($product['shop_name']) ?>
                </small>
                
                <h1 class="fw-bold mt-2 mb-3"><?= htmlspecialchars($product['name']) ?></h1>
                
                <div class="mb-4 d-flex align-items-center gap-3">
                    <span class="price-tag">₵<?= number_format($currentPrice, 2) ?></span>
                    <?php if ($isOnSale): ?>
                        <span class="old-price">₵<?= number_format($basePrice, 2) ?></span>
                    <?php endif; ?>
                </div>

                <div class="mb-4">
                    <p class="text-secondary" style="line-height: 1.6;">
                        <?= nl2br(htmlspecialchars($product['description'])) ?>
                    </p>
                </div>

                <?php if (!empty($variants)): ?>
                <div class="mb-4">
                    <label class="fw-bold mb-2 d-block">Select Size</label>
                    <div class="d-flex gap-2">
                        <?php foreach ($variants as $v): ?>
                            <input type="radio" class="btn-check" name="size" id="size-<?= $v['size'] ?>" autocomplete="off">
                            <label class="btn btn-outline-secondary px-3" for="size-<?= $v['size'] ?>">
                                <?= htmlspecialchars($v['size']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <form action="../routes/cart.php" method="POST" class="d-grid gap-2 mb-4">
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    <div class="row g-2">
                        <div class="col-4">
                            <input type="number" name="quantity" class="form-control form-control-lg text-center fw-bold" value="1" min="1" max="<?= $product['stock'] ?>">
                        </div>
                        <div class="col-8">
                            <?php if ($product['stock'] > 0): ?>
                                <button type="submit" class="btn btn-dark btn-lg w-100">Add to Cart</button>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary btn-lg w-100" disabled>Out of Stock</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>

                <?php if (!empty($specs)): ?>
                    <div class="mt-4 pt-4 border-top">
                        <h6 class="fw-bold mb-3">Product Specifications</h6>
                        <?php foreach ($specs as $spec): ?>
                            <div class="spec-row">
                                <span class="spec-label"><?= htmlspecialchars($spec['spec_key']) ?></span>
                                <span class="spec-value"><?= htmlspecialchars($spec['spec_value']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>

</body>
</html>