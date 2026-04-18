<?php
session_start();
require_once __DIR__ . "/../app/config/db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wishlist_id'])) {
    $wishlistId = (int)$_POST['wishlist_id'];
    $userId = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM wishlist WHERE id = ? AND user_id = ?");
        $stmt->execute([$wishlistId, $userId]);
        
        $_SESSION['success'] = "Item removed from wishlist.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Failed to remove item.";
    }

    header("Location: ../public/wishlist.php");
    exit;
}