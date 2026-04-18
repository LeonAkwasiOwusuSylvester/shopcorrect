<?php
require_once __DIR__ . "/../../app/config/db.php";
require_once __DIR__ . "/../../app/helpers/mailer.php";

$message = "";
$status = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"]);

    // 1. Check if the email belongs to a valid staff member
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND role IN ('supadmin', 'country_agent', 'support') LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // 2. Generate a secure random token
        $token = bin2hex(random_bytes(32)); // 64 character hex string
        $expires = date("Y-m-d H:i:s", strtotime('+1 hour')); // Token dies in 1 hour

        // 3. Clear any old tokens for this email so they don't pile up
        $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

        // 4. Save the new token to the database
        $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)")->execute([$email, $token, $expires]);

        // 5. Send the Email
        // Make sure this path matches your live domain when you deploy!
        $resetLink = "http://localhost/shopcorrect/public/admin/reset-password.php?token=" . $token;
        
        $emailBody = "
        <div style='text-align: center; margin-bottom: 30px;'>
            <img src='https://cdn-icons-png.flaticon.com/512/6195/6195696.png' style='width: 64px; height: 64px; margin-bottom: 15px;'>
            <h2 style='color: #0B2447; margin: 0; font-size: 24px; font-weight: 800;'>Password Reset</h2>
        </div>
        <div style='background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 20px; color: #475569; font-size: 15px;'>
            <p>Hi <strong>{$user['name']}</strong>,</p>
            <p>We received a request to reset the password for your ShopCorrect Admin console account.</p>
            <p>Click the button below to choose a new password. This link is only valid for the next <strong>1 hour</strong>.</p>
            <p style='font-size: 12px; color: #94a3b8; margin-top: 20px;'>If you did not request this, please ignore this email or contact the Super Admin immediately.</p>
        </div>";
        
        $button = ['text' => 'Reset Password', 'url' => $resetLink];
        
        sendMail($email, "Admin Password Reset - ShopCorrect", "", $emailBody, $button);
    }

    // 6. Security Best Practice: ALWAYS show a success message, even if the email doesn't exist.
    // This prevents hackers from typing in random emails to see who works for you.
    $status = "success";
    $message = "If an admin account exists for that email, a password reset link has been sent.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password | ShopCorrect Admin</title>
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
            <h4>Recover Access</h4>
            <p class="text-secondary small mb-4">Enter your staff email address to receive a password reset link.</p>
        </div>

        <?php if ($status === "success"): ?>
            <div class="alert-box alert-success-custom">
                <i class="bi bi-check-circle-fill mt-1"></i>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
        <?php else: ?>
            <form method="POST" autocomplete="off">
                <label class="text-white-50 small fw-bold mb-2 ps-1 text-uppercase" style="font-size: 0.7rem;">Account Email</label>
                <div class="input-group-custom">
                    <span class="input-icon"><i class="bi bi-envelope-at-fill"></i></span>
                    <input type="email" name="email" class="form-control" placeholder="agent@shopcorrect.com" required autofocus>
                </div>
                <button type="submit" class="btn-auth">Send Recovery Link</button>
            </form>
        <?php endif; ?>

        <div class="text-center mt-4 pt-3 border-top border-secondary border-opacity-10">
            <a href="login.php" style="color: #64748b; text-decoration: none; font-size: 0.85rem; font-weight: 500;">
                <i class="bi bi-arrow-left me-1"></i> Back to Login
            </a>
        </div>
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