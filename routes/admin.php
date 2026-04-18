<?php
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/helpers/mail.php";

/*
|--------------------------------------------------------------------------
| 1. AUTH GUARD (SUPADMIN ONLY)
|--------------------------------------------------------------------------
*/
if (
    empty($_SESSION["user_id"]) ||
    empty($_SESSION["role"]) ||
    $_SESSION["role"] !== "supadmin"
) {
    header("Location: ../public/login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| 2. CSRF PROTECTION
|--------------------------------------------------------------------------
*/
if (
    $_SERVER["REQUEST_METHOD"] !== "POST" ||
    empty($_POST["csrf_token"]) ||
    empty($_SESSION["csrf_token"]) ||
    !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
) {
    $_SESSION["flash_error"] = "Invalid request token.";
    header("Location: ../public/admin/vendors.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| 3. VALIDATE VENDOR ID
|--------------------------------------------------------------------------
*/
$vendorId = filter_input(INPUT_POST, "vendor_id", FILTER_VALIDATE_INT);

if (!$vendorId) {
    $_SESSION["flash_error"] = "Invalid vendor ID.";
    header("Location: ../public/admin/vendors.php");
    exit;
}

try {

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | FETCH VENDOR + USER
    |--------------------------------------------------------------------------
    */
    $stmt = $pdo->prepare("
        SELECT v.id, v.user_id, v.status,
               u.email, u.name
        FROM vendors v
        JOIN users u ON v.user_id = u.id
        WHERE v.id = ?
        LIMIT 1
    ");
    $stmt->execute([$vendorId]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vendor) {
        throw new Exception("Vendor not found.");
    }

    $adminId = (int) $_SESSION["user_id"];

    // Professional Vendor ID format
    $formattedVendorId = "VND-" . str_pad($vendorId, 6, "0", STR_PAD_LEFT);

    // Compliance reference number
    $referenceNumber = "SC-" . strtoupper(bin2hex(random_bytes(3)));

    /*
    |--------------------------------------------------------------------------
    | APPROVE VENDOR
    |--------------------------------------------------------------------------
    */
    if (isset($_POST["approve_vendor"])) {

        if ($vendor["status"] === "approved") {
            throw new Exception("Vendor already approved.");
        }

        $pdo->prepare("
            UPDATE vendors
            SET status = 'approved'
            WHERE id = ?
        ")->execute([$vendorId]);

        $pdo->prepare("
            UPDATE vendor_verification
            SET verification_status = 'approved',
                reviewed_by = ?,
                reviewed_at = NOW()
            WHERE vendor_id = ?
        ")->execute([$adminId, $vendorId]);

        $pdo->prepare("
            INSERT INTO admin_logs (admin_id, action, reference_id, created_at)
            VALUES (?, 'vendor_approved', ?, NOW())
        ")->execute([$adminId, $vendorId]);

        $decision = "APPROVED";
        $reasonText = "Congratulations. Your vendor account has been approved. You may now access your vendor dashboard and begin selling.";

    }

    /*
    |--------------------------------------------------------------------------
    | REJECT VENDOR
    |--------------------------------------------------------------------------
    */
    elseif (isset($_POST["reject_vendor"])) {

        $reason = trim($_POST["rejection_reason"] ?? "");

        if (!$reason) {
            throw new Exception("Rejection reason is required.");
        }

        $pdo->prepare("
            UPDATE vendors
            SET status = 'rejected'
            WHERE id = ?
        ")->execute([$vendorId]);

        $pdo->prepare("
            UPDATE vendor_verification
            SET verification_status = 'rejected',
                rejection_reason = ?,
                reviewed_by = ?,
                reviewed_at = NOW()
            WHERE vendor_id = ?
        ")->execute([$reason, $adminId, $vendorId]);

        $pdo->prepare("
            INSERT INTO admin_logs (admin_id, action, reference_id, created_at)
            VALUES (?, 'vendor_rejected', ?, NOW())
        ")->execute([$adminId, $vendorId]);

        $decision = "REJECTED";
        $reasonText = "Your vendor application was not approved.<br><br><strong>Reason:</strong> " . htmlspecialchars($reason);

    } else {
        throw new Exception("Invalid action.");
    }

    /*
    |--------------------------------------------------------------------------
    | GENERATE PDF LETTER
    |--------------------------------------------------------------------------
    */
    $pdfPath = generateCompliancePdf(
        $vendor["name"],
        $formattedVendorId,
        $decision,
        strip_tags($reasonText),
        $referenceNumber
    );

    /*
    |--------------------------------------------------------------------------
    | BUILD PROFESSIONAL EMAIL TEMPLATE
    |--------------------------------------------------------------------------
    */
    $emailBody = buildVendorDecisionEmail(
        $vendor["name"],
        $formattedVendorId,
        $decision,
        $reasonText,
        $referenceNumber
    );

    /*
    |--------------------------------------------------------------------------
    | SEND EMAIL WITH PDF ATTACHMENT
    |--------------------------------------------------------------------------
    */
    sendMail(
        $vendor["email"],
        "ShopCorrect Vendor Application Decision",
        $emailBody,
        [$pdfPath => "ShopCorrect_Compliance_Letter.pdf"]
    );

    $pdo->commit();

    $_SESSION["flash_success"] = "Vendor {$decision} successfully.";

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION["flash_error"] = $e->getMessage();
}

header("Location: ../public/admin/vendors.php");
exit;
