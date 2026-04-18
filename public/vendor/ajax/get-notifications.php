<?php
// public/vendor/ajax/get-notifications.php
require_once __DIR__ . "/../../../app/config/db.php";
require_once __DIR__ . "/../../../app/config/session.php";

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;
$sessionVendorId = $_SESSION['vendor_id'] ?? null; 
$count = 0;

if ($userId || $sessionVendorId) {
    try {
        // Get Vendor ID
        if ($sessionVendorId) {
            $vendorId = $sessionVendorId;
        } else {
            $vStmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
            $vStmt->execute([$userId]);
            $vendorId = $vStmt->fetchColumn();
        }

        if ($vendorId) {
            // 1. BACKGROUND SYNC ORDERS (Matches your notifications.php logic)
            try {
                $orderStmt = $pdo->prepare("
                    SELECT o.id, o.created_at, u.name as customer_name
                    FROM orders o
                    JOIN order_items oi ON o.id = oi.order_id
                    LEFT JOIN users u ON o.user_id = u.id
                    WHERE oi.vendor_id = ? AND o.created_at >= NOW() - INTERVAL 2 DAY
                    GROUP BY o.id
                ");
                $orderStmt->execute([$vendorId]);
                
                while ($order = $orderStmt->fetch()) {
                    $title = "New Order #" . $order['id'];
                    $check = $pdo->prepare("SELECT id FROM notifications WHERE vendor_id = ? AND title = ? LIMIT 1");
                    $check->execute([$vendorId, $title]);
                    
                    if (!$check->fetch()) {
                        $msg = "Customer " . ($order['customer_name'] ?: 'Guest') . " placed a new order.";
                        $ins = $pdo->prepare("INSERT INTO notifications (vendor_id, type, title, message, link, created_at) VALUES (?, 'order', ?, ?, 'orders.php', ?)");
                        $ins->execute([$vendorId, $title, $msg, $order['created_at']]);
                    }
                }
            } catch (Exception $e) {}

            // 2. BACKGROUND SYNC LOW STOCK (Matches your notifications.php logic)
            try {
                $stockStmt = $pdo->prepare("SELECT id, name, stock FROM products WHERE vendor_id = ? AND stock < 5 AND is_deleted = 0");
                $stockStmt->execute([$vendorId]);
                
                while ($prod = $stockStmt->fetch()) {
                    $title = "Stock Alert: " . $prod['name'];
                    $check = $pdo->prepare("SELECT id FROM notifications WHERE vendor_id = ? AND title = ? LIMIT 1");
                    $check->execute([$vendorId, $title]);
                    
                    if (!$check->fetch()) {
                        $msg = "Your inventory is low (" . $prod['stock'] . " left). Restock soon!";
                        $ins = $pdo->prepare("INSERT INTO notifications (vendor_id, type, title, message, link) VALUES (?, 'stock', ?, ?, 'products.php')");
                        $ins->execute([$vendorId, $title, $msg]);
                    }
                }
            } catch (Exception $e) {}

            // 3. ✅ THE FIX: Count ONLY unread AND non-deleted notifications
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE vendor_id = ? AND is_read = 0 AND is_deleted = 0");
            $countStmt->execute([$vendorId]);
            $count = (int)$countStmt->fetchColumn();
        }
    } catch (Exception $e) {
        // Fail silently
    }
}

echo json_encode(['total' => $count]);
?>