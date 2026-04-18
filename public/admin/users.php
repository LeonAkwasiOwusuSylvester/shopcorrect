<?php
require_once __DIR__ . "/../../app/config/db.php";

// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) session_start();

// Update: Allow Super Admin, Country Agent, and Support
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['supadmin', 'country_agent', 'support'])) {
    header("Location: ../login.php");
    exit;
}

// 2. HANDLE SUSPENSION, UNSUSPENSION & EMAIL (SUPER ADMIN ONLY)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['user_id'])) {
    
    // Extra Backend Security: Block agents from submitting forms
    if ($_SESSION['role'] !== 'supadmin') {
        $error_msg = "Permission denied. Only Super Admins can modify user accounts.";
    } else {
        $user_id = (int)$_POST['user_id'];
        
        // SUSPEND USER
        if ($_POST['action'] === 'suspend') {
            $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($targetUser) {
                $updateStmt = $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
                if ($updateStmt->execute([$user_id])) {
                    
                    require_once __DIR__ . "/../../app/helpers/mailer.php";
                    $to = $targetUser['email'];
                    $subject = "Important: Account Suspended";
                    $title = "Account Suspended";
                    $message = "
                        <p>Hello <b>" . htmlspecialchars($targetUser['name']) . "</b>,</p>
                        <p>Your account has been suspended due to suspicious activities on our platform.</p>
                        <p>If you believe this is a mistake, please reach out to us to resolve the issue.</p>
                    ";
                    $button = ['url' => 'mailto:support@shopcorrect.com', 'text' => 'Contact Support'];
                    
                    $mailSent = sendMail($to, $subject, $title, $message, $button, null, 'correction'); 
                    
                    if ($mailSent) {
                        $success_msg = "User suspended and notified successfully.";
                    } else {
                        $success_msg = "User suspended successfully, but the email failed to send.";
                    }
                } else {
                    $error_msg = "Failed to suspend user.";
                }
            }
        }
        
        // UNSUSPEND USER
        if ($_POST['action'] === 'unsuspend') {
            $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($targetUser) {
                $updateStmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                if ($updateStmt->execute([$user_id])) {
                    
                    require_once __DIR__ . "/../../app/helpers/mailer.php";
                    $to = $targetUser['email'];
                    $subject = "Update: Account Restored";
                    $title = "Account Restored";
                    $message = "
                        <p>Hello <b>" . htmlspecialchars($targetUser['name']) . "</b>,</p>
                        <p>Good news! Your account has been reviewed and successfully restored.</p>
                        <p>You can now log in and continue using ShopCorrect.</p>
                    ";
                    $button = ['url' => 'http://localhost/shopcorrect/public/login.php', 'text' => 'Log In Now'];
                    
                    // 'approved' triggers the 🎉 Account Active badge
                    $mailSent = sendMail($to, $subject, $title, $message, $button, null, 'approved'); 
                    
                    if ($mailSent) {
                        $success_msg = "User restored and notified successfully.";
                    } else {
                        $success_msg = "User restored successfully, but the email failed to send.";
                    }
                } else {
                    $error_msg = "Failed to restore user.";
                }
            }
        }
    }
}

