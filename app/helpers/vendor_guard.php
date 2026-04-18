<?php
require_once __DIR__ . "/../config/session.php";
require_once __DIR__ . "/../config/db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "vendor") {
    die("Vendor only");
}

// Check vendor approval
$stmt = $pdo->prepare(
    "SELECT status FROM vendors WHERE user_id = ? LIMIT 1"
);
$stmt->execute([$_SESSION["user_id"]]);
$vendor = $stmt->fetch();

if (!$vendor || $vendor["status"] !== "approved") {
    die("Vendor account not approved");
}
