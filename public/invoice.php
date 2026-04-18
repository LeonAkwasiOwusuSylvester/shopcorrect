<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/../app/helpers/currency.php";

/* -------------------------------------------------
   1. SMART SECURITY & VALIDATION
------------------------------------------------- */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid Order ID.");
}

$orderId = (int) $_GET['id'];

// Fetch the order FIRST so we can check permissions
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Invoice not found.");
}

// ✅ GENERATE SECURE TOKEN: Creates an unguessable password for this specific order
$secureToken = hash('sha256', $order['id'] . $order['created_at'] . 'ShopCorrectSecureInvoice2026');

// ✅ CHECK ACCESS RIGHTS:
$isBuyer       = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $order['user_id'];
$isAdmin       = isset($_SESSION['role']) && in_array($_SESSION['role'], ['supadmin', 'support', 'country_agent']);
$hasValidToken = isset($_GET['token']) && $_GET['token'] === $secureToken; // For Delivery Drivers scanning the QR

// If they are not the buyer, not an admin, AND don't have the scanner token, kick them out!
if (!$isBuyer && !$isAdmin && !$hasValidToken) {
    header("Location: login.php");
    exit;
}

// Check which language is active for stealth translation
$activeLangCode = $_SESSION['lang'] ?? $_COOKIE['shop_lang'] ?? 'en'; 

/* -------------------------------------------------
   2. SMART ADDRESS LOGIC
------------------------------------------------- */
$shippingMethodRaw = strtolower($order['shipping_method'] ?? '');
$isPickup = (strpos($shippingMethodRaw, 'pickup') !== false);

// If a driver scans it, fallback to the shipping name/phone
$customerName  = !empty($order['shipping_name']) ? $order['shipping_name'] : ($order['name'] ?? 'Valued Customer');
$customerPhone = !empty($order['shipping_phone']) ? $order['shipping_phone'] : ($order['phone'] ?? 'N/A');

if ($isPickup) {
    $addrLabel   = "PICKUP STATION";
    $addrLine1   = "ShopCorrect Main Hub";
    $addrLine2   = "123 Market Street, East Legon";
    $addrRegion  = "Greater Accra, Ghana";
} else {
    $addrLabel   = "DELIVERY ADDRESS";
    $addrLine1   = !empty($order['shipping_address']) ? $order['shipping_address'] : ($order['address'] ?? 'No Address Provided');
    $addrLine2   = !empty($order['shipping_city'])    ? $order['shipping_city']    : ($order['city'] ?? '');
    $addrRegion  = !empty($order['shipping_region'])  ? $order['shipping_region']  : ($order['region'] ?? '');
}

/* -------------------------------------------------
   3. FETCH ITEMS & CALCULATE
------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT oi.quantity, oi.price, oi.selected_color, oi.selected_size, p.name, p.id as pid, p.image
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$orderId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$subtotal = 0;
foreach ($items as $item) {
    $subtotal += ($item['price'] * $item['quantity']);
}

$shippingCost   = isset($order['shipping_cost'])    ? (float)$order['shipping_cost']    : 0.00;
$discountAmount = isset($order['discount_amount'])  ? (float)$order['discount_amount']  : 0.00;
$promoCode      = $order['promo_code'] ?? '';
$grandTotal     = isset($order['total_amount'])     ? (float)$order['total_amount']     : ($subtotal + $shippingCost - $discountAmount);

$dispPaymentMethod  = ucwords(str_replace('_', ' ', $order['payment_method'] ?? 'N/A'));
$dispShippingMethod = ucwords($order['shipping_method'] ?? 'Standard');
$orderDate          = date("M d, Y", strtotime($order['created_at']));
$activeCurrencyCode = $_SESSION['currency'] ?? 'GHS';

/* -------------------------------------------------
   4. GENERATE SECURE INVOICE QR CODE
------------------------------------------------- */
$isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$protocol = $isSecure ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$basePath = (strpos($_SERVER['REQUEST_URI'], '/shopcorrect/') !== false) ? '/shopcorrect' : '';
$dynamicBaseUrl = $protocol . $host . $basePath;

