<?php
// admin/includes/header.php
if (session_status() === PHP_SESSION_NONE) session_start();
$current_page = basename($_SERVER['PHP_SELF']);

require_once __DIR__ . "/../../../app/helpers/currency.php";

// --- DETERMINE ROLE & DISPLAY NAME ---
$userRole       = $_SESSION['role']            ?? 'user';
$userName       = $_SESSION['name']            ?? 'Agent';
$managedCountry = $_SESSION['managed_country'] ?? null;

$roleBadgeText = "Admin";
$roleInitials  = "AD";

if ($userRole === 'supadmin') {
    $roleBadgeText = "Super Admin";
    $roleInitials  = "SA";
} elseif ($userRole === 'country_agent') {
    $roleBadgeText = "Agent (" . strtoupper(substr($managedCountry, 0, 3)) . ")";
    $roleInitials  = strtoupper(substr($managedCountry, 0, 2));
} elseif ($userRole === 'support') {
    $roleBadgeText = "Support";
    $roleInitials  = "CS";
}

// --- DYNAMIC LANGUAGE & FLAG LOGIC ---
// ✅ shop_lang is the source of truth — never read googtrans directly
$activeLangCode      = $_SESSION['lang']          ?? $_COOKIE['shop_lang'] ?? 'en';
$activeCountry       = $_SESSION['country_code']  ?? $_COOKIE['shop_c']    ?? 'gb';
$currentDisplayLabel = $_SESSION['country_label'] ?? $_COOKIE['shop_l']    ?? 'EN';
$currentDisplayFlag  = "https://flagcdn.com/w20/{$activeCountry}.png";