// 3. DATA FETCHING
$users = $pdo->query("
    SELECT 
        u.id, u.name, u.email, u.role, u.created_at, u.status AS user_status,
        v.shop_name, v.status AS vendor_status
    FROM users u
    LEFT JOIN vendors v ON u.id = v.user_id
    ORDER BY u.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Determine column span for empty table based on role
$colspan = ($_SESSION['role'] === 'supadmin') ? 6 : 5;

// 4. INCLUDE HEADER
require_once __DIR__ . "/includes/header.php";
?>

<style>
    /* Filter Section */
    .filter-bar { background: #fff; border-radius: 20px; padding: 20px; box-shadow: 0px 10px 30px rgba(112, 144, 176, 0.1); margin-bottom: 30px; }
    .form-control, .form-select { background-color: #F4F7FE; border: 1px solid #E2E8F0; border-radius: 12px; padding: 0.6rem 1rem; font-size: 0.9rem; font-weight: 500; }
    .btn-filter { background-color: var(--shop-brand); color: white; border-radius: 12px; font-weight: 700; transition: all 0.3s; }
    .btn-filter:hover { background-color: var(--shop-accent); color: white; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(11,36,71,0.2);}

    /* Tables */
    .table-responsive { overflow: visible !important; }
    .table thead th { background: #FAFCFF; color: #A3AED0; font-size: 0.75rem; text-transform: uppercase; border-bottom: 1px solid #E2E8F0; padding: 1.2rem 1.5rem; }
    .table td { padding: 1.2rem 1.5rem; vertical-align: middle; font-size: 0.9rem; border-bottom: 1px solid #F4F7FE; }
    
    /* Badges & Avatars */
    .user-avatar { width: 42px; height: 42px; border-radius: 12px; background: #EEF2FF; color: #4318FF; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.1rem; flex-shrink: 0; }
    .badge-role { padding: 6px 12px; border-radius: 10px; font-size: 0.7rem; font-weight: 800; text-transform: capitalize; }
    .role-supadmin { background: #1B2559; color: white; }
    .role-vendor { background: #E6FAF5; color: #05CD99; }
    .role-user { background: #F4F7FE; color: #4318FF; }
    
    .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 6px; }
    .dot-approved { background: #05CD99; box-shadow: 0 0 8px rgba(5, 205, 153, 0.4); }
    .dot-pending { background: #FFB547; box-shadow: 0 0 8px rgba(255, 181, 71, 0.4); }
    .dot-suspended { background: #EE5D50; box-shadow: 0 0 8px rgba(238, 93, 80, 0.4); }

    @media print {
        .header-dropdowns, .filter-bar, .no-print, #sidebarToggle { display: none !important; }
        .main-content { margin: 0; padding: 0; }
        .glass-card { box-shadow: none; border: 1px solid #ddd; }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
    <div>
        <h6 class="text-secondary fw-bold text-uppercase mb-1" style="font-size: 11px; letter-spacing: 1px;">User Management</h6>
        <h3 class="fw-bold mb-0" style="color: var(--shop-brand);">User Directory</h3>
    </div>
    <button onclick="window.print()" class="btn btn-white border px-4 rounded-3 shadow-sm fw-bold text-secondary bg-white no-print">
        <i class="bi bi-printer-fill me-2"></i> Print Report
    </button>
</div>

<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($success_msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error_msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="filter-bar no-print">
    <form class="row g-3 align-items-center">
        <div class="col-lg-4">
            <div class="input-group">
                <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control border-start-0 ps-0" placeholder="Search name or email...">
            </div>
        </div>
        <div class="col-lg-3">
            <select class="form-select fw-bold text-secondary">
                <option value="">All Roles</option>
                <option value="supadmin">Administrators</option>
                <option value="vendor">Merchants</option>
                <option value="user">Customers</option>
            </select>
        </div>
        <div class="col-lg-3">
            <select class="form-select fw-bold text-secondary">
                <option value="">All Statuses</option>
                <option value="approved">Verified</option>
                <option value="pending">Pending</option>
                <option value="suspended">Suspended</option>
            </select>
        </div>
        <div class="col-lg-2">
            <button type="button" class="btn btn-filter w-100 py-2">Apply Filters</button>
        </div>
    </form>
</div>

<div class="glass-card p-0">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th class="ps-4">User Profile</th>
                    <th>Role & Access</th>
                    <th>Account Status</th>
                    <th>Shop Identity</th>
                    <th>Joined On</th>
                    <?php if ($_SESSION['role'] === 'supadmin'): ?>
                        <th class="text-end pe-4">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="<?= $colspan ?>" class="text-center py-5">
                        <div class="py-4 opacity-50">
                            <i class="bi bi-people display-4 text-muted mb-3 d-block"></i>
                            <h6 class="text-secondary fw-bold">Directory Empty</h6>
                            <p class="small text-muted">No users found matching your criteria.</p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td class="ps-4">
                        <div class="d-flex align-items-center">
                            <div class="user-avatar me-3">
                                <?= strtoupper(substr($u["name"], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="text-dark fw-bold mb-0 lh-1"><?= htmlspecialchars($u["name"]) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($u["email"]) ?></small>
                            </div>
                        </div>
                    </td>
                    
                    <td>
                        <?php if ($u["role"] === 'supadmin'): ?>
                            <span class="badge-role role-supadmin">Super Admin</span>
                        <?php elseif ($u["role"] === 'vendor'): ?>
                            <span class="badge-role role-vendor">Merchant</span>
                        <?php else: ?>
                            <span class="badge-role role-user">Customer</span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php if (isset($u['user_status']) && $u['user_status'] === 'suspended'): ?>
                            <span class="small fw-bold text-danger d-flex align-items-center"><span class="status-dot dot-suspended"></span> Suspended</span>
                        <?php else: ?>
                            <span class="small fw-bold text-success d-flex align-items-center"><span class="status-dot dot-approved"></span> Active</span>
                        <?php endif; ?>
                    </td>
                    
                    <td>
                        <?php if ($u["role"] === 'vendor' && $u["shop_name"]): ?>
                            <div class="text-dark small fw-bold mb-1 notranslate"><?= htmlspecialchars($u["shop_name"]) ?></div>
                            <?php if ($u["vendor_status"] === "approved"): ?>
                                <span class="small fw-bold text-success d-flex align-items-center"><span class="status-dot dot-approved"></span> Verified</span>
                            <?php else: ?>
                                <span class="small fw-bold text-warning d-flex align-items-center"><span class="status-dot dot-pending"></span> Pending</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted small opacity-50 fw-bold">N/A</span>
                        <?php endif; ?>
                    </td>
                    
                    <td>
                        <div class="text-dark small fw-bold"><?= date("M d, Y", strtotime($u["created_at"])) ?></div>
                        <div class="text-muted" style="font-size: 0.75rem;"><?= date("h:i A", strtotime($u["created_at"])) ?></div>
                    </td>
                    
                    <?php if ($_SESSION['role'] === 'supadmin'): ?>
                    <td class="text-end pe-4">
                        <div class="dropdown">
                            <button class="btn btn-light border-0 btn-sm rounded-circle p-2" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots-vertical text-muted"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                <li><a class="dropdown-item small fw-bold" href="#"><i class="bi bi-pencil-square me-2 text-primary"></i> Edit Profile</a></li>
                                <?php if ($u["role"] === 'vendor'): ?>
                                    <li><a class="dropdown-item small fw-bold" href="vendors.php?id=<?= $u['id'] ?>"><i class="bi bi-shop me-2 text-warning"></i> Audit Shop</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider opacity-25"></li>
                                
                                <li>
                                    <form method="POST" style="margin: 0;">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        
                                        <?php if (isset($u['user_status']) && $u['user_status'] === 'suspended'): ?>
                                            <input type="hidden" name="action" value="unsuspend">
                                            <button type="submit" class="dropdown-item small fw-bold text-success" onclick="return confirm('Restore this user\'s access? An email will be sent to them.');">
                                                <i class="bi bi-check-circle me-2"></i> Unsuspend User
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="action" value="suspend">
                                            <button type="submit" class="dropdown-item small fw-bold text-danger" onclick="return confirm('Are you sure you want to suspend this user? An email will be sent to them.');">
                                                <i class="bi bi-trash3 me-2"></i> Suspend User
                                            </button>
                                        <?php endif; ?>
                                        
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php 
// 5. INCLUDE FOOTER
require_once __DIR__ . "/includes/footer.php"; 
?>