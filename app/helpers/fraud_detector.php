<?php

class FraudDetector {
    
    public static function analyzeProduct($name, $description, $price) {
        $nameLower = strtolower($name);
        $descLower = strtolower($description);
        $combinedText = $nameLower . ' ' . $descLower;

        // --- LEVEL 1: SMART KEYWORD ENGINE ---
        // Scammers often use these words to bypass trademark rules
        $bannedWords = [
            'replica', '1:1', 'clone', 'aaa grade', 'super copy', 
            'oem copy', 'knockoff', 'first copy', 'master copy'
        ];

        foreach ($bannedWords as $word) {
            if (strpos($combinedText, $word) !== false) {
                return [
                    'is_suspicious' => true, 
                    'reason' => "Contains prohibited counterfeit keyword: '" . strtoupper($word) . "'"
                ];
            }
        }

        // --- LEVEL 2: PRICE-TO-BRAND HEURISTICS ---
        // If a vendor lists a brand new PS5 for 500 GHS, it's a scam.
        // Format: 'brand name' => Minimum Logical Price (in GHS)
        $brandRules = [
            'iphone 16' => 8000,
            'iphone 15' => 6000,
            'iphone 14' => 4000,
            'playstation 5' => 4500,
            'ps5' => 4500,
            'macbook pro' => 3000,
            'rolex' => 2000,
            'airpods pro' => 1000
        ];

        foreach ($brandRules as $brand => $minPrice) {
            // If the title contains the brand, but the price is suspiciously low
            if (strpos($nameLower, $brand) !== false && $price < $minPrice) {
                return [
                    'is_suspicious' => true, 
                    'reason' => "Price (₵$price) is suspiciously low for a '" . strtoupper($brand) . "'. Possible counterfeit."
                ];
            }
        }

        // --- LEVEL 3: AI API INTEGRATION (Ready for connection) ---
        // Once you get a free API key, you can uncomment this to send the text to a real AI.
        /*
        $aiResult = self::checkWithAIAPI($name, $description, $price);
        if ($aiResult['is_suspicious']) {
            return $aiResult;
        }
        */

        // If it passes all tests, it is good to go!
        return [
            'is_suspicious' => false, 
            'reason' => null
        ];
    }
}