#!/usr/bin/env php
<?php
/**
 * Secure Blog CMS — Password Reset Utility
 * 
 * Run from command line to reset a user's password.
 * This is the ONLY secure way to reset passwords since
 * the CMS has no email functionality.
 * 
 * Usage:
 *   php reset_password.php <username>
 *   php reset_password.php <username> <new_password>
 * 
 * If no password is provided as an argument, you will be
 * prompted to enter it interactively (hidden input).
 * 
 * Examples:
 *   php reset_password.php admin
 *   php reset_password.php admin 'MyN3wP@ssword!'
 * 
 * IMPORTANT: If passing password as an argument, ALWAYS use
 * single quotes to prevent shell expansion of special
 * characters like $, !, *, ?, and backticks.
 * 
 * Interactive mode (no password argument) is the safest
 * option — it avoids shell expansion entirely.
 */

if (php_sapi_name() !== 'cli') {
    echo "This script can only be run from the command line.\n";
    exit(1);
}

// Parse arguments
$username = isset($argv[1]) ? trim($argv[1]) : '';
$newPassword = isset($argv[2]) ? $argv[2] : null;

// Show usage if no username provided
if (empty($username)) {
    echo "Secure Blog CMS — Password Reset Utility\n";
    echo "==========================================\n\n";
    echo "Usage: php reset_password.php <username> [password]\n\n";
    echo "If password is not provided, you will be prompted to enter it\n";
    echo "(hidden input — safest option, avoids shell expansion).\n\n";
    echo "Password requirements:\n";
    echo "  - Minimum 12 characters\n";
    echo "  - At least one uppercase letter\n";
    echo "  - At least one lowercase letter\n";
    echo "  - At least one digit\n";
    echo "  - At least one special character\n\n";
    echo "IMPORTANT: If passing password as argument, use single quotes:\n";
    echo "  php reset_password.php admin 'MyN3wP@ssword!'\n\n";
    echo "Interactive mode (recommended — no shell expansion risk):\n";
    echo "  php reset_password.php admin\n";
    exit(1);
}

// Find CMS root
$cmsRoot = dirname(__DIR__);
chdir($cmsRoot);

// Load CMS
if (!file_exists($cmsRoot . '/includes/config.php')) {
    echo "Error: Could not find includes/config.php\n";
    echo "Make sure you're running this from the cli/ directory or CMS root.\n";
    exit(1);
}

define('SECURE_CMS_INIT', true);
require_once $cmsRoot . '/includes/config.php';
require_once $cmsRoot . '/includes/Security.php';
require_once $cmsRoot . '/includes/users.php';

$users = Users::getInstance();

// Check if user exists
if (!$users->userExists($username)) {
    echo "Error: User '$username' not found.\n";
    echo "Available users:\n";
    foreach ($users->getAllUsers() as $user) {
        echo "  - " . $user['username'] . " (" . $user['role'] . ")\n";
    }
    exit(1);
}

// If no password provided on command line, prompt interactively
if ($newPassword === null) {
    echo "Secure Blog CMS — Password Reset\n";
    echo "==================================\n\n";
    echo "User: $username\n\n";
    echo "Enter new password (input hidden):\n";
    
    // Read password from stdin without echoing (hidden input)
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows: use a workaround since stty isn't available
        echo "Warning: Hidden input may not work on Windows. Characters may be visible.\n";
        echo "New password: ";
        $newPassword = trim(fgets(STDIN));
        
        echo "\nConfirm new password: ";
        $confirmPassword = trim(fgets(STDIN));
    } else {
        // Unix/Linux/macOS: disable echo for secure input
        echo "New password: ";
        system('stty -echo');
        $newPassword = trim(fgets(STDIN));
        system('stty echo');
        echo "\n";
        
        echo "Confirm new password: ";
        system('stty -echo');
        $confirmPassword = trim(fgets(STDIN));
        system('stty echo');
        echo "\n";
    }
    
    if ($newPassword !== $confirmPassword) {
        echo "\nError: Passwords do not match. Please try again.\n";
        exit(1);
    }
    
    if (empty($newPassword)) {
        echo "\nError: Password cannot be empty.\n";
        exit(1);
    }
}

// Validate password policy
$validation = $users->validatePasswordPolicy($newPassword);
if (!$validation['valid']) {
    echo "Error: Password does not meet requirements.\n";
    echo "  " . $validation['message'] . "\n";
    exit(1);
}

// Confirm
echo "\nSecure Blog CMS — Password Reset\n";
echo "==================================\n\n";
echo "User:     $username\n";
echo "Password: " . str_repeat('*', strlen($newPassword)) . "\n\n";
echo "Are you sure you want to reset this user's password? [y/N] ";

$handle = fopen('php://stdin', 'r');
$line = trim(fgets($handle));
fclose($handle);

if (strtolower($line) !== 'y' && strtolower($line) !== 'yes') {
    echo "Cancelled.\n";
    exit(0);
}

// Reset the password
$userData = $users->getUser($username);
if (!$userData) {
    echo "Error: Could not load user data.\n";
    exit(1);
}

$userData['password_hash'] = defined('PASSWORD_ARGON2ID')
    ? password_hash($newPassword, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1])
    : password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

$userFile = $users->getUserFile($username);
if ($userFile && file_put_contents($userFile, json_encode($userData, JSON_PRETTY_PRINT), LOCK_EX)) {
    chmod($userFile, 0600);
    echo "\n✓ Password reset successfully for user '$username'.\n";
    echo "You can now log in with the new password.\n";
    exit(0);
} else {
    echo "\n✗ Error: Failed to save password. Check file permissions.\n";
    exit(1);
}