<?php
require_once __DIR__ . "/../../app/config/db.php";
require_once __DIR__ . "/../../app/helpers/mailer.php"; 

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) session_start();

// Bouncer: Allow Super Admin AND Country Agent
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['supadmin', 'country_agent'])) {
    header("Location: login.php");
    exit;
}

$userRole = $_SESSION['role'];
$managedCountry = $_SESSION['managed_country'] ?? null;

// =========================================================
// ✅ SECURE DOCUMENT VIEWER PROXY (Bypasses the 403 Error!)
// =========================================================
if (isset($_GET['view_doc'])) {
    // basename() strips out slashes to prevent hackers from viewing other server files
    $fileName = basename($_GET['view_doc']); 
    $filePath = __DIR__ . "/../../storage/uploads/vendor_verification/" . $fileName;
    
    if (file_exists($filePath)) {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mime = 'application/octet-stream';
        if (in_array($ext, ['jpg', 'jpeg'])) $mime = 'image/jpeg';
        elseif ($ext === 'png') $mime = 'image/png';
        elseif ($ext === 'pdf') $mime = 'application/pdf';
        
        ob_clean(); // Clears any invisible spaces that could corrupt the image
        header("Content-Type: $mime");
        header("Content-Disposition: inline; filename=\"" . $fileName . "\"");
        readfile($filePath);
        exit;
    } else {
        die("<h3 style='color:red; text-align:center; margin-top:50px; font-family:sans-serif;'>Document file not found on server.</h3>");
    }
}

// Ensure dynamic base URL for the email buttons
$isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$protocol = $isSecure ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$basePath = (strpos($_SERVER['REQUEST_URI'], '/shopcorrect/') !== false) ? '/shopcorrect' : '';
$dynamicBaseUrl = $protocol . $host . $basePath;

