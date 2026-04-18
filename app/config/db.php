<?php
ob_start();
// Use getenv to pull secrets from Render's vault
$host = getenv('DB_HOST');
$db   = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$port = getenv('DB_PORT');
$charset = "utf8mb4";

// On Render, the site URL changes, so we pull it from an environment variable too
define('BASE_URL', getenv('BASE_URL'));

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$db;charset=$charset",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            // This path points from app/config/ up to the root where ca.pem sits
            PDO::MYSQL_ATTR_SSL_CA       => __DIR__ . '/../../ca.pem',
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

require_once __DIR__ . "/env.php";

// ... keep your Super Admin creation logic below this line ...

/*
|----------------------------------------------------------------------
| AUTO-CREATE DEFAULT SUPER ADMIN (DEV ONLY, SAFE)
|----------------------------------------------------------------------
| - Runs ONLY in dev
| - Creates admin ONLY if email does not exist
| - NEVER throws duplicate error
| - Safe to include on every request
*/
if (defined('APP_ENV') && APP_ENV === 'dev') {

    $adminEmail = "admin@shopcorrect.com";

    // Check by EMAIL (strongest unique constraint)
    $stmt = $pdo->prepare(
        "SELECT id FROM users WHERE email = ? LIMIT 1"
    );
    $stmt->execute([$adminEmail]);

    if (!$stmt->fetch()) {

        $passwordHash = password_hash("admin123", PASSWORD_DEFAULT);

        $insert = $pdo->prepare(
            "INSERT INTO users (name, email, password_hash, role)
             VALUES (?, ?, ?, ?)"
        );

        $insert->execute([
            "System Super Admin",
            $adminEmail,
            $passwordHash,
            "supadmin"   // 🔑 MATCH YOUR SYSTEM ROLE
        ]);
    }
}