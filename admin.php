<?php
define("SECURE_CMS_INIT", true);
require_once __DIR__ . "/includes/config.php";
require_once __DIR__ . "/includes/Security.php";

$security = Security::getInstance();
if ($security->isAuthenticated()) {
    header("Location: " . cms_path("admin/admin.php"));
    exit();
}
header("Location: " . cms_path("admin/login.php"));
exit();
