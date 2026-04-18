<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function requireRole(string $role)
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== $role) {
        header("Location: /shopcorrect/public/login.php");
        exit;
    }
}