// ✅ THE FIX: CALCULATE ONLY MESSAGES FOR THE ADMIN NOTIFICATION BELL
$adminNotifCount = 0;
global $pdo; 
if (isset($pdo)) {
    try {
        // Only count Contact Messages that need attention (No orders, vendors, or payouts)
        if (in_array($userRole, ['supadmin', 'support', 'country_agent'])) {
            $adminNotifCount += (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'pending' OR status = 'unread'")->fetchColumn();
        }
    } catch (PDOException $e) { 
        // Fail silently if table is missing
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ShopCorrect Admin Console</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --shop-brand:    #0B2447;
            --shop-accent:   #19376D;
            --sidebar-width: 260px;
            --admin-bg:      #f4f7fe;
            --text-color:    #2b3674;
        }

        *, *::before, *::after { box-sizing: border-box; }

        html { overflow-x: hidden; }
        body { overflow-x: clip; width: 100%; }

        .main-content { overflow-x: auto; }

        body {
            background-color: var(--admin-bg);
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: clamp(13px, 1.4vw, 15px);
        }

        /* ══════════════════════════════
            SIDEBAR
        ══════════════════════════════ */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0; left: 0;
            background: var(--shop-brand);
            padding: 24px 16px;
            z-index: 1050;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease;
            overflow-y: auto;
        }

        @media (max-width: 991px) {
            .sidebar { transform: translateX(-100%); padding: 20px 14px; }
            .sidebar.show { transform: translateX(0); }
        }

        .logo-container {
            display: flex;
            align-items: center;
            margin-bottom: 32px;
            padding: 8px 0;
            flex-shrink: 0;
        }
        .logo-img {
            max-width: 40px;
            height: auto;
            border-radius: 8px;
            margin-right: 10px;
            flex-shrink: 0;
        }
        .logo-text {
            font-size: clamp(16px, 2vw, 22px);
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #ffffff !important;
            white-space: nowrap;
        }

        .sidebar-menu {
            flex-grow: 1;
            overflow-y: auto;
            overflow-x: hidden;
            margin-right: -6px;
            padding-right: 6px;
        }
        .sidebar-menu::-webkit-scrollbar        { width: 4px; }
        .sidebar-menu::-webkit-scrollbar-track { background: transparent; }
        .sidebar-menu::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 10px; }

        .nav-link {
            display: flex;
            align-items: center;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
            padding: 12px 14px;
            margin-bottom: 6px;
            border-radius: 12px;
            transition: all 0.2s ease;
            gap: 12px;
            text-decoration: none;
            white-space: nowrap;
            font-size: 0.9rem;
        }
        .nav-link i { font-size: 1.15rem; min-width: 22px; display: flex; justify-content: center; }
        .nav-link:hover  { color: #fff; background: rgba(255,255,255,0.1); transform: translateX(4px); }
        .nav-link.active { background: var(--shop-accent); color: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.2); font-weight: 600; }

        .logout-section {
            margin-top: auto;
            padding-top: 16px;
            border-top: 1px solid rgba(255,255,255,0.1);
            flex-shrink: 0;
        }
        .logout-link { color: #ff9999 !important; margin-top: 4px; }
        .logout-link:hover { background: rgba(255,0,0,0.1) !important; color: #fff !important; }

        /* ══════════════════════════════
            MAIN CONTENT
        ══════════════════════════════ */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 28px 24px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s ease;
        }

        @media (max-width: 991px) {
            .main-content { margin-left: 0; padding: 16px 12px; }
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 1.5rem;
        }

        .top-bar-left  { display: flex; align-items: center; gap: 12px; min-width: 0; }
        .top-bar-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

        .page-title {
            font-size: clamp(1rem, 2.5vw, 1.4rem);
            font-weight: 700;
            color: var(--shop-brand);
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .page-subtitle {
            font-size: 11px;
            letter-spacing: 0.5px;
            font-weight: 700;
            text-transform: uppercase;
            color: #6b7280;
        }

        .top-pill {
            display: flex;
            align-items: center;
            gap: 6px;
            background: #fff;
            padding: 6px 12px;
            border-radius: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            text-decoration: none;
            color: #1f2937;
            font-weight: 600;
            font-size: 0.82rem;
            white-space: nowrap;
            transition: box-shadow 0.2s;
        }
        .top-pill:hover { box-shadow: 0 4px 14px rgba(0,0,0,0.12); color: var(--shop-brand); }

        @media (max-width: 480px) {
            .top-pill { padding: 5px 9px; font-size: 0.78rem; gap: 4px; }
            .top-bar-right { gap: 6px; }
        }

        .pill-label { display: inline; }
        @media (max-width: 400px) { .pill-label { display: none; } }

        .role-avatar {
            width: 30px; height: 30px;
            border-radius: 50%;
            background: var(--shop-brand);
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px;
            font-weight: 700;
            flex-shrink: 0;
        }

        /* ✅ NEW: NOTIFICATION BELL PULSE ANIMATION */
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

        .glass-card {
            background: #fff;
            border-radius: 20px;
            padding: clamp(14px, 2vw, 24px);
            box-shadow: 0 18px 40px rgba(112,144,176,0.12);
            border: none;
            height: 100%;
        }

        .stat-card { display: flex; align-items: center; }
        @media (max-width: 576px) { .stat-card { padding: 14px; } }

        .icon-box {
            width: 52px; height: 52px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
            margin-right: 1rem;
            background: #f4f7fe;
            color: var(--shop-brand);
            flex-shrink: 0;
        }

        .table-responsive-wrap { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }

        .admin-footer { margin-top: auto; padding-top: 24px; text-align: center; color: #0B2447; font-size: 0.82rem; }
        .badge-pill   { padding: 5px 11px; border-radius: 30px; font-size: 0.72rem; font-weight: 700; text-transform: capitalize; }
        .nav-flag     { width: 18px; height: auto; border-radius: 2px; object-fit: cover; }
        .currency-prefix { font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; min-width: 20px; display: inline-block; }

        .dropdown-menu     { border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1); padding: 10px 0; }
        .dropdown-item     { font-size: 0.83rem; font-weight: 500; padding: 8px 18px; color: var(--text-color); display: flex; align-items: center; gap: 10px; }
        .dropdown-item:hover { background-color: #f4f7fe; color: var(--shop-brand); }

        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1040; }
        .sidebar-overlay.show { display: block; }

        /* ════ GOOGLE TRANSLATE WIDGET HIDE ════ */
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
        aside.sidebar .logo-text.notranslate, .logo-text { color: #ffffff !important; }
    </style>

    <script>
        // If Chrome forces the hidden URL hash on page load, instantly erase it
        if (window.location.hash.indexOf('googtrans') !== -1) {
            window.history.replaceState(null, '', window.location.pathname + window.location.search);
        }

        function adminSwitchLang(lang, country, label) {
            var host = window.location.hostname;
            var rootDomain = "." + host.replace(/^www\./, '');

            var expire  = "expires=Thu, 01 Jan 1970 00:00:00 UTC";
            var paths   = ["/", "/shopcorrect/public/", "/public/", "/admin/"];
            var domains = ["", host, rootDomain];

            paths.forEach(function(p) {
                domains.forEach(function(d) {
                    var dStr = d ? "; domain=" + d : "";
                    document.cookie = "googtrans=; " + expire + dStr + "; path=" + p;
                });
            });

            localStorage.removeItem('googtrans');
            sessionStorage.removeItem('googtrans');

            window.location.href = "../set_language.php?lang=" + lang + "&c=" + country + "&l=" + label;
        }
    </script>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">

    <div class="logo-container">
        <img src="../assets/images/logo_w.png" alt="SC Logo" class="logo-img" onerror="this.style.display='none'">
        <span class="logo-text notranslate">ShopCorrect</span>
    </div>

    <nav class="sidebar-menu d-flex flex-column">

        <?php if ($userRole === 'supadmin'): ?>
            <a href="index.php"            class="nav-link <?= $current_page=='index.php'            ?'active':'' ?>"><i class="bi bi-grid-fill"></i>                <span>Dashboard</span></a>
            <a href="users.php"            class="nav-link <?= $current_page=='users.php'            ?'active':'' ?>"><i class="bi bi-people"></i>                  <span>Customers</span></a>
            <a href="vendors.php"          class="nav-link <?= $current_page=='vendors.php'          ?'active':'' ?>"><i class="bi bi-shop"></i>                    <span>Vendors</span></a>
            <a href="orders.php"           class="nav-link <?= $current_page=='orders.php'           ?'active':'' ?>"><i class="bi bi-cart3"></i>                   <span>Orders</span></a>
            <a href="messages.php"         class="nav-link <?= $current_page=='messages.php'         ?'active':'' ?>"><i class="bi bi-envelope"></i>                <span>Messages</span></a>
            <a href="refunds.php"          class="nav-link <?= $current_page=='refunds.php'          ?'active':'' ?>"><i class="bi bi-arrow-return-left"></i>       <span>Refunds</span></a>
            <a href="complaints.php"       class="nav-link <?= $current_page=='complaints.php'       ?'active':'' ?>"><i class="bi bi-exclamation-triangle"></i>    <span>Complaints</span></a>
            <a href="flagged-products.php" class="nav-link <?= $current_page=='flagged-products.php' ?'active':'' ?>"><i class="bi bi-shield-exclamation text-warning"></i> <span>Flagged Items</span></a>
            <a href="coupons.php"          class="nav-link <?= $current_page=='coupons.php'          ?'active':'' ?>"><i class="bi bi-ticket-perforated"></i>       <span>Promo Codes</span></a>
            <a href="marketing.php"        class="nav-link <?= $current_page=='marketing.php'        ?'active':'' ?>"><i class="bi bi-megaphone"></i>               <span>Marketing</span></a>
            <a href="reports.php"          class="nav-link <?= $current_page=='reports.php'          ?'active':'' ?>"><i class="bi bi-bar-chart-fill"></i>          <span>Reports</span></a>
            <a href="payouts.php"          class="nav-link <?= $current_page=='payouts.php'          ?'active':'' ?>"><i class="bi bi-wallet2"></i>                 <span>Payouts</span></a>
            <a href="staff.php"            class="nav-link <?= $current_page=='staff.php'            ?'active':'' ?>" style="margin-top:14px;border-top:1px solid rgba(255,255,255,0.1);padding-top:18px;">
                <i class="bi bi-person-badge-fill text-info"></i> <span>Staff & Agents</span>
            </a>
            <a href="audit-logs.php"       class="nav-link <?= $current_page=='audit-logs.php'       ?'active':'' ?>" style="background:rgba(255,255,255,0.05);">
                <i class="bi bi-shield-lock text-warning"></i> <span>Audit Logs</span>
            </a>

        <?php elseif ($userRole === 'country_agent'): ?>
            <a href="index.php"            class="nav-link <?= $current_page=='index.php'            ?'active':'' ?>"><i class="bi bi-grid-fill"></i>                <span>Dashboard</span></a>
            <a href="users.php"            class="nav-link <?= $current_page=='users.php'            ?'active':'' ?>"><i class="bi bi-people"></i>                  <span>Customers</span></a>
            <a href="vendors.php"          class="nav-link <?= $current_page=='vendors.php'          ?'active':'' ?>"><i class="bi bi-shop"></i>                    <span>Vendors</span></a>
            <a href="orders.php"           class="nav-link <?= $current_page=='orders.php'           ?'active':'' ?>"><i class="bi bi-cart3"></i>                   <span>Orders</span></a>
            <a href="messages.php"         class="nav-link <?= $current_page=='messages.php'         ?'active':'' ?>"><i class="bi bi-envelope"></i>                <span>Messages</span></a>
            <a href="refunds.php"          class="nav-link <?= $current_page=='refunds.php'          ?'active':'' ?>"><i class="bi bi-arrow-return-left"></i>       <span>Refunds</span></a>
            <a href="complaints.php"       class="nav-link <?= $current_page=='complaints.php'       ?'active':'' ?>"><i class="bi bi-exclamation-triangle"></i>    <span>Complaints</span></a>
            <a href="flagged-products.php" class="nav-link <?= $current_page=='flagged-products.php' ?'active':'' ?>"><i class="bi bi-shield-exclamation text-warning"></i> <span>Flagged Items</span></a>
            <a href="reports.php"          class="nav-link <?= $current_page=='reports.php'          ?'active':'' ?>"><i class="bi bi-bar-chart-fill"></i>          <span>Reports</span></a>

        <?php elseif ($userRole === 'support'): ?>
            <a href="index.php"            class="nav-link <?= $current_page=='index.php'            ?'active':'' ?>"><i class="bi bi-grid-fill"></i>                <span>Dashboard</span></a>
            <a href="users.php"            class="nav-link <?= $current_page=='users.php'            ?'active':'' ?>"><i class="bi bi-people"></i>                  <span>Customers</span></a>
            <a href="orders.php"           class="nav-link <?= $current_page=='orders.php'           ?'active':'' ?>"><i class="bi bi-cart3"></i>                   <span>Orders</span></a>
            <a href="messages.php"         class="nav-link <?= $current_page=='messages.php'         ?'active':'' ?>"><i class="bi bi-envelope"></i>                <span>Messages</span></a>
            <a href="refunds.php"          class="nav-link <?= $current_page=='refunds.php'          ?'active':'' ?>"><i class="bi bi-arrow-return-left"></i>       <span>Refunds</span></a>
            <a href="complaints.php"       class="nav-link <?= $current_page=='complaints.php'       ?'active':'' ?>"><i class="bi bi-exclamation-triangle"></i>    <span>Complaints</span></a>
            <a href="flagged-products.php" class="nav-link <?= $current_page=='flagged-products.php' ?'active':'' ?>"><i class="bi bi-shield-exclamation text-warning"></i> <span>Flagged Items</span></a>
        <?php endif; ?>

    </nav>

    <div class="logout-section">
        <?php if ($userRole === 'supadmin'): ?>
            <a href="settings.php" class="nav-link <?= $current_page=='settings.php' ?'active':'' ?>">
                <i class="bi bi-gear"></i> <span>Platform Settings</span>
            </a>
        <?php endif; ?>
        <a href="profile.php" class="nav-link <?= $current_page=='profile.php' ?'active':'' ?>">
            <i class="bi bi-person-circle"></i> <span>My Account</span>
        </a>
        <a href="../logout.php" class="nav-link logout-link">
            <i class="bi bi-box-arrow-right"></i> <span>Log Out</span>
        </a>
    </div>

</aside>

<main class="main-content">

    <div class="top-bar">

        <div class="top-bar-left">
            <button class="btn btn-light d-lg-none shadow-sm flex-shrink-0" id="sidebarToggle" style="min-height:40px;min-width:40px;">
                <i class="bi bi-list fs-5"></i>
            </button>
            <div style="min-width:0;">
                <div class="page-subtitle">Admin Console</div>
                <h3 class="page-title">
                    <?php
                        $titles = [
                            'index.php'            => 'Dashboard Overview',
                            'users.php'            => 'Manage Customers',
                            'vendors.php'          => 'Manage Vendors',
                            'orders.php'           => 'Order Management',
                            'complaints.php'       => 'Product Complaints',
                            'flagged-products.php' => 'Fraud Detection Center',
                            'audit-logs.php'       => 'Security Logs',
                            'staff.php'            => 'Team Management',
                            'profile.php'          => 'My Account Settings',
                            'reports.php'          => 'Reports',
                            'payouts.php'          => 'Payouts',
                            'coupons.php'          => 'Promo Codes',
                            'marketing.php'        => 'Marketing Campaigns',
                            'messages.php'         => 'Messages',
                            'refunds.php'          => 'Refunds',
                            'settings.php'         => 'Platform Settings',
                        ];
                        echo $titles[$current_page] ?? 'ShopCorrect Admin';
                    ?>
                </h3>
            </div>
        </div>

        <div class="top-bar-right">

            <a href="messages.php" class="text-dark notif-wrapper me-3" title="Unread Messages">
                <i class="bi bi-envelope fs-4"></i>
                <?php if ($adminNotifCount > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger pulse-badge">
                        <?= $adminNotifCount > 99 ? '99+' : $adminNotifCount ?>
                    </span>
                <?php endif; ?>
            </a>

            <?php $redirectUrl = urlencode($_SERVER['REQUEST_URI']); ?>

            <div class="dropdown" title="Reference Currency (Base: GHS)">
                <a href="#" class="top-pill" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-cash-coin text-secondary"></i>
                    <span class="pill-label notranslate"><?= $_SESSION['currency'] ?? 'GHS' ?></span>
                    <i class="bi bi-chevron-down small opacity-50"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" style="max-height:350px;overflow-y:auto;">
                    <li><h6 class="dropdown-header fw-bold text-dark text-uppercase" style="font-size:0.68rem;">Est. Reference Currency</h6></li>
                    <li>
                        <div class="px-3 pb-2 text-muted" style="font-size:0.68rem;white-space:normal;line-height:1.3;">
                            <i class="bi bi-info-circle-fill text-primary"></i> Core accounting stays in GHS. Display only.
                        </div>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="../change-currency.php?cur=GHS&redirect_to=<?= $redirectUrl ?>"><img src="https://flagcdn.com/w20/gh.png" class="nav-flag"> GHS — Ghana Cedi (Base)</a></li>
                    <li><a class="dropdown-item" href="../change-currency.php?cur=NGN&redirect_to=<?= $redirectUrl ?>"><img src="https://flagcdn.com/w20/ng.png" class="nav-flag"> NGN — Nigerian Naira</a></li>
                    <li><a class="dropdown-item" href="../change-currency.php?cur=XOF&redirect_to=<?= $redirectUrl ?>"><img src="https://flagcdn.com/w20/ci.png" class="nav-flag"> XOF — CFA Franc (Côte d'Ivoire / Togo)</a></li>
                    <li><a class="dropdown-item" href="../change-currency.php?cur=ZAR&redirect_to=<?= $redirectUrl ?>"><img src="https://flagcdn.com/w20/za.png" class="nav-flag"> ZAR — South African Rand</a></li>
                    <li><a class="dropdown-item" href="../change-currency.php?cur=KES&redirect_to=<?= $redirectUrl ?>"><img src="https://flagcdn.com/w20/ke.png" class="nav-flag"> KES — Kenyan Shilling</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header fw-bold text-secondary text-uppercase" style="font-size:0.68rem;">International</h6></li>
                    <li><a class="dropdown-item" href="../change-currency.php?cur=GBP&redirect_to=<?= $redirectUrl ?>"><img src="https://flagcdn.com/w20/gb.png" class="nav-flag"> GBP — British Pound</a></li>
                    <li><a class="dropdown-item" href="../change-currency.php?cur=USD&redirect_to=<?= $redirectUrl ?>"><img src="https://flagcdn.com/w20/us.png" class="nav-flag"> USD — US Dollar</a></li>
                    <li><a class="dropdown-item" href="../change-currency.php?cur=CAD&redirect_to=<?= $redirectUrl ?>"><img src="https://flagcdn.com/w20/ca.png" class="nav-flag"> CAD — Canadian Dollar</a></li>
                    <li><a class="dropdown-item" href="../change-currency.php?cur=EUR&redirect_to=<?= $redirectUrl ?>"><img src="https://flagcdn.com/w20/de.png" class="nav-flag"> EUR — Euro (Germany / Spain)</a></li>
                    <li><a class="dropdown-item" href="../change-currency.php?cur=CNY&redirect_to=<?= $redirectUrl ?>"><img src="https://flagcdn.com/w20/cn.png" class="nav-flag"> CNY — Chinese Yuan</a></li>
                </ul>
            </div>

            <div class="dropdown">
                <a href="#" class="top-pill" data-bs-toggle="dropdown" aria-expanded="false">
                    <span id="current-lang-text" class="notranslate d-flex align-items-center gap-2">
                        <img src="<?= $currentDisplayFlag ?>" alt="flag" class="nav-flag">
                        <span class="pill-label"><?= htmlspecialchars($currentDisplayLabel) ?></span>
                    </span>
                    <i class="bi bi-chevron-down small opacity-50"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" style="max-height:400px;overflow-y:auto;">
                    <li><h6 class="dropdown-header fw-bold text-secondary text-uppercase" style="font-size:0.72rem;">Africa</h6></li>
                    <li><a class="dropdown-item" href="#" onclick="adminSwitchLang('en','gh','EN');return false;"><img src="https://flagcdn.com/w20/gh.png" class="nav-flag"> Ghana</a></li>
                    <li><a class="dropdown-item" href="#" onclick="adminSwitchLang('en','ng','EN');return false;"><img src="https://flagcdn.com/w20/ng.png" class="nav-flag"> Nigeria</a></li>
                    <li><a class="dropdown-item" href="#" onclick="adminSwitchLang('fr','ci','FR');return false;"><img src="https://flagcdn.com/w20/ci.png" class="nav-flag"> Cote d'Ivoire</a></li>
                    <li><a class="dropdown-item" href="#" onclick="adminSwitchLang('en','za','EN');return false;"><img src="https://flagcdn.com/w20/za.png" class="nav-flag"> South Africa</a></li>
                    <li><a class="dropdown-item" href="#" onclick="adminSwitchLang('sw','ke','SW');return false;"><img src="https://flagcdn.com/w20/ke.png" class="nav-flag"> Kenya</a></li>
                    <li><a class="dropdown-item" href="#" onclick="adminSwitchLang('fr','tg','FR');return false;"><img src="https://flagcdn.com/w20/tg.png" class="nav-flag"> Togo</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header fw-bold text-secondary text-uppercase" style="font-size:0.72rem;">International</h6></li>
                    <li><a class="dropdown-item" href="#" onclick="adminSwitchLang('en','gb','EN');return false;"><img src="https://flagcdn.com/w20/gb.png" class="nav-flag"> United Kingdom</a></li>
                    <li><a class="dropdown-item" href="#" onclick="adminSwitchLang('en','us','EN');return false;"><img src="https://flagcdn.com/w20/us.png" class="nav-flag"> United States</a></li>
                    <li><a class="dropdown-item" href="#" onclick="adminSwitchLang('en','ca','EN');return false;"><img src="https://flagcdn.com/w20/ca.png" class="nav-flag"> Canada</a></li>
                    <li><a class="dropdown-item" href="#" onclick="adminSwitchLang('de','de','DE');return false;"><img src="https://flagcdn.com/w20/de.png" class="nav-flag"> Germany</a></li>
                    <li><a class="dropdown-item" href="#" onclick="adminSwitchLang('zh-CN','cn','ZH');return false;"><img src="https://flagcdn.com/w20/cn.png" class="nav-flag"> China</a></li>
                    <li><a class="dropdown-item" href="#" onclick="adminSwitchLang('es','es','ES');return false;"><img src="https://flagcdn.com/w20/es.png" class="nav-flag"> Spain (Spanish)</a></li>
                </ul>
            </div>

            <div class="top-pill" style="cursor:default;">
                <div class="role-avatar"><?= htmlspecialchars($roleInitials) ?></div>
                <span class="pill-label fw-bold"><?= htmlspecialchars($roleBadgeText) ?></span>
            </div>

        </div>
    </div>

    <?php if ($activeLangCode !== 'en'): ?>
        <div id="google_translate_element" style="display:none;"></div>
        <script type="text/javascript">
            function googleTranslateElementInit() {
                new google.translate.TranslateElement({
                    pageLanguage: 'en',
                    includedLanguages: 'en,fr,de,es,sw,zh-CN',
                    autoDisplay: false
                }, 'google_translate_element');

                // ✅ Fire INSIDE the callback — Google is guaranteed ready here
                setTimeout(function() {
                    forceTranslate("<?= htmlspecialchars($activeLangCode) ?>");
                }, 600);
            }

            function forceTranslate(lang) {
                var tries = 0;
                var maxTries = 25;

                function applyLang() {
                    var select = document.querySelector(".goog-te-combo");

                    if (select) {
                        // Always force — no conditional skip that could get stuck
                        select.value = lang;
                        select.dispatchEvent(new Event("change"));

                        // Verify it stuck after 600ms, retry if not
                        setTimeout(function() {
                            var check = document.querySelector(".goog-te-combo");
                            if (check && check.value !== lang && tries < maxTries) {
                                tries++;
                                applyLang();
                            }
                        }, 600);

                    } else {
                        if (tries < maxTries) {
                            tries++;
                            setTimeout(applyLang, 300);
                        }
                    }
                }

                applyLang();
            }
        </script>
        <script type="text/javascript"
            src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit">
        </script>

    <?php else: ?>
        <script>
            (function() {
                var h = window.location.hostname;
                var r = "." + h.replace(/^www\./, '');
                var e = "expires=Thu, 01 Jan 1970 00:00:00 UTC";
                var paths   = ["/", "/public/", "/public/admin/", "/admin/", "/shopcorrect/public/"];
                var domains = ["", h, r];

                paths.forEach(function(p) {
                    domains.forEach(function(d) {
                        var dStr = d ? "; domain=" + d : "";
                        document.cookie = "googtrans=; " + e + dStr + "; path=" + p;
                    });
                });

                localStorage.removeItem('googtrans');
                sessionStorage.removeItem('googtrans');

                // If Google Translate widget is somehow already active, reset it to English
                var select = document.querySelector('.goog-te-combo');
                if (select) {
                    select.value = 'en';
                    select.dispatchEvent(new Event('change'));
                }
                document.body.style.top = '0px';
                document.documentElement.style.top = '0px';
            })();
        </script>
    <?php endif; ?>

    <script>
        // ✅ ADMIN NOTIFICATION SYNC
        document.addEventListener("DOMContentLoaded", function() {
            // Future background updates can go here
        });
    </script>