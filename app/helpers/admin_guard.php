<?php
require_once __DIR__ . "/../config/session.php";

if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["role"], ["admin", "super_admin"])) {
    die("Admin only");
}
