<?php
// vendor/includes/header.php
if (session_status() === PHP_SESSION_NONE) session_start();
$current_page = basename($_SERVER['PHP_SELF']);

// Safe fallback for shop name if it wasn't defined in the parent script
$displayShopName = isset($shopName) ? $shopName : 'Vendor';

// --- DYNAMIC LANGUAGE & FLAG LOGIC ---
// Reads from the custom cookie we set via JS to remember the specific country flag selected
$currentDisplayLabel = $_COOKIE['site_lang_label'] ?? 'EN';
$currentDisplayFlag  = $_COOKIE['site_lang_flag']  ?? 'https://flagcdn.com/w20/gb.png';

// --- DYNAMIC CURRENCY FLAG ---
$activeCurrency = $_SESSION['currency'] ?? 'GHS';

$currencyFlags = [
    'GHS' => 'https://flagcdn.com/w20/gh.png',
    'NGN' => 'https://flagcdn.com/w20/ng.png',
    'XOF' => 'https://flagcdn.com/w20/ci.png',
    'ZAR' => 'https://flagcdn.com/w20/za.png',
    'KES' => 'https://flagcdn.com/w20/ke.png',
    'GBP' => 'https://flagcdn.com/w20/gb.png',
    'USD' => 'https://flagcdn.com/w20/us.png',
    'CAD' => 'https://flagcdn.com/w20/ca.png',
    'EUR' => 'https://flagcdn.com/w20/de.png', 
    'CNY' => 'https://flagcdn.com/w20/cn.png',
];

