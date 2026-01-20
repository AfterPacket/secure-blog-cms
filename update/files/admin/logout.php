<?php
/**
 * Secure Blog CMS - Logout Page
 * Securely terminate user session and clear authentication
 */

// Initialize security constant
define("SECURE_CMS_INIT", true);

// Load configuration
require_once __DIR__ . "/../includes/config.php";

// Load required classes
require_once __DIR__ . "/../includes/Security.php";

// Initialize security
$security = Security::getInstance();

// Check if user is authenticated before logging out
if ($security->isAuthenticated()) {
    // Log the logout event
    $security->logSecurityEvent("User logout", $_SESSION["user"] ?? "unknown");

    // Perform logout
    $security->logout();
}

// Redirect to login page
header("Location: login.php");
exit();
