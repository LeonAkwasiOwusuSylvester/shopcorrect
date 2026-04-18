<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/../../app/config/db.php";

// Ensure Admin Session
$adminId    = $_SESSION['user_id'] ?? null;
$adminName  = $_SESSION['name'] ?? 'Administrator';
$adminRole  = $_SESSION['role'] ?? 'admin';

// Redirect if not logged in (Security precaution for the view)
if (!$adminId) {
    // Optional: header("Location: ../login.php"); 
}
?>

<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;800&display=swap" rel="stylesheet">
    
    <style>
        :root { 
            --sc-navy: #0B2447; 
            --sc-gold: #ffc107; 
            --sc-hover: rgba(255, 255, 255, 0.1); 
            --sc-accent: #19376D;
        }
        body { font-family: 'Inter', sans-serif; }
        
        .admin-nav { 
            background: linear-gradient(90deg, var(--sc-navy) 0%, #051329 100%); 
            border-bottom: 1px solid rgba(255,255,255,0.08); 
            padding: 0.6rem 0; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .navbar-brand { font-weight: 800; letter-spacing: -0.5px; display: flex; align-items: center; gap: 8px; }
        .admin-badge {
            background: var(--sc-gold); color: var(--sc-navy);
            font-size: 0.65rem; padding: 2px 6px; border-radius: 4px;
            font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;
        }
        
        /* Admin Navigation Links */
        .nav-main-link { 
            color: rgba(255,255,255,0.75) !important; 
            font-weight: 500; font-size: 0.9rem; 
            transition: 0.2s; padding: 0.6rem 1rem !important;
            border-radius: 6px; display: flex; align-items: center; gap: 6px;
        }
        .nav-main-link:hover, .nav-main-link.active { 
            color: #fff !important; background: var(--sc-hover); 
        }
        .nav-main-link i { font-size: 1.1rem; opacity: 0.8; }

        /* User Profile Pill */
        .user-pill {
            background: rgba(255, 255, 255, 0.05); border-radius: 50px; 
            padding: 4px 12px 4px 4px; 
            border: 1px solid rgba(255, 255, 255, 0.1); 
            display: flex; align-items: center; gap: 10px;
            color: white; text-decoration: none; transition: 0.3s;
        }
        .user-pill:hover { background: rgba(255, 255, 255, 0.15); border-color: rgba(255,255,255,0.3); color: white; }

        .avatar-box {
            width: 34px; height: 34px; border-radius: 50%;
            background: var(--sc-accent); color: white;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.9rem; border: 2px solid rgba(255,255,255,0.1);
        }

        /* Action Buttons */
        .nav-icon-btn {
            color: rgba(255,255,255,0.6); font-size: 1.2rem;
            padding: 8px; border-radius: 50%; transition: 0.2s;
            display: flex; align-items: center; justify-content: center;
            width: 40px; height: 40px; text-decoration: none;
        }
        .nav-icon-btn:hover { color: white; background: var(--sc-hover); }

        /* Logout Button Specifics */
        .btn-logout {
            color: #ef4444 !important; /* Red */
            background: rgba(239, 68, 68, 0.1);
            border-radius: 50%;
            transition: all 0.2s ease;
        }
        .btn-logout:hover {
            background: #ef4444;
            color: white !important;
            box-shadow: 0 0 12px rgba(239, 68, 68, 0.4);
            transform: scale(1.05);
        }

        .dropdown-menu { border: none; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); margin-top: 12px !important; }
        .dropdown-item { padding: 10px 20px; font-size: 0.9rem; font-weight: 500; }
        .dropdown-item i { margin-right: 8px; opacity: 0.7; }
        .dropdown-item:active { background-color: var(--sc-navy); }
    </style>
</head>

<nav class="navbar navbar-expand-lg navbar-dark admin-nav sticky-top">
    <div class="container-fluid px-lg-5">
        
        <a class="navbar-brand me-5" href="/shopcorrect/public/admin/index.php">
            <img src="/assets/images/shopcorrect-logo.png" height="32" alt="SC">
            <div class="d-flex flex-column lh-1">
                <span>ShopCorrect</span>
                <span class="admin-badge mt-1">Portal</span>
            </div>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="adminNav">
            
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-main-link" href="/shopcorrect/public/admin/index.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-main-link" href="/shopcorrect/public/admin/users.php">
                        <i class="bi bi-people"></i> Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-main-link" href="/shopcorrect/public/admin/vendors.php">
                        <i class="bi bi-shop"></i> Vendors
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-main-link" href="/shopcorrect/public/admin/orders.php">
                        <i class="bi bi-receipt"></i> Orders
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-main-link" href="/shopcorrect/public/admin/messages.php">
                        <i class="bi bi-envelope"></i> Messages
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-main-link" href="/shopcorrect/public/admin/reports.php">
                        <i class="bi bi-graph-up"></i> Reports
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-main-link" href="/shopcorrect/public/admin/payouts.php">
                        <i class="bi bi-bank"></i> Payouts
                    </a>
                </li>
            </ul>

            <div class="d-flex align-items-center justify-content-lg-end gap-3 mt-3 mt-lg-0">
                
                <div class="dropdown">
                    <a href="#" class="user-pill dropdown-toggle" data-bs-toggle="dropdown">
                        <div class="avatar-box">
                            <i class="bi bi-shield-lock-fill" style="font-size: 0.8rem;"></i>
                        </div>
                        <div class="d-flex flex-column lh-1 d-none d-md-block text-start">
                            <span style="font-size: 0.65rem; opacity: 0.6; text-transform: uppercase;">Logged in as</span>
                            <span style="font-size: 0.85rem; font-weight: 600;"><?= htmlspecialchars(explode(' ', $adminName)[0]) ?></span>
                        </div>
                    </a>
                    
                    <ul class="dropdown-menu dropdown-menu-end animate slideIn">
                        <li><h6 class="dropdown-header text-uppercase small text-muted">Account</h6></li>
                        <li><a class="dropdown-item" href="/shopcorrect/public/admin/profile.php"><i class="bi bi-person-gear"></i> Profile Settings</a></li>
                        <li><a class="dropdown-item" href="/shopcorrect/public/admin/change-password.php"><i class="bi bi-sliders"></i> System Config</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="/shopcorrect/public/admin/login.php"><i class="bi bi-power"></i> Secure Logout</a></li>
                    </ul>
                </div>

                <a href="/shopcorrect/public/admin/login.php" class="nav-icon-btn btn-logout" title="Secure Logout">
                    <i class="bi bi-power"></i>
                </a>

            </div>
        </div>
    </div>
</nav>