$currentCurrencyFlag = $currencyFlags[$activeCurrency] ?? 'https://flagcdn.com/w20/gh.png';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Dashboard | ShopCorrect</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <style>
        :root {
            --sidebar-bg:          #0B2447;
            --sidebar-text:        #8FA5B8;
            --sidebar-hover:       #FFFFFF;
            --sidebar-active-text: #FFFFFF;
            --logout-color:        #FF6B6B;
            --main-bg:             #F5F7FA;
            --text-dark:           #1E293B;
            --card-border:         #E2E8F0;
            --primary-accent:      #0B2447;
            --sidebar-width:       260px;
        }

        /* ── Base ── */
        *, *::before, *::after { box-sizing: border-box; }
        html, body { overflow-x: hidden; width: 100%; }

        body {
            background-color: var(--main-bg);
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text-dark);
            font-size: clamp(13px, 1.4vw, 15px);
        }

        /* ══════════════════════════════
            LAYOUT WRAPPER
        ══════════════════════════════ */
        #wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* ══════════════════════════════
            SIDEBAR
        ══════════════════════════════ */
        #sidebar-wrapper {
            width: var(--sidebar-width);
            min-height: 100vh;
            background-color: var(--sidebar-bg);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            padding: 1.5rem 1rem;
            overflow-y: auto;
            overflow-x: hidden;
            position: fixed;
            top: 0; left: 0;
            height: 100vh;
            z-index: 1050;
            transition: transform 0.3s ease;
        }

        #sidebar-wrapper::-webkit-scrollbar       { width: 4px; }
        #sidebar-wrapper::-webkit-scrollbar-thumb { background-color: rgba(255,255,255,0.1); border-radius: 4px; }

        @media (max-width: 991px) {
            #sidebar-wrapper { transform: translateX(-100%); }
            #sidebar-wrapper.show { transform: translateX(0); }
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 2rem;
            padding-left: 0.5rem;
            flex-shrink: 0;
        }
        .brand-logo-box {
            width: 40px; height: 40px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }
        .logo-img  { width: 100%; height: 100%; object-fit: contain; }
        .brand-text {
            color: #fff;
            font-size: clamp(1.1rem, 2vw, 1.35rem);
            font-weight: 700;
            letter-spacing: -0.5px;
            white-space: nowrap;
        }

        .sidebar-nav {
            list-style: none;
            padding: 0; margin: 0;
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
            flex-grow: 1;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 11px 14px;
            color: var(--sidebar-text);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s;
            position: relative;
            white-space: nowrap;
        }
        .nav-link i { font-size: 1.15rem; flex-shrink: 0; }
        .nav-link:hover { color: var(--sidebar-hover); background-color: rgba(255,255,255,0.06); }
        .nav-link.active {
            color: var(--sidebar-active-text);
            font-weight: 600;
            background-color: transparent;
        }
        .nav-link.active::after {
            content: '';
            position: absolute;
            right: -16px; top: 50%;
            transform: translateY(-50%);
            height: 24px; width: 4px;
            background-color: #3B82F6;
            border-top-left-radius: 4px;
            border-bottom-left-radius: 4px;
        }

        .nav-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            color: #4B5563;
            font-weight: 700;
            margin-top: 1.2rem;
            margin-bottom: 0.4rem;
            padding-left: 1rem;
        }

        .logout-section {
            margin-top: auto;
            border-top: 1px solid rgba(255,255,255,0.05);
            padding-top: 1rem;
            padding-bottom: 0.5rem;
            flex-shrink: 0;
        }
        .nav-link.logout-link { color: var(--logout-color); }
        .nav-link.logout-link:hover { background-color: rgba(255,107,107,0.1); color: #ff8787; }

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1040;
        }
        .sidebar-overlay.show { display: block; }

        #page-content-wrapper {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            transition: margin-left 0.3s ease, width 0.3s ease;
        }

        @media (max-width: 991px) {
            #page-content-wrapper {
                margin-left: 0;
                width: 100%;
            }
        }

        .top-navbar {
            background: white;
            padding: 0.8rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--card-border);
            flex-wrap: wrap;
            gap: 10px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        @media (min-width: 992px) {
            .top-navbar { padding: 1rem 2rem; }
        }

        .top-navbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        @media (min-width: 576px) {
            .top-navbar-right { gap: 14px; }
        }

        .top-pill {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--text-dark);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            white-space: nowrap;
            padding: 5px 2px;
            transition: color 0.2s;
        }
        .top-pill:hover { color: var(--primary-accent); }

        .pill-label { display: inline; }
        @media (max-width: 400px) {
            .pill-label { display: none; }
        }

        .shop-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            border-left: 1px solid var(--card-border);
            padding-left: 12px;
        }
        .shop-avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: rgba(11,36,71,0.1);
            color: var(--primary-accent);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .nav-flag { width: 18px; height: auto; border-radius: 2px; object-fit: cover; }

        .dropdown-menu {
            border: 1px solid rgba(255,255,255,0.3) !important;
            border-radius: 14px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1) !important;
            margin-top: 10px !important;
            padding: 10px 0;
            background: rgba(255,255,255,0.92) !important;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }
        .dropdown-item {
            padding: 8px 18px;
            font-weight: 500;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.87rem;
            transition: all 0.2s ease;
        }
        .dropdown-item:hover {
            background-color: rgba(11,36,71,0.05) !important;
            color: var(--primary-accent);
        }

        /* ✅ NEW: VENDOR NOTIFICATION BELL PULSE ANIMATION */
        @keyframes pulse-red {
            0% { transform: scale(1) translate(-50%, -50%); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { transform: scale(1.05) translate(-50%, -50%); box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
            100% { transform: scale(1) translate(-50%, -50%); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
        .notif-wrapper { 
            position: relative; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center;
            padding: 6px 10px;
            text-decoration: none !important;
            border-radius: 30px;
            transition: 0.2s;
        }
        .notif-wrapper:hover { background: rgba(0,0,0,0.05); }
        .pulse-badge { 
            animation: pulse-red 2s infinite; 
            border: 2px solid #ffffff; 
            font-size: 0.6rem; 
            padding: 0.35em 0.5em; 
            font-weight: 800;
            line-height: 1;
        }

        body > .skiptranslate { display: none !important; }
        .goog-te-banner-frame.skiptranslate { display: none !important; }
        body { top: 0px !important; }
        .notranslate { color: inherit; }
    </style>

    <script>
        function switchLang(langCode, label, flagUrl) {
            // Save the exact flag and label the user clicked in a cookie
            document.cookie = "site_lang_label=" + label + "; path=/; max-age=31536000";
            document.cookie = "site_lang_flag=" + flagUrl + "; path=/; max-age=31536000";
            
            // If the footer translation function is loaded, use it. Otherwise, force translation via cookie
            if (typeof changeLanguage === 'function') {
                changeLanguage(langCode);
            } else {
                document.cookie = "googtrans=/auto/" + langCode + "; path=/";
                window.location.reload();
            }
        }
    </script>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div id="wrapper">

    <div id="sidebar-wrapper">

        <div class="sidebar-brand">
            <div class="brand-logo-box">
                <img src="../assets/images/logo_w.png" alt="SC Logo" class="logo-img">
            </div>
            <div class="brand-text notranslate" style="color:#ffffff !important;">ShopCorrect</div>
        </div>

        <nav class="sidebar-nav">
            <a href="index.php"         class="nav-link <?= $current_page=='index.php' ?'active':'' ?>">
                <i class="bi bi-grid-fill"></i> <span>Dashboard</span>
            </a>
            <a href="products.php"      class="nav-link <?= in_array($current_page, ['products.php','add-product.php','edit-product.php']) ?'active':'' ?>">
                <i class="bi bi-box-seam"></i> <span>My Inventory</span>
            </a>
            <a href="orders.php"        class="nav-link <?= $current_page=='orders.php' ?'active':'' ?>">
                <i class="bi bi-cart3"></i> <span>Orders</span>
            </a>
            <a href="coupons.php"       class="nav-link <?= $current_page=='coupons.php' ?'active':'' ?>">
                <i class="bi bi-ticket-detailed"></i> <span>Coupons</span>
            </a>
            <a href="notifications.php" class="nav-link <?= $current_page=='notifications.php' ?'active':'' ?>">
                <i class="bi bi-bell"></i> <span>Messages</span>
            </a>

            <div class="nav-label">Finance & Shop</div>

            <a href="earnings.php"      class="nav-link <?= $current_page=='earnings.php' ?'active':'' ?>">
                <i class="bi bi-wallet2"></i> <span>Earnings</span>
            </a>
            <a href="payouts.php"       class="nav-link <?= $current_page=='payouts.php' ?'active':'' ?>">
                <i class="bi bi-cash-stack"></i> <span>Payouts</span>
            </a>
            <a href="vprofile.php"      class="nav-link <?= $current_page=='vprofile.php' ?'active':'' ?>">
                <i class="bi bi-shop"></i> <span>My Shop</span>
            </a>
        </nav>

        <div class="logout-section">
            <a href="settings.php"             class="nav-link <?= $current_page=='settings.php' ?'active':'' ?>">
                <i class="bi bi-gear"></i> <span>Settings</span>
            </a>
            <a href="../../public/logout.php"  class="nav-link logout-link">
                <i class="bi bi-box-arrow-right"></i> <span>Log Out</span>
            </a>
        </div>

    </div>

    <div id="page-content-wrapper">

        <nav class="top-navbar">

            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-outline-secondary d-lg-none" id="menu-toggle" style="min-width:40px;min-height:40px;">
                    <i class="bi bi-list fs-5"></i>
                </button>
            </div>

            <div class="top-navbar-right">

                <a href="notifications.php" class="text-dark notif-wrapper me-2" title="Notifications">
                    <i class="bi bi-bell fs-4"></i>
                    <span id="notifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger pulse-badge d-none">0</span>
                </a>

                <div class="dropdown">
                    <a href="#" class="top-pill" data-bs-toggle="dropdown" title="Language">
                        <span id="current-lang-text" class="notranslate d-flex align-items-center gap-1">
                            <img src="<?= $currentDisplayFlag ?>" alt="flag" class="nav-flag">
                            <span class="pill-label"><?= $currentDisplayLabel ?></span>
                        </span>
                        <i class="bi bi-chevron-down small opacity-50"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header fw-bold text-secondary text-uppercase" style="font-size:0.72rem;">Africa</h6></li>
                        <li><a class="dropdown-item" href="#" onclick="switchLang('en', 'EN', 'https://flagcdn.com/w20/gh.png');return false;"><img src="https://flagcdn.com/w20/gh.png" class="nav-flag"> Ghana</a></li>
                        <li><a class="dropdown-item" href="#" onclick="switchLang('en', 'EN', 'https://flagcdn.com/w20/ng.png');return false;"><img src="https://flagcdn.com/w20/ng.png" class="nav-flag"> Nigeria</a></li>
                        <li><a class="dropdown-item" href="#" onclick="switchLang('fr', 'FR', 'https://flagcdn.com/w20/ci.png');return false;"><img src="https://flagcdn.com/w20/ci.png" class="nav-flag"> Cote d'Ivoire</a></li>
                        <li><a class="dropdown-item" href="#" onclick="switchLang('en', 'EN', 'https://flagcdn.com/w20/za.png');return false;"><img src="https://flagcdn.com/w20/za.png" class="nav-flag"> South Africa</a></li>
                        <li><a class="dropdown-item" href="#" onclick="switchLang('sw', 'SW', 'https://flagcdn.com/w20/ke.png');return false;"><img src="https://flagcdn.com/w20/ke.png" class="nav-flag"> Kenya</a></li>
                        <li><a class="dropdown-item" href="#" onclick="switchLang('fr', 'FR', 'https://flagcdn.com/w20/tg.png');return false;"><img src="https://flagcdn.com/w20/tg.png" class="nav-flag"> Togo</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><h6 class="dropdown-header fw-bold text-secondary text-uppercase" style="font-size:0.72rem;">International</h6></li>
                        <li><a class="dropdown-item" href="#" onclick="switchLang('en', 'EN', 'https://flagcdn.com/w20/gb.png');return false;"><img src="https://flagcdn.com/w20/gb.png" class="nav-flag"> United Kingdom</a></li>
                        <li><a class="dropdown-item" href="#" onclick="switchLang('en', 'EN', 'https://flagcdn.com/w20/us.png');return false;"><img src="https://flagcdn.com/w20/us.png" class="nav-flag"> United States</a></li>
                        <li><a class="dropdown-item" href="#" onclick="switchLang('en', 'EN', 'https://flagcdn.com/w20/ca.png');return false;"><img src="https://flagcdn.com/w20/ca.png" class="nav-flag"> Canada</a></li>
                        <li><a class="dropdown-item" href="#" onclick="switchLang('de', 'DE', 'https://flagcdn.com/w20/de.png');return false;"><img src="https://flagcdn.com/w20/de.png" class="nav-flag"> Germany</a></li>
                        <li><a class="dropdown-item" href="#" onclick="switchLang('zh-CN', 'ZH', 'https://flagcdn.com/w20/cn.png');return false;"><img src="https://flagcdn.com/w20/cn.png" class="nav-flag"> China</a></li>
                        <li><a class="dropdown-item" href="#" onclick="switchLang('es', 'ES', 'https://flagcdn.com/w20/es.png');return false;"><img src="https://flagcdn.com/w20/es.png" class="nav-flag"> Spain (Spanish)</a></li>
                    </ul>
                </div>

                <?php $redirectUrl = urlencode($_SERVER['REQUEST_URI']); ?>
                <div class="dropdown">
                    <a href="#" class="top-pill" data-bs-toggle="dropdown" title="Currency">
                        <span class="notranslate d-flex align-items-center gap-1">
                            <img src="<?= $currentCurrencyFlag ?>" alt="flag" class="nav-flag">
                            <span class="pill-label notranslate"><?= $activeCurrency ?></span>
                        </span>
                        <i class="bi bi-chevron-down small opacity-50"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" style="max-height:380px;overflow-y:auto;">
                        <li><h6 class="dropdown-header fw-bold text-secondary text-uppercase" style="font-size:0.72rem;">Africa</h6></li>
                        <li><a class="dropdown-item" href="../../public/change-currency.php?cur=GHS&redirect_to=<?= $redirectUrl ?>"><img src="https://flagcdn.com/w20/gh.png" class="nav-flag"> GHS — Ghana Cedi (Base)</a></li>
                        <li><a class="dropdown-item" href="../../public/change-currency.php?cur=NGN&redirect_to=<?= $redirectUrl ?>"><img src="https://flagcdn.com/w20/ng.png" class="nav-flag"> NGN — Nigerian Naira</a></li>
                        <li><a class="dropdown-item" href="../../public/change-currency.php?cur=XOF&redirect_to=<?= $redirectUrl ?>"><img src="https://flagcdn.com/w20/ci.png" class="nav-flag"> XOF — CFA Franc (Côte d'Ivoire / Togo)</a></li>
                        <li><a class="dropdown-item" href="../../public/change-currency.php?cur=ZAR&redirect_to=<?= $redirectUrl ?>"><img src="https://flagcdn.com/w20/za.png" class="nav-flag"> ZAR — South African Rand</a></li>
                        <li><a class="dropdown-item" href="../../public/change-currency.php?cur=KES&redirect_to=<?= $redirectUrl ?>"><img src="https://flagcdn.com/w20/ke.png" class="nav-flag"> KES — Kenyan Shilling</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><h6 class="dropdown-header fw-bold text-secondary text-uppercase" style="font-size:0.72rem;">International</h6></li>
                        <li><a class="dropdown-item" href="../../public/change-currency.php?cur=GBP&redirect_to=<?= $redirectUrl ?>"><img src="https://flagcdn.com/w20/gb.png" class="nav-flag"> GBP — British Pound</a></li>
                        <li><a class="dropdown-item" href="../../public/change-currency.php?cur=USD&redirect_to=<?= $redirectUrl ?>"><img src="https://flagcdn.com/w20/us.png" class="nav-flag"> USD — US Dollar</a></li>
                        <li><a class="dropdown-item" href="../../public/change-currency.php?cur=CAD&redirect_to=<?= $redirectUrl ?>"><img src="https://flagcdn.com/w20/ca.png" class="nav-flag"> CAD — Canadian Dollar</a></li>
                        <li><a class="dropdown-item" href="../../public/change-currency.php?cur=EUR&redirect_to=<?= $redirectUrl ?>"><img src="https://flagcdn.com/w20/de.png" class="nav-flag"> EUR — Euro (Germany / Spain)</a></li>
                        <li><a class="dropdown-item" href="../../public/change-currency.php?cur=CNY&redirect_to=<?= $redirectUrl ?>"><img src="https://flagcdn.com/w20/cn.png" class="nav-flag"> CNY — Chinese Yuan</a></li>
                    </ul>
                </div>

                <div class="shop-badge">
                    <div class="d-none d-sm-block text-end">
                        <div class="fw-bold small text-dark"><?= htmlspecialchars($displayShopName) ?></div>
                        <div class="text-muted" style="font-size:0.72rem;">Vendor Account</div>
                    </div>
                    <div class="shop-avatar" title="<?= htmlspecialchars($displayShopName) ?>">
                        <?= strtoupper(substr($displayShopName, 0, 1)) ?>
                    </div>
                </div>

            </div>
        </nav>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                function updateNotificationBadge() {
                    // This calls your existing get-notifications.php script in the background
                    fetch('ajax/get-notifications.php') 
                        .then(response => response.json())
                        .then(data => {
                            const badge = document.getElementById('notifBadge');
                            if (badge) {
                                if (data.total !== undefined && data.total > 0) {
                                    // If there are notifications, show the badge and animate it
                                    badge.textContent = data.total > 99 ? '99+' : data.total;
                                    badge.classList.remove('d-none');
                                } else {
                                    // If zero notifications, hide the badge entirely
                                    badge.classList.add('d-none');
                                }
                            }
                        })
                        .catch(error => console.error("Error fetching notifications:", error));
                }

                // Run instantly on page load, then automatically check the server every 30 seconds
                updateNotificationBadge();
                setInterval(updateNotificationBadge, 30000);
            });
        </script>