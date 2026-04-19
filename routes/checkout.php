<?php
// ✅ FIXED: Added "app/" to the paths so it successfully finds your database!
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/helpers/mailer.php";
require_once __DIR__ . "/../app/helpers/currency.php"; 

// ================================================================
// ✅ SMART DYNAMIC URL DETECTOR (With Central Config Fallback)
// ================================================================
$isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$protocol = $isSecure ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$basePath = (strpos($_SERVER['REQUEST_URI'], '/shopcorrect/') !== false) ? '/shopcorrect' : '';

// If a payment gateway pings the raw IP address, fallback to the SHOP_URL defined in db.php!
if (filter_var($host, FILTER_VALIDATE_IP) && defined('SHOP_URL')) {
    $dynamicBaseUrl = rtrim(SHOP_URL, '/') . $basePath;
} else {
    $dynamicBaseUrl = $protocol . $host . $basePath;
}

/* |--------------------------------------------------------------------------
   | 1. AUTH CHECK
   |-------------------------------------------------------------------------- */
if (!isset($_SESSION["user_id"])) {
    $_SESSION['redirect_url'] = '../public/checkout.php';
    header("Location: ../public/login.php");
    exit;
}

$userId = $_SESSION["user_id"];

/* |--------------------------------------------------------------------------
   | 2. HANDLE REQUESTS
   |-------------------------------------------------------------------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* ======================================================================
       LOGIC A: EDIT ORDER (Restore Cart & Stock for unpaid orders)
       ====================================================================== */
    if (isset($_POST['edit_order'])) {
        $orderId = (int) $_POST['order_id'];

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT id, payment_method FROM orders 
                WHERE id = ? AND user_id = ? 
                AND status = 'processing' 
                AND payment_status IN ('pending', 'unpaid')
            ");
            $stmt->execute([$orderId, $userId]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new Exception("Cannot edit this order. It may have already been processed.");
            }

            $itemsStmt = $pdo->prepare("SELECT product_id, quantity, selected_color, selected_size FROM order_items WHERE order_id = ?");
            $itemsStmt->execute([$orderId]);
            $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            $cartStmt = $pdo->prepare("SELECT id FROM carts WHERE user_id = ?");
            $cartStmt->execute([$userId]);
            $cart = $cartStmt->fetch();

            if (!$cart) {
                $pdo->prepare("INSERT INTO carts (user_id, created_at) VALUES (?, NOW())")->execute([$userId]);
                $cartId = $pdo->lastInsertId();
            } else {
                $cartId = $cart['id'];
            }
            
            $toCart = $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, quantity, selected_color, selected_size) VALUES (?, ?, ?, ?, ?)");
            
            $restoreVariantStock = $pdo->prepare("UPDATE product_variants SET stock = stock + ? WHERE product_id = ? AND COALESCE(color, '') = COALESCE(?, '') AND COALESCE(size, '') = COALESCE(?, '')");
            $restoreBaseStock = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");

            foreach ($orderItems as $item) {
                $toCart->execute([
                    $cartId, 
                    $item['product_id'], 
                    $item['quantity'],
                    $item['selected_color'], 
                    $item['selected_size']
                ]);

                if ($order['payment_method'] === 'cod') {
                    if (!empty($item['selected_color']) || !empty($item['selected_size'])) {
                        $restoreVariantStock->execute([
                            $item['quantity'], 
                            $item['product_id'], 
                            $item['selected_color'] ?? '', 
                            $item['selected_size'] ?? ''
                        ]);
                    } else {
                        $restoreBaseStock->execute([$item['quantity'], $item['product_id']]);
                    }
                }
            }

            $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$orderId]);
            $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$orderId]);

            $pdo->commit();
            header("Location: ../public/checkout.php");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['error'] = "Error: " . $e->getMessage();
            header("Location: ../public/payment.php?order_id=" . $orderId);
            exit;
        }
    }

    /* ======================================================================
       LOGIC B: PLACE NEW ORDER
       ====================================================================== */
    if (isset($_POST["place_order"])) {

        try {
            $pdo->beginTransaction();

            // --- 1. Validate Input ---
            $shipName    = trim($_POST['shipping_name'] ?? '');
            $shipPhone   = trim($_POST['shipping_phone'] ?? '');
            $shipAddress = trim($_POST['shipping_address'] ?? '');
            $shipCity    = trim($_POST['shipping_city'] ?? '');
            $shipRegion  = trim($_POST['shipping_region'] ?? '');
            $shipCountry = trim($_POST['shipping_country'] ?? '');
            
            $notes          = trim($_POST['notes'] ?? '');
            $paymentMethod  = $_POST['payment_method'] ?? 'cod';
            $shippingMethod = $_POST['shipping_method'] ?? 'standard';
            $promoCode      = trim($_POST['promo_code'] ?? '');

            if (empty($shipName) || empty($shipPhone) || empty($shipAddress) || empty($shipCountry)) {
                throw new Exception("Please fill in all required shipping details including country.");
            }

            // --- 2. Fetch Cart Items & Check Logistics Origin ---
            $stmt = $pdo->prepare("
                SELECT 
                    ci.product_id, ci.quantity, ci.selected_color, ci.selected_size,
                    p.vendor_id, p.name, p.price as base_price, p.sale_price, p.discount_percent, p.stock as base_stock, p.image, p.weight,
                    p.fulfillment_type, p.warehouse_country,
                    vu.country AS vendor_country,
                    pv.price AS variant_price, pv.stock AS variant_stock, pv.id AS variant_id
                FROM cart_items ci
                JOIN carts c ON ci.cart_id = c.id
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

            if (!$items) throw new Exception("Your cart is empty.");

            // --- Fetch Live Commission Rate ---
            $commStmt = $pdo->query("SELECT commission_percent FROM settings LIMIT 1");
            $setting = $commStmt->fetch(PDO::FETCH_ASSOC);
            $globalCommissionRate = isset($setting['commission_percent']) ? (float)$setting['commission_percent'] : 10.0;

            $productTotal = 0;
            $totalWeight = 0;
            $finalItems = []; 
            $hasOverseasItems = false; 

            foreach ($items as $item) {
                $availableStock = !empty($item['variant_id']) ? (int)$item['variant_stock'] : (int)$item['base_stock'];
                
                if ($availableStock < $item['quantity']) {
                    throw new Exception("Stock Error: '{$item['name']}' only has {$availableStock} units available for the selected option.");
                }
                
                $originCountry = trim($item['vendor_country'] ?? '');
                if (($item['fulfillment_type'] ?? 'vendor') === 'shopcorrect' && !empty($item['warehouse_country'])) {
                    $originCountry = trim($item['warehouse_country']); 
                }

                if (strcasecmp($originCountry, trim($shipCountry)) !== 0) {
                    $hasOverseasItems = true;
                }
                
                $globalBase  = (float) $item['base_price'];
                $globalSale  = (float) $item['sale_price'];
                $isGlobalSale = ((int)$item['discount_percent'] > 0 && $globalSale > 0);
                $globalFinal = $isGlobalSale ? $globalSale : $globalBase;
                $finalPrice = !empty($item['variant_price']) ? (float)$item['variant_price'] : $globalFinal;
                
                $itemSubtotal = $finalPrice * $item['quantity'];

                $activeCommissionRate = $globalCommissionRate; 
                $commission   = $itemSubtotal * ($activeCommissionRate / 100); 
                $vendorCut    = $itemSubtotal - $commission;

                $productTotal += $itemSubtotal;
                $totalWeight += ($item['weight'] * $item['quantity']);

                $finalItems[] = [
                    'pid' => $item['product_id'], 
                    'vid' => $item['vendor_id'], 
                    'qty' => $item['quantity'], 
                    'price' => $finalPrice,
                    'commission_rate' => $activeCommissionRate, 
                    'commission' => $commission,
                    'vendor_earning' => $vendorCut,
                    'color' => $item['selected_color'],
                    'size'  => $item['selected_size'],
                    'name'  => $item['name'],
                    'image' => $item['image'],
                    'subtotal' => $itemSubtotal 
                ];
            }

            // --- STRICT COD RULE ---
            if ($paymentMethod === 'cod' && $hasOverseasItems) {
                throw new Exception("Cash on Delivery is unavailable because your cart contains items shipped from overseas. Please pay securely with your card.");
            }

            // --- 3. Calculate Shipping ---
            $shippingCost = 0.00;
            
            if ($shippingMethod !== 'pickup') {
                $rateStmt = $pdo->prepare("
                    SELECT sr.price, sr.express_price
                    FROM shipping_countries sc
                    JOIN shipping_rates sr ON sc.zone_id = sr.zone_id
                    WHERE sc.country_name = ? 
                    AND ? >= sr.min_weight AND ? <= sr.max_weight
                    LIMIT 1
                ");
                $rateStmt->execute([$shipCountry, $totalWeight, $totalWeight]);
                $rateResult = $rateStmt->fetch(PDO::FETCH_ASSOC);

                if ($rateResult) {
                    $shippingCost = ($shippingMethod === 'express') ? (float) $rateResult['express_price'] : (float) $rateResult['price'];
                } else {
                    throw new Exception("Shipping is not currently available for this weight or destination.");
                }
            }

            // --- 4. Secure Promo Code Verification ---
            $discountAmount = 0.00;
            $couponId = null;
            $couponVendorId = null; 
            $appliedPromoCode = null;

            if (!empty($promoCode)) {
                $couponStmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND status = 'active' LIMIT 1");
                $couponStmt->execute([$promoCode]);
                $coupon = $couponStmt->fetch(PDO::FETCH_ASSOC);

                if ($coupon) {
                    $isValid = true;
                    $todayDate = date('Y-m-d');
                    
                    if (!empty($coupon['starts_at']) && date('Y-m-d', strtotime($coupon['starts_at'])) > $todayDate) $isValid = false; 
                    if (!empty($coupon['expires_at']) && date('Y-m-d', strtotime($coupon['expires_at'])) < $todayDate) $isValid = false; 
                    if (!empty($coupon['usage_limit']) && $coupon['used_count'] >= $coupon['usage_limit']) $isValid = false; 

                    if ($isValid) {
                        $eligibleTotal = 0.00;
                        $isVendorCoupon = !is_null($coupon['vendor_id']);
                        $couponVendorId = $coupon['vendor_id'];

                        foreach ($finalItems as $fi) {
                            if (!$isVendorCoupon || $fi['vid'] == $coupon['vendor_id']) {
                                $eligibleTotal += $fi['subtotal'];
                            }
                        }

                        if ($eligibleTotal < $coupon['min_order_amount']) {
                            throw new Exception("The promo code applied requires a minimum eligible spend of ₵" . number_format($coupon['min_order_amount'], 2) . ".");
                        }

                        if ($coupon['type'] === 'percentage') {
                            $calcDiscount = $eligibleTotal * ($coupon['value'] / 100);
                            $discountAmount = (!empty($coupon['max_discount_amount']) && $calcDiscount > $coupon['max_discount_amount']) ? $coupon['max_discount_amount'] : $calcDiscount;
                        } else {
                            $discountAmount = $coupon['value'];
                        }

                        if ($discountAmount > $eligibleTotal) $discountAmount = $eligibleTotal;

                        $couponId = $coupon['id'];
                        $appliedPromoCode = $coupon['code'];
                    } else {
                        throw new Exception("The promo code you entered has expired or reached its usage limit.");
                    }
                } else {
                    throw new Exception("The promo code you entered is invalid.");
                }
            }

            // --- 5. Final Grand Total ---
            $grandTotal = ($productTotal + $shippingCost) - $discountAmount;
            if ($grandTotal < 0) $grandTotal = 0;

            // --- 6. Create Order Record ---
            $payStatus = ($paymentMethod === 'cod') ? 'pending' : 'unpaid';
            
            $sql = "INSERT INTO orders (user_id, shipping_name, shipping_phone, shipping_address, shipping_city, shipping_region, shipping_country, notes, total_amount, shipping_cost, promo_code, discount_amount, shipping_method, payment_method, payment_status, status, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'processing', NOW(), NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $userId, $shipName, $shipPhone, $shipAddress, $shipCity, $shipRegion, $shipCountry,
                $notes, $grandTotal, $shippingCost, $appliedPromoCode, $discountAmount, $shippingMethod, $paymentMethod, $payStatus
            ]);
            
            $orderId = $pdo->lastInsertId();
            $orderNumber = str_pad($orderId, 6, '0', STR_PAD_LEFT);

            // --- 7. Insert Order Items & Deduct Stock ---
            $insItem = $pdo->prepare("
                INSERT INTO order_items 
                (order_id, product_id, vendor_id, quantity, price, promo_code, discount_amount, commission_rate, commission_fee, vendor_earning, selected_color, selected_size) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $updVariantStock = $pdo->prepare("UPDATE product_variants SET stock = stock - ? WHERE product_id = ? AND COALESCE(color, '') = COALESCE(?, '') AND COALESCE(size, '') = COALESCE(?, '')");
            $updBaseStock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");

            foreach ($finalItems as $fi) {
                $itemDiscount = 0.00;
                $itemPromoCode = null;

                if ($couponId && $discountAmount > 0) {
                    if (!$couponVendorId || $fi['vid'] == $couponVendorId) {
                        $proportion = $fi['subtotal'] / $eligibleTotal;
                        $itemDiscount = $discountAmount * $proportion;
                        $itemPromoCode = $appliedPromoCode;

                        $discountedSubtotal = $fi['subtotal'] - $itemDiscount;
                        $fi['commission'] = $discountedSubtotal * ($fi['commission_rate'] / 100);
                        $fi['vendor_earning'] = $discountedSubtotal - $fi['commission'];
                    }
                }

                $insItem->execute([
                    $orderId, $fi['pid'], $fi['vid'], $fi['qty'], $fi['price'], 
                    $itemPromoCode, $itemDiscount, $fi['commission_rate'], $fi['commission'], 
                    $fi['vendor_earning'], $fi['color'], $fi['size']
                ]);

                if ($paymentMethod === 'cod') {
                    if (!empty($fi['color']) || !empty($fi['size'])) {
                        $updVariantStock->execute([$fi['qty'], $fi['pid'], $fi['color'] ?? '', $fi['size'] ?? '']);
                    } else {
                        $updBaseStock->execute([$fi['qty'], $fi['pid']]);
                    }
                }
            }

            // --- 8. Update Promo Code Usage ---
            if ($couponId) {
                $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?")->execute([$couponId]);
            }

            // --- 9. Clear User's Cart ---
            $pdo->prepare("DELETE FROM cart_items WHERE cart_id = (SELECT id FROM carts WHERE user_id = ? LIMIT 1)")->execute([$userId]);

            $pdo->commit();

            // --- 10. Handle Emails (Wrapped in Try-Catch to prevent crashes if mail server fails!) ---
            if ($paymentMethod === 'cod') {
                try {
                    // --- A. EMAIL THE BUYER ---
                    $uStmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
                    $uStmt->execute([$userId]);
                    $uData = $uStmt->fetch();

                    $itemsHtml = '';
                    foreach ($finalItems as $fi) {
                        $safeImageName = rawurlencode($fi['image']);
                        $imgUrl = !empty($fi['image']) ? rtrim($dynamicBaseUrl, '/') . '/public/uploads/products/' . $safeImageName : 'https://placehold.co/50x50?text=Item';
                        
                        $specText = '';
                        if(!empty($fi['color'])) $specText .= "Color: {$fi['color']} ";
                        if(!empty($fi['size'])) $specText .= "Size: {$fi['size']}";
                        $specHtml = $specText ? "<div style='color: #64748B; font-size: 12px; margin-top: 2px;'>$specText</div>" : "";

                        $itemsHtml .= "
                        <tr style='border-bottom: 1px solid #E2E8F0;'>
                            <td style='padding: 12px 0; width: 60px;'>
                                <img src='$imgUrl' style='width: 50px; height: 50px; border-radius: 6px; object-fit: cover; border: 1px solid #eee;'>
                            </td>
                            <td style='padding: 12px 0;'>
                                <div style='font-weight: 600; color: #1E293B; font-size: 14px;'>{$fi['name']}</div>
                                <div style='color: #64748B; font-size: 12px;'>Qty: {$fi['qty']}</div>
                                $specHtml
                            </td>
                            <td style='padding: 12px 0; text-align: right; font-weight: 600; color: #1E293B;'>
                                " . formatPrice($fi['price']) . "
                            </td>
                        </tr>";
                    }

                    $formattedTotal = formatPrice($grandTotal);
                    
                    $discountHtml = '';
                    if ($discountAmount > 0) {
                        $discountHtml = "
                        <tr>
                            <td style='color: #10B981; font-size: 14px;'>🎉 Discount Applied ($appliedPromoCode)</td>
                            <td style='text-align: right; font-weight: 700; color: #10B981; font-size: 14px;'>-" . formatPrice($discountAmount) . "</td>
                        </tr>";
                    }

                    // ✅ THE ULTIMATE QR CODE FIX: Added the Secure Token and forced URL structure
                    $timeStmt = $pdo->prepare("SELECT created_at FROM orders WHERE id = ?");
                    $timeStmt->execute([$orderId]);
                    $createdAt = $timeStmt->fetchColumn();

                    $secureToken = hash('sha256', $orderId . $createdAt . 'ShopCorrectSecureInvoice2026');

                    $invoiceUrl = rtrim($dynamicBaseUrl, '/') . "/public/invoice.php?id=" . $orderId . "&token=" . $secureToken;
                    $invoiceQrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&margin=0&data=" . urlencode($invoiceUrl);

                    $qrHtml = "
                    <div style='text-align: center; margin: 20px 0; padding: 15px; background: #ffffff; border: 2px dashed #e2e8f0; border-radius: 12px;'>
                        <p style='font-size: 14px; font-weight: bold; color: #0B2447; margin: 0 0 10px 0;'>Your Pickup / Delivery QR Code</p>
                        <img src='$invoiceQrApiUrl' alt='Order QR Code' width='120' height='120' style='display: block; margin: 0 auto;'>
                        <p style='font-size: 12px; color: #64748b; margin: 10px 0 0 0;'>Show this code to the agent or driver to view your invoice.</p>
                    </div>";

                    $message = "
                    <div style='text-align: center; margin-bottom: 30px;'>
                        <img src='https://cdn-icons-png.flaticon.com/512/10543/10543160.png' style='width: 64px; height: 64px; margin-bottom: 15px;'>
                        <h2 style='color: #0B2447; margin: 0; font-size: 24px; font-weight: 800;'>Order Placed!</h2>
                        <p style='color: #64748B; margin-top: 8px;'>Thank you for your order, <strong>{$uData['name']}</strong>!</p>
                    </div>
                    $qrHtml
                    <div style='background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 20px;'>
                        <div style='font-weight: 700; color: #475569; font-size: 13px; text-transform: uppercase; margin-bottom: 15px;'>
                            Order Recap #$orderNumber
                        </div>
                        <table width='100%' style='margin-bottom: 15px;'>
                            <tr>
                                <td style='color: #64748B; font-size: 14px;'>📦 Method</td>
                                <td style='text-align: right; font-weight: 700; color: #0B2447; font-size: 14px;'>Cash on Delivery</td>
                            </tr>
                            $discountHtml
                            <tr>
                                <td style='color: #64748B; font-size: 14px; padding-top: 5px;'>💵 Amount Due</td>
                                <td style='text-align: right; font-weight: 700; color: #0B2447; font-size: 16px; padding-top: 5px;'>$formattedTotal</td>
                            </tr>
                        </table>
                        <hr style='border: 0; border-top: 1px dashed #CBD5E1; margin: 15px 0;'>
                        <table width='100%' cellspacing='0'>$itemsHtml</table>
                        <div style='margin-top: 20px; text-align: right;'>
                            <span style='color: #64748B; margin-right: 10px;'>Total to Pay:</span>
                            <span style='color: #0B2447; font-size: 20px; font-weight: 800;'>$formattedTotal</span>
                        </div>
                    </div>";

                    $button = ['text' => 'View Your Order', 'url' => rtrim($dynamicBaseUrl, '/') . "/public/success.php?order_id=$orderId"];
                    if (function_exists('sendMail')) {
                        sendMail($uData['email'], "Order Placed - ShopCorrect #$orderNumber", "", $message, $button);
                    }

                    // --- B. EMAIL EACH VENDOR INDIVIDUALLY ---
                    $vendorOrders = [];
                    foreach ($finalItems as $fi) {
                        $vendorOrders[$fi['vid']][] = $fi;
                    }

                    foreach ($vendorOrders as $vId => $vItems) {
                        $vStmt = $pdo->prepare("
                            SELECT u.email, v.shop_name 
                            FROM vendors v
                            JOIN users u ON v.user_id = u.id
                            WHERE v.id = ?
                        ");
                        $vStmt->execute([$vId]);
                        $vData = $vStmt->fetch(PDO::FETCH_ASSOC);

                        if ($vData && !empty($vData['email'])) {
                            $vItemsHtml = '';
                            $vTotalEarning = 0;

                            foreach ($vItems as $vi) {
                                $specText = '';
                                if(!empty($vi['color'])) $specText .= "Color: {$vi['color']} ";
                                if(!empty($vi['size'])) $specText .= "Size: {$vi['size']}";
                                $specHtml = $specText ? "<div style='color: #64748B; font-size: 11px; margin-top: 2px;'>$specText</div>" : "";

                                $vItemsHtml .= "
                                <tr style='border-bottom: 1px dashed #E2E8F0;'>
                                    <td style='padding: 10px 0;'>
                                        <div style='font-weight: 600; color: #1E293B; font-size: 14px;'>{$vi['name']}</div>
                                        <div style='color: #64748B; font-size: 12px;'>Qty: {$vi['qty']}</div>
                                        $specHtml
                                    </td>
                                    <td style='padding: 10px 0; text-align: right; font-weight: 600; color: #1E293B;'>
                                        " . formatPrice($vi['vendor_earning']) . "
                                    </td>
                                </tr>";
                                $vTotalEarning += $vi['vendor_earning'];
                            }

                            $vFormattedTotal = formatPrice($vTotalEarning);

                            $vMessage = "
                            <div style='text-align: center; margin-bottom: 30px;'>
                                <div style='font-size: 40px; margin-bottom: 10px;'>🎉</div>
                                <h2 style='color: #0B2447; margin: 0; font-size: 22px; font-weight: 800;'>New Order Received!</h2>
                                <p style='color: #64748B; margin-top: 8px;'>Hello <strong>{$vData['shop_name']}</strong>, you have a new Cash on Delivery order to fulfill.</p>
                            </div>
                            <div style='background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 20px;'>
                                <div style='font-weight: 700; color: #475569; font-size: 13px; text-transform: uppercase; margin-bottom: 15px;'>
                                    Order #$orderNumber
                                </div>
                                <table width='100%' cellspacing='0'>
                                    $vItemsHtml
                                </table>
                                <div style='margin-top: 15px; padding-top: 15px; border-top: 2px solid #E2E8F0; text-align: right;'>
                                    <span style='color: #64748B; margin-right: 10px; font-size: 13px;'>Your Expected Payout:</span>
                                    <span style='color: #10B981; font-size: 18px; font-weight: 800;'>$vFormattedTotal</span>
                                </div>
                            </div>";

                            $vButton = ['text' => 'Process Order in Dashboard', 'url' => rtrim($dynamicBaseUrl, '/') . "/public/vendor/orders.php"];
                            if (function_exists('sendMail')) {
                                sendMail($vData['email'], "New Order #$orderNumber - ShopCorrect", "", $vMessage, $vButton);
                            }
                        }
                    }
                } catch (Exception $mailException) {
                    // Ignore email failure so the customer still gets directed to the success page!
                }

                $_SESSION['success'] = "Order #{$orderNumber} placed successfully!";
                header("Location: ../public/success.php?order_id=" . $orderId);
            } else {
                header("Location: ../public/payment.php?order_id=" . $orderId);
            }
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            // SCREAM THE ERROR TO THE SCREEN SO WE CAN SEE IT
            die("<div style='padding: 50px; background: #fff; color: red; font-size: 20px; font-family: sans-serif;'>
                    <strong>CHECKOUT CRASHED:</strong><br><br>" . $e->getMessage() . 
                "</div>");
        }
    }
}

header("Location: ../public/index.php");
exit;
?>