$invoiceUrl = $dynamicBaseUrl . "/public/invoice.php?id=" . $orderId . "&token=" . $secureToken;
$qrApiUrl   = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&margin=0&data=" . urlencode($invoiceUrl);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= str_pad($orderId, 6, '0', STR_PAD_LEFT) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --sc-navy: #0B2447;
            --sc-gray-bg: #F8FAFC;
            --sc-border: #E2E8F0;
            --sc-text: #1E293B;
            --sc-muted: #64748B;
        }

        body {
            background-color: #e2e8f0;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--sc-text);
            -webkit-print-color-adjust: exact;
        }

        .top-action-bar { background: var(--sc-navy); padding: 15px 0; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }

        .invoice-paper {
            background-color: white; width: 210mm; max-width: 100%; min-height: 297mm;
            margin: 2rem auto; padding: 15mm 20mm; position: relative; box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden; box-sizing: border-box; border-radius: 8px;
        }
        @media (max-width: 600px) {
            .invoice-paper { padding: 8mm 6mm; margin: 0.5rem auto; border-radius: 0; }
        }

        .watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: clamp(40px, 12vw, 100px); font-weight: 800; color: var(--sc-navy); opacity: 0.02; pointer-events: none; z-index: 0; text-transform: uppercase; white-space: nowrap; letter-spacing: 12px; }
        .content-layer { position: relative; z-index: 2; }
        .fw-bold { font-weight: 700 !important; }
        .text-xs { font-size: clamp(0.7rem, 1.5vw, 0.75rem); }
        .text-sm { font-size: clamp(0.8rem, 2vw, 0.9rem); }
        .text-muted { color: var(--sc-muted) !important; }

        .header-section { border-bottom: 2px solid var(--sc-border); padding-bottom: 20px; margin-bottom: 24px; }
        .brand-logo-img { height: clamp(40px, 8vw, 55px); width: auto; margin-right: 12px; }
        .brand-name { font-size: clamp(18px, 4vw, 26px); letter-spacing: -0.5px; color: var(--sc-navy); font-weight: 800; }
        .invoice-label { font-size: clamp(20px, 5vw, 36px); color: #cbd5e1; letter-spacing: 6px; font-weight: 800; line-height: 1; text-transform: uppercase; }

        .info-card { background-color: #ffffff; border: 1px solid var(--sc-border); border-radius: 12px; padding: clamp(12px, 3vw, 20px); height: 100%; box-shadow: 0 2px 4px rgba(0,0,0,0.01); }
        .card-label { text-transform: uppercase; letter-spacing: 1px; font-size: clamp(0.65rem, 1.5vw, 0.75rem); color: var(--sc-muted); margin-bottom: 8px; font-weight: 800; }

        .badge-status { padding: 5px 12px; border-radius: 6px; font-size: clamp(0.7rem, 1.5vw, 0.75rem); letter-spacing: 0.5px; font-weight: 800; display: inline-block; text-transform: uppercase; }
        .badge-paid    { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .badge-pending { background-color: #fef9c3; color: #854d0e; border: 1px solid #fef08a; }

        /* ✅ RESPONSIVE TABLE CONTAINER FIX */
        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; margin-bottom: 30px; }
        
        .invoice-table { width: 100%; border-collapse: collapse; min-width: 500px; /* Forces table to stay wide so it scrolls instead of crushing */ }
        .invoice-table thead th { background-color: var(--sc-navy); color: #ffffff; text-transform: uppercase; font-size: clamp(0.65rem, 1.5vw, 0.75rem); letter-spacing: 1px; padding: clamp(10px, 2vw, 14px); font-weight: 800; text-align: right; border-bottom: 2px solid var(--sc-navy); border-top: 1px solid var(--sc-navy); white-space: nowrap; }
        .invoice-table thead th:first-child { text-align: left; width: 50%; }
        
        .invoice-table tbody td { padding: clamp(10px, 2vw, 14px) clamp(8px, 1.5vw, 12px); border-bottom: 1px solid var(--sc-border); font-size: clamp(0.8rem, 2vw, 0.95rem); text-align: right; vertical-align: middle; font-weight: 500; }
        .invoice-table tbody td:first-child { text-align: left; }
        .invoice-table tbody tr:last-child td { border-bottom: 2px solid var(--sc-border); }

        .item-row-flex { display: flex; align-items: center; gap: 12px; }
        .invoice-item-img { width: clamp(40px, 6vw, 50px); height: clamp(40px, 6vw, 50px); border-radius: 8px; object-fit: cover; border: 1px solid #e2e8f0; background: #f8fafc; flex-shrink: 0; padding: 2px; }
        
        .spec-container { margin-top: 4px; display: flex; flex-wrap: wrap; gap: 6px; }
        .spec-item { font-size: clamp(0.65rem, 1.5vw, 0.7rem); background: #f1f5f9; padding: 3px 8px; border-radius: 6px; color: #475569; font-weight: 600; border: 1px solid #e2e8f0; }

        /* Totals */
        .totals-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: clamp(0.85rem, 2vw, 0.95rem); color: #475569; }
        .grand-total { display: flex; justify-content: space-between; border-top: 2px solid var(--sc-navy); background: var(--sc-gray-bg); padding: 12px 15px; margin-top: 15px; border-radius: 8px; font-size: clamp(1.1rem, 3vw, 1.3rem); font-weight: 800; color: var(--sc-navy); }

        .signature-img { max-width: clamp(100px, 20vw, 140px); height: auto; display: block; mix-blend-mode: multiply; margin-bottom: 8px; }
        .qr-wrapper { background: white; padding: 6px; border: 1px solid var(--sc-border); border-radius: 10px; flex-shrink: 0; }
        .qr-wrapper img { width: clamp(65px, 10vw, 85px); height: clamp(65px, 10vw, 85px); display: block; }

        @media (max-width: 500px) {
            .info-cols .col-6 { width: 100% !important; }
            .totals-cols .col-7, .totals-cols .col-5 { width: 100% !important; }
            .totals-cols .col-7 { margin-bottom: 2rem; }
        }

        @media print {
            body { background: none; margin: 0; }
            .top-action-bar { display: none !important; }
            .invoice-paper { box-shadow: none; margin: 0; padding: 10mm; width: 100%; height: auto; border: none; max-width: none; border-radius: 0; }
            .no-print { display: none !important; }
            .watermark { opacity: 0.04 !important; }
            .info-card { border: 1px solid #e2e8f0; }
            .qr-wrapper { border: 1px solid #e2e8f0; }
            .grand-total { background: none; border: 2px solid var(--sc-navy); border-left: none; border-right: none; border-radius: 0; }
            .skiptranslate { display: none !important; }
            /* Remove scrollbar on print so table fits page */
            .table-responsive { overflow: visible !important; }
            .invoice-table { min-width: 100% !important; }
        }

        /* ════ GOOGLE TRANSLATE WIDGET DESTROYER ════ */
        iframe.skiptranslate, iframe.goog-te-banner-frame, .goog-te-banner-frame, .goog-te-gadget, .goog-te-gadget-simple, .goog-te-gadget-icon, .VIpgJd-Zvi9od-aZ2wEe-wOHMyf, .VIpgJd-Zvi9od-aZ2wEe-wOHMyf-ti6hGc, #goog-gt-tt, #google_translate_element { display: none !important; visibility: hidden !important; opacity: 0 !important; width: 0 !important; height: 0 !important; position: absolute !important; left: -10000px !important; z-index: -1000 !important; pointer-events: none !important; }
        body { top: 0 !important; position: static !important; }
        .notranslate { color: inherit !important; }
    </style>
</head>
<body>

<div class="top-action-bar no-print">
    <div class="container d-flex justify-content-between align-items-center" style="max-width: 210mm;">
        <?php if ($hasValidToken): ?>
            <div class="text-white text-sm fw-bold"><i class="bi bi-shield-check text-success fs-5 me-1"></i> Secure Delivery View</div>
        <?php else: ?>
            <a href="my-orders.php" class="text-white text-decoration-none text-sm fw-bold opacity-75 hover-opacity-100 transition">
                <i class="bi bi-arrow-left me-1"></i> BACK TO ORDERS
            </a>
        <?php endif; ?>

        <button onclick="window.print()" class="btn btn-light text-dark fw-bold shadow-sm rounded-pill px-4 btn-sm">
            <i class="bi bi-printer-fill me-2"></i> PRINT INVOICE
        </button>
    </div>
</div>

<div class="invoice-paper">
    
    <div class="watermark notranslate">SHOPCORRECT</div>

    <div class="content-layer">

        <div class="row header-section align-items-center g-2">
            <div class="col-7 col-sm-6">
                <div class="d-flex align-items-center mb-2">
                    <img src="assets/images/logo_b.png" alt="Logo" class="brand-logo-img" onerror="this.style.display='none'">
                    <div class="brand-name notranslate">ShopCorrect</div>
                </div>
                <div class="text-sm text-muted lh-base">
                    123 Market Street, East Legon<br>
                    Accra, Ghana<br>
                    support@shopcorrect.com
                </div>
            </div>
            
            <div class="col-5 col-sm-6 d-flex justify-content-end align-items-center gap-3 gap-md-4">
                <div class="text-end d-none d-sm-block">
                    <div class="invoice-label mb-2">INVOICE</div>
                    <div class="fw-bold text-dark" style="font-size: clamp(1.2rem, 3vw, 1.5rem);">#<?= str_pad($orderId, 6, '0', STR_PAD_LEFT) ?></div>
                    <div class="text-sm text-muted fw-semibold mt-1"><?= $orderDate ?></div>
                </div>
                <div class="qr-wrapper shadow-sm">
                    <img src="<?= $qrApiUrl ?>" alt="Scan to Verify">
                </div>
            </div>
            <div class="col-12 d-sm-none text-end mt-2">
                <div class="fw-bold text-dark fs-5">INVOICE #<?= str_pad($orderId, 6, '0', STR_PAD_LEFT) ?></div>
                <div class="text-sm text-muted fw-semibold"><?= $orderDate ?></div>
            </div>
        </div>

        <div class="row g-3 mb-4 info-cols">
            <div class="col-6">
                <div class="info-card">
                    <div class="card-label">Billed To</div>
                    <h5 class="fw-bold text-dark mb-1" style="font-size: clamp(0.95rem, 3vw, 1.15rem);"><?= htmlspecialchars($customerName) ?></h5>
                    <div class="text-sm text-muted fw-medium mb-3"><?= htmlspecialchars($customerPhone) ?></div>

                    <div class="card-label border-top border-light pt-3 mt-3"><?= $addrLabel ?></div>
                    <div class="fw-bold text-dark text-sm mb-1"><?= htmlspecialchars($addrLine1) ?></div>
                    <?php if(!empty($addrLine2)): ?>
                        <div class="text-sm text-muted"><?= htmlspecialchars($addrLine2) ?></div>
                    <?php endif; ?>
                    <div class="text-sm text-muted"><?= htmlspecialchars($addrRegion) ?></div>
                </div>
            </div>

            <div class="col-6">
                <div class="info-card">
                    <div class="card-label">Order Details</div>
                    
                    <div class="d-flex justify-content-between mb-3 flex-wrap gap-1 border-bottom border-light pb-2">
                        <span class="text-sm text-muted fw-medium">Payment Method:</span>
                        <span class="text-sm fw-bold text-dark"><?= $dispPaymentMethod ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3 flex-wrap gap-1 border-bottom border-light pb-2">
                        <span class="text-sm text-muted fw-medium">Shipping Method:</span>
                        <span class="text-sm fw-bold text-dark"><?= $dispShippingMethod ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3 flex-wrap gap-1 border-bottom border-light pb-2">
                        <span class="text-sm text-muted fw-medium">Currency:</span>
                        <span class="text-sm fw-bold text-dark"><?= $activeCurrencyCode ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-1 mt-2">
                        <span class="text-sm text-muted fw-medium">Payment Status:</span>
                        <?php if($order['payment_status'] === 'paid' || $order['status'] === 'paid'): ?>
                            <span class="badge-status badge-paid">PAID</span>
                        <?php else: ?>
                            <span class="badge-status badge-pending"><?= strtoupper($order['payment_status']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>Item Description</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <div class="item-row-flex">
                                <?php 
                                    $imgName = $item['image'];
                                    $imgPath = 'assets/img/placeholder.png';
                                    if (!empty($imgName)) {
                                        $checkPaths = ['uploads/products/' . $imgName, '../uploads/products/' . $imgName, 'uploads/' . $imgName];
                                        foreach ($checkPaths as $path) {
                                            if (file_exists(__DIR__ . '/' . $path)) { $imgPath = $path; break; }
                                        }
                                    }
                                ?>
                                <img src="<?= htmlspecialchars($imgPath) ?>" alt="Item" class="invoice-item-img" onerror="this.src='assets/img/placeholder.png'">
                                
                                <div>
                                    <div class="fw-bold text-dark lh-sm mb-1" style="font-size: clamp(0.85rem, 2vw, 0.95rem); white-space: normal;"><?= htmlspecialchars($item['name']) ?></div>
                                    
                                    <?php if (!empty($item['selected_color']) || !empty($item['selected_size'])): ?>
                                        <div class="spec-container">
                                            <?php if (!empty($item['selected_color'])): ?>
                                                <span class="spec-item"><i class="bi bi-palette-fill me-1 opacity-50"></i><?= htmlspecialchars($item['selected_color']) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($item['selected_size'])): ?>
                                                <span class="spec-item"><i class="bi bi-arrows-angle-expand me-1 opacity-50"></i><?= htmlspecialchars($item['selected_size']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="text-xs text-muted mt-1 fw-medium notranslate">SKU: SC-<?= str_pad($item['pid'], 4, '0', STR_PAD_LEFT) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="text-center fw-bold text-dark"><?= $item['quantity'] ?></td>
                        <td class="notranslate text-muted" style="white-space: nowrap;"><?= formatPrice($item['price']) ?></td>
                        <td class="fw-bold notranslate text-dark" style="white-space: nowrap;"><?= formatPrice($item['price'] * $item['quantity']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="row mt-4 totals-cols">
            <div class="col-7 pe-md-5">
                <div class="card-label mb-2 text-dark">Terms & Conditions</div>
                <p class="text-xs text-muted lh-base mb-4 pe-4">
                    Goods sold are non-refundable after 7 days from delivery. Please retain this invoice for any warranty claims or return requests. Thank you for shopping with ShopCorrect!
                </p>
                
                <div class="mt-4 pt-3">
                    <?php if(file_exists('assets/images/signature.png')): ?>
                        <img src="assets/images/signature.png" alt="Signature" class="signature-img">
                    <?php else: ?>
                        <div style="height: 50px;"></div> 
                    <?php endif; ?>
                    <div class="border-top border-dark d-inline-block pt-2 text-xs fw-bold text-dark" style="min-width: 180px;">
                        AUTHORIZED SIGNATORY
                    </div>
                </div>
            </div>

            <div class="col-5">
                <div class="totals-row">
                    <span class="fw-medium">Subtotal</span>
                    <span class="fw-bold text-dark notranslate"><?= formatPrice($subtotal) ?></span>
                </div>
                <div class="totals-row">
                    <span class="fw-medium">Shipping</span>
                    <span class="fw-bold text-dark notranslate"><?= formatPrice($shippingCost) ?></span>
                </div>
                
                <?php if ($discountAmount > 0): ?>
                <div class="totals-row text-danger">
                    <span class="fw-bold">Discount (<?= htmlspecialchars($promoCode) ?>)</span>
                    <span class="fw-bold notranslate">-<?= formatPrice($discountAmount) ?></span>
                </div>
                <?php endif; ?>

                <div class="grand-total">
                    <span>Total Due</span>
                    <span class="notranslate"><?= formatPrice($grandTotal) ?></span>
                </div>
                
                <?php if ($activeCurrencyCode !== 'GHS'): ?>
                    <div class="text-end mt-2 text-xs text-muted fw-medium">
                        *Billed to your card as: <strong class="notranslate text-dark">GHS <?= number_format($grandTotal, 2) ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-5 pt-4 border-top text-center">
            
            <div class="fw-bolder mb-3 notranslate" style="color: var(--sc-navy); font-size: clamp(1.1rem, 2.5vw, 1.3rem); letter-spacing: -0.3px;">
                <i class="bi bi-check-lg" style="color: #64748B; -webkit-text-stroke: 2px;"></i> Shop Smart. Shop Correct.
            </div>

            <div class="text-dark fw-bold d-flex justify-content-center align-items-center gap-3 mb-2" style="font-size: clamp(0.8rem, 2vw, 0.9rem);">
                <span class="notranslate"><i class="bi bi-globe me-1 text-muted"></i> www.shopcorrect.com</span>
                <span style="color: #e2e8f0;">|</span>
                <span class="notranslate"><i class="bi bi-envelope-fill me-1 text-muted"></i> support@shopcorrect.com</span>
            </div>
            
            <div class="text-xs text-muted fw-medium mt-3">
                This is a computer-generated invoice and requires no physical seal.
            </div>
        </div>

    </div>
</div>

<?php if (isset($activeLangCode) && $activeLangCode !== 'en'): ?>
    <script type="text/javascript">
        function googleTranslateElementInit() {
            new google.translate.TranslateElement({
                pageLanguage: 'en', 
                includedLanguages: 'en,fr,sw,de,zh-CN,es', 
                autoDisplay: false
            }, 'google_translate_element');
        }
    </script>
    <script type="text/javascript" src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>