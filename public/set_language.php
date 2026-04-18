<?php
// ✅ 1. Bring in the session config so it DOES NOT log you out!
require_once __DIR__ . '/../app/config/session.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$allowed_langs = ['en', 'fr', 'de', 'es', 'sw', 'zh-CN'];

if (isset($_GET['lang'])) {
    $lang = trim($_GET['lang']);
    
    if (in_array($lang, $allowed_langs)) {
        // Save UI settings
        $_SESSION['lang'] = $lang;
        if (isset($_GET['c']) && isset($_GET['l'])) {
            $_SESSION['country_code'] = $_GET['c'];
            $_SESSION['country_label'] = $_GET['l'];
            setcookie('shop_c', $_GET['c'], time() + (86400 * 30), '/');
            setcookie('shop_l', $_GET['l'], time() + (86400 * 30), '/');
        }

        $host = $_SERVER['HTTP_HOST'];
        $rootDomain = '.' . preg_replace('/^www\./', '', $host);

        // Nuke old cookies
        setcookie('googtrans', '', 1, '/');
        setcookie('googtrans', '', 1, '/', $host);
        setcookie('googtrans', '', 1, '/', $rootDomain);

        // Write the new cookie
        if ($lang !== 'en') {
            $gt_cookie = "/en/" . $lang;
            setcookie('googtrans', $gt_cookie, time() + (86400 * 30), '/');
            setcookie('googtrans', $gt_cookie, time() + (86400 * 30), '/', $rootDomain);
        }
    }
}

$redirect = $_SERVER['HTTP_REFERER'] ?? '/';

// ✅ 2. Clean the URL: Remove hashes and any looping _gt parameters
$redirect = explode('#', $redirect)[0];
$redirect = preg_replace('/(\?|&)_gt=[^&]*/', '', $redirect); 

session_write_close();
header("Location: " . $redirect);
exit;