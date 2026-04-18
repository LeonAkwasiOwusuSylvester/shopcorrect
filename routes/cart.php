<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";

header('Content-Type: application/json');

/*
|--------------------------------------------------
| AUTH CHECK
|--------------------------------------------------
*/
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "Login required"
    ]);
    exit;
}

$userId = $_SESSION["user_id"];

/*
|--------------------------------------------------
| ADD TO CART
|--------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    try {
        $productId = (int) ($_POST["product_id"] ?? 0);
        $qty       = max(1, (int) ($_POST["quantity"] ?? 1));
        
        // 1. Capture Options
        $color = !empty($_POST['color']) ? trim($_POST['color']) : null;
        $size  = !empty($_POST['size']) ? trim($_POST['size']) : null;

        if ($productId <= 0) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Invalid product"
            ]);
            exit;
        }

        /*
        |----------------------------------------------
        | ENSURE CART EXISTS
        |----------------------------------------------
        */
        $stmt = $pdo->prepare("SELECT id FROM carts WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $cartId = $stmt->fetchColumn();

        if (!$cartId) {
            $pdo->prepare("INSERT INTO carts (user_id, created_at) VALUES (?, NOW())")
                ->execute([$userId]);
            $cartId = $pdo->lastInsertId();
        }

        /*
        |----------------------------------------------
        | CHECK PRODUCT STOCK (Base or Variant)
        |----------------------------------------------
        */
        // Join with product_variants to get specific stock if options were selected
        $p = $pdo->prepare("
            SELECT p.stock as base_stock, pv.stock as variant_stock, pv.id as variant_id
            FROM products p
            LEFT JOIN product_variants pv 
                ON p.id = pv.product_id 
                AND COALESCE(pv.color, '') = COALESCE(?, '')
                AND COALESCE(pv.size, '') = COALESCE(?, '')
            WHERE p.id = ?
            LIMIT 1
        ");
        $p->execute([$color ?? '', $size ?? '', $productId]);
        $product = $p->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            http_response_code(404);
            echo json_encode([
                "status" => "error",
                "message" => "Product not found"
            ]);
            exit;
        }

        // Determine accurate stock based on whether this is a variant or the base product
        $availableStock = !empty($product['variant_id']) ? (int)$product['variant_stock'] : (int)$product['base_stock'];

        if ($availableStock < $qty) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Insufficient stock for this option"
            ]);
            exit;
        }

        /*
        |----------------------------------------------
        | ADD OR UPDATE CART ITEM (With Options)
        |----------------------------------------------
        */
        // Check for EXACT match (Product + Color + Size)
        $sql = "SELECT id, quantity FROM cart_items 
                WHERE cart_id = ? 
                AND product_id = ? 
                AND COALESCE(selected_color, '') = COALESCE(?, '')
                AND COALESCE(selected_size, '') = COALESCE(?, '')
                LIMIT 1";
                
        $ci = $pdo->prepare($sql);
        $ci->execute([$cartId, $productId, $color ?? '', $size ?? '']);
        $item = $ci->fetch(PDO::FETCH_ASSOC);

        if ($item) {
            // Validate Stock Limit for existing item + new quantity
            if (($item["quantity"] + $qty) > $availableStock) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Stock limit reached for this option"
                ]);
                exit;
            }

            // Update Quantity
            $pdo->prepare("
                UPDATE cart_items
                SET quantity = quantity + ?
                WHERE id = ?
            ")->execute([$qty, $item["id"]]);
            
        } else {
            // Insert New Item
            $pdo->prepare("
                INSERT INTO cart_items (cart_id, product_id, quantity, selected_color, selected_size, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ")->execute([$cartId, $productId, $qty, $color, $size]);
        }

        /*
        |----------------------------------------------
        | RETURN REAL CART COUNT
        |----------------------------------------------
        */
        $countStmt = $pdo->prepare("
            SELECT SUM(quantity)
            FROM cart_items
            WHERE cart_id = ?
        ");
        $countStmt->execute([$cartId]);
        $cartCount = (int) ($countStmt->fetchColumn() ?: 0);

        http_response_code(200);
        echo json_encode([
            "status"      => "success",
            "message"     => "Added to cart",
            "cart_count"  => $cartCount
        ]);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Server error: " . $e->getMessage()
        ]);
        exit;
    }
}

/*
|--------------------------------------------------
| INVALID REQUEST
|--------------------------------------------------
*/
http_response_code(405);
echo json_encode([
    "status" => "error",
    "message" => "Invalid request"
]);
exit;