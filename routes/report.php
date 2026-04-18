<?php
session_start();
require_once __DIR__ . "/../app/config/db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = (int) $_POST['product_id'];
    $reason = trim($_POST['reason']);
    $details = trim($_POST['details'] ?? '');

    if ($product_id && !empty($reason)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO product_reports (product_id, reason, details) VALUES (?, ?, ?)");
            $stmt->execute([$product_id, $reason, $details]);
            
            $_SESSION['success_msg'] = "Report submitted successfully. Our team will review this shortly.";
        } catch (Exception $e) {
            $_SESSION['error_msg'] = "Something went wrong. Please try again.";
        }
    } else {
        $_SESSION['error_msg'] = "Please provide a reason for your report.";
    }

    // Send them back to the verification page
    header("Location: ../public/verify.php?id=" . $product_id);
    exit;
}

header("Location: ../public/index.php");
exit;