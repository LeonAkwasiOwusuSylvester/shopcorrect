<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure a default currency is set to Ghana Cedis
if (!isset($_SESSION['currency'])) {
    $_SESSION['currency'] = 'GHS';
}

// Wrap in function_exists to prevent errors if the file is included twice
if (!function_exists('formatPrice')) {
    function formatPrice($amount_in_ghs) {
        $currency = $_SESSION['currency'];

        // Static exchange rates (Base: 1 GHS). 
        // You can update these numbers based on current market rates.
        $rates = [
            'GHS' => 1.00,
            'USD' => 0.065,   
            'EUR' => 0.061,   
            'GBP' => 0.052,
            'NGN' => 97.50,   
            'KES' => 8.40,    
            'ZAR' => 1.49,
            'CAD' => 0.13,
            'AUD' => 0.13,
            'XOF' => 52.25,
            'JPY' => 14.70,
            'CNY' => 0.65,
            'INR' => 8.57,
            'AED' => 0.35
        ];

        // Currency Symbols
        $symbols = [
            'GHS' => '₵',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'NGN' => '₦',
            'KES' => 'KSh ',
            'ZAR' => 'R ',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'XOF' => 'CFA ',
            'JPY' => '¥',
            'CNY' => '¥',
            'INR' => '₹',
            'AED' => 'د.إ '
        ];

        // Get the correct rate and symbol, fallback to GHS if not found
        $rate = $rates[$currency] ?? 1.00;
        $symbol = $symbols[$currency] ?? '₵';

        // Calculate new price
        $converted_amount = (float)$amount_in_ghs * $rate;

        // Return formatted string (e.g., "$15.50" or "₦1,500.00")
        return $symbol . number_format($converted_amount, 2);
    }
}
?>