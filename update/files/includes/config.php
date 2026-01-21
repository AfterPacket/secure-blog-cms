<?php
/**
 * Secure Blog CMS - Configuration File
 * SQL-Free, XSS Protected, CSRF Protected
 */

// Prevent direct access
if (!defined("SECURE_CMS_INIT")) {
    die("Direct access not permitted");
}

// Security Configuration
define("SECURE_CMS_VERSION", "1.2.4");
define("SESSION_NAME", "SECURE_CMS_SESSION");
define("SESSION_LIFETIME", 172800); // 48 hours
define("CSRF_TOKEN_LENGTH", 32);
define("CSRF_TOKEN_LIFETIME", 172800);
define("MAX_LOGIN_ATTEMPTS", 5);
define("LOGIN_LOCKOUT_TIME", 900); // 15 minutes
define("PASSWORD_MIN_LENGTH", 12);

// File-based storage paths
define("DATA_DIR", __DIR__ . "/../data");
define("APP_ROOT", realpath(__DIR__ . "/.."));
define("POSTS_DIR", DATA_DIR . "/posts");
define("USERS_DIR", DATA_DIR . "/users");
define("SESSIONS_DIR", DATA_DIR . "/sessions");
define("COMMENTS_DIR", DATA_DIR . "/comments");
define("LOGS_DIR", DATA_DIR . "/logs");

// Content Security Policy
// Set to false to disable CSP headers entirely (eases compatibility).
define("ENABLE_CSP_HEADERS", false);

// Public pages stay strict; admin pages need inline scripts and TinyMCE CDN.
define(
    "CSP_POLICY",
    "default-src 'self' https: data: blob:; " .
        "script-src 'self' https: 'unsafe-inline' 'unsafe-eval'; " .
        "style-src 'self' 'unsafe-inline' https:; " .
        "img-src 'self' data: blob: https:; " .
        "font-src 'self' data: https:; " .
        "connect-src 'self' https:; " .
        "frame-src https:; " .
        "frame-ancestors 'self' https:; " .
        "base-uri 'self'; " .
        "form-action 'self' https:;",
);

// Admin CSP: allows TinyMCE (cdn.tiny.cloud) + inline admin scripts.
define(
    "CSP_POLICY_ADMIN",
    "default-src 'self' https: data: blob:; " .
        "base-uri 'self'; " .
        "object-src 'none'; " .
        "frame-ancestors 'self' https:; " .
        "form-action 'self' https:; " .
        "img-src 'self' data: blob: https:; " .
        "style-src 'self' 'unsafe-inline' https:; " .
        "style-src-elem 'self' 'unsafe-inline' https:; " .
        "font-src 'self' data: https:; " .
        "connect-src 'self' https:; " .
        "frame-src 'self' https:; " .
        "script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; " .
        "script-src-elem 'self' 'unsafe-inline' 'unsafe-eval' https:; " .
        "script-src-attr 'self' 'unsafe-inline';",
);

// Upload security toggles
define("ENABLE_UPLOAD_MALWARE_SCAN", false);
// Sanitization settings
define(
    "ALLOWED_HTML_TAGS",
    "<p><br><strong><em><u><h1><h2><h3><h4><ul><ol><li><a><img><blockquote><code><pre>",
);
define("MAX_POST_TITLE_LENGTH", 200);
define("MAX_POST_CONTENT_LENGTH", 50000);
define("MAX_POST_EXCERPT_LENGTH", 500);

// Ensure base data dirs exist early (so error logging works)
if (!is_dir(DATA_DIR)) {
    @mkdir(DATA_DIR, 0700, true);
}
if (!is_dir(LOGS_DIR)) {
    @mkdir(LOGS_DIR, 0700, true);
}

// Ensure storage directories exist
if (!is_dir(POSTS_DIR)) {
    @mkdir(POSTS_DIR, 0700, true);
}
if (!is_dir(USERS_DIR)) {
    @mkdir(USERS_DIR, 0700, true);
}
if (!is_dir(SESSIONS_DIR)) {
    @mkdir(SESSIONS_DIR, 0700, true);
}
if (!is_dir(COMMENTS_DIR)) {
    @mkdir(COMMENTS_DIR, 0700, true);
}

// Site/runtime settings (loaded from data/settings/site.json)
define("SETTINGS_DIR", DATA_DIR . "/settings");
define("SITE_SETTINGS_FILE", SETTINGS_DIR . "/site.json");

if (!is_dir(SETTINGS_DIR)) {
    @mkdir(SETTINGS_DIR, 0700, true);
}

$__site_settings = [];
if (is_file(SITE_SETTINGS_FILE)) {
    $raw = file_get_contents(SITE_SETTINGS_FILE);
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $__site_settings = $decoded;
    }
}

// Timezone (defaults to UTC)
$__timezone = $__site_settings["timezone"] ?? "UTC";
if (!is_string($__timezone) || $__timezone === "") {
    $__timezone = "UTC";
}
@date_default_timezone_set($__timezone);

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set("display_errors", "0");
ini_set("log_errors", "1");
ini_set("error_log", LOGS_DIR . "/php_errors.log");

