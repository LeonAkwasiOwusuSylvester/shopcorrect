<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";

/*
|--------------------------------------------------
| AUTH CHECK
|--------------------------------------------------
*/
if (!isset($_SESSION["user_id"])) {
    // AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        echo json_encode(['cart_count' => 0]);
        exit;
    }

    // Normal request
    header("Location: ../public/login.php");
    exit;
}

$userId = $_SESSION["user_id"];

/*
|--------------------------------------------------
| HELPER: GET REAL CART COUNT
|--------------------------------------------------
*/
function getCartCount(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare("
        SELECT SUM(ci.quantity)
        FROM cart_items ci
        JOIN carts c ON ci.cart_id = c.id
        WHERE c.user_id = ?
    ");
    $stmt->execute([$userId]);
    return (int) ($stmt->fetchColumn() ?: 0);
}

/*
|--------------------------------------------------
| AJAX: FETCH CART COUNT ONLY
|--------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET["count"])) {
    header('Content-Type: application/json');
    echo json_encode([
        'cart_count' => getCartCount($pdo, $userId)
    ]);
    exit;
}

/*
|--------------------------------------------------
| UPDATE QUANTITY
|--------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_qty"])) {

    $itemId = (int) $_POST["item_id"];
    $qty    = max(1, (int) $_POST["quantity"]);

    try {
        $stmt = $pdo->prepare("
            SELECT ci.id, p.stock
            FROM cart_items ci
            JOIN carts c ON ci.cart_id = c.id
            JOIN products p ON ci.product_id = p.id
            WHERE ci.id = ? AND c.user_id = ?
        ");
        $stmt->execute([$itemId, $userId]);
        $item = $stmt->fetch();

        if ($item) {
            $finalQty = min($qty, (int) $item['stock']);
            $pdo->prepare("
                UPDATE cart_items SET quantity = ? WHERE id = ?
            ")->execute([$finalQty, $itemId]);
        }
    } catch (Exception $e) {
        // log if needed
    }

    // AJAX response
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'cart_count' => getCartCount($pdo, $userId)
        ]);
        exit;
    }

    header("Location: ../public/cart.php");
    exit;
}

/*
|--------------------------------------------------
| REMOVE ITEM
|--------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["remove_item"])) {

    $itemId = (int) $_POST["item_id"];

    try {
        $stmt = $pdo->prepare("
            DELETE ci FROM cart_items ci
            INNER JOIN carts c ON ci.cart_id = c.id
            WHERE ci.id = ? AND c.user_id = ?
        ");
        $stmt->execute([$itemId, $userId]);
    } catch (Exception $e) {
        // log if needed
    }

    // AJAX response
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'cart_count' => getCartCount($pdo, $userId)
        ]);
        exit;
    }

    header("Location: ../public/cart.php");
    exit;
}

/*
|--------------------------------------------------
| FALLBACK
|--------------------------------------------------
*/
header("Location: ../public/cart.php");
exit;
