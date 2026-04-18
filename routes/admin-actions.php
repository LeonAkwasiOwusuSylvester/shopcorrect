<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/../app/helpers/admin_guard.php";

if (isset($_POST["approve_vendor"])) {
    $pdo->prepare(
        "UPDATE vendors SET status = 'approved' WHERE id = ?"
    )->execute([$_POST["vendor_id"]]);
}

if (isset($_POST["suspend_vendor"])) {
    $pdo->prepare(
        "UPDATE vendors SET status = 'suspended' WHERE id = ?"
    )->execute([$_POST["vendor_id"]]);
}

header("Location: ../public/admin/vendors.php");