// PHP Security Settings
$__isHttps =
    (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ||
    (isset($_SERVER["HTTP_X_FORWARDED_PROTO"]) &&
        strtolower((string) $_SERVER["HTTP_X_FORWARDED_PROTO"]) === "https") ||
    (isset($_SERVER["HTTP_CF_VISITOR"]) &&
        strpos((string) $_SERVER["HTTP_CF_VISITOR"], "https") !== false);

ini_set("session.cookie_httponly", "1");
ini_set("session.cookie_secure", $__isHttps ? "1" : "0");
ini_set("session.cookie_samesite", "Strict");
ini_set("session.use_strict_mode", "1");
ini_set("session.use_only_cookies", "1");
ini_set("session.cookie_lifetime", "0");
ini_set("session.gc_maxlifetime", SESSION_LIFETIME);

// Disable dangerous functions
ini_set("allow_url_fopen", "0");
ini_set("allow_url_include", "0");

// Admin credentials (CHANGE THESE!)
// Password should be hashed with password_hash()
define("ADMIN_USERNAME", "REPLACE_ME_USERNAME");
// Default password: "ChangeThisSecurePassword123!"
// Generate new hash using: password_hash('your_password', PASSWORD_ARGON2ID);
define("ADMIN_PASSWORD_HASH", "REPLACE_ME_PASSWORD_HASH");

// Rate limiting
define("RATE_LIMIT_REQUESTS", 100);
define("RATE_LIMIT_PERIOD", 3600); // per hour

// Pagination (defaults to 10, bounded 1..100)
$__ppp = isset($__site_settings["posts_per_page"])
    ? (int) $__site_settings["posts_per_page"]
    : 10;
if ($__ppp < 1 || $__ppp > 100) {
    $__ppp = 10;
}
define("POSTS_PER_PAGE", $__ppp);

// Site settings (fallbacks to placeholders)
$__site_name =
    isset($__site_settings["site_name"]) &&
    is_string($__site_settings["site_name"]) &&
    $__site_settings["site_name"] !== ""
        ? $__site_settings["site_name"]
        : "REPLACE_ME_SITE_NAME";

$__site_desc =
    isset($__site_settings["site_description"]) &&
    is_string($__site_settings["site_description"]) &&
    $__site_settings["site_description"] !== ""
        ? $__site_settings["site_description"]
        : "REPLACE_ME_SITE_DESCRIPTION";

$__site_url =
    isset($__site_settings["site_url"]) &&
    is_string($__site_settings["site_url"]) &&
    $__site_settings["site_url"] !== ""
        ? rtrim($__site_settings["site_url"], "/")
        : "REPLACE_ME_SITE_URL";

define("SITE_NAME", $__site_name);
define("SITE_DESCRIPTION", $__site_desc);
define("SITE_URL", $__site_url);

// Backup settings
define("AUTO_BACKUP", true);
define("BACKUP_DIR", DATA_DIR . "/backups");
if (!is_dir(BACKUP_DIR)) {
    @mkdir(BACKUP_DIR, 0700, true);
}
define("MAX_BACKUPS", 10);

// Runtime feature toggles
define("ALLOW_SEARCH", (bool) ($__site_settings["allow_search"] ?? true));
define(
    "REQUIRE_LOGIN_FOR_POSTS",
    (bool) ($__site_settings["require_login_for_posts"] ?? false),
);
define("POST_PASSWORD_TTL", 3600); // seconds
define(
    "APP_VERSION",
    defined("SECURE_CMS_VERSION") ? SECURE_CMS_VERSION : "dev",
);

// hCaptcha (comments)
// Provide secrets via environment variables to avoid storing secrets on disk.
define(
    "HCAPTCHA_SITEKEY",
    (string) (getenv("HCAPTCHA_SITEKEY") ?:
    $__site_settings["hcaptcha_sitekey"] ?? ""),
);
define("HCAPTCHA_SECRET", (string) (getenv("HCAPTCHA_SECRET") ?: ""));
define("HCAPTCHA_ENABLED", HCAPTCHA_SITEKEY !== "" && HCAPTCHA_SECRET !== "");

/**
 * App base path (for installs in subfolders like /secure-blog-cms)
 *
 * Examples:
 *  - SCRIPT_NAME: /secure-blog-cms/admin/admin.php -> APP_BASE_PATH=/secure-blog-cms
 *  - SCRIPT_NAME: /secure-blog-cms/post.php       -> APP_BASE_PATH=/secure-blog-cms
 *  - SCRIPT_NAME: /admin/admin.php                -> APP_BASE_PATH=
 */
$__scriptName = str_replace(
    "\\",
    "/",
    (string) ($_SERVER["SCRIPT_NAME"] ?? ""),
);
$__dir = str_replace("\\", "/", (string) dirname($__scriptName));
$__dir = rtrim($__dir, "/");
if (preg_match('#/(admin|install)$#', $__dir)) {
    $__dir = preg_replace('#/(admin|install)$#', "", $__dir);
}
if ($__dir === "" || $__dir === "/") {
    $__dir = "";
}
define("APP_BASE_PATH", $__dir);

function cms_path($path = "")
{
    $path = ltrim((string) $path, "/");
    $base = APP_BASE_PATH;
    if ($base === "") {
        return "/" . $path;
    }
    if ($path === "") {
        return $base . "/";
    }
    return $base . "/" . $path;
}
