<?php
/*
|--------------------------------------------------------------------------
| SECURE DOCUMENT VIEWER (Final Verdict)
|--------------------------------------------------------------------------
| Serves files from ../storage/uploads/vendor_verification/
| Only allows Super Admins to access.
*/

require_once __DIR__ . "/../../app/config/session.php";

// 1. SECURITY: Only Super Admins allowed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'supadmin') {
    http_response_code(403);
    die("Access Denied.");
}

// 2. INPUT: Get the filename securely
// We use 'file' because vendors.php sends ?file=filename.jpg
$file = basename($_GET['file'] ?? ''); 

// 3. VALIDATION: Allow only safe filenames (alphanumeric, dots, underscores, dashes)
if (empty($file) || !preg_match('/^[a-zA-Z0-9_.-]+$/', $file)) {
    http_response_code(400);
    die("Invalid filename.");
}

// 4. PATH: The exact folder you specified
$storagePath = __DIR__ . "/../../storage/uploads/vendor_verification/";
$fullPath    = $storagePath . $file;

// 5. SERVE THE FILE
if (file_exists($fullPath)) {
    // Detect MIME type securely
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $fullPath);
    finfo_close($finfo);
    
    // SECURITY ENHANCEMENT: Prevent browser from caching sensitive ID documents
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    
    // Set Headers
    header("Content-Type: $mime");
    header("Content-Length: " . filesize($fullPath));
    header("X-Content-Type-Options: nosniff");
    
    // Handle Download vs Preview securely
    $mode = $_GET['mode'] ?? 'inline'; 
    $mode = ($mode === 'attachment') ? 'attachment' : 'inline'; // Strict validation
    
    header("Content-Disposition: $mode; filename=\"$file\"");
    
    // Prevent corrupted files by clearing any accidental whitespace output
    if (ob_get_length()) {
        ob_clean();
    }
    
    // Output file
    readfile($fullPath);
    exit;
} else {
    http_response_code(404);
    die("File not found in storage.");
}