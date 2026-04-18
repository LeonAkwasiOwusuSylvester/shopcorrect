<?php
session_start();

// ✅ FIXED: Restored the /app/ folder to all file paths!
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/helpers/mailer.php"; 
require_once __DIR__ . "/../app/helpers/phpqrcode/qrlib.php"; 

ini_set('display_errors', 1);
error_reporting(E_ALL);

// ----------------------------------------------------------------
// AUTHORIZATION
// ----------------------------------------------------------------
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "vendor") {
    header("Location: ../public/login.php");
    exit;
}

// ----------------------------------------------------------------
// GET VENDOR ID
// ----------------------------------------------------------------
$stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendorId = $stmt->fetchColumn();

if (!$vendorId) {
    die("Vendor profile not found.");
}

// ================================================================
// DYNAMIC URL DETECTOR (Fixes the Localhost/IP Address Bug!)
// ================================================================
$isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$protocol = $isSecure ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
// Detect if we are in a subfolder (like localhost/shopcorrect) or on the live root domain
$basePath = (strpos($_SERVER['REQUEST_URI'], '/shopcorrect/') !== false) ? '/shopcorrect' : '';
$dynamicBaseUrl = $protocol . $host . $basePath;

// ================================================================
// ACTION 1: UPDATE ORDER STATUS 
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {

    try {
        $orderId = (int) $_POST['order_id'];
        $status  = $_POST['status'];
        $trackingNumber = trim($_POST['tracking_number'] ?? '');

        $allowed = ["processing", "shipped", "delivered"];
        if (!in_array($status, $allowed)) {
            throw new Exception("Invalid status selected.");
        }

        // 1. Security Check: Verify Vendor Ownership
        $checkStmt = $pdo->prepare("SELECT 1 FROM order_items WHERE order_id = ? AND vendor_id = ? LIMIT 1");
        $checkStmt->execute([$orderId, $vendorId]);

        if ($checkStmt->rowCount() > 0) {
            // 2. Perform Database Update
            $updateStmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
            $updateStmt->execute([$status, $orderId]);

            // 3. FETCH BUYER DETAILS
            $userStmt = $pdo->prepare("
                SELECT u.email, u.name, o.shipping_name 
                FROM orders o 
                JOIN users u ON o.user_id = u.id 
                WHERE o.id = ?
            ");
            $userStmt->execute([$orderId]);
            $buyer = $userStmt->fetch(PDO::FETCH_ASSOC);

            if ($buyer) {
                $buyerEmail = $buyer['email'];
                $buyerName = !empty($buyer['shipping_name']) ? $buyer['shipping_name'] : $buyer['name'];
                $orderNumber = str_pad($orderId, 6, '0', STR_PAD_LEFT);

                // 4. PREPARE DARK MODE CONTENT
                $subject = "Update on Order #$orderNumber - ShopCorrect";
                
                // Using our new robust dynamic URL
                $btn = ['text' => 'View Order Details', 'url' => $dynamicBaseUrl . "/public/my-orders.php"];
                
                if ($status === 'processing') {
                    $title = "Order Processing ⚙️";
                    $msg = "Hello <strong>$buyerName</strong>, your order is now being processed. Our team is carefully packing your items.";
                    sendMail($buyerEmail, $subject, $title, $msg, $btn, null, 'processing');

                } elseif ($status === 'shipped') {
                    $title = "Your Order is on the Way 🚚";
                    $msg = "Great news! Order #$orderNumber has been shipped and is heading to you.";
                    $glassContent = "Tracking Number: <strong>" . ($trackingNumber ?: 'N/A') . "</strong>";
                    sendMail($buyerEmail, $subject, $title, $msg, $btn, $glassContent, 'shipped');

                } elseif ($status === 'delivered') {
                    $title = "Package Delivered ✅";
                    $msg = "Hi $buyerName, your package has been delivered! We hope you love your purchase. Please take a moment to rate your items.";
                    $glassContent = "#" . $orderNumber; 
                    
                    $btn = [
                        'text' => 'Rate & Review Items', 
                        'url'  => $dynamicBaseUrl . "/public/review.php?order_id=$orderId"
                    ];
                    
                    sendMail($buyerEmail, $subject, $title, $msg, $btn, $glassContent, 'delivered');
                }
            }

            $_SESSION['success'] = "Order status updated and customer notified.";
        } else {
            $_SESSION['error'] = "Permission denied.";
        }

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }

    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../public/vendor/orders.php'));
    exit;
}

// ================================================================
// ACTION 2: DELETE ORDER
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_order') {
    try {
        $orderId = (int) $_POST['order_id'];
        $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ? AND vendor_id = ?");
        $stmt->execute([$orderId, $vendorId]);
        $_SESSION['success'] = "Order record removed from your view.";
    } catch (PDOException $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header("Location: ../public/vendor/orders.php");
    exit;
}

// ================================================================
// ACTION 3: ADD PRODUCT
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {

    try {
        $currencyRate = (float) ($_SESSION['currency_rate'] ?? 1.0);
        if ($currencyRate <= 0) $currencyRate = 1.0;

        $name        = trim($_POST['name']);
        $description = trim($_POST['description']);
        $categoryId  = (int) $_POST['category_id'];
        
        $inputPrice  = (float) $_POST['original_price'];
        $price       = round($inputPrice / $currencyRate, 2); 
        
        $weight      = (float) ($_POST['weight'] ?? 0.00);
        $stock       = (int) $_POST['stock'];
        
        $colors      = null; 
        $sizes       = null; 
        
        $specs       = !empty($_POST['specifications']) ? $_POST['specifications'] : null;

        $fulfillmentType  = $_POST['fulfillment_type'] ?? 'vendor';
        $warehouseCountry = ($fulfillmentType === 'shopcorrect' && !empty($_POST['warehouse_country'])) ? trim($_POST['warehouse_country']) : null;

        $inputSalePrice = (!empty($_POST['discount_price']) && $_POST['discount_price'] > 0) ? (float) $_POST['discount_price'] : null;
        $salePrice      = $inputSalePrice ? round($inputSalePrice / $currencyRate, 2) : null;

        $discountPercent = ($salePrice && $salePrice < $price)
            ? round((($price - $salePrice) / $price) * 100)
            : 0;

        if ($discountPercent <= 0) { $salePrice = null; }

        // --- RUN AI FRAUD DETECTION ---
        // ✅ FIXED: Restored the /app/ folder path
        require_once __DIR__ . "/../app/helpers/fraud_detector.php";
        $fraudCheck = FraudDetector::analyzeProduct($name, $description, $price);
        
        $flaggedReason = null;
        
        if ($fraudCheck['is_suspicious']) {
            $status = 'flagged'; // Hide from public store instantly
            $flaggedReason = $fraudCheck['reason'];
        } else {
            $status = ($stock > 0) ? 'active' : 'inactive';
        }

        $mainImage = null;
        $gallery   = [];

        if (!empty($_FILES['images']['name'][0])) {
            $uploadDir = __DIR__ . '/../public/uploads/products/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            foreach ($_FILES['images']['name'] as $i => $fileName) {
                if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (!in_array($ext, $allowed)) continue;

                $newFileName = uniqid('prod_', true) . "_$i.$ext";
                if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $uploadDir . $newFileName)) {
                    $gallery[] = $newFileName;
                    if (!$mainImage) $mainImage = $newFileName;
                }
            }
        }

        $galleryJson = $gallery ? json_encode($gallery) : null;

        $pdo->beginTransaction();
        
        // Insert main product
        $stmt = $pdo->prepare("
            INSERT INTO products (
                vendor_id, category_id, name, description,
                price, weight, sale_price, discount_percent,
                stock, image, gallery, status, flagged_reason,
                colors, sizes, specifications, 
                fulfillment_type, warehouse_country, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $vendorId, $categoryId, $name, $description,
            $price, $weight, $salePrice, $discountPercent,
            $stock, $mainImage, $galleryJson,
            $status, $flaggedReason, $colors, $sizes, $specs,
            $fulfillmentType, $warehouseCountry
        ]);

        $productId = $pdo->lastInsertId();
        
        // Insert product variants
        if (isset($_POST['variant_color']) && is_array($_POST['variant_color'])) {
            $varStmt = $pdo->prepare("INSERT INTO product_variants (product_id, color, size, price, stock) VALUES (?, ?, ?, ?, ?)");
            $vColors = $_POST['variant_color'];
            $vSizes  = $_POST['variant_size'];
            $vPrices = $_POST['variant_price'];
            $vStocks = $_POST['variant_stock'];

            for ($i = 0; $i < count($vColors); $i++) {
                $vCol = trim($vColors[$i]);
                $vSiz = trim($vSizes[$i]);
                $vPri = trim($vPrices[$i]);
                $vStk = trim($vStocks[$i]);

                if ($vCol === '' && $vSiz === '' && $vPri === '' && $vStk === '') continue;

                $finalVPri = ($vPri !== '') ? round((float)$vPri / $currencyRate, 2) : null;
                $finalVStk = ($vStk !== '') ? (int)$vStk : 0;

                $varStmt->execute([$productId, $vCol, $vSiz, $finalVPri, $finalVStk]);
            }
        }
        
        // ✅ DYNAMIC QR CODE GENERATION
        $verifyUrl = $dynamicBaseUrl . "/public/verify.php?id=" . $productId;
        
        $qrDir = __DIR__ . '/../public/uploads/qrcodes/';
        if (!is_dir($qrDir)) mkdir($qrDir, 0777, true);
        
        $qrFileName = 'qr_' . $productId . '_' . time() . '.png';
        $qrFilePath = $qrDir . $qrFileName;
        QRcode::png($verifyUrl, $qrFilePath, QR_ECLEVEL_L, 4);
        
        $qrUpdateStmt = $pdo->prepare("UPDATE products SET qr_path = ? WHERE id = ?");
        $qrUpdateStmt->execute([$qrFileName, $productId]);

        $pdo->commit();

        if ($status === 'flagged') {
            $_SESSION['error_msg'] = "Product submitted, but flagged for admin review due to suspicious pricing/keywords.";
        } else {
            $_SESSION['success_msg'] = "Product published successfully.";
        }
        
        header("Location: ../public/vendor/products.php");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_msg'] = $e->getMessage();
        header("Location: ../public/vendor/add-product.php");
        exit;
    }
}

