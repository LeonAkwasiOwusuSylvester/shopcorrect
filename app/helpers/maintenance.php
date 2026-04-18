<?php
require_once __DIR__ . "/../config/db.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$stmt = $pdo->query("SELECT maintenance_mode, maintenance_roles FROM settings LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

if ($settings && $settings['maintenance_mode'] == 1) {
    
    $currentRole = $_SESSION['role'] ?? 'guest';
    
    // Super Admins are completely immune
    if ($currentRole !== 'supadmin') {
        
        $blockedRoles = !empty($settings['maintenance_roles']) ? explode(',', $settings['maintenance_roles']) : [];
        
        // Pages that must NEVER be blocked so people can log in or recover accounts
        $allowedPages = ['login.php', 'register.php', 'forgot-password.php', 'reset-password.php', 'maintenance.php', 'logout.php'];
        $currentPage = basename($_SERVER['PHP_SELF']);

        // 1. Handle Logged-In Users
        if ($currentRole !== 'guest' && in_array($currentRole, $blockedRoles)) {
            // Let them log out, otherwise send them to maintenance
            if (!in_array($currentPage, ['maintenance.php', 'logout.php'])) {
                header("Location: /shopcorrect/public/maintenance.php");
                exit;
            }
        }
        
        // 2. Handle Guests (Not Logged In)
        if ($currentRole === 'guest') {
            // If standard buyers are blocked, block guests from the main shop too
            if (in_array('user', $blockedRoles)) {
                // Only let them see the allowed auth pages
                if (!in_array($currentPage, $allowedPages)) {
                    header("Location: /shopcorrect/public/maintenance.php");
                    exit;
                }
            }
        }
    }
}