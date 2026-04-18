<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/../app/config/session.php';

$config = require __DIR__ . '/../app/config/google.php';

$client = new Google_Client();
$client->setClientId($config['client_id']);
$client->setClientSecret($config['client_secret']);
$client->setRedirectUri($config['redirect_uri']);

if (!isset($_GET['code'])) {
    $_SESSION['flash_error'] = 'Google login failed.';
    header('Location: login.php');
    exit;
}

// Exchange auth code for token
$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

if (isset($token['error'])) {
    $_SESSION['flash_error'] = 'Google authentication error.';
    header('Location: login.php');
    exit;
}

$client->setAccessToken($token);

// Fetch Google user info
$oauth = new Google_Service_Oauth2($client);
$userInfo = $oauth->userinfo->get();

$email    = $userInfo->email;
$name     = $userInfo->name;
$googleId = $userInfo->id;

/*
|--------------------------------------------------------------------------
| Find or create user
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare(
    "SELECT id, role FROM users WHERE email = ? LIMIT 1"
);
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // Create buyer account by default
    $stmt = $pdo->prepare(
        "INSERT INTO users (name, email, role, google_id)
         VALUES (?, ?, 'buyer', ?)"
    );
    $stmt->execute([$name, $email, $googleId]);

    $userId = $pdo->lastInsertId();
    $role   = 'buyer';
} else {
    $userId = $user['id'];
    $role   = $user['role'];

    // Update google_id if missing
    $pdo->prepare(
        "UPDATE users SET google_id = ? WHERE id = ? AND google_id IS NULL"
    )->execute([$googleId, $userId]);
}

/*
|--------------------------------------------------------------------------
| STEP 7C — OTP GENERATION (GOOGLE LOGIN)
|--------------------------------------------------------------------------
*/
$otp     = random_int(100000, 999999);
$expires = date("Y-m-d H:i:s", time() + 300); // 5 minutes

$pdo->prepare(
    "UPDATE users
     SET otp_code = ?, otp_expires_at = ?
     WHERE id = ?"
)->execute([$otp, $expires, $userId]);

// Store temporary OTP session
$_SESSION['otp_user'] = $userId;

// Send OTP email
mail(
    $email,
    "Your ShopCorrect Login Code",
    "Your OTP code is: $otp\n\nThis code expires in 5 minutes.",
    "From: ShopCorrect <no-reply@shopcorrect.com>"
);

$_SESSION['flash_success'] =
    "A verification code has been sent to your email.";

// Redirect to OTP verification
header('Location: verify-otp.php');
exit;
