<?php
/**
 * Secure Blog CMS - Image Serving Endpoint
 * Securely delivers uploaded images with comprehensive security checks
 */

// Initialize security constant
define("SECURE_CMS_INIT", true);

// Load configuration
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/Security.php";

// Initialize security
$security = Security::getInstance();

// Get and sanitize image filename
$filename = $_GET["img"] ?? "";

// Security Check 1: Validate filename parameter exists
if (empty($filename)) {
    http_response_code(400);
    die("No image specified");
}

// Security Check 2: Prevent directory traversal - use basename only
$filename = basename($filename);

// Security Check 3: Additional sanitization to remove any path separators
$filename = str_replace(["/", "\\", ".."], "", $filename);

// Security Check 4: Validate filename format (alphanumeric, underscore, hyphen, dot only)
if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
    http_response_code(400);
    $security->logSecurityEvent("Invalid image filename format", $filename);
    die("Invalid filename format");
}

// Construct full image path
$imagePath = DATA_DIR . "/uploads/images/" . $filename;

// Security Check 5: Verify file exists
if (!file_exists($imagePath)) {
    http_response_code(404);
    die("Image not found");
}

// Security Check 6: Verify it's actually a file (not a directory)
if (!is_file($imagePath)) {
    http_response_code(403);
    $security->logSecurityEvent(
        "Attempt to access non-file resource",
        $filename);
    die("Invalid resource");
}

// Security Check 7: Get and validate MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $imagePath);
finfo_close($finfo);

// Security Check 8: Only allow image MIME types
$allowedMimeTypes = [
    "image/jpeg",
    "image/jpg",
    "image/pjpeg",
    "image/png",
    "image/x-png",
    "image/gif",
    "image/webp",
];

if (!in_array($mimeType, $allowedMimeTypes)) {
    http_response_code(403);
    $security->logSecurityEvent(
        "Attempt to access non-image file via serve-image.php",
        $filename . " - MIME: " . $mimeType);
    die("Invalid file type");
}

// Security Check 9: Verify file size is reasonable (prevent serving huge files)
$fileSize = filesize($imagePath);
if ($fileSize === false || $fileSize > 10485760) {
    // 10MB max
    http_response_code(403);
    die("File too large or unreadable");
}

// Security Check 10: Verify image integrity using getimagesize
$imageInfo = @getimagesize($imagePath);
if ($imageInfo === false) {
    http_response_code(403);
    $security->logSecurityEvent(
        "Corrupted image file access attempt",
        $filename);
    die("Corrupted or invalid image file");
}

// All security checks passed - serve the image

// Set cache control headers (images can be cached for 1 year)
header("Cache-Control: public, max-age=31536000, immutable");
header("Expires: " . gmdate("D, d M Y H:i:s", time() + 31536000) . " GMT");

// Set ETag for browser caching
$etag = md5_file($imagePath);
header('ETag: "' . $etag . '"');

// Check if browser has cached version
if (
    isset($_SERVER["HTTP_IF_NONE_MATCH"]) &&
    $_SERVER["HTTP_IF_NONE_MATCH"] === '"' . $etag . '"'
) {
    http_response_code(304); // Not Modified
    exit();
}

// Set content type header
header("Content-Type: " . $mimeType);

// Set content length
header("Content-Length: " . $fileSize);

// Set content disposition (inline display)
header('Content-Disposition: inline; filename="' . addslashes($filename) . '"');

// Security headers
header("X-Content-Type-Options: nosniff");

// Disable script execution in case of browser bugs
header("X-Content-Type-Options: nosniff");
header(
    'Content-Security-Policy: default-src \'none\'; img-src \'self\'; style-src \'none\'; script-src \'none\';');

// Send the file
readfile($imagePath);

// Exit to prevent any additional output
exit();
