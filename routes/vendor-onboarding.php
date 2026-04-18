<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";

/*
|--------------------------------------------------------------------------
| 1. METHOD & SESSION GUARD
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../public/register.php");
    exit;
}

if (
    empty($_SESSION["registration_user"]) ||
    empty($_SESSION["registration_role"]) ||
    $_SESSION["registration_role"] !== "vendor"
) {
    header("Location: ../public/register.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| 2. CSRF VALIDATION
|--------------------------------------------------------------------------
*/
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    $_SESSION["flash_error"] = "Invalid request token.";
    header("Location: ../public/complete-vendor-profile.php");
    exit;
}

$vendorUserId = (int) $_SESSION["registration_user"];

/*
|--------------------------------------------------------------------------
| 3. INPUT VALIDATION
|--------------------------------------------------------------------------
*/

$shopName         = trim($_POST["shop_name"] ?? "");
$businessType     = trim($_POST["business_type"] ?? "");
$regNumber        = trim($_POST["business_registration_number"] ?? "");
$businessPhone    = trim($_POST["business_phone"] ?? "");
$businessAddress  = trim($_POST["business_address"] ?? "");
$businessLocation = trim($_POST["business_location"] ?? "");
$description      = trim($_POST["shop_description"] ?? "");

$allowedBusinessTypes = ['individual','registered_business'];

if (
    !$shopName ||
    !$businessType ||
    !$businessPhone ||
    !$businessAddress ||
    !$businessLocation
) {
    $_SESSION["flash_error"] = "Please complete all required fields.";
    header("Location: ../public/complete-vendor-profile.php");
    exit;
}

if (!in_array($businessType, $allowedBusinessTypes, true)) {
    $_SESSION["flash_error"] = "Invalid business type selected.";
    header("Location: ../public/complete-vendor-profile.php");
    exit;
}

if ($businessType === "registered_business" && empty($regNumber)) {
    $_SESSION["flash_error"] = "Business registration number is required.";
    header("Location: ../public/complete-vendor-profile.php");
    exit;
}

if (!preg_match('/^[0-9+\-\s]{7,20}$/', $businessPhone)) {
    $_SESSION["flash_error"] = "Invalid phone number format.";
    header("Location: ../public/complete-vendor-profile.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| 4. PROCESS
|--------------------------------------------------------------------------
*/
try {

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Get Vendor Record
    |--------------------------------------------------------------------------
    */
    $stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ? LIMIT 1");
    $stmt->execute([$vendorUserId]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vendor) {
        throw new Exception("Vendor record not found.");
    }

    $vendorId = (int) $vendor["id"];

    /*
    |--------------------------------------------------------------------------
    | Generate Unique Shop Slug
    |--------------------------------------------------------------------------
    */
    $baseSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $shopName)));
    $slug = $baseSlug;
    $counter = 1;

    while (true) {
        $slugCheck = $pdo->prepare("SELECT id FROM vendors WHERE shop_slug = ? LIMIT 1");
        $slugCheck->execute([$slug]);
        if (!$slugCheck->fetch()) break;

        $slug = $baseSlug . "-" . $counter;
        $counter++;
    }

    /*
    |--------------------------------------------------------------------------
    | Update Vendors Table
    |--------------------------------------------------------------------------
    */
    $pdo->prepare("
        UPDATE vendors
        SET shop_name = ?,
            shop_slug = ?,
            shop_description = ?,
            business_type = ?,
            business_registration_number = ?,
            business_phone = ?,
            business_address = ?,
            business_location = ?
        WHERE id = ?
    ")->execute([
        $shopName,
        $slug,
        $description,
        $businessType,
        $businessType === "registered_business" ? $regNumber : null,
        $businessPhone,
        $businessAddress,
        $businessLocation,
        $vendorId
    ]);

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | Redirect to Step 3 (Verification)
    |--------------------------------------------------------------------------
    */
    header("Location: ../public/vendor-verification.php");
    exit;

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION["flash_error"] = "Unable to save business information.";
    header("Location: ../public/complete-vendor-profile.php");
    exit;
}
