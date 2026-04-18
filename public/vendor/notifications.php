<?php
require_once __DIR__ . "/../../app/helpers/vendor_guard.php"; 
require_once __DIR__ . "/../../app/config/db.php";
require_once __DIR__ . "/../../app/helpers/currency.php"; // Included for header compatibility

// 1. Get Vendor Details 
$stmt = $pdo->prepare("SELECT id, shop_name FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION["user_id"]]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    header("Location: ../create-shop.php");
    exit;
}

$vendorId = $vendor['id'];
$shopName = $vendor['shop_name'];

// ---------------------------------------------------------
// 2. SYNC LOGIC (Wrapped in Crash-Protection)
// ---------------------------------------------------------

// A. Sync Orders
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
            $link = "orders.php"; 
            $ins = $pdo->prepare("INSERT INTO notifications (vendor_id, type, title, message, link) VALUES (?, 'order', ?, ?, ?)");
            $ins->execute([$vendorId, $title, $msg, $link]);
        }
    }
} catch (Exception $e) { /* Fail silently to protect page load */ }

// B. Sync Low Stock
try {
    $stockStmt = $pdo->prepare("SELECT id, name, stock FROM products WHERE vendor_id = ? AND stock < 5 AND is_deleted = 0");
    $stockStmt->execute([$vendorId]);

    while ($prod = $stockStmt->fetch()) {
        $title = "Stock Alert: " . $prod['name'];
        $check = $pdo->prepare("SELECT id FROM notifications WHERE vendor_id = ? AND title = ? LIMIT 1");
        $check->execute([$vendorId, $title]);
        
        if (!$check->fetch()) {
            $msg = "Your inventory is low (" . $prod['stock'] . " left). Restock soon!";
            $link = "products.php"; 
            $ins = $pdo->prepare("INSERT INTO notifications (vendor_id, type, title, message, link) VALUES (?, 'stock', ?, ?, ?)");
            $ins->execute([$vendorId, $title, $msg, $link]);
        }
    }
} catch (Exception $e) { /* Fail silently */ }


// ---------------------------------------------------------
// 3. ACTION HANDLER (Resilient Updates & Deletes)
// ---------------------------------------------------------
$safeRedirect = basename($_SERVER['PHP_SELF']); 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Mark ONE as read
        if (isset($_POST['action']) && $_POST['action'] === 'mark_one' && isset($_POST['notif_id'])) {
            $upd = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND vendor_id = ?");
            $upd->execute([$_POST['notif_id'], $vendorId]);
            header("Location: " . $safeRedirect);
            exit;
        }

        // Delete ONE
        if (isset($_POST['action']) && $_POST['action'] === 'delete_one' && isset($_POST['notif_id'])) {
            try {
                // Try Soft Delete First
                $del = $pdo->prepare("UPDATE notifications SET is_deleted = 1 WHERE id = ? AND vendor_id = ?");
                $del->execute([$_POST['notif_id'], $vendorId]);
            } catch (Exception $e) {
                // Fallback to Hard Delete if is_deleted column doesn't exist
                $del = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND vendor_id = ?");
                $del->execute([$_POST['notif_id'], $vendorId]);
            }
            header("Location: " . $safeRedirect);
            exit;
        }

        // Mark ALL as read
        if (isset($_POST['action']) && $_POST['action'] === 'mark_all') {
            $upd = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE vendor_id = ?");
            $upd->execute([$vendorId]);
            header("Location: " . $safeRedirect);
            exit;
        }
    } catch (Exception $e) { /* Ignore action errors */ }
}

// ---------------------------------------------------------
// 4. DATA FETCHING & FORMATTING
// ---------------------------------------------------------

// ✅ FIXED: Bulletproof Date Parser (Prevents 500 error if date is empty/invalid)
function time_elapsed_string($datetime) {
    if (empty($datetime)) return 'Recently';
    try {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);
        if ($diff->d > 7) return date('M j, Y', strtotime($datetime));
        
        $string = ['y'=>'year','m'=>'month','d'=>'day','h'=>'hour','i'=>'minute','s'=>'second'];
        foreach ($string as $k => &$v) {
            if ($diff->$k) $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            else unset($string[$k]);
        }
        if (!$string) return 'just now';
        return implode(', ', array_slice($string, 0, 1)) . ' ago';
    } catch (Exception $e) {
        return 'Recently'; // Failsafe fallback
    }
}

