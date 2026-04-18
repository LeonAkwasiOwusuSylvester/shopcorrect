<?php
require_once __DIR__ . "/../../app/config/db.php";
require_once __DIR__ . "/../../app/config/session.php";
require_once __DIR__ . "/../../app/helpers/mailer.php";

// 1. STRICT SECURITY: Only Super Admin can send bulk emails
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'supadmin') {
    header("Location: ../login.php");
    exit;
}

// ================================================================
// ✅ DYNAMIC URL DETECTOR
// ================================================================
$isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$protocol = $isSecure ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$basePath = (strpos($_SERVER['REQUEST_URI'], '/shopcorrect/') !== false) ? '/shopcorrect' : '';
$dynamicBaseUrl = $protocol . $host . $basePath;

// 2. FETCH LATEST 4 PRODUCTS
$prodStmt = $pdo->query("
    SELECT id, name, price, sale_price, discount_percent, image 
    FROM products 
    WHERE status = 'active' AND is_deleted = 0 AND stock > 0 
    ORDER BY created_at DESC 
    LIMIT 4
");
$latestProducts = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

// 3. HANDLE CAMPAIGN SENDING
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_campaign'])) {
    
    if (count($latestProducts) < 2) {
        $_SESSION['error'] = "You need at least 2 active products to send a campaign.";
        header("Location: marketing.php");
        exit;
    }

    set_time_limit(0); // Prevent timeout

    $userStmt = $pdo->query("SELECT email, name FROM users WHERE role = 'user' AND status = 'active'");
    $buyers = $userStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$buyers) {
        $_SESSION['error'] = "No active customers found to email.";
        header("Location: marketing.php");
        exit;
    }

    $sentCount = 0;

    // --- LOOP THROUGH EVERY BUYER ---
    foreach ($buyers as $buyer) {
        
        // Build the Grid (Clean, Price-less layout for global markets)
        $gridHtml = '<div style="background-color: #f4f7fe; padding: 20px; border-radius: 12px; margin-top: 20px;">';
        $gridHtml .= '<table width="100%" cellpadding="0" cellspacing="10" border="0"><tr>';
        
        $count = 0;
        foreach($latestProducts as $p) {
            if ($count == 2) { $gridHtml .= '</tr><tr>'; } 
            
            $safeImageName = rawurlencode($p['image']);
            $imgUrl = !empty($p['image']) ? $dynamicBaseUrl . '/public/uploads/products/' . $safeImageName : 'https://placehold.co/150x150?text=Item';
            $prodUrl = $dynamicBaseUrl . '/public/product.php?id=' . $p['id'];
            
            $discountTag = '';
            if ($p['discount_percent'] > 0) {
                $discountTag = '<div style="margin-bottom: 8px;"><span style="color: #e60023; border: 1px solid #e60023; padding: 4px 8px; font-size: 11px; border-radius: 4px; font-weight: bold; display: inline-block;">-'.$p['discount_percent'].'% OFF</span></div>';
            }

            // Clean layout focused on imagery and product name
            $gridHtml .= '
            <td width="50%" valign="top" style="background: #ffffff; border-radius: 8px; overflow: hidden; text-align: center; border: 1px solid #e2e8f0;">
                <a href="'.$prodUrl.'" style="text-decoration: none; color: inherit; display: block;">
                    <div style="width: 100%; height: 180px; line-height: 180px; text-align: center; background: #ffffff; border-bottom: 1px solid #f1f5f9;">
                        <img src="'.$imgUrl.'" style="max-width: 100%; max-height: 180px; vertical-align: middle; display: inline-block; outline: none; border: none; object-fit: contain;">
                    </div>
                    <div style="padding: 15px;">
                        '.$discountTag.'
                        <div style="font-weight: 700; font-size: 14px; color: #0b2447; line-height: 1.4; max-height: 40px; overflow: hidden;">'.$p['name'].'</div>
                        <div style="margin-top: 10px; color: #3b82f6; font-size: 13px; font-weight: bold;">Shop Now &rarr;</div>
                    </div>
                </a>
            </td>';
            $count++;
        }
        $gridHtml .= '</tr></table></div>';

        $subject = "New Arrivals Now Available on ShopCorrect 🔥";
        $title = "Recommended For You";
        $msg = "<p>Hello <strong>{$buyer['name']}</strong>,</p><p>We just dropped some amazing new items. Check out the latest arrivals trending right now!</p>" . $gridHtml;
        $btn = ['text' => 'Shop All New Arrivals', 'url' => $dynamicBaseUrl . "/public/index.php"];
        
        if(sendMail($buyer['email'], $subject, $title, $msg, $btn)) {
            $sentCount++;
        }
    }

    $_SESSION['success'] = "Campaign successfully sent to $sentCount customers!";
    header("Location: marketing.php");
    exit;
}

