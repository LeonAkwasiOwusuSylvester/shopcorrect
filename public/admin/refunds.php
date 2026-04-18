<?php
require_once __DIR__ . "/../../app/config/db.php";
require_once __DIR__ . "/../../app/config/session.php";
require_once __DIR__ . "/../../app/helpers/mailer.php"; // Include the mailer

// 1. SESSION & ROLE SECURITY
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['supadmin', 'country_agent', 'support'])) {
    header("Location: ../login.php");
    exit;
}

$userRole = $_SESSION['role'];
$managedCountry = $_SESSION['managed_country'] ?? null;

// 2. HANDLE APPROVE / DECLINE ACTIONS & SEND EMAILS
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    // SECURITY: Support agents cannot process refunds
    if ($userRole === 'support') {
        $_SESSION['error'] = "Permission denied. Support agents cannot approve or decline refunds.";
        header("Location: refunds.php");
        exit;
    }

    $orderId = (int) $_POST['order_id'];
    $action = $_POST['action'];

    // Fetch buyer details and check country for security
    $userStmt = $pdo->prepare("
        SELECT u.email, u.name, o.shipping_name, o.shipping_country 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.id = ?
    ");
    $userStmt->execute([$orderId]);
    $buyer = $userStmt->fetch(PDO::FETCH_ASSOC);

    if ($buyer) {
        // SECURITY: Country Agents can only process refunds for their assigned country
        if ($userRole === 'country_agent' && $buyer['shipping_country'] !== $managedCountry) {
            $_SESSION['error'] = "Unauthorized action. You can only process refunds for orders in your assigned country.";
            header("Location: refunds.php");
            exit;
        }

        if ($action === 'approve') {
            // Change status to refunded AND update notes so it leaves the queue
            $stmt = $pdo->prepare("UPDATE orders SET status = 'refunded', payment_status = 'refunded', notes = REPLACE(IFNULL(notes, ''), '[REFUND REQUESTED]', '[REFUND APPROVED]') WHERE id = ?");
            $stmt->execute([$orderId]);
            $_SESSION['success'] = "Order #{$orderId} has been marked as Refunded and the buyer has been notified.";

            // Send Approval Email
            $buyerEmail = $buyer['email'];
            $buyerName = !empty($buyer['shipping_name']) ? $buyer['shipping_name'] : $buyer['name'];
            $orderNumber = str_pad($orderId, 6, '0', STR_PAD_LEFT);
            
            $subject = "Refund Approved - Order #$orderNumber";
            $title = "Refund Processed 💸";
            $msg = "Hello <strong>$buyerName</strong>, your refund request for Order #$orderNumber has been successfully approved. The funds are being returned to your original payment method. Depending on your bank or provider, it may take 2-5 business days to reflect.";
            $btn = ['text' => 'View Order', 'url' => "http://localhost/shopcorrect/public/my-orders.php"];
            
            sendMail($buyerEmail, $subject, $title, $msg, $btn, null, 'refund_approved');
            
        } elseif ($action === 'decline') {
            // Revert to delivered AND update notes so it leaves the queue
            $stmt = $pdo->prepare("UPDATE orders SET status = 'delivered', notes = REPLACE(IFNULL(notes, ''), '[REFUND REQUESTED]', '[REFUND DECLINED]') WHERE id = ?");
            $stmt->execute([$orderId]);
            $_SESSION['error'] = "Refund request for Order #{$orderId} was Declined and the buyer has been notified.";

            // Send Rejection Email
            $buyerEmail = $buyer['email'];
            $buyerName = !empty($buyer['shipping_name']) ? $buyer['shipping_name'] : $buyer['name'];
            $orderNumber = str_pad($orderId, 6, '0', STR_PAD_LEFT);
            
            $subject = "Update on Refund Request - Order #$orderNumber";
            $title = "Refund Request Declined ❌";
            $msg = "Hello <strong>$buyerName</strong>, we have reviewed your refund request for Order #$orderNumber, but unfortunately, it has been declined. Your order remains marked as delivered. If you have any questions or require further assistance, please reach out to our support team.";
            $btn = ['text' => 'Contact Support', 'url' => "http://localhost/shopcorrect/public/help.php"];
            
            sendMail($buyerEmail, $subject, $title, $msg, $btn, null, 'refund_declined');
        }
    }
    
    header("Location: refunds.php");
    exit;
}

// 3. FETCH PENDING REFUNDS & PAID CANCELLATIONS (Dynamically based on role)
$refundRequests = [];
$pendingRefunds = 0;

