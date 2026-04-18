<?php
require_once __DIR__ . "/../app/helpers/admin_guard.php";
require_once __DIR__ . "/../app/config/db.php";

if (isset($_POST["delete_review"])) {
    $pdo->prepare(
        "DELETE FROM reviews WHERE id = ?"
    )->execute([$_POST["review_id"]]);
}

header("Location: ../public/admin/reviews.php");
