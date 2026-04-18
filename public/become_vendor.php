<?php
require_once __DIR__ . "/../app/config/session.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
?>

<form method="POST" action="../routes/vendor.php">
    <h2>Apply as a Vendor</h2>

    <input type="text" name="shop_name" placeholder="Shop name" required><br><br>

    <button type="submit" name="apply_vendor">Submit Application</button>
</form>
<?php require_once __DIR__ . "/../partials/footer.php"; ?>