// ================================================================
// ACTION 4: UPDATE PRODUCT
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_product') {

    try {
        $currencyRate = (float) ($_SESSION['currency_rate'] ?? 1.0);
        if ($currencyRate <= 0) $currencyRate = 1.0;

        $productId   = (int) $_POST['product_id'];
        $name        = trim($_POST['name']);
        $description = trim($_POST['description']);
        
        $inputPrice  = (float) $_POST['price'];
        $price       = round($inputPrice / $currencyRate, 2);

        $weight      = (float) ($_POST['weight'] ?? 0.00); 

        $fulfillmentType  = $_POST['fulfillment_type'] ?? 'vendor';
        $warehouseCountry = ($fulfillmentType === 'shopcorrect' && !empty($_POST['warehouse_country'])) ? trim($_POST['warehouse_country']) : null;

        $inputSalePrice = !empty($_POST['sale_price']) ? (float) $_POST['sale_price'] : null;
        $salePrice      = $inputSalePrice ? round($inputSalePrice / $currencyRate, 2) : null;

        $stock       = (int) $_POST['stock'];
        $categoryId  = (int) $_POST['category_id'];
        $inputStatus = $_POST['status'];
        
        $colors      = null; 
        $sizes       = null; 
        
        $specs       = !empty($_POST['specifications']) ? $_POST['specifications'] : null;
        
        $discountPercent = (int) ($_POST['discount_percent'] ?? 0);

        if ($discountPercent === 0 && $salePrice && $salePrice < $price) {
            $discountPercent = round((($price - $salePrice) / $price) * 100);
        }

        if ($discountPercent <= 0) { 
            $salePrice = null; 
            $discountPercent = 0;
        }

        // --- RUN AI FRAUD DETECTION ON UPDATE ---
        // ✅ FIXED: Restored the /app/ folder path
        require_once __DIR__ . "/../app/helpers/fraud_detector.php";
        $fraudCheck = FraudDetector::analyzeProduct($name, $description, $price);
        
        $flaggedReason = null;
        
        if ($fraudCheck['is_suspicious']) {
            $finalStatus = 'flagged'; 
            $flaggedReason = $fraudCheck['reason'];
        } else {
            $finalStatus = ($stock > 0)
                ? ($inputStatus === 'inactive' ? 'inactive' : 'active')
                : 'inactive';
        }

        $pdo->beginTransaction();

        // Update main product details
        $sql = "UPDATE products SET name = ?, description = ?, price = ?, weight = ?, sale_price = ?, discount_percent = ?, stock = ?, category_id = ?, status = ?, flagged_reason = ?, colors = ?, sizes = ?, specifications = ?, fulfillment_type = ?, warehouse_country = ?";
        $params = [$name, $description, $price, $weight, $salePrice, $discountPercent, $stock, $categoryId, $finalStatus, $flaggedReason, $colors, $sizes, $specs, $fulfillmentType, $warehouseCountry];

        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                $newFilename = uniqid('prod_', true) . '.' . $ext;
                $uploadDir = __DIR__ . '/../public/uploads/products/';
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $newFilename)) {
                    $sql .= ", image = ?";
                    $params[] = $newFilename;
                }
            }
        }

        $sql .= " WHERE id = ? AND vendor_id = ?";
        $params[] = $productId;
        $params[] = $vendorId;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Manage Variants: Clear old variants and insert updated ones
        $pdo->prepare("DELETE FROM product_variants WHERE product_id = ?")->execute([$productId]);
        
        if (isset($_POST['variant_color']) && is_array($_POST['variant_color'])) {
            $varStmt = $pdo->prepare("INSERT INTO product_variants (product_id, color, size, price, stock) VALUES (?, ?, ?, ?, ?)");
            $vColors = $_POST['variant_color'];
            $vSizes  = $_POST['variant_size'];
            $vPrices = $_POST['variant_price'];
            $vStocks = $_POST['variant_stock'];

            for ($i = 0; $i < count($vColors); $i++) {
                $vCol = trim($vColors[$i]);
                $vSiz = trim($vSizes[$i]);
                $vPri = trim($vPrices[$i]);
                $vStk = trim($vStocks[$i]);

                if ($vCol === '' && $vSiz === '' && $vPri === '' && $vStk === '') continue;

                $finalVPri = ($vPri !== '') ? round((float)$vPri / $currencyRate, 2) : null;
                $finalVStk = ($vStk !== '') ? (int)$vStk : 0;

                $varStmt->execute([$productId, $vCol, $vSiz, $finalVPri, $finalVStk]);
            }
        }

        // ✅ THE FIX: DYNAMIC RE-GENERATION OF BROKEN QR CODES ON UPDATE
        $verifyUrl = $dynamicBaseUrl . "/public/verify.php?id=" . $productId;
        $qrDir = __DIR__ . '/../public/uploads/qrcodes/';
        if (!is_dir($qrDir)) mkdir($qrDir, 0777, true);
        
        // Remove the old bad QR code if it exists
        $oldQrStmt = $pdo->prepare("SELECT qr_path FROM products WHERE id = ?");
        $oldQrStmt->execute([$productId]);
        $oldQr = $oldQrStmt->fetchColumn();
        if ($oldQr && file_exists($qrDir . $oldQr)) {
            @unlink($qrDir . $oldQr);
        }

        // Generate the new, correct QR code
        $qrFileName = 'qr_' . $productId . '_' . time() . '.png';
        $qrFilePath = $qrDir . $qrFileName;
        QRcode::png($verifyUrl, $qrFilePath, QR_ECLEVEL_L, 4);
        
        $qrUpdateStmt = $pdo->prepare("UPDATE products SET qr_path = ? WHERE id = ?");
        $qrUpdateStmt->execute([$qrFileName, $productId]);

        $pdo->commit();

        if ($finalStatus === 'flagged') {
            $_SESSION['error_msg'] = "Product updated, but flagged for admin review due to suspicious pricing/keywords.";
        } else {
            $_SESSION['success_msg'] = "Product updated successfully and QR Code refreshed!";
        }
        
        header("Location: ../public/vendor/products.php");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_msg'] = "Error updating product: " . $e->getMessage();
        header("Location: ../public/vendor/products.php");
        exit;
    }
}

header("Location: ../public/vendor/index.php");
exit;