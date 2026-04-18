<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";

/*
|--------------------------------------------------------------------------
| METHOD CHECK
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../public/register.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| SESSION CHECK
|--------------------------------------------------------------------------
*/
if (
    !isset($_SESSION["registration_user"]) ||
    !isset($_SESSION["registration_role"]) ||
    $_SESSION["registration_role"] !== "buyer"
) {
    header("Location: ../public/register.php");
    exit;
}

$userId = (int) $_SESSION["registration_user"];

/*
|--------------------------------------------------------------------------
| CSRF VALIDATION
|--------------------------------------------------------------------------
*/
if (
    empty($_POST["csrf_token"]) ||
    empty($_SESSION["csrf_token"]) ||
    !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
) {
    $_SESSION["flash_error"] = "Invalid request.";
    header("Location: ../public/buyer-info.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| INPUT VALIDATION
|--------------------------------------------------------------------------
*/
$fullName = trim($_POST["full_name"] ?? "");
$country  = trim($_POST["country"] ?? "");
$phone    = trim($_POST["phone"] ?? "");
$address  = trim($_POST["address"] ?? "");
$confirm  = isset($_POST["confirm_info"]);
$marketingOptIn = isset($_POST["marketing_opt_in"]) ? 1 : 0;

if (!$fullName || !$country || !$phone || !$address) {
    $_SESSION["flash_error"] = "Please complete all required fields.";
    header("Location: ../public/buyer-info.php");
    exit;
}

if (!preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
    $_SESSION["flash_error"] = "Invalid phone number format.";
    header("Location: ../public/buyer-info.php");
    exit;
}

if (!$confirm) {
    $_SESSION["flash_error"] = "Please confirm your information.";
    header("Location: ../public/buyer-info.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| UPDATE USER
|--------------------------------------------------------------------------
*/
try {

    $stmt = $pdo->prepare("
        UPDATE users
        SET name = ?,
            country = ?,
            phone = ?,
            address = ?,
            marketing_opt_in = ?
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $fullName,
        $country,
        $phone,
        $address,
        $marketingOptIn,
        $userId
    ]);

    /*
    |--------------------------------------------------------------------------
    | AUTO LOGIN BUYER
    |--------------------------------------------------------------------------
    */

    $_SESSION["user_id"] = $userId;
    $_SESSION["role"]    = "buyer";
    $_SESSION["name"]    = $fullName;

    /*
    |--------------------------------------------------------------------------
    | CLEAR REGISTRATION SESSION
    |--------------------------------------------------------------------------
    */

    unset($_SESSION["registration_user"]);
    unset($_SESSION["registration_role"]);
    unset($_SESSION["csrf_token"]);

    header("Location: ../public/buyer-success.php");
    exit;

} catch (PDOException $e) {

    $_SESSION["flash_error"] = "Unable to complete registration. Please try again.";
    header("Location: ../public/buyer-info.php");
    exit;
}
