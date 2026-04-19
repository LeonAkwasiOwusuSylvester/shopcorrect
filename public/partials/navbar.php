<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/../../app/config/db.php";

// Get the saved flag data (Defaults to English/UK)
$activeLangCode = $_SESSION['lang'] ?? $_COOKIE['shop_lang'] ?? 'en';
$activeCountry  = $_SESSION['country_code'] ?? $_COOKIE['shop_c'] ?? 'gb';
$activeLabel    = $_SESSION['country_label'] ?? $_COOKIE['shop_l'] ?? 'EN';

$userId     = $_SESSION['user_id'] ?? null;
$userName   = $_SESSION['name'] ?? 'Guest';
$isLoggedIn = isset($userId);

$cartCount     = 0;
$navCategories = [];

// ════ SECURITY FIX: Sanitize Search Query & Prevent Array Injection ════
$safeSearchQuery = '';
if (isset($_GET['q']) && is_string($_GET['q'])) {
    // ENT_QUOTES prevents malicious code from breaking out of HTML attributes
    $safeSearchQuery = htmlspecialchars(trim($_GET['q']), ENT_QUOTES, 'UTF-8');
}

// ════ PROMO BANNER LOGIC: Hide if user is actively searching ════
$isSearching = ($safeSearchQuery !== '');

if ($isLoggedIn) {
    try {
        $stmt = $pdo->prepare("SELECT SUM(ci.quantity) FROM cart_items ci JOIN carts c ON ci.cart_id = c.id WHERE c.user_id = ?");
        $stmt->execute([$userId]);
        $cartCount = (int) ($stmt->fetchColumn() ?: 0);
    } catch (PDOException $e) { }
}