// ✅ FIXED: Fetch using "id DESC" instead of "created_at" to prevent column crashes
$notifications = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE vendor_id = ? AND is_deleted = 0 ORDER BY id DESC LIMIT 50");
    $stmt->execute([$vendorId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    try {
        // Fallback if is_deleted doesn't exist in your table
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE vendor_id = ? ORDER BY id DESC LIMIT 50");
        $stmt->execute([$vendorId]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) {
        // Ultimate fallback
        $notifications = [];
    }
}

// --------------------------------------------------
// 5. Include Header
// --------------------------------------------------
require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* --- NOTIFICATION CARD STYLES --- */
    .notification-card { border: 1px solid var(--card-border); border-radius: 12px; background: white; transition: all 0.2s; border-left: 4px solid transparent; margin-bottom: 12px; }
    .notification-card:hover { transform: translateX(3px); box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    
    .unread { background-color: white; border-left-color: var(--primary-accent); box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .read { opacity: 0.8; border-left-color: #cbd5e1; background-color: #F8FAFC; }
    
    .type-order { border-left-color: #10b981; } 
    .type-stock { border-left-color: #f59e0b; } 
    
    .icon-box { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; }
    .bg-order { background: #dcfce7; color: #10b981; }
    .bg-stock { background: #fef3c7; color: #f59e0b; }
    
    .btn-action { border: none; background: none; color: #94a3b8; padding: 0 5px; transition: 0.2s; }
    .btn-check:hover { color: #10b981; transform: scale(1.1); }
    .btn-trash:hover { color: #ef4444; transform: scale(1.1); }
    .link-btn { text-decoration: none; font-size: 0.85rem; font-weight: 600; color: var(--primary-accent); }
</style>

<div class="container-fluid px-4 py-4">
    
    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-8">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold mb-1 text-dark">Notifications</h4>
                    <p class="text-muted small mb-0">Stay updated on your store's activity.</p>
                </div>
                <?php if (!empty($notifications)): ?>
                <form method="POST" onsubmit="return confirm('Mark all as read?');">
                    <input type="hidden" name="action" value="mark_all">
                    <button type="submit" class="btn btn-light border btn-sm fw-semibold shadow-sm">
                        <i class="bi bi-check-all me-1"></i> Mark all read
                    </button>
                </form>
                <?php endif; ?>
            </div>

            <?php if (empty($notifications)): ?>
                <div class="text-center py-5 bg-white border rounded-3 shadow-sm">
                    <div class="mb-3"><i class="bi bi-bell-slash text-muted opacity-25 display-1"></i></div>
                    <h5 class="fw-bold text-secondary">All caught up!</h5>
                    <p class="text-muted small">No new notifications at the moment.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $note): ?>
                    <?php 
                        $type = $note['type'] ?? 'order'; 
                        $bg = ($type == 'order') ? 'bg-order' : 'bg-stock';
                        $icon = ($type == 'order') ? 'bi-bag-check' : 'bi-exclamation-triangle';
                        $statusClass = !empty($note['is_read']) ? 'read' : 'unread';
                        $borderClass = !empty($note['is_read']) ? '' : "type-$type";

                        // Magically clean up old broken links saved in the database!
                        $cleanLink = !empty($note['link']) ? str_replace('/shopcorrect/public/vendor/', '', $note['link']) : '#';
                        $createdAt = $note['created_at'] ?? null;
                    ?>
                    <div class="card notification-card p-3 <?= $statusClass ?> <?= $borderClass ?>">
                        <div class="d-flex align-items-start gap-3">
                            <div class="icon-box <?= $bg ?>"><i class="bi <?= $icon ?>"></i></div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($note['title'] ?? 'Notification') ?></h6>
                                    <span class="small text-muted" style="font-size: 0.75rem;"><?= time_elapsed_string($createdAt) ?></span>
                                </div>
                                
                                <p class="text-secondary small mb-2 lh-sm"><?= htmlspecialchars($note['message'] ?? '') ?></p>
                                
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <?php if($cleanLink !== '#'): ?>
                                        <a href="<?= htmlspecialchars($cleanLink) ?>" class="link-btn">
                                            View Details <i class="bi bi-arrow-right ms-1"></i>
                                        </a>
                                    <?php else: ?>
                                        <span></span>
                                    <?php endif; ?>

                                    <div class="d-flex gap-3">
                                        <?php if(empty($note['is_read'])): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="mark_one">
                                                <input type="hidden" name="notif_id" value="<?= $note['id'] ?>">
                                                <button class="btn-action btn-check" title="Mark as read">
                                                    <i class="bi bi-check-circle fs-5"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this notification?');">
                                            <input type="hidden" name="action" value="delete_one">
                                            <input type="hidden" name="notif_id" value="<?= $note['id'] ?>">
                                            <button class="btn-action btn-trash" title="Delete">
                                                <i class="bi bi-trash fs-5"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>

</div> 

<?php
// --------------------------------------------------
// 6. Include Footer
// --------------------------------------------------
require_once __DIR__ . '/includes/footer.php';
?>