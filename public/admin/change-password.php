<?php
// 1. SESSION & SECURITY
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . "/../../app/config/db.php";

// Strict Role Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'supadmin') {
    header("Location: ../login.php");
    exit;
}

$message = "";
$messageType = "info";

// 2. FORM PROCESSING
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $current = $_POST["current_password"] ?? "";
    $new     = $_POST["new_password"] ?? "";

    if (strlen($new) < 8) {
        $message = "New password must be at least 8 characters long.";
        $messageType = "danger";
    } else {

        $stmt = $pdo->prepare(
            "SELECT password_hash FROM users WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$_SESSION["user_id"]]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($current, $user["password_hash"])) {
            $message = "Current password is incorrect.";
            $messageType = "danger";
        } else {

            $hash = password_hash($new, PASSWORD_DEFAULT);

            $pdo->prepare(
                "UPDATE users SET password_hash = ? WHERE id = ?"
            )->execute([$hash, $_SESSION["user_id"]]);

            $message = "Password updated successfully.";
            $messageType = "success";
        }
    }
}

// 3. INCLUDE HEADER (Brings in Sidebar, Top Nav, and CSS)
require_once __DIR__ . "/includes/header.php";
?>

<div class="container-fluid py-4">

    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6 col-xl-5">

            <div class="glass-card">
                
                <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                    <div class="bg-light rounded-circle p-2 me-3" style="width: 45px; height: 45px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-shield-lock" style="color: var(--shop-brand); font-size: 1.5rem;"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0" style="color: var(--shop-brand);">Change Password</h4>
                        <p class="text-muted small mb-0">Update your admin account password securely.</p>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show py-2 small fw-semibold" role="alert">
                        <?php if($messageType === 'success'): ?>
                            <i class="bi bi-check-circle-fill me-2"></i>
                        <?php else: ?>
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?php endif; ?>
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" style="padding: 0.8rem;" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary">
                            Current Password
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted">
                                <i class="bi bi-key"></i>
                            </span>
                            <input 
                                type="password" 
                                name="current_password" 
                                id="current_password"
                                class="form-control border-start-0 ps-0" 
                                placeholder="Enter current password"
                                required
                            >
                            <button class="btn btn-outline-secondary border-start-0" type="button" onclick="togglePassword('current_password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary">
                            New Password
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted">
                                <i class="bi bi-lock"></i>
                            </span>
                            <input 
                                type="password" 
                                name="new_password" 
                                id="new_password"
                                class="form-control border-start-0 ps-0" 
                                placeholder="Enter new password"
                                minlength="8" 
                                required
                            >
                            <button class="btn btn-outline-secondary border-start-0" type="button" onclick="togglePassword('new_password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text small mt-2 text-muted">
                            <i class="bi bi-info-circle me-1"></i> Minimum 8 characters required.
                        </div>
                    </div>

                    <button type="submit" class="btn w-100 py-3 fw-bold mt-2 shadow-sm" style="background-color: var(--shop-brand); color: white; border-radius: 10px; transition: transform 0.2s;">
                        Update Password <i class="bi bi-arrow-right ms-2"></i>
                    </button>
                </form>

            </div>

        </div>
    </div>

</div>

<script>
    function togglePassword(inputId, btnElement) {
        const input = document.getElementById(inputId);
        const icon = btnElement.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('bi-eye', 'bi-eye-slash-fill');
            icon.classList.add('text-primary');
        } else {
            input.type = 'password';
            icon.classList.replace('bi-eye-slash-fill', 'bi-eye');
            icon.classList.remove('text-primary');
        }
    }
</script>

<?php 
// 4. INCLUDE FOOTER
require_once __DIR__ . "/includes/footer.php"; 
?>