try {
    $navCategories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { }

$navLangFlag  = "https://flagcdn.com/w40/{$activeCountry}.png";
$navLangLabel = $activeLabel;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ShopCorrect</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <style>
    /* ════ COMPLETELY HIDE ALL GOOGLE WIDGETS & FLOATING ICONS ════ */
    #google_translate_element, 
    .goog-te-banner-frame, 
    .goog-te-gadget, 
    .skiptranslate,
    .goog-tooltip,
    .goog-tooltip:hover,
    #goog-gt-tt,
    .VIpgJd-Zvi9od-aZ2wEe-wOHMyf,
    .VIpgJd-Zvi9od-aZ2wEe-OiiCO { 
        display: none !important; 
        visibility: hidden !important;
        opacity: 0 !important;
    }
    body { top: 0px !important; position: static !important; }
    .notranslate { color: inherit !important; }
        
        /* ════ NAVBAR STYLES ════ */
        :root { --sc-navy: #0B2447; --sc-gold: #FFD700; }
        .marketplace-nav { background: var(--sc-navy); padding: 0.5rem 0; font-family: 'Inter', sans-serif; }
        .nav-logo-img { height: 48px; width: auto; }
        @media (min-width: 768px) { .nav-logo-img { height: 58px; } }
        @media (min-width: 992px) { .nav-logo-img { height: 65px; } }
        .nav-left-section { display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0; }
        .nav-search-container { flex: 1; margin: 0 1rem; display: flex; }
        .nav-search-box { display: flex; width: 100%; position: relative; align-items: center; }
        
        /* ✅ UPDATED SEARCH BAR STYLES */
        .nav-search-box input { 
            width: 100%; 
            height: 44px; 
            padding: 10px 52px 10px 20px; 
            border-radius: 50px; 
            border: none; 
            font-size: 0.9rem; 
            outline: none; 
            background-color: #ffffff; 
            color: #333333; 
        }
        .nav-search-box input::placeholder { color: #6c757d; }
        .nav-search-box input:focus { box-shadow: 0 0 0 3px rgba(255,215,0,0.3); }
        .nav-search-box button { position: absolute; right: 4px; top: 50%; transform: translateY(-50%); height: 36px; width: 36px; border: none; background: var(--sc-gold); color: var(--sc-navy); border-radius: 50%; display: flex; align-items: center; justify-content: center; padding: 0; transition: transform 0.2s ease; }
        .nav-search-box button:hover { transform: translateY(-50%) scale(1.05); }
        
        .nav-actions-container { display: flex; align-items: center; gap: 0.4rem; flex-shrink: 0; margin-left: auto; }
        @media (min-width: 400px) { .nav-actions-container { gap: 0.6rem; } }
        @media (min-width: 768px) { .nav-actions-container { gap: 1rem; } }
        @media (min-width: 992px) { .nav-actions-container { gap: 1.2rem; } }
        .nav-action { display: flex; align-items: center; gap: 3px; color: white; text-decoration: none; font-weight: 600; font-size: 0.75rem; white-space: nowrap; cursor: pointer; background: transparent; border: none; padding: 4px 2px; transition: color 0.2s; }
        @media (min-width: 576px) { .nav-action { font-size: 0.85rem; gap: 5px; } }
        .nav-action:hover { color: var(--sc-gold); }
        .nav-action i.fs-5, .nav-action .bi-cart3 { font-size: 1.2rem !important; }
        @media (min-width: 768px) { .nav-action i.fs-5, .nav-action .bi-cart3 { font-size: 1.35rem !important; } }
        
        /* ✅ UPDATED BADGE STYLES FOR ANIMATION */
        .sc-badge { position: absolute; top: -6px; right: -8px; background: var(--sc-gold); color: var(--sc-navy); font-size: 0.65rem; font-weight: 800; height: 18px; min-width: 18px; border-radius: 50px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(0,0,0,0.2); transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        
        .dropdown-menu { border: none; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); margin-top: 15px !important; padding: 10px 0; }
        .dropdown-item { padding: 8px 18px; font-weight: 500; color: #334155; display: flex; align-items: center; gap: 10px; font-size: 0.88rem; transition: background 0.2s; }
        .dropdown-item i { color: #64748B; font-size: 1rem; }
        .dropdown-item:hover { background-color: #F8FAFC; color: var(--sc-navy); }
        @media (max-width: 991px) {
            .nav-actions-container .dropdown { position: static; }
            .nav-actions-container .dropdown-menu { position: absolute !important; top: 100% !important; right: 10px !important; left: auto !important; margin-top: 5px !important; width: min(300px, calc(100vw - 20px)); max-height: 80vh; overflow-y: auto; -webkit-overflow-scrolling: touch; z-index: 1050; border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.18); }
        }
        #mobileSearch .nav-search-box { margin-top: 0.4rem; }
        .offcanvas-categories { background-color: white; width: 280px; z-index: 1060; }
        .offcanvas-header { background-color: var(--sc-navy); color: white; padding: 1.1rem 1.2rem; }
        .category-list-item { display: flex; align-items: center; gap: 14px; padding: 11px 18px; color: #334155; text-decoration: none; border-bottom: 1px solid #F1F5F9; font-weight: 500; font-size: 0.9rem; transition: 0.3s; }
        .category-list-item:hover { background-color: #F8FAFC; padding-left: 22px; color: var(--sc-navy); }
        .nav-flag-active { width: 24px; height: 16px; object-fit: cover; border-radius: 3px; box-shadow: 0 1px 4px rgba(0,0,0,0.35); }
        .lang-dropdown { width: 292px !important; padding: 14px !important; border-radius: 14px !important; border: none !important; box-shadow: 0 12px 36px rgba(0,0,0,0.18) !important; }
        .lang-section-title { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.09em; color: #94a3b8; margin: 0 0 8px 2px; }
        .lang-divider { border-color: #e2e8f0; margin: 12px 0; }
        .lang-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
        .lang-tile { display: flex; flex-direction: column; align-items: center; gap: 5px; padding: 8px 4px; border-radius: 10px; text-decoration: none; color: #334155; font-size: 0.7rem; font-weight: 600; transition: background 0.15s ease, transform 0.15s ease, border-color 0.15s ease; border: 1.5px solid transparent; text-align: center; line-height: 1.2; cursor: pointer; }
        .lang-tile img { width: 38px; height: 26px; object-fit: cover; border-radius: 5px; box-shadow: 0 1px 5px rgba(0,0,0,0.18); transition: transform 0.15s ease, box-shadow 0.15s ease; }
        .lang-tile:hover { background: #F1F5F9; border-color: #cbd5e1; color: var(--sc-navy); transform: translateY(-2px); }
        .lang-tile:hover img { transform: scale(1.05); box-shadow: 0 3px 8px rgba(0,0,0,0.22); }
        .lang-tile.active { background: #EFF6FF; border-color: var(--sc-navy); color: var(--sc-navy); }
        .lang-tile.active img { box-shadow: 0 2px 6px rgba(11,36,71,0.3); }
    </style>
</head>
<body>

<?php 
// ✅ CONDITIONALLY LOAD BANNER: Only show it if the user isn't actively searching
if (!$isSearching) {
    require_once __DIR__ . "/promo_banner.php"; 
}
?>

<nav class="navbar navbar-expand-lg navbar-dark marketplace-nav sticky-top flex-column shadow-sm" id="mainNavbar">
    <div class="container-fluid px-3 d-flex align-items-center w-100 position-relative">
        <div class="nav-left-section">
            <button class="hamburger-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#categoriesSidebar" style="background:none;border:none;color:white;font-size:1.6rem;padding:2px 4px;transition: color 0.2s;">
                <i class="bi bi-list"></i>
            </button>
            <a class="navbar-brand m-0" href="index.php">
                <img src="assets/images/logo_w.png" class="nav-logo-img" alt="ShopCorrect">
            </a>
        </div>

        <div class="nav-search-container d-none d-lg-flex">
            <form class="nav-search-box" method="GET" action="index.php">
                <input type="text" name="q" value="<?= $safeSearchQuery ?>" placeholder="Search products, brands and categories">
                <button type="submit" title="Search">
                    <i class="bi bi-search" style="font-size: 1.1rem; font-weight: bold; color: var(--sc-navy);"></i>
                </button>
            </form>
        </div>

        <div class="nav-actions-container">
            
            <div class="dropdown">
                <button class="nav-action" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Language">
                    <span id="current-lang-text" class="d-flex align-items-center gap-1">
                        <img id="nav-lang-flag" src="<?= $navLangFlag ?>" alt="flag" class="nav-flag-active">
                        <span id="nav-lang-label" class="d-none d-sm-inline notranslate"><?= $navLangLabel ?></span>
                    </span>
                    <i class="bi bi-chevron-down small opacity-50 d-none d-md-inline"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end lang-dropdown">
                    <p class="lang-section-title">🌍 Africa</p>
                    <div class="lang-grid">
                        <a class="lang-tile <?= $activeCountry == 'gh' ? 'active' : '' ?>" onclick="switchLang('en', 'gh', 'GH'); return false;">
                            <img src="https://flagcdn.com/w40/gh.png" alt="Ghana"><span class="notranslate">Ghana</span>
                        </a>
                        <a class="lang-tile <?= $activeCountry == 'ng' ? 'active' : '' ?>" onclick="switchLang('en', 'ng', 'NG'); return false;">
                            <img src="https://flagcdn.com/w40/ng.png" alt="Nigeria"><span class="notranslate">Nigeria</span>
                        </a>
                        <a class="lang-tile <?= $activeCountry == 'ci' ? 'active' : '' ?>" onclick="switchLang('fr', 'ci', 'FR'); return false;">
                            <img src="https://flagcdn.com/w40/ci.png" alt="Côte d'Ivoire"><span class="notranslate">Côte d'Ivoire</span>
                        </a>
                        <a class="lang-tile <?= $activeCountry == 'za' ? 'active' : '' ?>" onclick="switchLang('en', 'za', 'ZA'); return false;">
                            <img src="https://flagcdn.com/w40/za.png" alt="South Africa"><span class="notranslate">South Africa</span>
                        </a>
                        <a class="lang-tile <?= $activeCountry == 'ke' ? 'active' : '' ?>" onclick="switchLang('sw', 'ke', 'SW'); return false;">
                            <img src="https://flagcdn.com/w40/ke.png" alt="Kenya"><span class="notranslate">Kenya</span>
                        </a>
                        <a class="lang-tile <?= $activeCountry == 'tg' ? 'active' : '' ?>" onclick="switchLang('fr', 'tg', 'FR'); return false;">
                            <img src="https://flagcdn.com/w40/tg.png" alt="Togo"><span class="notranslate">Togo</span>
                        </a>
                    </div>
                    <hr class="lang-divider">
                    <p class="lang-section-title">🌐 International</p>
                    <div class="lang-grid">
                        <a class="lang-tile <?= $activeCountry == 'gb' ? 'active' : '' ?>" onclick="switchLang('en', 'gb', 'EN'); return false;">
                            <img src="https://flagcdn.com/w40/gb.png" alt="United Kingdom"><span class="notranslate">UK</span>
                        </a>
                        <a class="lang-tile <?= $activeCountry == 'us' ? 'active' : '' ?>" onclick="switchLang('en', 'us', 'EN'); return false;">
                            <img src="https://flagcdn.com/w40/us.png" alt="United States"><span class="notranslate">USA</span>
                        </a>
                        <a class="lang-tile <?= $activeCountry == 'ca' ? 'active' : '' ?>" onclick="switchLang('en', 'ca', 'EN'); return false;">
                            <img src="https://flagcdn.com/w40/ca.png" alt="Canada"><span class="notranslate">Canada</span>
                        </a>
                        <a class="lang-tile <?= $activeCountry == 'de' ? 'active' : '' ?>" onclick="switchLang('de', 'de', 'DE'); return false;">
                            <img src="https://flagcdn.com/w40/de.png" alt="Germany"><span class="notranslate">Germany</span>
                        </a>
                        <a class="lang-tile <?= $activeCountry == 'cn' ? 'active' : '' ?>" onclick="switchLang('zh-CN', 'cn', 'ZH'); return false;">
                            <img src="https://flagcdn.com/w40/cn.png" alt="China"><span class="notranslate">China</span>
                        </a>
                        <a class="lang-tile <?= $activeCountry == 'es' ? 'active' : '' ?>" onclick="switchLang('es', 'es', 'ES'); return false;">
                            <img src="https://flagcdn.com/w40/es.png" alt="Spain"><span class="notranslate">España</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="dropdown">
                <button class="nav-action" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Account">
                    <?php if (!$isLoggedIn): ?>
                        <i class="bi bi-person fs-5"></i>
                        <span class="d-none d-sm-inline"> Account</span>
                    <?php else: ?>
                        <i class="bi bi-person-check-fill text-warning fs-5"></i>
                        <span class="d-none d-sm-inline"> Hi, <?= htmlspecialchars(explode(' ', $userName)[0]) ?></span>
                    <?php endif; ?>
                    <i class="bi bi-chevron-down small opacity-50 d-none d-sm-inline"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php if (!$isLoggedIn): ?>
                        <li class="px-3 py-2">
                            <a href="login.php" class="btn btn-dark w-100 fw-bold mb-2">Log In</a>
                            <a href="register.php" class="btn btn-outline-dark w-100 fw-bold">Sign Up</a>
                        </li>
                    <?php else: ?>
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-gear"></i> My Account</a></li>
                        <li><a class="dropdown-item" href="my-orders.php"><i class="bi bi-box-seam"></i> Orders</a></li>
                        <li><a class="dropdown-item" href="wishlist.php"><i class="bi bi-heart"></i> Wishlist</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger fw-bold" href="logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="dropdown">
                <button class="nav-action" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Help">
                    <i class="bi bi-question-circle fs-5"></i>
                    <span class="d-none d-sm-inline"> Help</span>
                    <i class="bi bi-chevron-down small opacity-50 d-none d-sm-inline"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="help.php"><i class="bi bi-chat-dots"></i> Help Center</a></li>
                    <li><a class="dropdown-item" href="my-orders.php"><i class="bi bi-truck"></i> Track Order</a></li>
                    <li><a class="dropdown-item" href="help.php#returns"><i class="bi bi-arrow-return-left"></i> Returns & Refunds</a></li>
                    <li><a class="dropdown-item" href="contact.php"><i class="bi bi-telephone"></i> Contact Us</a></li>
                </ul>
            </div>

            <button class="nav-action d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#mobileSearch" title="Search">
                <i class="bi bi-search fs-5"></i>
            </button>

            <a href="cart.php" class="nav-action" title="Cart">
                <div class="icon-wrapper" style="position:relative;">
                    <i class="bi bi-cart3 fs-5"></i>
                    <span id="navCartBadge" class="sc-badge <?= $cartCount > 0 ? '' : 'd-none' ?>">
                        <?= $cartCount ?>
                    </span>
                </div>
                <span class="d-none d-sm-inline"> Cart</span>
            </a>
        </div>
    </div>

    <div class="collapse w-100 d-lg-none px-3 pb-2" id="mobileSearch">
        <form class="nav-search-box mt-2" method="GET" action="index.php">
            <input type="text" name="q" value="<?= $safeSearchQuery ?>" placeholder="Search products, brands and categories">
            <button type="submit" title="Search">
                <i class="bi bi-search" style="font-size: 1.1rem; font-weight: bold; color: var(--sc-navy);"></i>
            </button>
        </form>
    </div>
</nav>

<div class="offcanvas offcanvas-start offcanvas-categories" tabindex="-1" id="categoriesSidebar">
    <div class="offcanvas-header">
        <h5 class="mb-0 fw-bold"><i class="bi bi-grid-3x3-gap-fill me-2 text-warning"></i> Categories</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0">
        <?php foreach($navCategories as $c): ?>
            <?php
                $catName = strtolower($c['name']); $icon = 'bi-tag';
                if(strpos($catName, 'phone') !== false)                                                $icon = 'bi-phone';
                elseif(strpos($catName, 'fashion') !== false || strpos($catName, 'cloth') !== false)   $icon = 'bi-handbag';
                elseif(strpos($catName, 'elect') !== false)                                            $icon = 'bi-lightning-charge';
                elseif(strpos($catName, 'home') !== false)                                             $icon = 'bi-house-door';
                elseif(strpos($catName, 'comput') !== false || strpos($catName, 'laptop') !== false)   $icon = 'bi-laptop';
                elseif(strpos($catName, 'beauty') !== false || strpos($catName, 'health') !== false)   $icon = 'bi-magic';
                elseif(strpos($catName, 'sport') !== false)                                            $icon = 'bi-bicycle';
                elseif(strpos($catName, 'game') !== false)                                             $icon = 'bi-controller';
            ?>
            <a href="index.php?category=<?= $c['id'] ?>" class="category-list-item">
                <i class="bi <?= $icon ?> text-secondary fs-5"></i>
                <?= htmlspecialchars($c['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<script>
    // This perfectly syncs the flag you clicked with the Google Engine
    function switchLang(lang, country, label) {
        var host = window.location.hostname;
        var rootDomain = "." + host.replace(/^www\./, '');

        var expire = "expires=Thu, 01 Jan 1970 00:00:00 UTC";
        var paths = ["/", "/shopcorrect/public/"];
        var domains = ["", host, rootDomain];

        paths.forEach(function(p) {
            domains.forEach(function(d) {
                var dStr = d ? "; domain=" + d : "";
                document.cookie = "googtrans=; " + expire + dStr + "; path=" + p;
            });
        });

        localStorage.removeItem('googtrans');
        sessionStorage.removeItem('googtrans');

        window.location.href = "set_language.php?lang=" + lang + "&c=" + country + "&l=" + label;
    }

    // ✅ NEW GLOBAL FUNCTION: Call this from your Add to Cart scripts!
    window.updateCartBadge = function(newCount) {
        let badge = document.getElementById('navCartBadge');
        if (badge) {
            badge.innerText = newCount;
            if (parseInt(newCount) > 0) {
                badge.classList.remove('d-none');
                // Give it a satisfying pop animation
                badge.style.transform = 'scale(1.4)';
                setTimeout(() => { badge.style.transform = 'scale(1)'; }, 200);
            } else {
                badge.classList.add('d-none');
            }
        }
    };
</script>

<?php if ($activeLangCode !== 'en'): ?>
    <div id="google_translate_element"></div>
    <script type="text/javascript">
        function googleTranslateElementInit() {
            new google.translate.TranslateElement({
                pageLanguage: 'en',
                includedLanguages: 'en,fr,de,es,sw,zh-CN',
                autoDisplay: false
            }, 'google_translate_element');

            // ✅ Fire INSIDE the callback — Google is guaranteed ready here
            setTimeout(function() {
                forceTranslate("<?= $activeLangCode ?>");
            }, 600);
        }

        function forceTranslate(lang) {
            var tries = 0;
            var maxTries = 25; // More retries = more reliable on fast desktops

            function applyLang() {
                var select = document.querySelector(".goog-te-combo");

                if (select) {
                    // ✅ ALWAYS force the value and dispatch — no conditional skip
                    select.value = lang;
                    select.dispatchEvent(new Event("change"));

                    // ✅ Verify it actually stuck after 600ms, retry if not
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
        var host = window.location.hostname;
        var rootDomain = "." + host.replace(/^www\./, '');
        var expire = "expires=Thu, 01 Jan 1970 00:00:00 UTC";
        document.cookie = "googtrans=; " + expire + "; path=/;";
        document.cookie = "googtrans=; " + expire + "; domain=" + host + "; path=/;";
        document.cookie = "googtrans=; " + expire + "; domain=" + rootDomain + "; path=/;";
    </script>
<?php endif; ?>

<script>
// Dropdown positioning logic
document.addEventListener('DOMContentLoaded', function () {
    var navbar = document.getElementById('mainNavbar');
    function positionDropdown(menu) {
        if (!menu) return;
        if (window.innerWidth < 992) {
            var navBottom = navbar ? navbar.getBoundingClientRect().bottom : 60;
            menu.style.top  = (navBottom + 4) + 'px';
            menu.style.left = 'auto';
        } else {
            menu.style.top  = '';
            menu.style.left = '';
        }
    }
    document.addEventListener('shown.bs.dropdown', function (e) {
        var menu = e.target.closest('.dropdown') ? e.target.closest('.dropdown').querySelector('.dropdown-menu') : null;
        positionDropdown(menu);
    });
    var openMenu = null;
    document.addEventListener('shown.bs.dropdown',  function (e) {
        openMenu = e.target.closest('.dropdown') ? e.target.closest('.dropdown').querySelector('.dropdown-menu') : null;
    });
    document.addEventListener('hidden.bs.dropdown', function () { openMenu = null; });
    window.addEventListener('resize', function () {
        if (openMenu) positionDropdown(openMenu);
    });
});
</script>
</body>
</html>