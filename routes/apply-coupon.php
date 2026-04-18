<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";

// 1. Tell the browser we are sending JSON data back
header('Content-Type: application/json');

// 2. Only allow logged-in users to use the API
if (!isset($_SESSION["user_id"])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to apply promo codes.']);
    exit;
}

// 3. Get the JSON payload
$data = json_decode(file_get_contents('php://input'), true);
$code = isset($data['code']) ? strtoupper(trim($data['code'])) : '';

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'No code provided.']);
    exit;
}

try {
    // 4. Fetch the coupon (Notice we removed 'AND vendor_id IS NULL' so it grabs ANY active code)
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        echo json_encode(['success' => false, 'message' => 'Invalid or inactive promo code.']);
        exit;
    }

    // 5. TIME & USAGE VALIDATION
    $now = time();
    // Check if it hasn't started yet (Based on your DB screenshot)
    if (!empty($coupon['starts_at']) && strtotime($coupon['starts_at']) > $now) {
        echo json_encode(['success' => false, 'message' => 'This promo code is not active yet.']);
        exit;
    }
    // Check Expiration Date
    if (!empty($coupon['expires_at']) && strtotime($coupon['expires_at']) < $now) {
        echo json_encode(['success' => false, 'message' => 'This promo code has expired.']);
        exit;
    }
    // Check Usage Limits
    if (!empty($coupon['usage_limit']) && $coupon['used_count'] >= $coupon['usage_limit']) {
        echo json_encode(['success' => false, 'message' => 'This promo code has reached its usage limit.']);
        exit;
    }

    // 6. FETCH CART ITEMS SECURELY FROM DATABASE
    // We cannot trust the JS frontend. We must calculate the eligible total ourselves.
    $cartStmt = $pdo->prepare("
        SELECT ci.quantity, 
               (CASE WHEN p.sale_price > 0 AND p.sale_price < p.price THEN p.sale_price ELSE p.price END) as final_price,
               p.vendor_id 
        FROM cart_items ci 
        JOIN carts c ON ci.cart_id = c.id 
        JOIN products p ON ci.product_id = p.id 
        WHERE c.user_id = ?
    ");
    $cartStmt->execute([$_SESSION["user_id"]]);
    $cartItems = $cartStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cartItems)) {
        echo json_encode(['success' => false, 'message' => 'Your cart is empty.']);
        exit;
    }

    // 7. CALCULATE ELIGIBLE SUBTOTAL
    $eligibleTotal = 0.00;
    $isVendorCoupon = !is_null($coupon['vendor_id']);

    foreach ($cartItems as $item) {
        // If it's a global coupon, add everything. 
        // If it's a vendor coupon, ONLY add items that belong to this specific vendor.
        if (!$isVendorCoupon || $item['vendor_id'] == $coupon['vendor_id']) {
            $eligibleTotal += ($item['quantity'] * $item['final_price']);
        }
    }

    // If they have items in their cart, but none from this specific vendor
    if ($eligibleTotal == 0.00) {
        echo json_encode(['success' => false, 'message' => 'This promo code does not apply to any items in your cart.']);
        exit;
    }

    // 8. CHECK MINIMUM SPEND AGAINST ELIGIBLE ITEMS ONLY
    if ($eligibleTotal < $coupon['min_order_amount']) {
        echo json_encode(['success' => false, 'message' => 'You need to spend at least ₵' . number_format($coupon['min_order_amount'], 2) . ' on eligible items to use this code.']);
        exit;
    }

    // 9. CALCULATE THE DISCOUNT
    $discountAmount = 0.00;

    if ($coupon['type'] === 'percentage') {
        $discountAmount = $eligibleTotal * ($coupon['value'] / 100);
        
        // Apply maximum discount cap
        if (!empty($coupon['max_discount_amount']) && $discountAmount > $coupon['max_discount_amount']) {
            $discountAmount = $coupon['max_discount_amount'];
        }
    } else {
        // Fixed amount discount
        $discountAmount = $coupon['value'];
    }

    // Failsafe: Don't let the discount be larger than the eligible items' cost
    if ($discountAmount > $eligibleTotal) {
        $discountAmount = $eligibleTotal;
    }

    // 10. RETURN SUCCESS
    echo json_encode([
        'success' => true,
        'discount_amount' => round($discountAmount, 2),
        'message' => 'Promo applied successfully!'
    ]);
    exit;

} catch (Exception $e) {
    error_log("Coupon Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A system error occurred. Please try again.']);
    exit;
}