<?php
session_start();
require_once __DIR__ . "/../app/config/db.php";

// 1. Basic Security & Login Check
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please login to add items to your wishlist.";
    header("Location: ../public/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $userId = $_SESSION['user_id'];
    $productId = (int)$_POST['product_id'];

    try {
        // 2. Check if product already exists in wishlist to avoid duplicates
        $checkStmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
        $checkStmt->execute([$userId, $productId]);

        if ($checkStmt->rowCount() > 0) {
            $_SESSION['info'] = "This item is already in your wishlist.";
        } else {
            // 3. Insert into wishlist
            $insertStmt = $pdo->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
            $insertStmt->execute([$userId, $productId]);
            $_SESSION['success'] = "Product added to wishlist!";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Something went wrong. Please try again.";
    }

    // Redirect back to the previous page
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}