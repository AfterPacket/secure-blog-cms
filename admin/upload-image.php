<?php
/**
 * Secure Blog CMS - Image Upload Endpoint
 * Handles AJAX image uploads with comprehensive security
 */

// Initialize security constant
define("SECURE_CMS_INIT", true);

// Load configuration
require_once __DIR__ . "/../includes/config.php";

// Load required classes
require_once __DIR__ . "/../includes/Security.php";
require_once __DIR__ . "/../includes/ImageUpload.php";

// Set JSON response header
header("Content-Type: application/json");

// Initialize security
$security = Security::getInstance();

// Check 1: Only authenticated admins can upload
if (!$security->isAuthenticated()) {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "error" => "Unauthorized. Please login first.",
    ]);
    $security->logSecurityEvent(
        "Unauthorized image upload attempt",
        $_SERVER["REMOTE_ADDR"] ?? "unknown");
    exit();
}

// Check 2: Validate CSRF token (from POST or GET)
$csrfToken = $_POST["csrf_token"] ?? ($_GET["csrf_token"] ?? "");

if (
    empty($csrfToken) ||
    !$security->validateCSRFToken($csrfToken, "image_upload")
) {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "error" => "Invalid security token. Please refresh the page.",
    ]);
    $security->logSecurityEvent(
        "CSRF validation failed on image upload",
        $_SESSION["user"] ?? "unknown");
    exit();
}

// Check 3: Rate limiting (20 uploads per hour)
$clientIdentifier = "upload_" . ($_SESSION["user"] ?? $_SERVER["REMOTE_ADDR"]);
if (!$security->checkRateLimit($clientIdentifier, 20, 3600)) {
    http_response_code(429);
    echo json_encode([
        "success" => false,
        "error" =>
            "Too many upload attempts. Please wait 1 hour before trying again.",
    ]);
    $security->logSecurityEvent(
        "Upload rate limit exceeded",
        $_SESSION["user"] ?? "unknown");
    exit();
}

// Check 4: Validate request method
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "error" => "Invalid request method. Use POST.",
    ]);
    exit();
}

// Check 5: Ensure file was uploaded
if (!isset($_FILES["file"]) || empty($_FILES["file"]["tmp_name"])) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => "No file uploaded. Please select an image.",
    ]);
    exit();
}

// Initialize image upload handler
try {
    $imageUpload = new ImageUpload();

    // Process the upload
    $result = $imageUpload->handleUpload($_FILES["file"]);

    // Set appropriate HTTP status code
    if ($result["success"]) {
        http_response_code(200);

        // Add additional info for TinyMCE
        $result["location"] = $result["url"]; // TinyMCE uses 'location' key

        // Log successful upload
        $security->logSecurityEvent(
            "Image uploaded successfully",
            $result["filename"] . " by " . ($_SESSION["user"] ?? "unknown"));
    } else {
        http_response_code(400);

        // Log failed upload
        $security->logSecurityEvent(
            "Image upload failed",
            ($result["error"] ?? "Unknown error") .
                " - " .
                ($_SESSION["user"] ?? "unknown"));
    }

    // Return JSON response
    echo json_encode($result);
} catch (Exception $e) {
    // Handle unexpected errors
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "An unexpected error occurred during upload.",
    ]);

    $security->logSecurityEvent("Image upload exception", $e->getMessage());
}
