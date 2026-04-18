<?php
require_once __DIR__ . "/../config/session.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "super_admin") {
    die("Super admin only");
}
