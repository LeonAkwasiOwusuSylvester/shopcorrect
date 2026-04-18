<?php
// Path: shopcorrect/routes/verify_transaction.php

require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/../app/helpers/mailer.php";

$paystackSecretKey = getenv('PAYSTACK_SECRET_KEY');

// ✅ SMART DYNAMIC URL DETECTOR (With Central Config Fallback)
// ================================================================
$isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$protocol = $isSecure ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$basePath = (strpos($_SERVER['REQUEST_URI'], '/shopcorrect/') !== false) ? '/shopcorrect' : '';

// If Paystack's server pings this via IP, fallback to the SHOP_URL defined in db.php!
if (filter_var($host, FILTER_VALIDATE_IP) && defined('SHOP_URL')) {
    $baseUrl = rtrim(SHOP_URL, '/') . $basePath;
} else {
    $baseUrl = $protocol . $host . $basePath;
}

if (!isset($_GET['reference'])) {
    die("No reference supplied");
}

$reference = $_GET['reference'];

// 1. Verify with Paystack API
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "accept: application/json",
        "authorization: Bearer " . $paystackSecretKey,
        "cache-control: no-cache"
    ],
));

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) { die("cURL Error: " . $err); }

$result = json_decode($response);

// 2. Process Result
if ($result->status && $result->data->status === 'success') {
    
    $orderId = $result->data->metadata->order_id;

    try {
        $pdo->beginTransaction();

        // --- FETCH CUSTOMER & ORDER DATA ---
        $stmtUser = $pdo->prepare("SELECT u.email, u.name, o.order_number, o.shipping_method, o.total_amount, o.shipping_cost, o.discount_amount, o.created_at FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
        $stmtUser->execute([$orderId]);
        $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

        $userEmail      = $userData['email'];
        $customerName   = $userData['name'];
        $orderCreatedAt = $userData['created_at'];
        $orderNumber    = !empty($userData['order_number']) ? $userData['order_number'] : 'SC-' . str_pad($orderId, 6, '0', STR_PAD_LEFT);
        $shippingMethod = !empty($userData['shipping_method']) ? htmlspecialchars(ucwords(str_replace('_', ' ', $userData['shipping_method']))) : 'Standard Delivery';
        
        $orderGrandTotal = (float)$userData['total_amount'];
        $orderShipping   = (float)$userData['shipping_cost'];
        $orderDiscount   = (float)$userData['discount_amount'];

        // --- FETCH ITEMS FOR BUYER EMAIL ---
        $itemsStmt = $pdo->prepare("
            SELECT oi.price, oi.quantity, oi.selected_size, oi.selected_color, p.name, p.image 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = ?
        ");
        $itemsStmt->execute([$orderId]);
        $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        $productSubtotal = 0;
        
        // Fully Responsive Email Container
        $buyerItemsHtml = "<div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; margin-top: 20px; max-width: 100%; box-sizing: border-box;'>";
        $buyerItemsHtml .= "<h3 style='margin-top: 0; color: #0B2447; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; font-size: 16px;'>Order Recap (#$orderNumber)</h3>";
        
        // Shipping Method Display
        $buyerItemsHtml .= "<div style='font-size: 14px; color: #475569; margin-bottom: 15px;'><strong>Shipping Method:</strong> <span style='color: #0B2447;'>$shippingMethod</span></div>";
        
        $buyerItemsHtml .= "<table style='width: 100%; border-collapse: collapse; min-width: 100%; table-layout: auto;'>";

        foreach ($orderItems as $item) {
            $lineTotal = $item['price'] * $item['quantity'];
            $productSubtotal += $lineTotal;
            
            // Image handling
            $imgName = $item['image'] ?? '';
            $imgUrl = !empty($imgName) ? $baseUrl . '/public/uploads/products/' . htmlspecialchars($imgName) : 'https://placehold.co/70x70?text=Item';

            // Variations handling
            $variations = [];
            if (!empty($item['selected_color'])) $variations[] = htmlspecialchars($item['selected_color']);
            if (!empty($item['selected_size'])) $variations[] = htmlspecialchars($item['selected_size']);
            $varText = implode(" | ", $variations);
            $varHtml = $varText ? "<div style='font-size: 12px; color: #64748B; margin-bottom: 4px;'>$varText</div>" : "";

            $buyerItemsHtml .= "
            <tr>
                <td style='padding: 10px 5px 10px 0; border-bottom: 1px solid #e2e8f0; vertical-align: top; width: 55px;'>
                    <img src='$imgUrl' width='50' height='50' style='border-radius:6px; object-fit:cover; display:block; background-color: #f1f5f9;'>
                </td>
                <td style='padding: 10px 5px; border-bottom: 1px solid #e2e8f0; vertical-align: top;'>
                    <div style='font-size: 14px; font-weight: bold; color: #0B2447; margin-bottom: 4px; word-wrap: break-word; overflow-wrap: break-word;'>{$item['name']}</div>
                    $varHtml
                    <div style='font-size: 12px; color: #64748B;'>Qty: {$item['quantity']} &times; ₵" . number_format($item['price'], 2) . "</div>
                </td>
                <td style='padding: 10px 0 10px 5px; border-bottom: 1px solid #e2e8f0; text-align: right; vertical-align: top; font-weight: bold; color: #0B2447; width: 75px;'>
                    ₵" . number_format($lineTotal, 2) . "
                </td>
            </tr>";
        }
        $buyerItemsHtml .= "</table>";
        
        // Add Subtotal, Shipping, and Discount to email
        $buyerItemsHtml .= "<div style='margin-top: 15px; font-size: 14px; color: #475569;'>";
        $buyerItemsHtml .= "<div style='display: flex; justify-content: space-between; margin-bottom: 5px;'><span>Subtotal:</span> <span>₵" . number_format($productSubtotal, 2) . "</span></div>";
        $buyerItemsHtml .= "<div style='display: flex; justify-content: space-between; margin-bottom: 5px;'><span>Shipping:</span> <span>+₵" . number_format($orderShipping, 2) . "</span></div>";
        if ($orderDiscount > 0) {
            $buyerItemsHtml .= "<div style='display: flex; justify-content: space-between; margin-bottom: 5px; color: #ef4444;'><span>Discount:</span> <span>-₵" . number_format($orderDiscount, 2) . "</span></div>";
        }
        $buyerItemsHtml .= "</div>";
        
        $buyerItemsHtml .= "<div style='text-align: right; margin-top: 10px; padding-top: 10px; border-top: 2px dashed #e2e8f0; font-size: 18px; color: #0B2447;'>Total Paid: <strong>₵" . number_format($orderGrandTotal, 2) . "</strong></div>";
        $buyerItemsHtml .= "</div>";

        // A. Update Order Status
        $stmt = $pdo->prepare("UPDATE orders SET status = 'processing', payment_status = 'paid', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$orderId]);

        // B. DEDUCT STOCK (Updated for Variations)
        $updateVariantStock = $pdo->prepare("UPDATE product_variants SET stock = stock - ? WHERE product_id = ? AND COALESCE(color, '') = COALESCE(?, '') AND COALESCE(size, '') = COALESCE(?, '')");
        $updateBaseStock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        
        $itemsFetch = $pdo->prepare("SELECT product_id, quantity, selected_color, selected_size FROM order_items WHERE order_id = ?");
        $itemsFetch->execute([$orderId]);
        $stockItems = $itemsFetch->fetchAll(PDO::FETCH_ASSOC);

        foreach ($stockItems as $item) {
            if (!empty($item['selected_color']) || !empty($item['selected_size'])) {
                // Deduct from variant table
                $updateVariantStock->execute([
                    $item['quantity'], 
                    $item['product_id'], 
                    $item['selected_color'] ?? '', 
                    $item['selected_size'] ?? ''
                ]);
            } else {
                // Deduct from base table
                $updateBaseStock->execute([$item['quantity'], $item['product_id']]);
            }
        }

        $pdo->commit();

        // --- 1. SEND CUSTOMER SUCCESS MAIL (WITH SECURE QR CODE) ---
        $subject  = "Payment Confirmed - $orderNumber";
        $title    = "Payment Successful";
        
        // ✅ THE FIX: Apply the Secure Token and the new $baseUrl!
        $secureToken = hash('sha256', $orderId . $orderCreatedAt . 'ShopCorrectSecureInvoice2026');
        $invoiceUrl = $baseUrl . "/public/invoice.php?id=" . $orderId . "&token=" . $secureToken;
        $invoiceQrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&margin=0&data=" . urlencode($invoiceUrl);
        
        $qrHtml = "
        <div style='text-align: center; margin: 25px 0; padding: 15px; background: #ffffff; border: 2px dashed #e2e8f0; border-radius: 12px;'>
            <p style='font-size: 14px; font-weight: bold; color: #0B2447; margin: 0 0 10px 0;'>Your Order QR Code</p>
            <img src='$invoiceQrApiUrl' alt='Order QR Code' width='120' height='120' style='display: block; margin: 0 auto;'>
            <p style='font-size: 12px; color: #64748b; margin: 10px 0 0 0;'>Show this code at the pickup station or scan to view your live invoice.</p>
        </div>";

        $message  = "Hello $customerName,<br><br>Thank you for your order! Your payment was successful and we are preparing your items for delivery.<br>" . $qrHtml . $buyerItemsHtml;
        $button = ['text' => 'View Order Details', 'url' => $baseUrl . "/public/success.php?order_id=$orderId"];
        
        try {
            if (function_exists('sendMail')) {
                sendMail($userEmail, $subject, $title, $message, $button, $orderNumber);
            }
        } catch (Exception $e) {}

        // --- 2. SEND VENDOR NOTIFICATION MAILS ---
        $vStmt = $pdo->prepare("
            SELECT DISTINCT v.id as vendor_id, u.email AS vendor_email, v.shop_name, u.name as vendor_owner
            FROM order_items oi
            JOIN vendors v ON oi.vendor_id = v.id
            JOIN users u ON v.user_id = u.id
            WHERE oi.order_id = ?
        ");
        $vStmt->execute([$orderId]);
        $orderVendors = $vStmt->fetchAll(PDO::FETCH_ASSOC);

        $vItemsStmt = $pdo->prepare("
            SELECT oi.price, oi.quantity, oi.selected_size, oi.selected_color, oi.vendor_earning, p.name, p.image 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = ? AND oi.vendor_id = ?
        ");

        foreach ($orderVendors as $vData) {
            $vItemsStmt->execute([$orderId, $vData['vendor_id']]);
            $vendorItems = $vItemsStmt->fetchAll(PDO::FETCH_ASSOC);

            $vendorTotal = 0;
            
            $vendorItemsHtml = "<div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; margin-top: 20px; max-width: 100%; box-sizing: border-box;'>";
            $vendorItemsHtml .= "<div style='font-size: 14px; color: #475569; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0;'><strong>Fulfillment Method:</strong> <span style='color: #0B2447;'>$shippingMethod</span></div>";
            $vendorItemsHtml .= "<table style='width: 100%; border-collapse: collapse; min-width: 100%; table-layout: auto;'>";
            
            foreach ($vendorItems as $vItem) {
                // Use vendor_earning instead of full price for vendor email
                $vLineEarning = $vItem['vendor_earning'];
                $vendorTotal += $vLineEarning;

                $vImgName = $vItem['image'] ?? '';
                $vImgUrl = !empty($vImgName) ? $baseUrl . '/public/uploads/products/' . htmlspecialchars($vImgName) : 'https://placehold.co/70x70?text=Item';

                $vVars = [];
                if (!empty($vItem['selected_color'])) $vVars[] = htmlspecialchars($vItem['selected_color']);
                if (!empty($vItem['selected_size'])) $vVars[] = htmlspecialchars($vItem['selected_size']);
                $vVarText = implode(" | ", $vVars);
                $vVarHtml = $vVarText ? "<div style='font-size: 12px; color: #64748B; margin-bottom: 4px;'>$vVarText</div>" : "";

                $vendorItemsHtml .= "
                <tr>
                    <td style='padding: 10px 5px 10px 0; border-bottom: 1px solid #e2e8f0; vertical-align: top; width: 55px;'>
                        <img src='$vImgUrl' width='50' height='50' style='border-radius:6px; object-fit:cover; display:block; background-color: #f1f5f9;'>
                    </td>
                    <td style='padding: 10px 5px; border-bottom: 1px solid #e2e8f0; vertical-align: top;'>
                        <div style='font-size: 14px; font-weight: bold; color: #0B2447; margin-bottom: 4px; word-wrap: break-word; overflow-wrap: break-word;'>{$vItem['name']}</div>
                        $vVarHtml
                        <div style='font-size: 12px; color: #64748B;'>Qty: {$vItem['quantity']}</div>
                    </td>
                    <td style='padding: 10px 0 10px 5px; border-bottom: 1px solid #e2e8f0; text-align: right; vertical-align: top; font-weight: bold; color: #0B2447; width: 75px;'>
                        ₵" . number_format($vLineEarning, 2) . "
                    </td>
                </tr>";
            }
            $vendorItemsHtml .= "</table><div style='text-align: right; margin-top: 15px; font-size: 15px; font-weight: bold; color: #0B2447;'>Earnings: ₵" . number_format($vendorTotal, 2) . "</div></div>";

            $vSubject = "New Order Received - $orderNumber";
            $vTitle   = "New Order Alert";
            $vMsg     = "Hello <strong>" . htmlspecialchars($vData['vendor_owner']) . "</strong>,<br><br>You have received a new paid order for <strong>" . htmlspecialchars($vData['shop_name']) . "</strong>. Please prepare the following items for shipping:<br>" . $vendorItemsHtml;
            $vBtn     = ['text' => 'Go to Dashboard', 'url' => $baseUrl . '/public/vendor/orders.php'];
            
            try {
                if (function_exists('sendMail')) {
                    sendMail($vData['vendor_email'], $vSubject, $vTitle, $vMsg, $vBtn, $orderNumber);
                }
            } catch (Exception $e) {}
        }

        header("Location: ../public/success.php?order_id=" . $orderId);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("System Error: " . $e->getMessage());
    }

} else {
    // --- FAILURE EMAIL ---
    $orderId = $result->data->metadata->order_id ?? null;
    
    if ($orderId) {
        $stmtUser = $pdo->prepare("SELECT u.email, u.name, o.order_number FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
        $stmtUser->execute([$orderId]);
        $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if ($userData) {
            $orderNumber  = !empty($userData['order_number']) ? $userData['order_number'] : 'SC-' . str_pad($orderId, 6, '0', STR_PAD_LEFT);
            $subject  = "Payment Failed - $orderNumber";
            $title    = "Transaction Failed";
            $message  = "Hello " . htmlspecialchars($userData['name']) . ",<br>We could not process your payment for Order <strong>$orderNumber</strong>. Please try again.";
            $btn      = ['text' => 'Retry Payment', 'url' => $baseUrl . '/public/checkout.php'];
            
            try {
                if (function_exists('sendMail')) {
                    sendMail($userData['email'], $subject, $title, $message, $btn);
                }
            } catch (Exception $e) {}
        }
    }

    $msg = $result->data->gateway_response ?? 'Transaction Failed';
    header("Location: ../public/checkout.php?error=" . urlencode($msg));
    exit;
}
?>