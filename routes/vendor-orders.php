<?php
require_once __DIR__ . "/../../app/config/db.php";
require_once __DIR__ . "/../../app/config/session.php";
require_once __DIR__ . "/../../app/helpers/vendor_guard.php";

// ----------------------------------------------------------------
// HANDLE ORDER STATUS UPDATE
// ----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {

    try {
        // 1. Get Vendor ID
        $stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
        $stmt->execute([$_SESSION["user_id"]]);
        $vendorId = $stmt->fetchColumn();

        if (!$vendorId) {
            throw new Exception("Vendor profile not found.");
        }

        // 2. Sanitize Inputs
        $orderId = (int) $_POST['order_id'];
        $status  = $_POST['status'];

        // 3. Validate Status (Security Whitelist)
        $allowed = ["processing", "shipped", "delivered"];
        if (!in_array($status, $allowed)) {
            throw new Exception("Invalid status selected.");
        }

        // 4. Update Database (With Ownership Check)
        // Only allow update if this vendor has items in this order
        $updateStmt = $pdo->prepare("
            UPDATE orders
            SET status = ?, updated_at = NOW()
            WHERE id = ?
            AND EXISTS (
                SELECT 1 FROM order_items 
                WHERE order_id = orders.id 
                AND vendor_id = ?
            )
        ");

        $updateStmt->execute([$status, $orderId, $vendorId]);

        if ($updateStmt->rowCount() > 0) {
            $_SESSION['success'] = "Order #{$orderId} status updated to " . ucfirst($status);
        } else {
            // If rowCount is 0, either the order doesn't exist, 
            // the vendor doesn't own it, or the status was already the same.
            // We'll treat it as a generic permissions issue or 'no change' for safety.
            $_SESSION['error'] = "Could not update status. You may not have permission or no changes were made.";
        }

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }

    // 5. Redirect Back to Orders Page
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}
?>