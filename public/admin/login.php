<?php
require_once __DIR__ . "/../../app/config/db.php";
require_once __DIR__ . "/../../app/config/session.php";

$error = "";

// Check which language is active for stealth translation
$activeLangCode = $_SESSION['lang'] ?? $_COOKIE['shop_lang'] ?? 'en'; 

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = trim($_POST["email"]);
    $password = $_POST["password"];

    // 1. Grab Security Details for the Audit Log
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    // UPDATED QUERY: Allow supadmin, country_agent, and support roles
    $stmt = $pdo->prepare("
        SELECT id, name, role, password_hash, managed_country 
        FROM users 
        WHERE email = ? AND role IN ('supadmin', 'country_agent', 'support') 
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin["password_hash"])) {
        
        // 2. AUDIT LOG: SUCCESS
        try {
            $logStmt = $pdo->prepare("INSERT INTO admin_login_logs (admin_id, email_attempted, ip_address, user_agent, status) VALUES (?, ?, ?, ?, 'success')");
            $logStmt->execute([$admin['id'], $email, $ipAddress, $userAgent]);
        } catch (Exception $e) {
            // Silently fail if the log table doesn't exist yet, so we don't break the login
            error_log("Audit Log Error: " . $e->getMessage());
        }

        // 3. SET SESSION VARIABLES
        session_regenerate_id(true);
        $_SESSION["user_id"]         = $admin["id"];
        $_SESSION["role"]            = $admin["role"]; // Important for hiding/showing menu items
        $_SESSION["name"]            = $admin["name"];
        $_SESSION["managed_country"] = $admin["managed_country"]; // Important for Country Agents

        header("Location: index.php");
        exit;
    } else {
        // 4. AUDIT LOG: FAILED
        // We use NULL for admin_id because the email might not belong to a real admin
        try {
            $logStmt = $pdo->prepare("INSERT INTO admin_login_logs (admin_id, email_attempted, ip_address, user_agent, status) VALUES (NULL, ?, ?, ?, 'failed')");
            $logStmt->execute([$email, $ipAddress, $userAgent]);
        } catch (Exception $e) {
            error_log("Audit Log Error: " . $e->getMessage());
        }

        $error = "Access Denied. Invalid credentials.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Access | ShopCorrect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --sc-admin-dark: #0f172a;
            --sc-accent: #3b82f6;
            --font-main: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            margin: 0; padding: 0;
            font-family: var(--font-main);
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            background-color: var(--sc-admin-dark);
            overflow: hidden;
            position: relative;
        }

        /* --- SPOTLIGHT EFFECT BACKGROUND --- */
        body::before {
            content: "";
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background-image: 
                radial-gradient(at 0% 0%, rgba(59, 130, 246, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(15, 23, 42, 1) 0px, transparent 50%);
            z-index: -2;
        }

        .spotlight {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: radial-gradient(600px circle at var(--x, 50%) var(--y, 50%), rgba(59, 130, 246, 0.06), transparent 40%);
            z-index: -1; pointer-events: none;
        }

        /* --- GLASS CARD --- */
        .admin-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            padding: 2.5rem 2.5rem;
            width: 100%; max-width: 400px;
            position: relative; z-index: 10;
        }

        /* Logo & Branding */
        .brand-header {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            margin-bottom: 1.5rem;
        }
        .brand-logo { height: 32px; width: auto; }
        
        /* ✅ THE FIX: Stronger CSS rule to keep the brand text white */
        .brand-text, .brand-text.notranslate {
            color: #ffffff !important; font-size: 1.4rem; font-weight: 800; letter-spacing: -0.5px;
        }

        h4 { color: #94a3b8; font-size: 0.9rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; display: inline-block; }

        /* --- UNIFIED INPUT GROUPS --- */
        .input-group-custom {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            display: flex; align-items: center; padding: 4px;
            transition: all 0.2s ease; margin-bottom: 1.25rem;
        }
        .input-group-custom:focus-within {
            border-color: var(--sc-accent);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
            background: rgba(15, 23, 42, 0.9);
        }
        .input-icon { color: #64748b; padding: 0 12px 0 16px; font-size: 1.1rem; }
        .form-control { background: transparent; border: none; color: #fff; padding: 10px 0; font-size: 0.95rem; font-weight: 500; }
        .form-control:focus { background: transparent; color: #fff; box-shadow: none; }
        .form-control::placeholder { color: #475569; }

        .btn-toggle { background: transparent; border: none; color: #64748b; padding: 0 16px; cursor: pointer; transition: color 0.2s; }
        .btn-toggle:hover { color: var(--sc-accent); }

        .btn-auth {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border: none; color: white; font-weight: 700; padding: 12px;
            border-radius: 12px; width: 100%; transition: 0.3s; margin-top: 1rem;
        }
        .btn-auth:hover { transform: translateY(-2px); box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.4); }

        .alert-error {
            background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2);
            color: #fca5a5; font-size: 0.85rem; border-radius: 12px; padding: 12px;
            display: flex; align-items: center; gap: 10px; margin-bottom: 1.5rem;
        }

        /* --- FOOTER --- */
        .admin-footer {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.75rem;
            color: #475569;
        }
        .admin-footer a { color: #64748b; text-decoration: none; font-weight: 600; margin-left: 5px; }
        .admin-footer a:hover { color: #fff; }

        /* ════ THE ULTIMATE GOOGLE TRANSLATE WIDGET DESTROYER ════ */
        iframe.skiptranslate,
        iframe.goog-te-banner-frame,
        .goog-te-banner-frame,
        .goog-te-gadget,
        .goog-te-gadget-simple,
        .goog-te-gadget-icon,
        .VIpgJd-Zvi9od-aZ2wEe-wOHMyf,
        .VIpgJd-Zvi9od-aZ2wEe-wOHMyf-ti6hGc,
        #goog-gt-tt,
        #google_translate_element {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            width: 0 !important;
            height: 0 !important;
            position: absolute !important;
            left: -10000px !important;
            z-index: -1000 !important;
            pointer-events: none !important;
        }
        body { top: 0 !important; position: static !important; }
        .notranslate { color: inherit !important; }
    </style>
</head>

<body>

<div class="spotlight"></div>

<div class="container d-flex flex-column align-items-center">
    
    <div class="admin-card">
        <div class="text-center">
            <div class="brand-header">
                <img src="../assets/images/logo_w.png" alt="Logo" class="brand-logo" onerror="this.style.display='none'">
                <span class="brand-text notranslate">ShopCorrect</span>
            </div>
            <h4>Admin Console</h4>
        </div>

        <?php if ($error): ?>
            <div class="alert-error">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <label class="text-white-50 small fw-bold mb-2 ps-1 text-uppercase" style="font-size: 0.7rem;">Team Email</label>
            <div class="input-group-custom">
                <span class="input-icon"><i class="bi bi-envelope-at-fill"></i></span>
                <input type="email" name="email" class="form-control" placeholder="agent@shopcorrect.com" required autofocus>
            </div>

            <label class="text-white-50 small fw-bold mb-2 ps-1 text-uppercase mt-1" style="font-size: 0.7rem;">Security Key</label>
            <div class="input-group-custom">
                <span class="input-icon"><i class="bi bi-key-fill"></i></span>
                <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
                <button type="button" class="btn-toggle" onclick="togglePassword()">
                    <i class="bi bi-eye-fill"></i>
                </button>
            </div>

            <div class="text-end mb-3">
                <a href="forgot-password.php" class="text-white-50 small" style="text-decoration: none;">Forgot Password?</a>
            </div>

            <button type="submit" class="btn-auth">
                Verify Credentials
            </button>
        </form>

        <div class="text-center mt-4 pt-3 border-top border-secondary border-opacity-10">
            <a href="../index.php" style="color: #64748b; text-decoration: none; font-size: 0.85rem; font-weight: 500;">
                <i class="bi bi-arrow-left me-1"></i> Return to Main Shop
            </a>
        </div>
    </div>

    <div class="admin-footer">
        &copy; <?= date("Y") ?> <strong class="text-white notranslate">ShopCorrect</strong> Inc. All rights reserved.<br>
        <span style="opacity: 0.5;">Secure Terminal V1.0.5</span>
    </div>
</div>

<script>
    document.addEventListener('mousemove', e => {
        document.body.style.setProperty('--x', e.clientX + 'px');
        document.body.style.setProperty('--y', e.clientY + 'px');
    });

    function togglePassword() {
        const input = document.getElementById("password");
        const btn = document.querySelector(".btn-toggle i");
        if (input.type === "password") {
            input.type = "text";
            btn.classList.replace("bi-eye-fill", "bi-eye-slash-fill");
            btn.style.color = "#3b82f6";
        } else {
            input.type = "password";
            btn.classList.replace("bi-eye-slash-fill", "bi-eye-fill");
            btn.style.color = "";
        }
    }
</script>

<?php if (isset($activeLangCode) && $activeLangCode !== 'en'): ?>
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
<?php endif; ?>

</body>
</html>