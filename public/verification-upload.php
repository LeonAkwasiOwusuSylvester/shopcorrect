<?php
/* 1. INITIALIZE GLOBAL VARIABLES */
$steps = [
    ["label" => "Acct", "status" => "completed"],
    ["label" => "Verif", "status" => "completed"],
    ["label" => "Info", "status" => "completed"],
    ["label" => "Docs", "status" => "active"],
    ["label" => "Done", "status" => "pending"]
];
$error = "";
$rejectionReason = "";

require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/config/session.php";
require_once __DIR__ . "/../app/helpers/mailer.php"; // REQUIRED: Import mailer helper

/* 2. SECURITY GUARD */
$userId = $_SESSION["user_id"] ?? null;
if (!$userId) { 
    header("Location: login.php"); 
    exit; 
}

// Fetch vendor info AND check for previous rejection reasons
$vStmt = $pdo->prepare("
    SELECT v.id, v.status, v.shop_name, u.email, u.name, vv.rejection_reason 
    FROM vendors v 
    JOIN users u ON v.user_id = u.id
    LEFT JOIN vendor_verification vv ON v.id = vv.vendor_id 
    WHERE v.user_id = ? 
    LIMIT 1
");
$vStmt->execute([$userId]);
$vendor = $vStmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    header("Location: complete-vendor-profile.php");
    exit;
}

$vendorId = $vendor['id'];
$vendorEmail = $vendor['email'];
$shopName = htmlspecialchars($vendor['shop_name']);
$rejectionReason = $vendor['rejection_reason'] ?? "";

// Fallback just in case the 'name' column is empty or null
$vendorName = !empty($vendor['name']) ? htmlspecialchars($vendor['name']) : 'Vendor';

/* 3. FORM ACTIONS */
if (isset($_POST["back_action"])) {
    header("Location: complete-vendor-profile.php"); 
    exit;
}

