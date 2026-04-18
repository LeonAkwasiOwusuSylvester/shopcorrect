<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";

/*
|--------------------------------------------------------------------------
| GUARD — ONLY VERIFIED VENDORS
|--------------------------------------------------------------------------
*/
if (
    empty($_SESSION["user_id"]) ||
    empty($_SESSION["role"]) ||
    $_SESSION["role"] !== "vendor"
) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION["user_id"];

/*
|--------------------------------------------------------------------------
| CHECK IF VENDOR PROFILE EXISTS
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("SELECT id, status FROM vendors WHERE user_id = ?");
$stmt->execute([$userId]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| IF PROFILE DOES NOT EXIST → CREATE PROFILE
|--------------------------------------------------------------------------
*/
if (!$vendor) {
    header("Location: complete-vendor-profile.php");
    exit;
}

$status = $vendor["status"];

/*
|--------------------------------------------------------------------------
| ROUTING BASED ON STATUS
|--------------------------------------------------------------------------
*/
if ($status === "approved") {
    header("Location: vendor-dashboard.php");
    exit;
}

if ($status === "pending") {
    $statusMessage = "Your account is under review.";
    $badgeColor = "warning";
} elseif ($status === "rejected") {
    $statusMessage = "Your documents were rejected. Please update them.";
    $badgeColor = "danger";
} else {
    header("Location: complete-vendor-profile.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Vendor Setup | ShopCorrect</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background: linear-gradient(135deg, #0B2447, #19376D);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.card-box {
    background: rgba(255,255,255,0.08);
    backdrop-filter: blur(15px);
    border-radius: 16px;
    padding: 40px;
    color: #fff;
    width: 100%;
    max-width: 500px;
    box-shadow: 0 15px 40px rgba(0,0,0,0.25);
}
</style>
</head>
<body>

<div class="card-box text-center">
    <h4 class="mb-3">Vendor Account Status</h4>

    <span class="badge bg-<?= $badgeColor ?> mb-3">
        <?= htmlspecialchars($statusMessage) ?>
    </span>

    <?php if ($status === "rejected"): ?>
        <a href="complete-vendor-profile.php" class="btn btn-light w-100 mt-3">
            Update Profile
        </a>
    <?php endif; ?>

    <a href="logout.php" class="btn btn-outline-light w-100 mt-3">
        Logout
    </a>
</div>

</body>
</html>
