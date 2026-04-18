<?php
require_once __DIR__ . "/../app/helpers/admin_guard.php";
require_once __DIR__ . "/../app/config/db.php";

if (isset($_POST["disable_product"])) {
    $pdo->prepare(
        "UPDATE products SET status = 'inactive' WHERE id = ?"
    )->execute([$_POST["product_id"]]);
}

if (isset($_POST["enable_product"])) {
    $pdo->prepare(
        "UPDATE products SET status = 'active' WHERE id = ?"
    )->execute([$_POST["product_id"]]);
}

header("Location: ../public/admin/products.php");