/* 4. UPLOAD LOGIC */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit_verification'])) {
    
    $idType = trim($_POST['id_type'] ?? '');
    $idNumber = trim($_POST['id_number'] ?? '');

    if (empty($idType) || empty($idNumber)) {
        $error = "Please fill in all required fields.";
    } elseif (empty($_FILES['id_front_image']['name']) || empty($_FILES['selfie_with_id']['name'])) {
        $error = "ID front and Selfie are mandatory.";
    } else {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        
        // Dynamic path resolution for XAMPP/Windows or Linux
        $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . "storage" . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "vendor_verification" . DIRECTORY_SEPARATOR;
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $uploadedPaths = ['id_front' => null, 'selfie' => null, 'id_back' => null, 'biz_cert' => null];
        $uploadError = false;

        $filesToProcess = [
            'id_front' => $_FILES['id_front_image'],
            'selfie'   => $_FILES['selfie_with_id'],
            'id_back'  => $_FILES['id_back_image'] ?? null,
            'biz_cert' => $_FILES['business_certificate_file'] ?? null
        ];

        foreach ($filesToProcess as $key => $file) {
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if (!in_array($ext, $allowedExtensions)) {
                    $error = "Invalid file type: $ext. Use JPG, PNG, or PDF.";
                    $uploadError = true; break;
                }

                $newFileName = "v" . $vendorId . "_" . $key . "_" . time() . "." . $ext;
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $newFileName)) {
                    $uploadedPaths[$key] = $newFileName;
                } else {
                    $error = "Storage error. Please check folder permissions.";
                    $uploadError = true; break;
                }
            }
        }

        if (!$uploadError) {
            try {
                $pdo->beginTransaction();

                // UPSERT: Insert or update vendor_verification table
                $stmt = $pdo->prepare("
                    INSERT INTO vendor_verification 
                    (vendor_id, id_type, id_number, id_front_image, selfie_with_id, id_back_image, business_certificate_file, verification_status, rejection_reason, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NULL, NOW())
                    ON DUPLICATE KEY UPDATE
                    id_type = VALUES(id_type), id_number = VALUES(id_number),
                    id_front_image = VALUES(id_front_image), selfie_with_id = VALUES(selfie_with_id),
                    id_back_image = VALUES(id_back_image), business_certificate_file = VALUES(business_certificate_file),
                    verification_status = 'pending', rejection_reason = NULL
                ");
                
                $stmt->execute([
                    $vendorId, $idType, $idNumber, 
                    $uploadedPaths['id_front'], $uploadedPaths['selfie'], 
                    $uploadedPaths['id_back'], $uploadedPaths['biz_cert']
                ]);

                // Reset main vendor status to pending
                $pdo->prepare("UPDATE vendors SET status = 'pending' WHERE id = ?")->execute([$vendorId]);

                // --- OFFICIAL EMAIL NOTIFICATION: UNDER REVIEW ---
                $subject = "Application Received: Verification Under Review";
                
                // Since mailer.php already has the header/footer, we only send the inner content
                $emailContent = "
                    <h2 style='color: #0B2447; margin-bottom: 20px;'>Documents Received</h2>
                    <p style='font-size: 16px; color: #1e293b;'>Hello <strong>{$vendorName}</strong>,</p>
                    <p style='font-size: 16px; color: #1e293b;'>This is to confirm that we have successfully received your verification documents for <strong>{$shopName}</strong>.</p>
                    
                    <div style='background-color: #f0f7ff; padding: 20px; border-radius: 10px; color: #0B2447; border: 1px solid #cfe2ff; margin: 25px 0;'>
                        <strong>Current Status:</strong> Under Review ⚖️
                    </div>
                    
                    <p style='font-size: 16px; color: #1e293b;'>Our compliance team will now verify your ID and business details. This process typically takes 24-48 hours.</p>
                    <p style='font-size: 14px; color: #64748b;'>You will receive another email once a decision has been made.</p>
                ";
                
                // FIXED: The order is now Email, Subject, Body, and the 4th argument at the end.
                sendMail($vendorEmail, $subject, $emailContent, $vendorName);

                $pdo->commit();
                $_SESSION["vendor_application_submitted"] = true;
                header("Location: verification-finish.php");
                exit;

            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vendor Verification | ShopCorrect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --brand-primary: #0B2447; --brand-hover: #19376D; --border-color: #E2E8F0; }
        body {
            background: linear-gradient(rgba(11, 36, 71, 0.85), rgba(11, 36, 71, 0.85)), 
                        url('https://images.unsplash.com/photo-1557821552-17105176677c?auto=format&fit=crop&w=1920&q=80');
            background-size: cover; background-position: center; background-attachment: fixed;
            font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0;
        }
        body::before {
            content: "SHOPCORRECT"; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 15vw; font-weight: 900; color: rgba(255, 255, 255, 0.03); white-space: nowrap; pointer-events: none; letter-spacing: 2rem; z-index: 0;
        }
        .auth-card-container { z-index: 1; width: 100%; max-width: 680px; padding: 15px; }
        .auth-card { border: none; border-radius: 24px; background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(15px); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6); padding: 2rem 2.5rem; }
        .brand-logo-text { font-weight: 800; font-size: 1.5rem; letter-spacing: -1px; color: var(--brand-primary); margin: 0; }
        .steps-wrapper { margin-bottom: 25px; padding: 0 5px; }
        .steps-list { list-style: none; padding: 0; margin: 0; display: flex; justify-content: space-between; position: relative; }
        .steps-list::before { content: ''; position: absolute; top: 9px; left: 0; width: 100%; height: 2px; background: var(--border-color); z-index: 0; }
        .step-item { position: relative; z-index: 1; text-align: center; flex: 1; }
        .step-dot { width: 18px; height: 18px; border-radius: 50%; background: #fff; border: 2px solid var(--border-color); margin: 0 auto 4px auto; }
        .step-text { font-size: 0.65rem; color: #64748b; font-weight: 600; text-transform: uppercase; display: block; }
        .step-item.active .step-dot { background: var(--brand-primary); border-color: var(--brand-primary); transform: scale(1.1); }
        .step-item.completed .step-dot { background: var(--brand-primary); border-color: var(--brand-primary); }
        .form-control, .form-select { border-radius: 10px; padding: 10px 14px; border: 1.5px solid #E2E8F0; background-color: #F8FAFC; font-size: 14px; }
        .btn-brand { background-color: var(--brand-primary); color: #fff; border-radius: 10px; padding: 12px; font-weight: 700; border: none; transition: 0.3s; }
        .btn-brand:hover:not(:disabled) { background-color: var(--brand-hover); transform: translateY(-2px); }
        .btn-brand:disabled { background: #94a3b8; cursor: not-allowed; }
        .btn-ghost { background: transparent; border: 1.5px solid var(--border-color); color: #64748b; padding: 12px; border-radius: 10px; font-weight: 600; text-decoration: none; display: flex; align-items: center; justify-content: center; }
        .rejection-alert { background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; border-radius: 12px; padding: 15px; margin-bottom: 25px; font-size: 0.85rem; }

        /* Google Translate Auto-Hide Overrides */
        body > .skiptranslate { display: none !important; }
        .goog-te-banner-frame.skiptranslate { display: none !important; }
        body { top: 0px !important; }
        .notranslate { color: inherit !important; }
    </style>
</head>
<body>

<div class="auth-card-container">
    <div class="card auth-card">
        <div class="d-flex align-items-center justify-content-center gap-2 mb-3">
            <img src="/assets/images/logo_b.png" height="60" alt="Logo" onerror="this.style.display='none'">
            <span class="brand-logo-text notranslate">ShopCorrect</span>
        </div>

        <div class="steps-wrapper">
            <ul class="steps-list">
                <?php foreach(($steps ?? []) as $step): ?>
                    <li class="step-item <?= $step['status'] ?>">
                        <div class="step-dot"></div>
                        <span class="step-text"><?= $step['label'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="text-center mb-4">
            <h4 class="fw-bold text-dark mb-1">Identity Verification</h4>
            <p class="text-muted small">Upload your documents for account review.</p>
        </div>

        <?php if($vendor['status'] === 'rejected' && !empty($rejectionReason)): ?>
            <div class="rejection-alert">
                <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Action Required</div>
                Your previous application was rejected for the following reason:<br>
                <strong>"<?= htmlspecialchars($rejectionReason) ?>"</strong>
            </div>
        <?php endif; ?>

        <?php if($error): ?>
            <div class="alert alert-danger border-0 small py-2 text-center mb-3">
                <i class="bi bi-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-secondary">ID Type</label>
                    <select name="id_type" class="form-select" required>
                        <option value="">Select ID Type</option>
                        <option value="ghana_card">Ghana Card</option>
                        <option value="passport">Passport</option>
                        <option value="drivers_license">Driver's License</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-secondary">ID Number</label>
                    <input type="text" name="id_number" class="form-control" placeholder="GHA-XXXXXXX-X" required>
                </div>
            </div>

            <h6 class="fw-bold mb-3 small text-secondary" style="border-bottom: 1.5px solid var(--border-color); padding-bottom: 8px;">Upload Documents</h6>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-secondary">ID Front Image <span class="text-danger">*</span></label>
                    <input type="file" name="id_front_image" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-secondary">ID Back Image (Optional)</label>
                    <input type="file" name="id_back_image" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                </div>
                <div class="col-12">
                    <label class="form-label small fw-bold text-secondary">Selfie Holding ID <span class="text-danger">*</span></label>
                    <input type="file" name="selfie_with_id" class="form-control" accept=".jpg,.jpeg,.png" required>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-bold text-secondary">Business Certificate (Optional)</label>
                    <input type="file" name="business_certificate_file" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                </div>
            </div>

            <div class="p-3 bg-light rounded-3 mt-4 mb-4 border">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="confirmDetails" required>
                    <label class="form-check-label small text-muted" for="confirmDetails">
                        I confirm that all documents provided belong to me and are valid.
                    </label>
                </div>
            </div>

            <div class="row g-2">
                <div class="col-4">
                    <button type="submit" name="back_action" class="btn btn-ghost w-100" formnovalidate>Back</button>
                </div>
                <div class="col-8">
                    <button type="submit" name="submit_verification" id="submitBtn" class="btn btn-brand w-100 shadow-sm" disabled>Submit Application</button>
                </div>
            </div>
        </form>
    </div>

    <p class="text-center mt-4 text-white-50 small fw-medium">
       &copy; <?= date("Y") ?> <span class="notranslate">ShopCorrect</span>. Shop Smart. Shop Correct. <i class="bi bi-check-circle-fill text-success mx-1"></i>
    </p>
</div>

<div id="google_translate_element" style="display:none;"></div>
<script type="text/javascript">
    function googleTranslateElementInit() {
        new google.translate.TranslateElement({
            pageLanguage: 'en', 
            includedLanguages: 'en,fr,sw,de,zh-CN,es', 
            autoDisplay: false
        }, 'google_translate_element');
    }
</script>
<script type="text/javascript" src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>

<script>
    document.getElementById('confirmDetails').addEventListener('change', function() {
        document.getElementById('submitBtn').disabled = !this.checked;
    });
</script>

</body>
</html>