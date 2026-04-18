<?php
if (session_status() === PHP_SESSION_NONE) session_start();

/* ==========================================================
   BULLETPROOF DATABASE CONNECTION
========================================================== */
$possibleDbPaths = [
    __DIR__ . "/../app/config/db.php",       
    __DIR__ . "/../../app/config/db.php",    
    __DIR__ . "/../../../app/config/db.php"  
];

$dbFound = false;
foreach ($possibleDbPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $dbFound = true;
        break;
    }
}

if (!$dbFound) {
    die("<div style='padding:20px; background:#FEE2E2; color:#DC2626; text-align:center; font-family:sans-serif;'>
            <strong>Critical Error:</strong> Cannot locate your database configuration file (db.php). 
         </div>");
}
/* ========================================================== */

$userId     = $_SESSION['user_id'] ?? null;
$userName   = $_SESSION['name'] ?? 'Guest';
$isLoggedIn = isset($userId);

// If the user is already a vendor or supadmin, they shouldn't be here. Send to dashboard.
if ($isLoggedIn && isset($_SESSION['role']) && in_array($_SESSION['role'], ['vendor', 'supadmin'])) {
    header("Location: ../vendor/index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sell on ShopCorrect | Partner With Us</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <style>
        :root { 
            --sc-navy: #0B2447; 
            --sc-gold: #FFD700; 
            --sc-bg: #f8fafc;
        }
        body { font-family: 'Inter', sans-serif; background-color: var(--sc-bg); color: #334155; }

        /* ==========================================
           HERO SECTION
        ========================================== */
        .vendor-hero { 
            background: linear-gradient(rgba(11, 36, 71, 0.88), rgba(11, 36, 71, 0.95)), url('https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?auto=format&fit=crop&w=1920&q=80') center center / cover no-repeat;
            color: white; 
            padding: 120px 0; 
            position: relative; 
            overflow: hidden; 
            text-align: center;
        }
        .vendor-hero::after { 
            content: ''; position: absolute; top: -50%; right: -10%; width: 500px; height: 500px; 
            background: radial-gradient(circle, rgba(255,215,0,0.15) 0%, rgba(255,255,255,0) 70%); 
            border-radius: 50%; pointer-events: none;
        }
        
        .badge-gold { 
            background: rgba(255, 215, 0, 0.15); color: var(--sc-gold); 
            padding: 8px 20px; border-radius: 50px; font-weight: 800; font-size: 0.85rem; 
            letter-spacing: 1px; text-transform: uppercase; border: 1px solid rgba(255,215,0,0.3); 
        }

        .hero-title {
            font-size: clamp(2.5rem, 5vw, 4rem);
            font-weight: 800;
            letter-spacing: -1.5px;
            margin-bottom: 20px;
            line-height: 1.1;
        }

        .hero-subtitle {
            font-size: clamp(1.1rem, 2vw, 1.25rem);
            color: rgba(255,255,255,0.8);
            max-width: 600px;
            margin: 0 auto 40px auto;
        }

        .btn-gold-lg {
            background: var(--sc-gold);
            color: var(--sc-navy);
            font-weight: 800;
            font-size: 1.1rem;
            padding: 16px 40px;
            border-radius: 50px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px rgba(255, 215, 0, 0.2);
        }
        .btn-gold-lg:hover {
            background: #eab308;
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(255, 215, 0, 0.3);
            color: var(--sc-navy);
        }

        /* ==========================================
           BENEFITS SECTION
        ========================================== */
        .benefits-section { padding: 80px 0; background: white; }
        
        .benefit-card {
            padding: 40px 30px;
            border-radius: 24px;
            background: #fff;
            border: 1px solid #e2e8f0;
            height: 100%;
            transition: 0.3s ease;
            text-align: center;
        }
        .benefit-card:hover {
            border-color: #cbd5e1;
            box-shadow: 0 20px 40px -10px rgba(11, 36, 71, 0.08);
            transform: translateY(-5px);
        }
        
        .b-icon-wrap {
            width: 80px; height: 80px;
            border-radius: 20px;
            background: #f0f9ff;
            color: var(--sc-navy);
            display: flex; align-items: center; justify-content: center;
            font-size: 2.2rem;
            margin: 0 auto 20px auto;
        }

        /* ==========================================
           STEPS SECTION
        ========================================== */
        .steps-section { padding: 80px 0; background: var(--sc-bg); }
        
        .step-box {
            position: relative;
            padding: 30px;
            z-index: 2;
        }
        .step-number {
            font-size: 4rem;
            font-weight: 800;
            color: #e2e8f0;
            line-height: 1;
            position: absolute;
            top: 0; left: 20px;
            z-index: -1;
            opacity: 0.5;
        }
        .step-title {
            font-weight: 800;
            color: var(--sc-navy);
            margin-top: 20px;
            font-size: 1.25rem;
        }

        /* ==========================================
           BOTTOM CTA (Redesigned for contrast)
        ========================================== */
        .bottom-cta {
            background: #ffffff;
            padding: 80px 0;
            text-align: center;
            color: var(--sc-navy);
            border-top: 1px solid #e2e8f0;
        }

        .btn-navy-lg {
            background: var(--sc-navy);
            color: #ffffff;
            font-weight: 800;
            font-size: 1.1rem;
            padding: 16px 40px;
            border-radius: 50px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px rgba(11, 36, 71, 0.15);
        }
        .btn-navy-lg:hover {
            background: #1e3a8a;
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(11, 36, 71, 0.25);
            color: #ffffff;
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . "/partials/navbar.php"; ?>

<div class="vendor-hero">
    <div class="container position-relative" style="z-index: 2;">
        <span class="badge-gold mb-4 d-inline-block">ShopCorrect Sellers</span>
        <h1 class="hero-title">Turn Your Inventory<br>Into National Income.</h1>
        <p class="hero-subtitle">Join thousands of successful merchants selling to verified buyers nationwide. Setup is completely free, secure, and fast.</p>
        
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <a href="register.php" class="btn-gold-lg">
                Start Selling Today <i class="bi bi-arrow-right"></i>
            </a>
            <?php if (!$isLoggedIn): ?>
                <a href="login.php" class="btn btn-outline-light rounded-pill px-4 fw-bold" style="padding-top: 15px; padding-bottom: 15px; border-width: 2px;">
                    Log In to Account
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="benefits-section">
    <div class="container">
        <div class="text-center mb-5 pb-3">
            <h2 class="fw-bold" style="color: var(--sc-navy); font-size: clamp(2rem, 3vw, 2.5rem); letter-spacing: -0.5px;">Why partner with us?</h2>
            <p class="text-muted">Everything you need to scale your business online.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="benefit-card">
                    <div class="b-icon-wrap"><i class="bi bi-people-fill"></i></div>
                    <h4 class="fw-bold mb-3" style="color: var(--sc-navy);">Reach Millions</h4>
                    <p class="text-muted mb-0">Stop relying on foot traffic. Get your products directly in front of active buyers actively searching for what you sell.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="benefit-card">
                    <div class="b-icon-wrap" style="background: #fffbeb;"><i class="bi bi-wallet2" style="color: #d97706;"></i></div>
                    <h4 class="fw-bold mb-3" style="color: var(--sc-navy);">Fast Payouts</h4>
                    <p class="text-muted mb-0">Your money is yours. Request secure, automated payouts directly to your Mobile Money or Bank Account the moment an order is completed.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="benefit-card">
                    <div class="b-icon-wrap" style="background: #f0fdf4;"><i class="bi bi-boxes" style="color: #15803d;"></i></div>
                    <h4 class="fw-bold mb-3" style="color: var(--sc-navy);">Flexible Logistics</h4>
                    <p class="text-muted mb-0">Ship orders yourself directly to customers, or securely drop them off at a ShopCorrect Hub for hassle-free automated fulfillment. The choice is yours.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="steps-section">
    <div class="container">
        <h2 class="fw-bold text-center mb-5" style="color: var(--sc-navy); font-size: clamp(2rem, 3vw, 2.5rem); letter-spacing: -0.5px;">How it works</h2>
        
        <div class="row g-5">
            <div class="col-md-4">
                <div class="step-box">
                    <div class="step-number">01</div>
                    <h4 class="step-title">Register & Verify</h4>
                    <p class="text-muted mt-3">Create your free account. You will then be prompted to provide your shop name and a valid ID to verify your business identity.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="step-box">
                    <div class="step-number">02</div>
                    <h4 class="step-title">Upload Inventory</h4>
                    <p class="text-muted mt-3">Use our professional dashboard to upload product images, set your prices, and define available sizes and colors.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="step-box">
                    <div class="step-number">03</div>
                    <h4 class="step-title">Start Earning</h4>
                    <p class="text-muted mt-3">Receive instant notifications when customers place orders. Pack the item, fulfill the delivery, and watch your dashboard earnings grow.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="bottom-cta">
    <div class="container">
        <h2 class="fw-bold mb-4" style="font-size: clamp(2rem, 4vw, 3rem); letter-spacing: -1px;">Ready to grow your sales?</h2>
        <p class="mb-5 text-muted mx-auto" style="max-width: 600px; font-size: 1.1rem;">Join the leading marketplace for verified, high-quality products. It only takes 5 minutes to get started.</p>
        <a href="register.php" class="btn-navy-lg">
            Create Your Seller Account <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>
</body>
</html>