// 4. INCLUDE HEADER
require_once __DIR__ . '/includes/header.php'; 
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h6 class="text-secondary fw-bold text-uppercase mb-1" style="font-size: 11px; letter-spacing: 1px;">Growth & Engagement</h6>
        <h3 class="fw-bold mb-0" style="color: var(--shop-brand);">Email Campaigns</h3>
    </div>
</div>

<?php if(isset($_SESSION['success'])): ?>
    <div class="alert alert-success border-0 shadow-sm rounded-3 mb-4 fw-bold">
        <i class="bi bi-check-circle-fill me-2"></i> <?= $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>
<?php if(isset($_SESSION['error'])): ?>
    <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4 fw-bold">
        <i class="bi bi-x-circle-fill me-2"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="glass-card">
            <h5 class="fw-bold mb-3" style="color: var(--shop-brand);"><i class="bi bi-send-fill text-primary me-2"></i> Send "New Arrivals" Blast</h5>
            <p class="text-muted small mb-4">This tool will automatically fetch the 4 newest products uploaded to the platform and send a beautifully formatted email to every single registered buyer.</p>
            
            <form method="POST" onsubmit="return confirm('WARNING: This will send an email to ALL registered users on the platform. Are you sure you want to proceed?');">
                <input type="hidden" name="send_campaign" value="1">
                
                <div class="bg-light p-3 rounded-3 border mb-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small fw-bold">Audience:</span>
                        <span class="fw-bold text-dark">All Registered Buyers</span>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-2 mt-2">
                        <span class="text-muted small fw-bold">Layout Style:</span>
                        <span class="fw-bold text-success"><i class="bi bi-image me-1"></i> Global / Image-First</span>
                    </div>
                </div>

                <button type="submit" class="btn w-100 py-3 fw-bold rounded-pill shadow-sm" style="background-color: #E60023; color: white; font-size: 1.1rem;">
                    <i class="bi bi-rocket-takeoff-fill me-2"></i> Launch Email Campaign
                </button>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="glass-card bg-light border">
            <h6 class="fw-bold text-muted mb-3 text-uppercase" style="font-size: 0.8rem; letter-spacing: 1px;"><i class="bi bi-eye me-2"></i> Customer Email Preview (Local View)</h6>
            
            <div class="bg-white p-4 rounded-4 shadow-sm" style="max-width: 500px; margin: 0 auto; border-top: 5px solid #E60023;">
                <div class="text-center mb-4">
                    <h4 class="fw-bold mb-1" style="color: #0b2447;">Recommended For You</h4>
                    <p class="small text-muted">We just dropped some amazing new items. Check out the latest arrivals!</p>
                </div>
                
                <?php if (empty($latestProducts)): ?>
                    <div class="text-center text-muted py-4">Not enough products to preview.</div>
                <?php else: ?>
                    <div class="row g-2">
                        <?php foreach($latestProducts as $p): 
                            $previewSafeName = rawurlencode($p['image']);
                            $previewImg = !empty($p['image']) ? $dynamicBaseUrl . '/public/uploads/products/' . $previewSafeName : 'https://placehold.co/150x150';
                        ?>
                            <div class="col-6">
                                <div class="border rounded-3 overflow-hidden text-center h-100 pb-3 bg-white">
                                    <div style="width: 100%; height: 150px; display: flex; align-items: center; justify-content: center; background: #ffffff; border-bottom: 1px solid #f8f9fa;" class="mb-2">
                                        <img src="<?= $previewImg ?>" style="max-width: 100%; max-height: 150px; object-fit: contain;">
                                    </div>
                                    <?php if ($p['discount_percent'] > 0): ?>
                                        <div class="mb-2"><span class="badge text-danger border border-danger px-2 py-1">-<?= $p['discount_percent'] ?>% OFF</span></div>
                                    <?php endif; ?>
                                    <div class="fw-bold text-dark px-2 text-truncate" style="font-size: 0.85rem;"><?= htmlspecialchars($p['name']) ?></div>
                                    <div class="small mt-1 fw-bold" style="color: var(--shop-brand);">Shop Now <i class="bi bi-arrow-right"></i></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="mt-4 text-center">
                    <a href="../index.php" target="_blank" class="btn btn-dark rounded-pill px-4 fw-bold w-100 py-2 text-decoration-none">Shop All New Arrivals</a>
                </div>
            </div>
            
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>