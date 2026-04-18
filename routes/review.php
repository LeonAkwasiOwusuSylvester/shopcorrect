<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";

if (!isset($_SESSION["user_id"])) {
    die("Login required");
}

if (isset($_POST["submit_review"])) {

    $userId = $_SESSION["user_id"];
    $productId = (int)$_POST["product_id"];
    $rating = (int)$_POST["rating"];
    $comment = trim($_POST["comment"]);

    if ($rating < 1 || $rating > 5) {
        die("Invalid rating");
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO reviews (user_id, product_id, rating, comment)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $productId, $rating, $comment]);

        echo "Review submitted successfully";

    } catch (PDOException $e) {
        die("You already reviewed this product");
    }
}
