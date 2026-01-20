<?php
/**
 * Secure Blog CMS - Short URL Redirect Handler
 * Redirects short URLs to their corresponding blog posts
 */

// Initialize security constant
define("SECURE_CMS_INIT", true);

// Load configuration
require_once __DIR__ . "/includes/config.php";

// Load required classes
require_once __DIR__ . "/includes/Security.php";
require_once __DIR__ . "/includes/UrlShortener.php";

// Initialize security and URL shortener
$security = Security::getInstance();
$urlShortener = new UrlShortener();

// Get short code from URL parameter
$code = $_GET["code"] ?? "";

// Sanitize the code
$code = $security->sanitizeInput($code, "alphanumeric");

// Validate code exists
if (empty($code)) {
    http_response_code(400);
    $security->logSecurityEvent(
        "Short URL access with empty code",
        $_SERVER["REQUEST_URI"] ?? "");
    die('<!DOCTYPE html>
<html>
<head>
    <title>Invalid Short URL</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
        .error { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 500px; margin: 0 auto; }
        h1 { color: #e74c3c; margin-bottom: 20px; }
        p { color: #7f8c8d; margin-bottom: 20px; }
        a { color: #3498db; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="error">
        <h1>⚠️ Invalid Short URL</h1>
        <p>The short URL you requested is invalid or malformed.</p>
        <a href="index.php">← Return to Blog Home</a>
    </div>
</body>
</html>');
}

// Rate limiting check (prevent abuse)
$clientIP = $_SERVER["REMOTE_ADDR"] ?? "unknown";
if (!$security->checkRateLimit("shorturl_" . $clientIP, 100, 60)) {
    http_response_code(429);
    $security->logSecurityEvent("Short URL rate limit exceeded", $clientIP);
    die('<!DOCTYPE html>
<html>
<head>
    <title>Too Many Requests</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
        .error { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 500px; margin: 0 auto; }
        h1 { color: #f39c12; margin-bottom: 20px; }
        p { color: #7f8c8d; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="error">
        <h1>⚠️ Too Many Requests</h1>
        <p>You have made too many requests. Please wait a moment before trying again.</p>
    </div>
</body>
</html>');
}

// Resolve short code to post slug
$postSlug = $urlShortener->resolveShortUrl($code);

// Check if resolution was successful
if ($postSlug === null) {
    // Short code not found
    http_response_code(404);
    $security->logSecurityEvent("Short URL not found", $code);
    die('<!DOCTYPE html>
<html>
<head>
    <title>Short URL Not Found</title>
    <meta name="robots" content="noindex, nofollow">
    <style>
        body { font-family: sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
        .error { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 500px; margin: 0 auto; }
        h1 { color: #e74c3c; font-size: 72px; margin-bottom: 20px; }
        h2 { color: #2c3e50; margin-bottom: 15px; }
        p { color: #7f8c8d; margin-bottom: 20px; line-height: 1.6; }
        a { display: inline-block; background: #3498db; color: white; padding: 12px 30px; border-radius: 4px; text-decoration: none; font-weight: 600; transition: background 0.3s; }
        a:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class="error">
        <h1>404</h1>
        <h2>Short URL Not Found</h2>
        <p>The short URL you requested does not exist or has been removed.</p>
        <p>This could happen if:</p>
        <ul style="text-align: left; max-width: 300px; margin: 20px auto;">
            <li>The link is incorrect or outdated</li>
            <li>The post was deleted</li>
            <li>The short URL expired</li>
        </ul>
        <a href="index.php">← Return to Blog Home</a>
    </div>
</body>
</html>');
}

// Success - redirect to the actual post
$redirectUrl = "post.php?slug=" . urlencode($postSlug);

// Log successful redirect (optional - can be disabled if too verbose)
// $security->logSecurityEvent('Short URL redirect', $code . ' -> ' . $postSlug);

// Set 301 Moved Permanently header for SEO
header("HTTP/1.1 301 Moved Permanently");
header("Location: " . $redirectUrl);

// Exit to ensure no additional output
exit();
