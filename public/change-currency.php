<?php
session_start();

// Valid currencies
$allowed = [
    'GHS', 'USD', 'EUR', 'GBP', 'NGN', 
    'KES', 'ZAR', 'CAD', 'AUD', 'XOF', 
    'JPY', 'CNY', 'INR', 'AED'
];
$new_currency = $_GET['cur'] ?? 'GHS';

if (in_array($new_currency, $allowed)) {
    $_SESSION['currency'] = $new_currency;
}

// Redirect back to the page the user was on
$referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header("Location: $referer");
exit;