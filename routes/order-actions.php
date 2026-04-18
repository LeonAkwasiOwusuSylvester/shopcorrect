<?php
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/../app/config/db.php";

/*
|--------------------------------------------------------------------------
| AUTH GUARD
|--------------------------------------------------------------------------
*/

if (empty($_SESSION["user_id"]) || $_SESSION["role"] !== "buyer") {
    header("Location: ../public/login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

/*
|--------------------------------------------------------------------------
| POST ONLY
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../public/my-orders.php");
    exit;
}

$orderId = filter_input(INPUT_POST, "order_id", FILTER_VALIDATE_INT);
$action  = $_POST["action"] ?? null;

if (!$orderId || !$action) {
    $_SESSION["flash_error"] = "Invalid request.";
    header("Location: ../public/my-orders.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| FETCH ORDER
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, status, user_id, delivered_at
    FROM orders
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order || $order["user_id"] != $userId) {
    $_SESSION["flash_error"] = "Order not found.";
    header("Location: ../public/my-orders.php");
    exit;
}

try {

    /*
    |--------------------------------------------------------------------------
    | CANCEL ORDER
    |--------------------------------------------------------------------------
    */

    if ($action === "cancel") {

        if (!in_array($order["status"], ["pending", "processing"])) {
            throw new Exception("Order cannot be cancelled.");
        }

        $pdo->prepare("
            UPDATE orders
            SET status = 'cancelled'
            WHERE id = ?
        ")->execute([$orderId]);

        $_SESSION["flash_success"] = "Order cancelled successfully.";
    }

    /*
    |--------------------------------------------------------------------------
    | REQUEST RETURN
    |--------------------------------------------------------------------------
    */

    if ($action === "return") {

        if ($order["status"] !== "delivered") {
            throw new Exception("Return not allowed.");
        }

        // 7 days return window
        $deliveredAt = strtotime($order["delivered_at"]);
        if (time() > $deliveredAt + (7 * 24 * 60 * 60)) {
            throw new Exception("Return window expired.");
        }

        $reason = trim($_POST["reason"] ?? "");

        if (!$reason) {
            throw new Exception("Return reason required.");
        }

        // Prevent duplicate return
        $check = $pdo->prepare("
            SELECT id FROM order_returns
            WHERE order_id = ?
        ");
        $check->execute([$orderId]);

        if ($check->fetch()) {
            throw new Exception("Return already requested.");
        }

        $pdo->prepare("
            INSERT INTO order_returns
            (order_id, user_id, reason)
            VALUES (?, ?, ?)
        ")->execute([
            $orderId,
            $userId,
            $reason
        ]);

        $_SESSION["flash_success"] = "Return request submitted.";
    }

} catch (Exception $e) {

    $_SESSION["flash_error"] = $e->getMessage();
}

header("Location: ../public/my-orders.php");
exit;
