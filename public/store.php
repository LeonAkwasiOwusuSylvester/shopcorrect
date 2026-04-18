<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";

/* ============================================================
   1. LOGIC: FETCH STORE BY SLUG
============================================================ */

// Get slug from URL (e.g., store.php?slug=best-gadgets-gh)
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    // Redirect to home if no slug is provided
    header("Location: index.php");
    exit;
}

try {
    // Fetch vendor details only if they are approved
    $stmt = $pdo->prepare("
        SELECT * FROM vendors 
        WHERE shop_slug = ? AND status = 'approved' 
        LIMIT 1
    ");
    $stmt->execute([$slug]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);

    // If store doesn't exist or isn't approved
    if (!$store) {
        http_response_code(404);
        die("<div style='text-align:center; padding:50px; font-family:sans-serif;'>
                <h1>Store Not Found</h1>
                <p>The store you are looking for does not exist or is awaiting approval.</p>
                <a href='index.php'>Return to Marketplace</a>
             </div>");
    }
} catch (PDOException $e) {
    die("System Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($store['shop_name']) ?> | ShopCorrect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --brand-primary: #0B2447;
            --brand-hover: #19376D;
            --bg-light: #F8FAFC;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            color: #1e293b;
        }

        /* Banner Style */
        .store-banner {
            height: 280px;
            background: linear-gradient(rgba(11, 36, 71, 0.6), rgba(11, 36, 71, 0.6)), 
                        url('<?= $store['shop_banner'] ?: "https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=1200&q=80" ?>');
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: flex-end;
            padding-bottom: 40px;
            color: white;
        }

        /* Profile & Branding */
        .store-logo-wrapper {
            margin-top: -60px;
            position: relative;
            z-index: 2;
        }

        .store-logo {
            width: 130px;
            height: 130px;
            object-fit: cover;
            border-radius: 24px;
            border: 6px solid #fff;
            background: #fff;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        }

        .btn-contact {
            background-color: var(--brand-primary);
            color: white;
            font-weight: 700;
            border-radius: 12px;
            padding: 10px 24px;
            transition: 0.3s;
        }

        .btn-contact:hover {
            background-color: var(--brand-hover);
            color: white;
            transform: translateY(-2px);
        }

        .card-custom {
            border: none;
            border-radius: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        }

        .product-card {
            transition: 0.3s;
            border: none;
            border-radius: 18px;
            overflow: hidden;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }

        .badge-verified {
            background-color: #0EA5E9;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 50px;
        }
    </style>
</head>
<body>

<header class="store-banner">
    <div class="container">
        <div class="d-flex align-items-center gap-3">
            <div>
                <h1 class="fw-extrabold mb-1"><?= htmlspecialchars($store['shop_name']) ?></h1>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge badge-verified"><i class="bi bi-patch-check-fill me-1"></i> Verified Vendor</span>
                    <span class="small opacity-75"><i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($store['business_location']) ?></span>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="container mb-5">
    <div class="row">
        <aside class="col-lg-3">
            <div class="store-logo-wrapper text-center text-lg-start">
                <img src="<?= $store['shop_logo'] ?: "https://ui-avatars.com/api/?name=".urlencode($store['shop_name'])."&background=0B2447&color=fff&size=128" ?>" 
                     alt="Logo" class="store-logo mb-3">
            </div>
            
            <div class="card card-custom p-4 mt-2">
                <h6 class="fw-bold text-uppercase small text-secondary mb-3">About the Store</h6>
                <p class="text-muted small mb-4">
                    <?= !empty($store['shop_description']) ? nl2br(htmlspecialchars($store['shop_description'])) : "No description provided yet." ?>
                </p>
                
                <hr class="my-3 opacity-50">
                
                <div class="d-grid gap-2">
                    <a href="tel:<?= htmlspecialchars($store['business_phone']) ?>" class="btn btn-contact">
                        <i class="bi bi-telephone-fill me-2"></i> Call Vendor
                    </a>
                    <p class="text-center small text-muted mt-2">Member since <?= date("M Y", strtotime($store['created_at'])) ?></p>
                </div>
            </div>
        </aside>

        <section class="col-lg-9 mt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold mb-0">Products</h4>
                <div class="dropdown">
                    <button class="btn btn-white border dropdown-toggle rounded-pill px-3 py-2 small fw-semibold" type="button" data-bs-toggle="dropdown">
                        Sort by: Newest
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#">Price: Low to High</a></li>
                        <li><a class="dropdown-item" href="#">Price: High to Low</a></li>
                    </ul>
                </div>
            </div>

            <div class="row g-4">
                <?php
                // Later, you will query your products table where vendor_id = $store['id']
                // For now, here is a professional placeholder card
                ?>
                <div class="col-6 col-md-4 col-xl-3">
                    <div class="card product-card h-100 shadow-sm">
                        <div style="height: 180px; background: #e2e8f0; display:flex; align-items:center; justify-content:center;">
                            <i class="bi bi-image text-white-50 fs-1"></i>
                        </div>
                        <div class="card-body p-3">
                            <h6 class="fw-bold text-truncate mb-1">Example Product</h6>
                            <p class="text-primary fw-extrabold mb-0">GHS 250.00</p>
                            <div class="mt-2 d-flex justify-content-between align-items-center">
                                <span class="text-muted small">Available</span>
                                <i class="bi bi-heart text-danger"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center py-5 mt-4">
                    <i class="bi bi-shop fs-1 text-muted opacity-25"></i>
                    <p class="text-muted mt-3">This vendor hasn't uploaded products yet.</p>
                </div>
            </div>
        </section>
    </div>
</main>

<footer class="bg-white border-top py-4 mt-auto">
    <div class="container text-center text-muted small">
        <p class="mb-0">&copy; <?= date("Y") ?> ShopCorrect. Authentically Ghanaian ❤️</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>