try {
    $mainQuery = "
        SELECT o.id, o.status, o.created_at, o.total_amount, o.notes, o.payment_method, o.payment_status, 
               COALESCE(u.name, o.shipping_name, 'Guest') as customer_name, 
               COALESCE(u.email, 'N/A') as email 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        WHERE (LOWER(TRIM(o.status)) = 'refund requested' 
           OR o.notes LIKE '%[REFUND REQUESTED]%'
           OR (LOWER(TRIM(o.status)) = 'cancelled' AND LOWER(TRIM(o.payment_status)) = 'paid'))
    ";

    $badgeQuery = "
        SELECT COUNT(*) FROM orders o
        WHERE (LOWER(TRIM(o.status)) = 'refund requested' 
           OR o.notes LIKE '%[REFUND REQUESTED]%'
           OR (LOWER(TRIM(o.status)) = 'cancelled' AND LOWER(TRIM(o.payment_status)) = 'paid'))
    ";

    $queryParams = [];

    // Filter list for Country Agents
    if ($userRole === 'country_agent') {
        $mainQuery .= " AND o.shipping_country = ?";
        $badgeQuery .= " AND o.shipping_country = ?";
        $queryParams[] = $managedCountry;
    }

    $mainQuery .= " ORDER BY o.created_at DESC";

    $stmt = $pdo->prepare($mainQuery);
    $stmt->execute($queryParams);
    $refundRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch number of pending actions for the sidebar badge
    $badgeStmt = $pdo->prepare($badgeQuery);
    $badgeStmt->execute($queryParams);
    $pendingRefunds = $badgeStmt->fetchColumn();

} catch (PDOException $e) {
    die("<div style='color:red; padding:20px; font-family:sans-serif;'><strong>Database Error:</strong> " . $e->getMessage() . "</div>");
}

// 4. INCLUDE HEADER
require_once __DIR__ . "/includes/header.php";
?>

<style>
    .reason-box { background-color: #F8FAFC; border-left: 4px solid #D97706; padding: 15px; border-radius: 6px; font-size: 0.9rem; color: #475569; margin-top: 15px; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1" style="color: var(--shop-brand);">Refund Management</h3>
        <p class="text-secondary small">Process cancelled orders and customer returns.</p>
    </div>
</div>

<?php if(isset($_SESSION['success'])): ?>
    <div class="alert alert-success border-0 shadow-sm rounded-3 mb-4 fw-bold">
        <i class="bi bi-check-circle-fill me-2"></i> <?= $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<?php if(isset($_SESSION['error'])): ?>
    <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4 fw-bold">
        <i class="bi bi-x-circle-fill me-2"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<?php if(empty($refundRequests)): ?>
    <div class="glass-card text-center py-5">
        <i class="bi bi-emoji-smile text-muted opacity-25" style="font-size: 4rem;"></i>
        <h5 class="fw-bold text-secondary mt-3">All caught up!</h5>
        <p class="text-muted">There are no pending refund requests or paid cancellations at the moment.</p>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach($refundRequests as $req): ?>
            <?php 
                // Safely determine if it's a cancellation or a return
                $isRefundReq = (strtolower(trim($req['status'])) === 'refund requested' || strpos($req['notes'], '[REFUND REQUESTED]') !== false);
                
                $badgeText = !$isRefundReq ? 'Cancelled (Awaiting Refund)' : 'Return Requested';
                $badgeColor = !$isRefundReq ? 'bg-danger text-white' : 'bg-warning text-dark';
            ?>
            <div class="col-12">
                <div class="glass-card border-start border-4 <?= !$isRefundReq ? 'border-danger' : 'border-warning' ?>" style="padding: 20px;">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div>
                            <h5 class="fw-bold text-dark mb-1">Order #<?= str_pad($req['id'], 6, '0', STR_PAD_LEFT) ?></h5>
                            <div class="text-secondary small mb-2">
                                <i class="bi bi-person-fill me-1"></i> <?= htmlspecialchars($req['customer_name']) ?> (<?= htmlspecialchars($req['email']) ?>)
                            </div>
                            <span class="badge <?= $badgeColor ?>"><i class="bi bi-clock-history me-1"></i> <?= $badgeText ?></span>
                        </div>
                        
                        <div class="text-end">
                            <div class="text-muted small">Amount to Refund</div>
                            <h4 class="fw-bold notranslate <?= !$isRefundReq ? 'text-danger' : 'text-warning' ?>">
                                <?= formatPrice($req['total_amount']) ?>
                            </h4>
                            <div class="small text-secondary fw-bold">Via: <?= strtoupper($req['payment_method']) ?></div>
                        </div>
                    </div>

                    <?php if ($isRefundReq && !empty($req['notes'])): ?>
                        <div class="reason-box mt-3">
                            <strong>Customer Reason for Return:</strong><br>
                            <?= nl2br(htmlspecialchars(str_replace('[REFUND REQUESTED]:', '', $req['notes']))) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($userRole !== 'support'): ?>
                        <div class="d-flex flex-wrap gap-2 mt-4 border-top pt-3">
                            <form method="POST" class="d-flex gap-2" onsubmit="return confirm('Have you returned the money to the customer? This will mark the order as fully Refunded in the system.');">
                                <input type="hidden" name="order_id" value="<?= $req['id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-success fw-bold px-4 rounded-pill"><i class="bi bi-check-lg me-1"></i> Mark as Refunded</button>
                            </form>

                            <?php if ($isRefundReq): ?>
                                <form method="POST" class="d-flex gap-2" onsubmit="return confirm('Are you sure you want to DECLINE this return? The order will be marked back as Delivered.');">
                                    <input type="hidden" name="order_id" value="<?= $req['id'] ?>">
                                    <input type="hidden" name="action" value="decline">
                                    <button type="submit" class="btn btn-outline-danger fw-bold px-4 rounded-pill"><i class="bi bi-x-lg me-1"></i> Decline Return</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="mt-4 border-top pt-3 text-muted small fw-bold">
                            <i class="bi bi-info-circle-fill text-primary me-1"></i> Refund processing is handled by regional administration.
                        </div>
                    <?php endif; ?>
                    
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php 
// 5. INCLUDE FOOTER
require_once __DIR__ . "/includes/footer.php"; 
?>