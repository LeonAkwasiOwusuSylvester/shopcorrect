<?php
require_once __DIR__ . "/../../app/config/db.php";

$token = $_GET['token'] ?? '';
$validToken = false;
$emailToReset = '';
$message = '';
$success = false;

// 1. Validate the token from the URL
if (!empty($token)) {
    $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$token]);
    $resetRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resetRecord) {
        $validToken = true;
        $emailToReset = $resetRecord['email'];
    }
}

// 2. Handle the Form Submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && $validToken) {
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    if (empty($newPassword) || empty($confirmPassword)) {
        $message = "Please fill in all fields.";
    } elseif ($newPassword !== $confirmPassword) {
        $message = "Your new passwords do not match.";
    } elseif (strlen($newPassword) < 8) {
        $message = "Password must be at least 8 characters long.";
    } else {
        // Securely hash the new password
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);

        try {
            $pdo->beginTransaction();

            // Update the user's password
            $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
            $updateStmt->execute([$hash, $emailToReset]);

            // Destroy the token so it can't be used twice
            $deleteStmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
            $deleteStmt->execute([$emailToReset]);

            $pdo->commit();
            $success = true;
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "System error. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password | ShopCorrect Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root { --sc-admin-dark: #0f172a; --sc-accent: #3b82f6; --font-main: 'Plus Jakarta Sans', sans-serif; }
        body { margin: 0; padding: 0; font-family: var(--font-main); min-height: 100vh; display: flex; align-items: center; justify-content: center; background-color: var(--sc-admin-dark); overflow: hidden; position: relative; }
        body::before { content: ""; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-image: radial-gradient(at 0% 0%, rgba(59, 130, 246, 0.15) 0px, transparent 50%), radial-gradient(at 100% 100%, rgba(15, 23, 42, 1) 0px, transparent 50%); z-index: -2; }
        .spotlight { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: radial-gradient(600px circle at var(--x, 50%) var(--y, 50%), rgba(59, 130, 246, 0.06), transparent 40%); z-index: -1; pointer-events: none; }
        
        .admin-card { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); padding: 2.5rem 2.5rem; width: 100%; max-width: 400px; position: relative; z-index: 10; }
        .brand-header { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 1rem; }
        .brand-logo { height: 32px; width: auto; }
        .brand-text { color: #fff !important; font-size: 1.4rem; font-weight: 800; letter-spacing: -0.5px; }
        h4 { color: #94a3b8; font-size: 0.9rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; display: inline-block; }

        .input-group-custom { background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; display: flex; align-items: center; padding: 4px; transition: all 0.2s ease; margin-bottom: 1.25rem; }
        .input-group-custom:focus-within { border-color: var(--sc-accent); box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2); background: rgba(15, 23, 42, 0.9); }
        .input-icon { color: #64748b; padding: 0 12px 0 16px; font-size: 1.1rem; }
        .form-control { background: transparent; border: none; color: #fff; padding: 10px 0; font-size: 0.95rem; font-weight: 500; }
        .form-control:focus { background: transparent; color: #fff; box-shadow: none; }
        .form-control::placeholder { color: #475569; }

        .btn-auth { background: linear-gradient(135deg, #3b82f6, #2563eb); border: none; color: white; font-weight: 700; padding: 12px; border-radius: 12px; width: 100%; transition: 0.3s; margin-top: 0.5rem; }
        .btn-auth:hover { transform: translateY(-2px); box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.4); }

        .alert-box { border-radius: 12px; padding: 12px; display: flex; align-items: flex-start; gap: 10px; margin-bottom: 1.5rem; font-size: 0.85rem; line-height: 1.4; }
        .alert-error-custom { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #fca5a5; }
        .alert-success-custom { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #6ee7b7; }
    </style>
</head>
<body>

<div class="spotlight"></div>

<div class="container d-flex flex-column align-items-center">
    <div class="admin-card">
        <div class="text-center">
            <div class="brand-header">
                <img src="/shopcorrect/public/assets/images/logo_w.png" alt="Logo" class="brand-logo" onerror="this.style.display='none'">
                <span class="brand-text notranslate">ShopCorrect</span>
            </div>
            <h4>Reset Password</h4>
        </div>

        <?php if ($success): ?>
            <div class="text-center py-4">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                <h5 class="text-white mt-3 fw-bold">Password Updated</h5>
                <p class="text-secondary small mb-4">Your administrative access has been successfully secured with your new password.</p>
                <a href="login.php" class="btn-auth d-inline-block text-decoration-none">Return to Login</a>
            </div>

        <?php elseif (!$validToken): ?>
            <div class="text-center py-4">
                <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 3rem;"></i>
                <h5 class="text-white mt-3 fw-bold">Link Expired</h5>
                <p class="text-secondary small mb-4">This password reset link is invalid or has expired. For security, links expire after 1 hour.</p>
                <a href="forgot-password.php" class="btn btn-outline-light w-100" style="border-radius: 12px; font-weight: 600; padding: 12px;">Request New Link</a>
            </div>

        <?php else: ?>
            <p class="text-secondary text-center small mb-4">Create a new secure password for <strong><?= htmlspecialchars($emailToReset) ?></strong></p>

            <?php if ($message): ?>
                <div class="alert-box alert-error-custom">
                    <i class="bi bi-x-circle-fill mt-1"></i>
                    <span><?= htmlspecialchars($message) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <label class="text-white-50 small fw-bold mb-2 ps-1 text-uppercase" style="font-size: 0.7rem;">New Password</label>
                <div class="input-group-custom">
                    <span class="input-icon"><i class="bi bi-key-fill"></i></span>
                    <input type="password" name="new_password" class="form-control" placeholder="Min 8 characters" required minlength="8" autofocus>
                </div>

                <label class="text-white-50 small fw-bold mb-2 ps-1 text-uppercase" style="font-size: 0.7rem;">Confirm Password</label>
                <div class="input-group-custom">
                    <span class="input-icon"><i class="bi bi-check-all"></i></span>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Re-type new password" required minlength="8">
                </div>

                <button type="submit" class="btn-auth mt-2">Save New Password</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('mousemove', e => {
        document.body.style.setProperty('--x', e.clientX + 'px');
        document.body.style.setProperty('--y', e.clientY + 'px');
    });
</script>

</body>
</html>