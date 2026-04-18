<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/../app/helpers/currency.php";

/* ----------------------------------------------------------
   1. LOGIC FIRST
---------------------------------------------------------- */
$isLoggedIn = isset($_SESSION["user_id"]);
$userId     = $_SESSION["user_id"] ?? null;

$items = [];
$total = 0;
$totalWeight = 0;
$user  = []; 
$originCountries = [];

if ($isLoggedIn) {
    // UPDATED SQL: Joins product_variants to get specific pricing
    $stmt = $pdo->prepare("
        SELECT 
            ci.id, ci.quantity, 
            ci.selected_color, 
            ci.selected_size, 
            p.id AS product_id, p.name, p.price as base_price, p.sale_price,
            p.discount_percent, p.stock, p.image, p.weight,
            p.fulfillment_type, p.warehouse_country,
            vu.country AS vendor_country,
            pv.price AS variant_price
        FROM carts c
        JOIN cart_items ci ON c.id = ci.cart_id
        JOIN products p ON ci.product_id = p.id
        JOIN vendors v ON p.vendor_id = v.id
        JOIN users vu ON v.user_id = vu.id
        LEFT JOIN product_variants pv 
            ON p.id = pv.product_id 
            AND COALESCE(ci.selected_color, '') = COALESCE(pv.color, '')
            AND COALESCE(ci.selected_size, '') = COALESCE(pv.size, '')
        WHERE c.user_id = ?
    ");
    $stmt->execute([$userId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmtUser = $pdo->prepare("SELECT name, phone, address, country, location FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
}

if ($isLoggedIn && empty($items)) {
    header("Location: index.php");
    exit;
}

foreach ($items as $item) {
    // Determine accurate price based on variants vs global price
    $globalBase  = (float) $item['base_price'];
    $globalSale  = (float) $item['sale_price'];
    $isGlobalSale = ((int)$item['discount_percent'] > 0 && $globalSale > 0);
    $globalFinal = $isGlobalSale ? $globalSale : $globalBase;
    
    $price = !empty($item['variant_price']) ? (float) $item['variant_price'] : $globalFinal;
    
    $total += $price * $item['quantity'];
    $totalWeight += ($item['weight'] * $item['quantity']); 
    
    $origin = trim($item['vendor_country'] ?? '');
    
    if (($item['fulfillment_type'] ?? 'vendor') === 'shopcorrect' && !empty($item['warehouse_country'])) {
        $origin = trim($item['warehouse_country']);
    }

    if (!empty($origin)) {
        $originCountries[] = $origin;
    }
}

$countryRates = [];
if ($totalWeight >= 0) {
    $stmtRates = $pdo->prepare("
        SELECT sc.country_name, sr.price, sr.express_price 
        FROM shipping_countries sc
        JOIN shipping_rates sr ON sc.zone_id = sr.zone_id
        WHERE ? >= sr.min_weight AND ? <= sr.max_weight
    ");
    $stmtRates->execute([$totalWeight, $totalWeight]);
    $ratesData = $stmtRates->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($ratesData as $row) {
        $countryRates[$row['country_name']] = [
            'standard' => (float)$row['price'],
            'express'  => (float)$row['express_price']
        ];
    }
}
$countryRatesJson = json_encode($countryRates);

$originCountries = array_unique($originCountries);
$originCountriesJson = json_encode(array_values($originCountries));

$fmt0 = formatPrice(0);
preg_match('/^[^\d]*/', $fmt0, $preMatch);
preg_match('/[^\d]*$/', $fmt0, $sufMatch);
$jsCurrencyPrefix = $preMatch[0] ?? ''; 
$jsCurrencySuffix = $sufMatch[0] ?? ''; 

$fmtLarge = formatPrice(10000); 
$cleanLarge = str_replace(',', '', $fmtLarge);
preg_match('/[\d\.]+/', $cleanLarge, $matches); 
$jsExchangeRate = isset($matches[0]) ? ((float)$matches[0] / 10000) : 1.0;

$grandTotal = $total; 

require_once __DIR__ . "/partials/navbar.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout</title>
</head>
<body>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<style>
    :root { --sc-navy: #0B2447; --sc-blue: #19376D; --sc-accent: #3b82f6; --sc-bg: #F3F4F6; }
    body { background-color: var(--sc-bg); font-family: 'Plus Jakarta Sans', sans-serif; color: #1f2937; }
    
    .checkout-stepper { display: flex; align-items: center; justify-content: center; margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap; }
    .step-item { display: flex; align-items: center; gap: 0.5rem; color: #9ca3af; font-weight: 600; font-size: clamp(0.78rem, 2vw, 0.9rem); }
    .step-item.active { color: var(--sc-navy); }
    .step-circle { width: 24px; height: 24px; border-radius: 50%; background: #e5e7eb; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 800; flex-shrink: 0; }
    .step-item.active .step-circle { background: var(--sc-navy); }
    .step-line { width: 40px; height: 2px; background: #e5e7eb; }
    .step-item.completed .step-circle { background: #10b981; }
    .step-item.completed { color: #10b981; }

    .checkout-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 24px; overflow: hidden; }
    .card-header-custom { padding: 18px 24px; border-bottom: 1px solid #f3f4f6; background: #fafafa; display: flex; align-items: center; gap: 10px; font-weight: 700; color: var(--sc-navy); font-size: clamp(0.9rem, 2vw, 1rem); }
    
    .mobile-summary-toggle { display: none; background: #fff; padding: 15px; border-bottom: 1px solid #e5e7eb; align-items: center; justify-content: space-between; color: var(--sc-navy); font-weight: 700; cursor: pointer; }
    @media(max-width: 991px) { .mobile-summary-toggle { display: flex; } }

    .shipping-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; }
    .ship-card { border: 2px solid #e5e7eb; border-radius: 12px; padding: 1rem; cursor: pointer; position: relative; transition: all 0.2s; background: white; height: 100%; display: flex; flex-direction: column; justify-content: space-between; }
    .ship-card:hover { border-color: #d1d5db; }
    .ship-card.active { border-color: var(--sc-navy); background-color: #f0f9ff; box-shadow: 0 4px 6px -1px rgba(11, 36, 71, 0.1); }
    .ship-card.active::after { content: "\F26B"; font-family: "bootstrap-icons"; position: absolute; top: 10px; right: 10px; color: var(--sc-navy); font-size: 1.2rem; }
    
    .badge-fast { position: absolute; top: -10px; left: 50%; transform: translateX(-50%); background: #10b981; color: white; font-size: 0.65rem; font-weight: 800; padding: 2px 10px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }

    .item-thumb { width: 60px; height: 60px; border-radius: 8px; background: #f3f4f6; border: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: center; padding: 4px; overflow: hidden; flex-shrink: 0; }
    .item-thumb img { width: 100%; height: 100%; object-fit: contain; }
    
    .specs-row { display: flex; gap: 6px; margin-top: 4px; flex-wrap: wrap; }
    .spec-badge { display: inline-flex; align-items: center; gap: 4px; font-size: 0.7rem; font-weight: 600; background: #f3f4f6; color: #4b5563; padding: 2px 8px; border-radius: 4px; border: 1px solid #e5e7eb; }
    .color-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; border: 1px solid rgba(0,0,0,0.1); }

    .amount-card { background: var(--sc-navy); color: white; border-radius: 16px; padding: clamp(20px, 4vw, 30px); margin-bottom: 24px; text-align: center; position: relative; overflow: hidden; }
    .amount-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%); pointer-events: none; }
    .amount-label { font-size: 0.75rem; text-transform: uppercase; opacity: 0.8; font-weight: 700; letter-spacing: 1px; }
    .amount-value { font-size: clamp(1.8rem, 5vw, 2.5rem); font-weight: 800; margin-top: 5px; }
    
    .btn-pay { background-color: var(--sc-navy); color: #fff; font-weight: 700; padding: 16px; border-radius: 12px; width: 100%; border: none; transition: 0.2s; font-size: clamp(0.95rem, 2.5vw, 1.1rem); display: flex; align-items: center; justify-content: center; gap: 10px; text-decoration: none; cursor: pointer; box-shadow: 0 4px 6px -1px rgba(11, 36, 71, 0.2); }
    .btn-pay:hover { background-color: #1a3b66; color: white; transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(11, 36, 71, 0.3); }

    .summary-sticky { position: sticky; top: 20px; }
    
    .form-floating > .form-control:focus ~ label, .form-floating > .form-select:focus ~ label { color: var(--sc-navy); }
    .form-floating > .form-control:focus, .form-floating > .form-select:focus { border-color: var(--sc-navy); box-shadow: 0 0 0 4px rgba(11, 36, 71, 0.1); }
    
    /* Promo Code */
    .promo-input-group { display: flex; gap: 10px; }
    .promo-input { background-color: #f8fafc; border: 1.5px dashed #cbd5e1; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; transition: 0.2s; }
    .promo-input:focus { border-color: var(--sc-navy); background-color: #fff; box-shadow: none; border-style: solid; }
    .btn-promo { background-color: #e2e8f0; color: #475569; font-weight: 700; border-radius: 8px; transition: 0.2s; white-space: nowrap; }
    .btn-promo:hover { background-color: var(--sc-navy); color: white; }
    #promo-msg { font-size: 0.8rem; font-weight: 700; margin-top: 8px; display: none; }

    /* Mobile order items - prevent overflow */
    .order-item-name { font-size: clamp(0.82rem, 2vw, 0.95rem); word-break: break-word; }
</style>

<div class="mobile-summary-toggle" data-bs-toggle="collapse" data-bs-target="#mobileSummary" aria-expanded="false">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-cart3"></i> Show Order Summary <i class="bi bi-chevron-down small"></i>
    </div>
    <div class="fw-bold" id="mobile-total-display"><?= formatPrice($grandTotal) ?></div>
</div>

<div class="collapse d-lg-none bg-light border-bottom" id="mobileSummary">
    <div class="p-3 container">
        <?php foreach ($items as $i): 
            $globalBase  = (float) $i['base_price'];
            $globalSale  = (float) $i['sale_price'];
            $isGlobalSale = ((int)$i['discount_percent'] > 0 && $globalSale > 0);
            $globalFinal = $isGlobalSale ? $globalSale : $globalBase;
            $price = !empty($i['variant_price']) ? (float)$i['variant_price'] : $globalFinal; 
        ?>
            <div class="d-flex justify-content-between mb-2 small">
                <span class="text-truncate me-2" style="max-width:65%;"><?= htmlspecialchars($i['name']) ?> (x<?= $i['quantity'] ?>)</span>
                <span class="fw-bold"><?= formatPrice($price * $i['quantity']) ?></span>
            </div>
        <?php endforeach; ?>
        <hr>
        <div class="d-flex justify-content-between fw-bold">
            <span>Total</span>
            <span id="mobile-dropdown-total"><?= formatPrice($grandTotal) ?></span>
        </div>
    </div>
</div>

<div class="container py-4 py-lg-5">
    
    <div class="checkout-stepper">
        <div class="step-item completed">
            <div class="step-circle"><i class="bi bi-check"></i></div> Cart
        </div>
        <div class="step-line"></div>
        <div class="step-item active">
            <div class="step-circle">2</div> Checkout
        </div>
        <div class="step-line"></div>
        <div class="step-item">
            <div class="step-circle">3</div> Finish
        </div>
    </div>

    <form method="POST" action="../routes/checkout.php">
        <div class="row g-4 g-lg-5">
            
            <div class="col-lg-8">
                
                <div class="checkout-card">
                    <div class="card-header-custom">
                        <i class="bi bi-geo-alt-fill"></i> Shipping Details
                    </div>
                    <div class="p-3 p-md-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" name="shipping_name" class="form-control" id="fName" 
                                           value="<?= htmlspecialchars($user['name'] ?? '') ?>" placeholder="Name" required>
                                    <label for="fName">Full Name</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="tel" name="shipping_phone" class="form-control" id="fPhone" 
                                           value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="Phone" required>
                                    <label for="fPhone">Phone Number</label>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select name="shipping_country" id="fCountry" class="form-select" required>
                                        <option value="" disabled <?= empty($user['country']) ? 'selected' : '' ?>>Select Country</option>
                                        <optgroup label="Africa">
                                            <option value="Ghana" <?= ($user['country'] ?? '') === 'Ghana' ? 'selected' : '' ?>>Ghana</option>
                                            <option value="Nigeria" <?= ($user['country'] ?? '') === 'Nigeria' ? 'selected' : '' ?>>Nigeria</option>
                                            <option value="Cote d'Ivoire" <?= ($user['country'] ?? '') === "Cote d'Ivoire" ? 'selected' : '' ?>>Côte d'Ivoire</option>
                                            <option value="South Africa" <?= ($user['country'] ?? '') === 'South Africa' ? 'selected' : '' ?>>South Africa</option>
                                            <option value="Kenya" <?= ($user['country'] ?? '') === 'Kenya' ? 'selected' : '' ?>>Kenya</option>
                                            <option value="Togo" <?= ($user['country'] ?? '') === 'Togo' ? 'selected' : '' ?>>Togo</option>
                                        </optgroup>
                                        <optgroup label="International">
                                            <option value="United Kingdom" <?= ($user['country'] ?? '') === 'United Kingdom' ? 'selected' : '' ?>>United Kingdom</option>
                                            <option value="United States" <?= ($user['country'] ?? '') === 'United States' ? 'selected' : '' ?>>United States</option>
                                            <option value="Canada" <?= ($user['country'] ?? '') === 'Canada' ? 'selected' : '' ?>>Canada</option>
                                            <option value="Germany" <?= ($user['country'] ?? '') === 'Germany' ? 'selected' : '' ?>>Germany</option>
                                            <option value="China" <?= ($user['country'] ?? '') === 'China' ? 'selected' : '' ?>>China</option>
                                            <option value="Spain" <?= ($user['country'] ?? '') === 'Spain' ? 'selected' : '' ?>>Spain</option>
                                        </optgroup>
                                    </select>
                                    <label for="fCountry">Country</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-floating" id="region_dropdown_container">
                                    <select id="fRegionSelect" class="form-select">
                                        <option value="" disabled selected>Select Region</option>
                                        <?php 
                                        $regions = ["Ahafo","Ashanti","Bono","Bono East","Central","Eastern","Greater Accra","North East","Northern","Oti","Savannah","Upper East","Upper West","Volta","Western","Western North"];
                                        foreach($regions as $reg) echo "<option value='$reg'>$reg</option>";
                                        ?>
                                    </select>
                                    <label for="fRegionSelect">Region</label>
                                </div>

                                <div class="form-floating d-none" id="region_text_container">
                                    <input type="text" id="fRegionInput" class="form-control" placeholder="State / Province">
                                    <label for="fRegionInput">State / Province</label>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-floating">
                                    <input type="text" name="shipping_address" class="form-control" id="fAddr" 
                                           value="<?= htmlspecialchars($user['address'] ?? '') ?>" placeholder="Address" required>
                                    <label for="fAddr">Delivery Address (House No / Street / GPS)</label>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <div class="form-floating">
                                    <input type="text" name="shipping_city" class="form-control" id="fCity" 
                                           value="<?= htmlspecialchars($user['location'] ?? '') ?>" placeholder="City" required>
                                    <label for="fCity">City / Town</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="checkout-card">
                    <div class="card-header-custom">
                        <i class="bi bi-truck"></i> Delivery Method
                    </div>
                    <div class="p-3 p-md-4">
                        <input type="hidden" name="shipping_method" id="input_method" value="standard">
                        <input type="hidden" name="shipping_cost" id="input_cost" value="0.00">

                        <div class="shipping-grid">
                            <div class="ship-card active" data-method="standard" onclick="setShippingMethod('standard', this)">
                                <div>
                                    <div class="fw-bold text-dark mb-1" style="font-size:clamp(0.85rem,2vw,1rem);">Standard Delivery</div>
                                    <div class="small text-muted mb-3">Reliable nationwide delivery.</div>
                                </div>
                                <div class="d-flex justify-content-between align-items-end mt-auto border-top pt-2">
                                    <div class="small fw-bold text-secondary">3 - 5 Days</div>
                                    <div class="fw-bold text-dark" id="lbl-standard-cost">-</div>
                                </div>
                            </div>

                            <div class="ship-card" data-method="express" onclick="setShippingMethod('express', this)">
                                <span class="badge-fast">Fastest</span>
                                <div>
                                    <div class="fw-bold text-dark mb-1" style="font-size:clamp(0.85rem,2vw,1rem);">Express Delivery</div>
                                    <div class="small text-muted mb-3">Priority shipping for urgent orders.</div>
                                </div>
                                <div class="d-flex justify-content-between align-items-end mt-auto border-top pt-2">
                                    <div class="small fw-bold text-secondary">Same Day</div>
                                    <div class="fw-bold text-dark" id="lbl-express-cost">-</div>
                                </div>
                            </div>

                            <div class="ship-card" data-method="pickup" onclick="setShippingMethod('pickup', this)">
                                <div>
                                    <div class="fw-bold text-dark mb-1" style="font-size:clamp(0.85rem,2vw,1rem);">Pickup Station</div>
                                    <div class="small text-muted mb-3">Collect from our Accra office.</div>
                                </div>
                                <div class="d-flex justify-content-between align-items-end mt-auto border-top pt-2">
                                    <div class="small fw-bold text-secondary">Mon - Fri</div>
                                    <div class="fw-bold text-success">Free</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="checkout-card">
                    <div class="card-header-custom">
                        <i class="bi bi-bag-check"></i> Order Items
                    </div>
                    <div class="px-3 px-md-4 pb-2">
                        <?php if (empty($items)): ?>
                            <div class="text-center py-5 text-muted">Cart is empty.</div>
                        <?php else: ?>
                            <?php foreach ($items as $i): 
                                $globalBase  = (float) $i['base_price'];
                                $globalSale  = (float) $i['sale_price'];
                                $isGlobalSale = ((int)$i['discount_percent'] > 0 && $globalSale > 0);
                                $globalFinal = $isGlobalSale ? $globalSale : $globalBase;
                                $price = !empty($i['variant_price']) ? (float)$i['variant_price'] : $globalFinal;
                                
                                $lineTotal = $price * $i['quantity'];
                                
                                $dbImage = $i['image'];
                                $imagePath = ''; 
                                if (!empty($dbImage)) {
                                    $p1 = __DIR__ . '/uploads/products/' . $dbImage;
                                    $p2 = __DIR__ . '/uploads/' . $dbImage;
                                    if (file_exists($p1)) $imagePath = 'uploads/products/' . $dbImage;
                                    elseif (file_exists($p2)) $imagePath = 'uploads/' . $dbImage;
                                }
                            ?>
                                <div class="py-3 border-bottom last-no-border">
                                    <div class="d-flex align-items-center justify-content-between gap-2">
                                        <div class="d-flex align-items-center gap-2 gap-md-3 min-w-0">
                                            <div class="item-thumb">
                                                <?php if ($imagePath): ?>
                                                    <img src="<?= htmlspecialchars($imagePath) ?>" alt="img">
                                                <?php else: ?>
                                                    <i class="bi bi-image text-muted"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="min-w-0">
                                                <h6 class="fw-bold mb-0 text-dark order-item-name text-truncate" style="max-width: 200px;"><?= htmlspecialchars($i['name']) ?></h6>
                                                <div class="text-muted small">Qty: <?= $i['quantity'] ?> • Wt: <?= $i['weight'] ?>kg</div>
                                                
                                                <?php if (!empty($i['selected_color']) || !empty($i['selected_size'])): ?>
                                                    <div class="specs-row">
                                                        <?php if (!empty($i['selected_color'])): ?>
                                                            <span class="spec-badge">
                                                                <span class="color-dot" style="background-color: <?= htmlspecialchars($i['selected_color']) ?>;"></span>
                                                                <?= htmlspecialchars($i['selected_color']) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($i['selected_size'])): ?>
                                                            <span class="spec-badge">Size: <?= htmlspecialchars($i['selected_size']) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="text-end fw-bold text-dark flex-shrink-0"><?= formatPrice($lineTotal) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="checkout-card">
                    <div class="card-header-custom">
                        <i class="bi bi-credit-card-2-front"></i> Payment
                    </div>
                    <div class="p-3 p-md-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select name="payment_method" class="form-select" id="payMethod" required>
                                        <option value="card">Visa / Mastercard</option>
                                        <option value="momo">Mobile Money (MTN/Telecel)</option>
                                        <option value="cod" id="optCod">Cash on Delivery</option>
                                    </select>
                                    <label for="payMethod">Payment Method</label>
                                </div>
                                <div id="codWarning" class="text-danger small mt-2 d-none fw-bold">
                                    <i class="bi bi-exclamation-triangle-fill"></i> Cash on Delivery is unavailable because your cart contains items shipped from overseas.
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating">
                                    <textarea name="notes" class="form-control" placeholder="Notes" id="orderNotes" style="height: 100px"></textarea>
                                    <label for="orderNotes">Order Notes (Optional)</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="col-lg-4">
                <div class="summary-sticky">
                    
                    <div class="amount-card">
                        <div class="amount-label">Total to Pay</div>
                        <div class="amount-value" id="ui-big-total"><?= formatPrice($total) ?></div>
                    </div>

                    <div class="checkout-card p-3 p-md-4 mb-4">
                        <label class="form-label fw-bold text-secondary small text-uppercase mb-2"><i class="bi bi-tag-fill me-1"></i> Have a Promo Code?</label>
                        <div class="promo-input-group">
                            <input type="text" id="promoInput" class="form-control promo-input" placeholder="ENTER CODE">
                            <button type="button" id="btnApplyPromo" class="btn btn-promo px-3" onclick="applyPromoCode()">Apply</button>
                        </div>
                        <div id="promo-msg"></div>
                        
                        <input type="hidden" name="promo_code" id="input_promo_code" value="">
                        <input type="hidden" name="discount_amount" id="input_discount_amount" value="0.00">
                    </div>

                    <div class="checkout-card p-3 p-md-4">
                        <h5 class="fw-bold mb-4">Order Summary</h5>
                        
                        <div class="d-flex justify-content-between mb-3 text-secondary">
                            <span>Subtotal</span>
                            <span class="fw-bold text-dark"><?= formatPrice($total) ?></span>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-3 text-secondary">
                            <span>Delivery</span>
                            <span class="fw-bold text-dark" id="ui-shipping-row">Calculating...</span>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-3 text-success d-none" id="ui-discount-row">
                            <span>Discount (<span id="ui-discount-code"></span>)</span>
                            <span class="fw-bold" id="ui-discount-val">-₵0.00</span>
                        </div>
                        
                        <hr class="border-light my-4">

                        <?php if ($isLoggedIn): ?>
                            <button type="submit" name="place_order" class="btn-pay" id="btn-submit">
                                <span>Confirm & Pay</span>
                                <i class="bi bi-arrow-right"></i>
                            </button>
                        <?php else: ?>
                            <a href="login.php?redirect=checkout" class="btn-pay" style="background-color: #64748b;">
                                Login to Checkout
                            </a>
                        <?php endif; ?>

                        <div class="mt-4 text-center small text-muted">
                            <i class="bi bi-lock-fill text-success"></i> Payments are SSL encrypted and secured.
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </form>
</div>

<script>
    const countryRates = <?= $countryRatesJson ?>;
    const originCountries = <?= $originCountriesJson ?>;
    const cartTotal = <?= $total ?>;
    let currentMethod = 'standard';
    
    let currentDiscountAmt = 0; 
    let currentShippingCost = 0;

    const currPrefix = <?= json_encode($jsCurrencyPrefix) ?>;
    const currSuffix = <?= json_encode($jsCurrencySuffix) ?>;
    const currRate = <?= $jsExchangeRate ?>;

    const dialCodes = {
        "Ghana": "+233 ", "Nigeria": "+234 ", "Cote d'Ivoire": "+225 ", "South Africa": "+27 ",
        "Kenya": "+254 ", "Togo": "+228 ", "United Kingdom": "+44 ", "United States": "+1 ",
        "Canada": "+1 ", "Germany": "+49 ", "China": "+86 ", "Spain": "+34 "
    };

    function formatCurrency(amount) {
        const converted = amount * currRate;
        return currPrefix + converted.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",") + currSuffix;
    }

    function applyPromoCode() {
        const codeInput = document.getElementById('promoInput');
        const code = codeInput.value.trim().toUpperCase();
        const msgDiv = document.getElementById('promo-msg');
        
        if (!code) {
            msgDiv.className = 'text-danger';
            msgDiv.innerText = 'Please enter a code.';
            msgDiv.style.display = 'block';
            return;
        }

        const btn = document.getElementById('btnApplyPromo');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
        btn.disabled = true;

        fetch(`../routes/apply-coupon.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code: code, cart_total: cartTotal })
        })
        .then(response => response.json())
        .then(data => {
            btn.innerHTML = 'Apply';
            btn.disabled = false;
            msgDiv.style.display = 'block';

            if (data.success) {
                currentDiscountAmt = parseFloat(data.discount_amount);
                
                msgDiv.className = 'text-success';
                msgDiv.innerHTML = '<i class="bi bi-check-circle-fill"></i> Promo applied successfully!';
                codeInput.disabled = true;
                btn.innerText = 'Applied';
                
                document.getElementById('input_promo_code').value = code;
                document.getElementById('input_discount_amount').value = currentDiscountAmt;

                document.getElementById('ui-discount-row').classList.remove('d-none');
                document.getElementById('ui-discount-code').innerText = code;
                document.getElementById('ui-discount-val').innerText = '-' + formatCurrency(currentDiscountAmt);

                recalculateTotal();
            } else {
                msgDiv.className = 'text-danger';
                msgDiv.innerHTML = '<i class="bi bi-x-circle-fill"></i> ' + data.message;
            }
        })
        .catch(err => {
            btn.innerHTML = 'Apply';
            btn.disabled = false;
            msgDiv.style.display = 'block';
            msgDiv.className = 'text-danger';
            msgDiv.innerText = 'Error connecting to server. Try again.';
        });
    }

    function handleCountryChange() {
        const country = document.getElementById('fCountry').value;
        const phoneInput = document.getElementById('fPhone');
        const dropCont = document.getElementById('region_dropdown_container');
        const textCont = document.getElementById('region_text_container');
        const dropInput = document.getElementById('fRegionSelect');
        const textInput = document.getElementById('fRegionInput');

        if(country === 'Ghana') {
            dropCont.classList.remove('d-none');
            textCont.classList.add('d-none');
            dropInput.setAttribute('name', 'shipping_region');
            dropInput.setAttribute('required', 'required');
            textInput.removeAttribute('name');
            textInput.removeAttribute('required');
        } else {
            dropCont.classList.add('d-none');
            textCont.classList.remove('d-none');
            textInput.setAttribute('name', 'shipping_region');
            textInput.setAttribute('required', 'required');
            dropInput.removeAttribute('name');
            dropInput.removeAttribute('required');
        }

        const newCode = dialCodes[country];
        if (newCode && phoneInput.value.length < 6) {
            phoneInput.value = newCode;
        }

        updateShippingUI();
        checkCODAvailability(country);
    }

    function checkCODAvailability(buyerCountry) {
        const codOption = document.getElementById('optCod');
        const payMethodSelect = document.getElementById('payMethod');
        const codWarning = document.getElementById('codWarning');

        if (!buyerCountry) return;

        const isOverseas = originCountries.some(oCountry => oCountry.toLowerCase() !== buyerCountry.toLowerCase());

        if (isOverseas) {
            codOption.disabled = true;
            if (payMethodSelect.value === 'cod') {
                payMethodSelect.value = 'card'; 
            }
            codWarning.classList.remove('d-none');
        } else {
            codOption.disabled = false;
            codWarning.classList.add('d-none');
        }
    }

    function updateShippingUI() {
        const country = document.getElementById('fCountry').value || 'Ghana';
        const rates = countryRates[country] !== undefined ? countryRates[country] : {standard: 15.00, express: 45.00};

        document.getElementById('lbl-standard-cost').innerText = formatCurrency(rates.standard);
        document.getElementById('lbl-express-cost').innerText = formatCurrency(rates.express);

        setShippingMethod(currentMethod);
    }

    function setShippingMethod(method, element = null) {
        currentMethod = method;
        const country = document.getElementById('fCountry').value || 'Ghana';
        const rates = countryRates[country] !== undefined ? countryRates[country] : {standard: 15.00, express: 45.00};

        let prettyName = '';

        if (method === 'standard') {
            currentShippingCost = rates.standard;
            prettyName = 'Standard Delivery';
        } else if (method === 'express') {
            currentShippingCost = rates.express; 
            prettyName = 'Express Delivery';
        } else if (method === 'pickup') {
            currentShippingCost = 0.00;
            prettyName = 'Pickup Station';
        }

        if (element) {
            document.querySelectorAll('.ship-card').forEach(c => c.classList.remove('active'));
            element.classList.add('active');
        } else {
            document.querySelectorAll('.ship-card').forEach(c => c.classList.remove('active'));
            const targetCard = document.querySelector(`.ship-card[data-method="${method}"]`);
            if (targetCard) targetCard.classList.add('active');
        }

        document.getElementById('input_method').value = method;
        document.getElementById('input_cost').value = currentShippingCost;

        const shippingRow = document.getElementById('ui-shipping-row');
        if (currentShippingCost === 0) {
            shippingRow.innerHTML = `<span class="text-success">${prettyName} (Free)</span>`;
        } else {
            shippingRow.innerHTML = `${prettyName} (+${formatCurrency(currentShippingCost)})`;
        }

        recalculateTotal();
    }

    function recalculateTotal() {
        let grandTotal = cartTotal + currentShippingCost - currentDiscountAmt;
        if (grandTotal < 0) grandTotal = 0;

        document.getElementById('ui-big-total').innerText = formatCurrency(grandTotal);
        
        const mobileTotal1 = document.getElementById('mobile-total-display');
        const mobileTotal2 = document.getElementById('mobile-dropdown-total');
        if (mobileTotal1) mobileTotal1.innerText = formatCurrency(grandTotal);
        if (mobileTotal2) mobileTotal2.innerText = formatCurrency(grandTotal);
    }

    document.addEventListener("DOMContentLoaded", function() {
        document.getElementById('fCountry').addEventListener('change', handleCountryChange);
        handleCountryChange();
    });
</script>

<?php require_once __DIR__ . "/partials/footer.php"; ?>
</body>
</html>