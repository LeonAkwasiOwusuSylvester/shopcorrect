<?php
require_once __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../app/config/google.php';

$client = new Google_Client();

$client->setClientId($config['client_id']);
$client->setClientSecret($config['client_secret']);
$client->setRedirectUri($config['redirect_uri']);

$client->addScope('email');
$client->addScope('profile');

$authUrl = $client->createAuthUrl();

header("Location: " . $authUrl);
exit;
