<?php
// 1. Initialize Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Database Connection (Robust Path Check)
$db_path = __DIR__ . "/../app/config/db.php";
$db_path_fallback = __DIR__ . "/../../app/config/db.php";

if (file_exists($db_path)) {
    require_once $db_path;
} elseif (file_exists($db_path_fallback)) {
    require_once $db_path_fallback;
} else {
    die("System Error: Database configuration file not found.");
}

// 3. Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = "";
$error   = "";

// 4. Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name    = trim($_POST['name']);
    $phone   = trim($_POST['phone']);
    $address = trim($_POST['address']);

    if (empty($name)) {
        $error = "Full Name is required.";
    } else {
        try {
            // Update SQL
            $sql = "UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $phone, $address, $user_id]);
            
            $_SESSION['name'] = $name;
            $message = "Profile details updated successfully!";
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// 5. Handle Password Change (FIXED)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'];
    $new_pass     = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if ($new_pass !== $confirm_pass) {
        $error = "New passwords do not match.";
    } else {
        try {
            // Check dynamically for the password column without forcing a strict column name
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?"); 
            $stmt->execute([$user_id]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Prefer password_hash if it exists, otherwise use password
            $pass_col = isset($user_data['password_hash']) ? 'password_hash' : 'password';
            $db_pass = $user_data[$pass_col] ?? null;

            if ($db_pass && password_verify($current_pass, $db_pass)) {
                $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $upd = $pdo->prepare("UPDATE users SET $pass_col = ? WHERE id = ?");
                $upd->execute([$new_hash, $user_id]);
                $message = "Password changed successfully.";
            } else {
                $error = "Current password is incorrect.";
            }
        } catch (PDOException $e) {
            $error = "Password Update Error: " . $e->getMessage();
        }
    }
}

// 6. Fetch User Data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Critical Error: Could not fetch user data. " . $e->getMessage());
}

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | ShopCorrect</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root { 
            --sc-navy: #0B2447; 
            --sc-bg: #F8FAFC; 
            --sc-text: #334155;
            --sc-border: #E2E8F0;
        }
        body { background-color: var(--sc-bg); font-family: 'Inter', sans-serif; color: var(--sc-text); }

        /* Page Header */
        .page-header {
            background: white;
            padding: 3rem 0 2rem;
            border-bottom: 1px solid var(--sc-border);
            margin-bottom: 2.5rem;
        }
        .page-title { color: var(--sc-navy); font-weight: 800; letter-spacing: -0.5px; }

        /* Sidebar */
        .profile-sidebar {
            background: white; border-radius: 16px; border: 1px solid var(--sc-border); overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        .user-brief {
            background: var(--sc-navy); color: white; padding: 2rem; text-align: center;
        }
        .user-avatar-lg {
            width: 80px; height: 80px; background: #ffc107; color: var(--sc-navy);
            border-radius: 50%; font-size: 2rem; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem; border: 4px solid rgba(255,255,255,0.2);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        .sidebar-menu a {
            display: block; padding: 15px 25px; color: #475569; text-decoration: none;
            font-weight: 500; border-bottom: 1px solid #f1f5f9; transition: 0.2s;
        }
        .sidebar-menu a:last-child { border-bottom: none; }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: #f8fafc; color: var(--sc-navy); border-left: 4px solid var(--sc-navy);
        }
        .sidebar-menu a i { margin-right: 12px; opacity: 0.7; font-size: 1.1rem; }

        /* Form Card */
        .card-profile {
            background: white; border-radius: 16px; border: 1px solid var(--sc-border); padding: 2.5rem;
            margin-bottom: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
        }
        .form-label { font-size: 0.8rem; font-weight: 700; color: var(--sc-navy); text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control { padding: 12px 16px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 0.95rem;}
        .form-control:focus { border-color: var(--sc-navy); box-shadow: 0 0 0 4px rgba(11, 36, 71, 0.05); }
        
        .btn-save {
            background-color: var(--sc-navy); color: white; padding: 12px 35px;
            font-weight: 600; border-radius: 50px; border: none; transition: 0.2s;
        }
        .btn-save:hover { background-color: #19376D; color: white; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(11, 36, 71, 0.2); }
        
        /* New styles for eye icon */
        .input-group-text {
            background-color: white;
            border-left: none;
            border-color: #cbd5e1;
            cursor: pointer;
            color: #94a3b8;
        }
        .input-group-text:hover { color: var(--sc-navy); }
        .input-group .form-control {
            border-right: none;
        }
        .input-group:focus-within .input-group-text {
            border-color: var(--sc-navy);
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . "/partials/navbar.php"; ?>

<div class="page-header">
    <div class="container">
        <h2 class="page-title mb-1">Account Settings</h2>
        <p class="text-muted mb-0">Manage your personal details and security preferences.</p>
    </div>
</div>

<div class="container pb-5">
    
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center" role="alert">
            <i class="bi bi-check-circle-fill me-3 fs-5"></i> 
            <div><?= htmlspecialchars($message) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-3 fs-5"></i> 
            <div><?= htmlspecialchars($error) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-5">
        
        <div class="col-lg-3">
            <div class="profile-sidebar sticky-top" style="top: 100px;">
                <div class="user-brief">
                    <div class="user-avatar-lg">
                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    </div>
                    <h5 class="fw-bold mb-0"><?= htmlspecialchars($user['name']) ?></h5>
                    <small class="opacity-75">Customer Account</small>
                </div>
                <div class="sidebar-menu">
                    <a href="profile.php" class="active"><i class="bi bi-person-gear"></i> Account Details</a>
                    <a href="my-orders.php"><i class="bi bi-box-seam"></i> My Orders</a>
                    <a href="help.php"><i class="bi bi-question-circle"></i> Help Center</a>
                    <a href="logout.php" class="text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            
            <div class="card-profile">
                <h4 class="fw-bold text-dark mb-4 border-bottom pb-3">Personal Information</h4>
                <form method="POST">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control bg-light" value="<?= htmlspecialchars($user['email']) ?>" readonly title="Contact support to change email">
                            <small class="text-muted mt-1 d-block" style="font-size: 0.75rem;"><i class="bi bi-info-circle me-1"></i>Email cannot be changed manually.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="e.g. 055 123 4567">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Default Shipping Address</label>
                            <textarea name="address" class="form-control" rows="3" placeholder="Enter your delivery location..."><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12 mt-4 pt-3 border-top">
                            <button type="submit" name="update_profile" class="btn btn-save"><i class="bi bi-floppy me-2"></i>Save Changes</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card-profile">
                <h4 class="fw-bold text-dark mb-4 border-bottom pb-3">Security Settings</h4>
                <form method="POST">
                    <div class="row g-4 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Current Password</label>
                            <div class="input-group">
                                <input type="password" name="current_password" id="current_password" class="form-control" required>
                                <span class="input-group-text" onclick="togglePassword('current_password', this)">
                                    <i class="bi bi-eye"></i>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" name="new_password" id="new_password" class="form-control" minlength="6" required>
                                <span class="input-group-text" onclick="togglePassword('new_password', this)">
                                    <i class="bi bi-eye"></i>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" minlength="6" required>
                                <span class="input-group-text" onclick="togglePassword('confirm_password', this)">
                                    <i class="bi bi-eye"></i>
                                </span>
                            </div>
                        </div>
                        <div class="col-12 mt-4 pt-3 border-top">
                            <button type="submit" name="change_password" class="btn btn-outline-dark fw-bold px-4 rounded-pill py-2"><i class="bi bi-shield-lock me-2"></i>Update Password</button>
                        </div>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . "/partials/footer.php"; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function togglePassword(inputId, iconSpan) {
        const input = document.getElementById(inputId);
        const icon = iconSpan.querySelector('i');
        
        if (input.type === "password") {
            input.type = "text";
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            input.type = "password";
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>