// ---------------------------------------------------------
// 2. PROCESSING LOGIC (Approve, Reject, or Correction)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) || isset($_POST['reject_vendor']) || isset($_POST['request_correction']))) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }

    $vendorId = $_POST['vendor_id'];
    $action = $_POST['action'] ?? '';
    if (isset($_POST['reject_vendor']) && $_POST['reject_vendor'] == '1') $action = 'reject';
    if (isset($_POST['request_correction']) && $_POST['request_correction'] == '1') $action = 'correction';

    // Verify vendor exists and fetch details
    $vStmt = $pdo->prepare("
        SELECT v.shop_name, u.email, u.name, u.id as user_id, u.country 
        FROM vendors v 
        JOIN users u ON v.user_id = u.id 
        WHERE v.id = ?
    ");
    $vStmt->execute([$vendorId]);
    $vendor = $vStmt->fetch(PDO::FETCH_ASSOC);

    if ($vendor) {
        // SECURITY CHECK: If it's a Country Agent, ensure they are only approving a vendor from their country
        if ($userRole === 'country_agent' && $vendor['country'] !== $managedCountry) {
            $_SESSION['flash_error'] = "Permission denied. You can only manage vendors in your assigned country.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        // ✅ EXACT LINKS FOR EMAILS USING DYNAMIC DETECTOR
        $loginUrl = $dynamicBaseUrl . "/public/login.php";
        $uploadUrl = $dynamicBaseUrl . "/public/verification-upload.php";

        $vendorName = htmlspecialchars($vendor['name']);
        $shopName = htmlspecialchars($vendor['shop_name']);

        if ($action === 'approve') {
            
            // ✅ THE ZERO-COMMISSION LOGIC
            // 1. Fetch the global grace period setting
            $setStmt = $pdo->query("SELECT vendor_grace_period_months FROM settings LIMIT 1");
            $setting = $setStmt->fetch(PDO::FETCH_ASSOC);
            $graceMonths = (int)($setting['vendor_grace_period_months'] ?? 3); // Fallback to 3 if missing
            
            $commissionFreeDate = null;
            if ($graceMonths > 0) {
                // Calculate the exact expiration date (e.g. exactly 3 months from right now)
                $commissionFreeDate = date('Y-m-d H:i:s', strtotime("+$graceMonths months"));
            }

            // 2. Update the vendor profile with approval AND the new expiration date
            $updateV = $pdo->prepare("UPDATE vendors SET status = 'approved', commission_free_until = ? WHERE id = ?");
            $updateV->execute([$commissionFreeDate, $vendorId]);
            
            $pdo->prepare("UPDATE vendor_verification SET verification_status = 'approved' WHERE vendor_id = ?")->execute([$vendorId]);
            
            // 3. Create the customized welcome email
            $subject = "Vendor Account Approved - ShopCorrect";
            $title   = "Welcome Vendor 🎉";
            $message = "
                <h2 style='color: #0B2447; margin-bottom: 20px;'>Congratulations!</h2>
                <p style='font-size: 16px; color: #1e293b;'>Hello <strong>{$vendorName}</strong>,</p>
                <p style='font-size: 16px; color: #1e293b;'>Great news! Your ShopCorrect vendor application for <strong>{$shopName}</strong> has been fully approved by our compliance team.</p>
                <p style='font-size: 16px; color: #1e293b;'>You can now access your dashboard, upload products, and start selling to our global community.</p>
            ";
            
            // Add a special highlight box to the email if they get a grace period
            if ($graceMonths > 0) {
                $formattedDate = date('F jS, Y', strtotime($commissionFreeDate));
                $message .= "
                <div style='background-color: #f0fdf4; padding: 20px; border-radius: 10px; color: #166534; border: 1px solid #bbf7d0; margin: 25px 0;'>
                    <strong style='display: block; margin-bottom: 5px; font-size: 18px;'>🎁 Special Launch Bonus Activated!</strong>
                    As a new vendor, you are now in your <strong>Zero-Commission Grace Period</strong>. You will keep 100% of the profits from your sales for the next {$graceMonths} months! 
                    <br><br>This bonus automatically expires on <strong>{$formattedDate}</strong>.
                </div>
                ";
            }
            
            $button  = ['text' => 'Login to Dashboard', 'url' => $loginUrl];
            
            sendMail($vendor['email'], $subject, $title, $message, $button, null, 'approved');
            $_SESSION['flash_success'] = "Vendor approved successfully.";
            
        } elseif ($action === 'correction') {
            $reason = $_POST['rejection_reason'] ?? "Please update your documents.";
            
            $pdo->prepare("UPDATE vendors SET status = 'correction' WHERE id = ?")->execute([$vendorId]);
            $pdo->prepare("UPDATE vendor_verification SET verification_status = 'rejected', rejection_reason = ? WHERE vendor_id = ?")->execute([$reason, $vendorId]);

            $subject = "Action Needed: Vendor Application Correction";
            $title   = "Action Needed ⚠️";
            $message = "
                <h2 style='color: #0B2447; margin-bottom: 20px;'>Action Required</h2>
                <p style='font-size: 16px; color: #1e293b;'>Hello <strong>{$vendorName}</strong>,</p>
                <p style='font-size: 16px; color: #1e293b;'>Our compliance team has reviewed your application for <strong>{$shopName}</strong>. To proceed, we need you to make a few corrections to your submitted documents.</p>
                
                <div style='background-color: #fffbeb; padding: 20px; border-radius: 10px; color: #b45309; border: 1px solid #fde68a; margin: 25px 0;'>
                    <strong style='display: block; margin-bottom: 5px;'>Required Correction:</strong>
                    {$reason}
                </div>
                
                <p style='font-size: 16px; color: #1e293b;'>Please click the button below to log in and upload your corrected documents.</p>
            ";
            
            $button  = ['text' => 'Update Application', 'url' => $uploadUrl];
            
            sendMail($vendor['email'], $subject, $title, $message, $button, null, 'correction');
            $_SESSION['flash_success'] = "Correction request sent to vendor.";
            
        } elseif ($action === 'reject') {
            $reason = $_POST['rejection_reason'] ?? "Application did not meet criteria.";
            
            $subject = "Update on your Vendor Application";
            $title   = "Application Declined";
            $message = "
                <h2 style='color: #0B2447; margin-bottom: 20px;'>Application Update</h2>
                <p style='font-size: 16px; color: #1e293b;'>Hello <strong>{$vendorName}</strong>,</p>
                <p style='font-size: 16px; color: #1e293b;'>Thank you for your interest in becoming a verified seller on ShopCorrect. Our compliance team has carefully reviewed your application for <strong>{$shopName}</strong>.</p>
                
                <p style='font-size: 16px; color: #1e293b;'>Unfortunately, we are unable to approve your vendor account at this time.</p>
                
                <div style='background-color: #fef2f2; padding: 20px; border-radius: 10px; color: #991b1b; border: 1px solid #fecaca; margin: 25px 0;'>
                    <strong style='display: block; margin-bottom: 5px;'>Reason for Decision:</strong>
                    {$reason}
                </div>
                
                <p style='font-size: 16px; color: #1e293b;'>To maintain a secure and trusted marketplace for our buyers, all vendors must meet our strict verification criteria. Because your application was declined, your temporary vendor profile has been removed from our system.</p>
                
                <p style='font-size: 16px; color: #1e293b;'>If you believe this was a mistake, or if your business circumstances change in the future, you are welcome to submit a brand new application with updated documentation.</p>
                
                <p style='font-size: 14px; color: #64748b; margin-top: 30px;'>Thank you for your understanding,<br><strong>The ShopCorrect Compliance Team</strong></p>
            ";
            
            sendMail($vendor['email'], $subject, $title, $message);
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$vendor['user_id']]);
            $_SESSION['flash_success'] = "Vendor application rejected and account deleted.";
        }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// 3. DATA FETCHING
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$success = $_SESSION['flash_success'] ?? null;
$error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// UPDATED QUERY: Now fetching shop_description
$sql = "
    SELECT v.id, v.shop_name, v.shop_description, v.status, u.name AS owner_name, u.email AS owner_email, u.country,
    vv.id_type, vv.id_number, vv.id_front_image, vv.selfie_with_id, vv.business_certificate_file
    FROM vendors v
    JOIN users u ON v.user_id = u.id
    LEFT JOIN vendor_verification vv ON vv.vendor_id = v.id
    WHERE v.status = 'pending' OR vv.verification_status = 'pending'
";

$params = [];

// If Country Agent, only show pending vendors from their specific country
if ($userRole === 'country_agent') {
    $sql .= " AND u.country = ?";
    $params[] = $managedCountry;
}

$sql .= " GROUP BY v.id ORDER BY v.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. INCLUDE HEADER
require_once __DIR__ . "/includes/header.php";
?>

<style>
    .table-responsive { overflow: visible !important; }
    .table thead th { background-color: #FAFCFF; color: #A3AED0; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; padding: 1.2rem 1.5rem; border-bottom: 1px solid #E2E8F0; }
    .table tbody td { padding: 1rem 1.5rem; vertical-align: middle; font-size: 0.9rem; color: var(--text-color); border-bottom: 1px solid #F4F7FE; }
    
    .shop-avatar { width: 40px; height: 40px; background: #F4F7FE; color: var(--shop-brand); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0; }
    
    .doc-btn { font-size: 0.75rem; font-weight: 600; padding: 8px 12px; border-radius: 8px; background: #F8FAFC; border: 1px solid #E2E8F0; color: #64748B; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; transition: 0.2s; white-space: nowrap; }
    .doc-btn:hover { background: #E2E8F0; color: var(--shop-brand); transform: translateY(-1px); }
    
    .btn-action { width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; border:none; transition: 0.2s; }
    .btn-approve { background: #E6FAF5; color: #05CD99; }
    .btn-approve:hover { background: #05CD99; color: white; transform: scale(1.05); }
    .btn-reject { background: #FEE2E2; color: #E31A1A; }
    .btn-reject:hover { background: #E31A1A; color: white; transform: scale(1.05); }
    .btn-warn { background: #FFF4E5; color: #FF9800; }
    .btn-warn:hover { background: #FF9800; color: white; transform: scale(1.05); }

    .desc-btn { font-size: 0.7rem; padding: 3px 8px; border-radius: 6px; background: #eef2ff; color: #3b82f6; border: none; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; transition: 0.2s; }
    .desc-btn:hover { background: #dbeafe; color: #2563eb; }

    /* Modal Enhancements */
    .preview-iframe { width: 100%; height: 65vh; border: none; border-radius: 12px; background: #f8fafc; }
    .modal-content { border-radius: 20px; overflow: hidden; border: none; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h6 class="text-secondary fw-bold text-uppercase mb-1" style="font-size: 11px; letter-spacing: 1px;">Compliance & Audits</h6>
        <h3 class="fw-bold mb-0" style="color: var(--shop-brand);">Vendor Onboarding</h3>
    </div>
    <div class="bg-white px-3 py-2 rounded-4 shadow-sm border d-inline-flex align-items-center">
        <div class="bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center me-2" style="width:24px;height:24px;">
            <i class="bi bi-shield-exclamation small"></i>
        </div>
        <span class="fw-bold small text-muted">Pending Audits: <?= count($vendors) ?></span>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 alert-dismissible fade show fw-bold">
        <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4 alert-dismissible fade show fw-bold">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="glass-card p-0">
    <?php if (empty($vendors)): ?>
        <div class="text-center py-5">
            <i class="bi bi-clipboard-check text-muted opacity-25 d-block mb-3" style="font-size: 4rem;"></i>
            <h5 class="fw-bold text-secondary">All caught up! 🎉</h5>
            <p class="text-muted small">There are no pending vendor applications at the moment.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Shop Directory</th>
                        <th>Owner Profile</th>
                        <th>Verification Documents</th>
                        <th class="text-center">Government ID</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendors as $v): ?>
                    <?php 
                        $safeShopName = htmlspecialchars($v['shop_name'], ENT_QUOTES, 'UTF-8'); 
                        $safeDesc = htmlspecialchars($v['shop_description'] ?? 'No description provided.', ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <div class="shop-avatar me-3 notranslate"><?= strtoupper(substr($v['shop_name'], 0, 1)) ?></div>
                                <div>
                                    <div class="fw-bold text-dark notranslate"><?= htmlspecialchars($v['shop_name']) ?></div>
                                    <div class="d-flex align-items-center gap-2 mt-1">
                                        <div class="text-muted small fw-bold">ID: #<?= $v['id'] ?></div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        
                        <td>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($v['owner_name']) ?></div>
                            <div class="text-muted small"><?= htmlspecialchars($v['owner_email']) ?></div>
                            <?php if($userRole === 'supadmin'): ?>
                                <div class="badge bg-light text-secondary mt-1"><i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars($v['country'] ?? 'Global') ?></div>
                            <?php endif; ?>
                        </td>
                        
                        <td>
                            <div class="d-flex flex-wrap gap-2">
                                <?php if($v['id_front_image']): ?>
                                    <button type="button" onclick="previewDocument('?view_doc=<?= urlencode($v['id_front_image']) ?>', 'ID Card - <?= $safeShopName ?>')" class="doc-btn shadow-sm">
                                        <i class="bi bi-eye text-primary"></i> ID Card
                                    </button>
                                <?php endif; ?>
                                
                                <?php if($v['selfie_with_id']): ?>
                                    <button type="button" onclick="previewDocument('?view_doc=<?= urlencode($v['selfie_with_id']) ?>', 'Selfie - <?= $safeShopName ?>')" class="doc-btn shadow-sm">
                                        <i class="bi bi-eye text-success"></i> Selfie
                                    </button>
                                <?php endif; ?>
                                
                                <?php if($v['business_certificate_file']): ?>
                                    <button type="button" onclick="previewDocument('?view_doc=<?= urlencode($v['business_certificate_file']) ?>', 'Certificate - <?= $safeShopName ?>')" class="doc-btn shadow-sm">
                                        <i class="bi bi-eye text-warning"></i> Certificate
                                    </button>
                                <?php endif; ?>
                                
                                <button type="button" onclick="showDescription('<?= $safeShopName ?>', '<?= $safeDesc ?>')" class="doc-btn shadow-sm">
                                    <i class="bi bi-card-text text-secondary"></i> Shop Details
                                </button>
                            </div>
                        </td>
                        
                        <td class="text-center">
                            <div class="badge bg-light text-dark border p-2 rounded-3">
                                <div class="small fw-bold text-uppercase opacity-75" style="font-size: 0.65rem; letter-spacing: 0.5px;">
                                    <?= htmlspecialchars(str_replace('_', ' ', $v['id_type'] ?? 'N/A')) ?>
                                </div>
                                <div class="fw-bold mt-1"><?= htmlspecialchars($v['id_number'] ?? 'Not Provided') ?></div>
                            </div>
                        </td>
                        
                        <td class="text-end pe-4">
                            <form method="POST" class="d-inline-flex gap-2 mb-0">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="vendor_id" value="<?= $v["id"] ?>">
                                
                                <button type="submit" name="action" value="approve" class="btn-action btn-approve shadow-sm" title="Approve Vendor" onclick="return confirm('Are you sure you want to completely APPROVE this vendor?')">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                                
                                <button type="button" class="btn-action btn-warn shadow-sm" onclick="openActionModal(<?= $v['id'] ?>, 'correction')" title="Request Document Correction">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </button>
                                
                                <button type="button" class="btn-action btn-reject shadow-sm" onclick="openActionModal(<?= $v['id'] ?>, 'reject')" title="Reject & Delete Application">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content shadow-lg">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark" id="previewTitle">Document Preview</h5>
                <button type="button" class="btn-close bg-light rounded-circle p-2" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <iframe id="previewFrame" src="" class="preview-iframe shadow-sm"></iframe>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="descModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header border-bottom-0 pt-4 px-4 pb-2">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-shop text-primary"></i> <span id="descShopName">Shop Details</span>
                </h5>
                <button type="button" class="btn-close bg-light rounded-circle p-2" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 pb-4 pt-2">
                <p class="text-muted small fw-bold text-uppercase letter-spacing-1 mb-2">Vendor Intended Products & Description</p>
                <div class="bg-light p-3 rounded-3 border" style="font-size: 0.95rem; color: #334155; line-height: 1.6;" id="descContent">
                    Loading description...
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="actionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header border-bottom-0 pt-4 px-4">
                <h5 class="modal-title fw-bold" id="actionModalTitle">Action Required</h5>
                <button type="button" class="btn-close bg-light rounded-circle p-2" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="actionForm" method="POST">
                <div class="modal-body px-4 pb-4 pt-2">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="vendor_id" id="actionVendorId">
                    <input type="hidden" name="reject_vendor" id="actionReject" value="0">
                    <input type="hidden" name="request_correction" id="actionCorrection" value="0">
                    
                    <p class="text-muted small mb-3" id="actionModalDesc">Please provide a reason for this action.</p>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">Message to Vendor</label>
                        <textarea name="rejection_reason" id="actionReason" class="form-control bg-light border-0" rows="4" required placeholder="Type your detailed reason here..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top-0 px-4 pb-4 pt-0">
                    <button type="button" class="btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn rounded-pill px-4 fw-bold" id="actionSubmitBtn">Confirm Action</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/includes/footer.php"; ?>

<script>
    function previewDocument(url, title) {
        document.getElementById('previewTitle').innerText = title;
        document.getElementById('previewFrame').src = url;
        var previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
        previewModal.show();
    }

    document.getElementById('previewModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('previewFrame').src = '';
    });

    function showDescription(shopName, description) {
        document.getElementById('descShopName').innerText = shopName;
        document.getElementById('descContent').innerText = description;
        var descModal = new bootstrap.Modal(document.getElementById('descModal'));
        descModal.show();
    }

    function openActionModal(vendorId, actionType) {
        document.getElementById('actionVendorId').value = vendorId;
        document.getElementById('actionReason').value = ''; 
        
        const titleEl = document.getElementById('actionModalTitle');
        const descEl = document.getElementById('actionModalDesc');
        const btnEl = document.getElementById('actionSubmitBtn');
        
        document.getElementById('actionReject').value = (actionType === 'reject') ? '1' : '0';
        document.getElementById('actionCorrection').value = (actionType === 'correction') ? '1' : '0';

        if (actionType === 'reject') {
            titleEl.innerText = "Reject Application";
            titleEl.className = "modal-title fw-bold text-danger";
            descEl.innerText = "Warning: This will permanently delete the vendor account.";
            btnEl.className = "btn btn-danger rounded-pill px-4 fw-bold";
            btnEl.innerText = "Reject & Delete";
        } else {
            titleEl.innerText = "Request Correction";
            titleEl.className = "modal-title fw-bold text-warning";
            descEl.innerText = "The vendor will receive an email asking them to log in and re-upload documents.";
            btnEl.className = "btn btn-warning text-white rounded-pill px-4 fw-bold";
            btnEl.innerText = "Send Request";
        }

        var actionModal = new bootstrap.Modal(document.getElementById('actionModal'));
        actionModal.show();
    }
</script>