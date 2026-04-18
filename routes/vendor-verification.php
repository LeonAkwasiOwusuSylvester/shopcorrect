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
    $_SESSION["flash_error"] = "Invalid request.";
    header("Location: ../public/vendor-verification.php");
    exit;
}

$vendorUserId = (int) $_SESSION["registration_user"];

/*
|--------------------------------------------------------------------------
| 3. INPUT VALIDATION
|--------------------------------------------------------------------------
*/

$idType   = trim($_POST["id_type"] ?? "");
$idNumber = trim($_POST["id_number"] ?? "");

$allowedIdTypes = ['ghana_card','passport','drivers_license'];

if (!$idType || !$idNumber) {
    $_SESSION["flash_error"] = "All required fields must be completed.";
    header("Location: ../public/vendor-verification.php");
    exit;
}

if (!in_array($idType, $allowedIdTypes, true)) {
    $_SESSION["flash_error"] = "Invalid ID type selected.";
    header("Location: ../public/vendor-verification.php");
    exit;
}

if (strlen($idNumber) < 5) {
    $_SESSION["flash_error"] = "Invalid ID number.";
    header("Location: ../public/vendor-verification.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| 4. SECURE FILE UPLOAD FUNCTION
|--------------------------------------------------------------------------
*/

function uploadSecureFile($fileKey, $required = true)
{
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]["error"] === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            throw new Exception("Missing required file: " . $fileKey);
        }
        return null;
    }

    $file = $_FILES[$fileKey];

    if ($file["error"] !== UPLOAD_ERR_OK) {
        throw new Exception("Upload failed for: " . $fileKey);
    }

    if ($file["size"] > 5 * 1024 * 1024) {
        throw new Exception("File too large. Maximum size is 5MB.");
    }

    $allowedMime = [
        "image/jpeg"      => "jpg",
        "image/png"       => "png",
        "image/webp"      => "webp",
        "application/pdf" => "pdf"
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file["tmp_name"]);

    if (!isset($allowedMime[$mime])) {
        throw new Exception("Invalid file type uploaded.");
    }

    $extension = $allowedMime[$mime];
    $newName   = bin2hex(random_bytes(16)) . "." . $extension;

    $uploadDir = __DIR__ . "/../storage/uploads/vendor_verification/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $destination = $uploadDir . $newName;

    if (!move_uploaded_file($file["tmp_name"], $destination)) {
        throw new Exception("Failed to store uploaded file.");
    }

    return $newName;
}

if (empty($_POST["confirm_details"])) {
    $_SESSION["flash_error"] = "You must confirm that your details are correct.";
    header("Location: ../public/vendor-verification.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| 5. PROCESS
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    // Fetch vendor record
    $stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ? LIMIT 1");
    $stmt->execute([$vendorUserId]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vendor) {
        throw new Exception("Vendor record not found.");
    }

    $vendorId = (int) $vendor["id"];

    // Prevent duplicate submission
    $check = $pdo->prepare("SELECT id FROM vendor_verification WHERE vendor_id = ? LIMIT 1");
    $check->execute([$vendorId]);

    if ($check->fetch()) {
        throw new Exception("Verification already submitted.");
    }

    // Upload files
    $idFront     = uploadSecureFile("id_front_image", true);
    $idBack      = uploadSecureFile("id_back_image", false);
    $selfie      = uploadSecureFile("selfie_with_id", true);
    $certificate = uploadSecureFile("business_certificate_file", false);

    // Insert verification record
    $pdo->prepare("
        INSERT INTO vendor_verification
        (vendor_id, id_type, id_number, id_front_image,
         id_back_image, selfie_with_id, business_certificate_file,
         verification_status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ")->execute([
        $vendorId,
        $idType,
        $idNumber,
        $idFront,
        $idBack,
        $selfie,
        $certificate
    ]);

    // Ensure vendor status remains pending
    $pdo->prepare("
        UPDATE vendors
        SET status = 'pending'
        WHERE id = ?
    ")->execute([$vendorId]);

    $pdo->commit();

    // Clear session onboarding markers
    unset($_SESSION["registration_user"]);
    unset($_SESSION["registration_role"]);
    unset($_SESSION["csrf_token"]);

   $_SESSION["vendor_application_submitted"] = true;

  header("Location: ../public/vendor-application-submitted.php");
 exit;


} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION["flash_error"] = $e->getMessage();
    header("Location: ../public/vendor-verification.php");
    exit;
}
