<?php
require_once __DIR__ . "/../../app/config/db.php";

if (session_status() === PHP_SESSION_NONE) session_start();

// Security Check: Make sure someone is actually logged in
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['supadmin', 'country_agent', 'support'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
$managedCountry = $_SESSION['managed_country'] ?? 'Global';

$successMsg = '';
$errorMsg = '';

// =========================================================
// ACTION HANDLERS
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ACTION A: Update Profile Info
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);

        if (empty($name) || empty($email)) {
            $errorMsg = "Name and Email cannot be empty.";
        } else {
            // Check if email is taken by someone else
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) {
                $errorMsg = "This email is already in use by another account.";
            } else {
                $update = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                if ($update->execute([$name, $email, $userId])) {
                    $_SESSION['name'] = $name; // Update session name
                    $successMsg = "Profile updated successfully.";
                } else {
                    $errorMsg = "Failed to update profile.";
                }
            }
        }
    }

    // ACTION B: Change Password
    if (isset($_POST['change_password'])) {
        $currentPass = $_POST['current_password'];
        $newPass = $_POST['new_password'];
        $confirmPass = $_POST['confirm_password'];

        if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
            $errorMsg = "All password fields are required.";
        } elseif ($newPass !== $confirmPass) {
            $errorMsg = "New passwords do not match.";
        } elseif (strlen($newPass) < 8) {
            $errorMsg = "New password must be at least 8 characters long.";
        } else {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($currentPass, $user['password_hash'])) {
                // Update to new password
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                if ($update->execute([$newHash, $userId])) {
                    $successMsg = "Password changed successfully. Please use your new password next time you log in.";
                } else {
                    $errorMsg = "Failed to update password.";
                }
            } else {
                $errorMsg = "Current password is incorrect.";
            }
        }
    }
}

// Fetch current user data to fill the form
$stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

require_once __DIR__ . "/includes/header.php";
?>

<style>
    .profile-card { background: #fff; border-radius: 20px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; }
    .avatar-lg { width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: bold; color: white; background: var(--shop-brand); margin-bottom: 15px; }
    
    .role-badge-lg { display: inline-block; padding: 6px 16px; border-radius: 30px; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
    .role-supadmin { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
    .role-country { background: #e0e7ff; color: #2563eb; border: 1px solid #bfdbfe; }
    .role-support { background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; }
    
    /* Input group styling for seamless borders */
    .input-group-seamless .form-control { border-right: none; }
    .input-group-seamless .form-control:focus { box-shadow: none; border-color: #dee2e6; }
    .input-group-seamless .btn-toggle { background-color: var(--bs-light); border: 1px solid #dee2e6; border-left: none; color: #6c757d; border-radius: 0 0.375rem 0.375rem 0; padding: 0 15px; }
    .input-group-seamless:focus-within { box-shadow: 0 0 0 0.25rem rgba(11, 36, 71, 0.25); border-radius: 0.375rem; }
    .input-group-seamless:focus-within .form-control, .input-group-seamless:focus-within .btn-toggle { border-color: var(--shop-brand); }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h6 class="text-secondary fw-bold text-uppercase mb-1" style="font-size: 11px; letter-spacing: 1px;">Security & Personalization</h6>
        <h3 class="fw-bold mb-0" style="color: var(--shop-brand);">My Account</h3>
    </div>
</div>

<?php if ($successMsg): ?>
    <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 alert-dismissible fade show">
        <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($successMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($errorMsg): ?>
    <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4 alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($errorMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="profile-card text-center mb-4">
            <div class="d-flex justify-content-center">
                <div class="avatar-lg"><?= strtoupper(substr($currentUser['name'], 0, 2)) ?></div>
            </div>
            <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($currentUser['name']) ?></h4>
            <p class="text-muted small mb-3"><?= htmlspecialchars($currentUser['email']) ?></p>
            
            <?php if ($userRole === 'supadmin'): ?>
                <div class="role-badge-lg role-supadmin"><i class="bi bi-star-fill me-1"></i> Super Admin</div>
            <?php elseif ($userRole === 'country_agent'): ?>
                <div class="role-badge-lg role-country"><i class="bi bi-map-fill me-1"></i> <?= htmlspecialchars($managedCountry) ?> Agent</div>
            <?php elseif ($userRole === 'support'): ?>
                <div class="role-badge-lg role-support"><i class="bi bi-headset me-1"></i> Support Agent</div>
            <?php endif; ?>
        </div>

        <div class="profile-card">
            <h5 class="fw-bold mb-4" style="color: var(--shop-brand);">Update Details</h5>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Full Name</label>
                    <input type="text" name="name" class="form-control bg-light" value="<?= htmlspecialchars($currentUser['name']) ?>" required>
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted">Email Address</label>
                    <input type="email" name="email" class="form-control bg-light" value="<?= htmlspecialchars($currentUser['email']) ?>" required>
                </div>
                <button type="submit" name="update_profile" class="btn w-100 fw-bold text-white py-2" style="background: var(--shop-brand); border-radius: 10px;">Save Changes</button>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="profile-card h-100">
            <div class="d-flex align-items-center mb-4">
                <div class="bg-danger bg-opacity-10 text-danger rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px; font-size: 1.2rem;">
                    <i class="bi bi-shield-lock-fill"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0" style="color: var(--shop-brand);">Security Settings</h5>
                    <small class="text-muted">Update your password to keep your account secure.</small>
                </div>
            </div>

            <form method="POST">
                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted">Current Password</label>
                    <div class="input-group input-group-seamless">
                        <input type="password" name="current_password" id="current_password" class="form-control bg-light py-2" placeholder="Enter your current password" required>
                        <button type="button" class="btn btn-toggle" onclick="toggleVisibility('current_password', this)">
                            <i class="bi bi-eye-fill"></i>
                        </button>
                    </div>
                </div>
                
                <hr class="text-muted opacity-25 mb-4">

                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">New Password</label>
                    <div class="input-group input-group-seamless">
                        <input type="password" name="new_password" id="new_password" class="form-control bg-light py-2" placeholder="Minimum 8 characters" required minlength="8">
                        <button type="button" class="btn btn-toggle" onclick="toggleVisibility('new_password', this)">
                            <i class="bi bi-eye-fill"></i>
                        </button>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted">Confirm New Password</label>
                    <div class="input-group input-group-seamless">
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control bg-light py-2" placeholder="Re-type new password" required minlength="8">
                        <button type="button" class="btn btn-toggle" onclick="toggleVisibility('confirm_password', this)">
                            <i class="bi bi-eye-fill"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" name="change_password" class="btn btn-dark w-100 fw-bold py-3" style="border-radius: 12px;">
                    <i class="bi bi-key-fill me-2"></i> Update Password
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    function toggleVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        const icon = btn.querySelector("i");
        
        if (input.type === "password") {
            input.type = "text";
            icon.classList.replace("bi-eye-fill", "bi-eye-slash-fill");
            icon.style.color = "var(--shop-brand)";
        } else {
            input.type = "password";
            icon.classList.replace("bi-eye-slash-fill", "bi-eye-fill");
            icon.style.color = "";
        }
    }
</script>

<?php require_once __DIR__ . "/includes/footer.php"; ?>