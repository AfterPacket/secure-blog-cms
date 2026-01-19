<?php
/**
 * Security Class - Comprehensive Security Implementation
 * Protects against XSS, CSRF, injection attacks, and other vulnerabilities
 */

if (!defined("SECURE_CMS_INIT")) {
    die("Direct access not permitted");
}

class Security
{
    private static $instance = null;
    private $csrfTokens = [];

    /**
     * Singleton pattern
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize security measures
     */
    public function __construct()
    {
        $this->startSecureSession();
        $this->setSecurityHeaders();
        $this->cleanOldSessions();
    }

    /**
     * Start secure session with enhanced security
     */
    private function startSecureSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Set session name
            session_name(SESSION_NAME);

            // Set secure session parameters
            session_set_cookie_params([
                "lifetime" => 0,
                "path" => "/",
                "domain" => $_SERVER["HTTP_HOST"] ?? "",
                "secure" =>
                    isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on",
                "httponly" => true,
                "samesite" => "Strict",
            ]);

            session_start();

            // Regenerate session ID periodically
            if (!isset($_SESSION["created"])) {
                $_SESSION["created"] = time();
            } elseif (time() - $_SESSION["created"] > 1800) {
                session_regenerate_id(true);
                $_SESSION["created"] = time();
            }

            // Validate session fingerprint
            if (!$this->validateSessionFingerprint()) {
                session_destroy();
                session_start();
                $this->setSessionFingerprint();
            }
        }
    }

    /**
     * Set session fingerprint for session hijacking prevention
     */
    private function setSessionFingerprint()
    {
        $ip =
            $_SERVER["HTTP_CF_CONNECTING_IP"] ??
            ($_SERVER["REMOTE_ADDR"] ?? "");
        $_SESSION["fingerprint"] = hash(
            "sha256",
            ($_SERVER["HTTP_USER_AGENT"] ?? "") .
                $ip .
                ($_SERVER["HTTP_ACCEPT_LANGUAGE"] ?? ""),
        );
    }

    /**
     * Validate session fingerprint
     */
    private function validateSessionFingerprint()
    {
        if (!isset($_SESSION["fingerprint"])) {
            $this->setSessionFingerprint();
            return true;
        }

        $ip =
            $_SERVER["HTTP_CF_CONNECTING_IP"] ??
            ($_SERVER["REMOTE_ADDR"] ?? "");
        $currentFingerprint = hash(
            "sha256",
            ($_SERVER["HTTP_USER_AGENT"] ?? "") .
                $ip .
                ($_SERVER["HTTP_ACCEPT_LANGUAGE"] ?? ""),
        );

        return hash_equals($_SESSION["fingerprint"], $currentFingerprint);
    }

    /**
     * Set comprehensive security headers
     */
    private function setSecurityHeaders()
    {
        // Content Security Policy
        // - Public pages stay strict
        // - Admin pages need inline scripts + TinyMCE CDN
        if ($this->isAdminPage() && defined("CSP_POLICY_ADMIN")) {
            header("Content-Security-Policy: " . CSP_POLICY_ADMIN);
        } else {
            header("Content-Security-Policy: " . CSP_POLICY);
        }

        // Helpful version header (safe to expose)
        if (defined("SECURE_CMS_VERSION")) {
            header("X-SecureBlogCMS-Version: " . SECURE_CMS_VERSION);
        }

        // Prevent clickjacking
        header("X-Frame-Options: DENY");

        // Prevent MIME type sniffing
        header("X-Content-Type-Options: nosniff");

        // XSS Protection (legacy browsers)
        header("X-XSS-Protection: 1; mode=block");

        // Referrer Policy
        header("Referrer-Policy: strict-origin-when-cross-origin");

        // Permissions Policy (formerly Feature Policy)
        header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

        // HSTS (if using HTTPS)
        if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on") {
            header(
                "Strict-Transport-Security: max-age=31536000; includeSubDomains; preload",
            );
        }

        // Prevent caching of sensitive pages
        if ($this->isAdminPage()) {
            header(
                "Cache-Control: no-store, no-cache, must-revalidate, max-age=0",
            );
            header("Pragma: no-cache");
            header("Expires: 0");
        }
    }

    /**
     * Check if current page is admin page
     */
    private function isAdminPage()
    {
        $scriptPath = (string) ($_SERVER["SCRIPT_NAME"] ?? "");
        $uri = (string) ($_SERVER["REQUEST_URI"] ?? "");
        $phpSelf = (string) ($_SERVER["PHP_SELF"] ?? "");

        if (
            strpos($scriptPath, "/admin/") !== false ||
            strpos($uri, "/admin/") !== false ||
            strpos($phpSelf, "/admin/") !== false
        ) {
            return true;
        }

        $scriptName = basename($scriptPath ?: $phpSelf);
        return in_array(
            $scriptName,
            [
                "admin.php",
                "login.php",
                "logout.php",
                "create-post.php",
                "edit-post.php",
                "settings.php",
                "users.php",
                "categories.php",
                "comments.php",
                "upload-image.php",
                "serve-image.php",
                "upgrade.php",
                "updates.php",
            ],
            true,
        );
    }

    /**
     * Generate CSRF token
     */
    public function generateCSRFToken($formName = "default")
    {
        $token = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        $_SESSION["csrf_tokens"][$formName] = [
            "token" => $token,
            "time" => time(),
        ];
        return $token;
    }

    /**
     * Validate CSRF token with constant-time comparison
     */
    public function validateCSRFToken($token, $formName = "default")
    {
        if (!isset($_SESSION["csrf_tokens"][$formName])) {
            $this->logSecurityEvent("CSRF token missing", $formName);
            return false;
        }

        $storedToken = $_SESSION["csrf_tokens"][$formName]["token"];
        $tokenTime = $_SESSION["csrf_tokens"][$formName]["time"];

        // Check token age
        if (time() - $tokenTime > CSRF_TOKEN_LIFETIME) {
            unset($_SESSION["csrf_tokens"][$formName]);
            $this->logSecurityEvent("CSRF token expired", $formName);
            return false;
        }

        // Constant-time comparison to prevent timing attacks
        if (!hash_equals($storedToken, $token)) {
            $this->logSecurityEvent("CSRF token mismatch", $formName);
            return false;
        }

        // Token is valid
        // For image uploads we allow reuse within the token lifetime (TinyMCE may upload multiple images).
        if ($formName !== "image_upload") {
            unset($_SESSION["csrf_tokens"][$formName]);
        }
        return true;
    }

    /**
     * Sanitize input - Multi-layer approach
     */
    public function sanitizeInput($input, $type = "string")
    {
        if (is_array($input)) {
            return array_map(function ($item) use ($type) {
                return $this->sanitizeInput($item, $type);
            }, $input);
        }

        // First layer: Remove null bytes
        $input = str_replace("\0", "", $input);

        // Type-specific sanitization
        switch ($type) {
            case "email":
                $input = filter_var($input, FILTER_SANITIZE_EMAIL);
                $input = filter_var($input, FILTER_VALIDATE_EMAIL)
                    ? $input
                    : "";
                break;

            case "url":
                $input = filter_var($input, FILTER_SANITIZE_URL);
                $input = filter_var($input, FILTER_VALIDATE_URL) ? $input : "";
                break;

            case "int":
                $input = filter_var($input, FILTER_SANITIZE_NUMBER_INT);
                $input =
                    filter_var($input, FILTER_VALIDATE_INT) !== false
                        ? (int) $input
                        : 0;
                break;

            case "float":
                $input = filter_var(
                    $input,
                    FILTER_SANITIZE_NUMBER_FLOAT,
                    FILTER_FLAG_ALLOW_FRACTION,
                );
                $input =
                    filter_var($input, FILTER_VALIDATE_FLOAT) !== false
                        ? (float) $input
                        : 0.0;
                break;

            case "alphanumeric":
                $input = preg_replace("/[^a-zA-Z0-9_-]/", "", $input);
                break;

            case "slug":
                $input = strtolower(trim($input));
                $input = preg_replace("/[^a-z0-9-]/", "-", $input);
                $input = preg_replace("/-+/", "-", $input);
                $input = trim($input, "-");
                break;

            case "html":
                // Allow specific HTML tags for content
                $input = strip_tags($input, ALLOWED_HTML_TAGS);
                // Remove dangerous attributes
                $input = $this->removeXSSAttributes($input);
                break;

            case "filename":
                // Sanitize filename to prevent directory traversal
                $input = basename($input);
                $input = preg_replace("/[^a-zA-Z0-9._-]/", "", $input);
                break;

            case "string":
            default:
                // Strip all HTML and PHP tags
                $input = strip_tags($input);
                // Remove any remaining script injections
                $input = preg_replace(
                    "/<script\b[^>]*>(.*?)<\/script>/is",
                    "",
                    $input,
                );
                break;
        }

        // Trim whitespace
        $input = trim($input);

        return $input;
    }

    /**
     * Remove dangerous XSS attributes from HTML
     */
    private function removeXSSAttributes($html)
    {
        // List of dangerous attributes
        $dangerous = [
            "onload",
            "onerror",
            "onclick",
            "onmouseover",
            "onmouseout",
            "onmousemove",
            "onmouseenter",
            "onmouseleave",
            "onfocus",
            "onblur",
            "onchange",
            "onsubmit",
            "onkeydown",
            "onkeyup",
            "onkeypress",
            "ondblclick",
            "oncontextmenu",
            "oninput",
            "onwheel",
            "ondrag",
            "ondrop",
            "onabort",
            "oncanplay",
            "oncanplaythrough",
            "oncuechange",
            "ondurationchange",
            "onemptied",
            "onended",
            "onloadeddata",
            "onloadedmetadata",
            "onloadstart",
            "onpause",
            "onplay",
            "onplaying",
            "onprogress",
            "onratechange",
            "onseeked",
            "onseeking",
            "onstalled",
            "onsuspend",
            "ontimeupdate",
            "onvolumechange",
            "onwaiting",
            "ontoggle",
            "formaction",
            "javascript:",
            "vbscript:",
            "data:text/html",
        ];

        foreach ($dangerous as $attr) {
            $html = preg_replace(
                "/" . preg_quote($attr, "/") . '\s*=\s*["\'][^"\']*["\']/i',
                "",
                $html,
            );
            $html = preg_replace(
                "/" . preg_quote($attr, "/") . '\s*=\s*[^"\'\s>]*/i',
                "",
                $html,
            );
        }

        // Remove javascript: and data: protocols from href and src
        $html = preg_replace(
            '/(<[^>]+)(href|src)\s*=\s*["\']?(javascript|data|vbscript):[^"\'\s>]*/i',
            '$1',
            $html,
        );

        return $html;
    }

    /**
     * Escape output for HTML context - prevent XSS
     */
    public function escapeHTML($string)
    {
        return htmlspecialchars(
            $string,
            ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE,
            "UTF-8",
            true,
        );
    }

    /**
     * Escape output for JavaScript context
     */
    public function escapeJS($string)
    {
        return json_encode(
            $string,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
        );
    }

    /**
     * Escape output for URL context
     */
    public function escapeURL($string)
    {
        return rawurlencode($string);
    }

    /**
     * Escape output for CSS context
     */
    public function escapeCSS($string)
    {
        return preg_replace("/[^a-zA-Z0-9\-_]/", "", $string);
    }

    /**
     * Validate and sanitize POST data
     */
    public function getPostData($key, $type = "string", $default = "")
    {
        if (!isset($_POST[$key])) {
            return $default;
        }
        return $this->sanitizeInput($_POST[$key], $type);
    }

    /**
     * Validate and sanitize GET data
     */
    public function getGetData($key, $type = "string", $default = "")
    {
        if (!isset($_GET[$key])) {
            return $default;
        }
        return $this->sanitizeInput($_GET[$key], $type);
    }

    /**
     * Hash password using Argon2id (most secure)
     */
    public function hashPassword($password)
    {
        if (defined("PASSWORD_ARGON2ID")) {
            return password_hash($password, PASSWORD_ARGON2ID, [
                "memory_cost" => 65536,
                "time_cost" => 4,
                "threads" => 1,
            ]);
        }
        // Fallback to bcrypt
        return password_hash($password, PASSWORD_BCRYPT, ["cost" => 12]);
    }

    /**
     * Verify password with constant-time comparison
     */
    public function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }

    /**
     * Rate limiting
     */
    public function checkRateLimit(
        $identifier,
        $maxAttempts = RATE_LIMIT_REQUESTS,
        $timeWindow = RATE_LIMIT_PERIOD,
    ) {
        $rateLimitFile =
            SESSIONS_DIR .
            "/ratelimit_" .
            hash("sha256", $identifier) .
            ".json";

        $attempts = [];
        if (file_exists($rateLimitFile)) {
            $attempts =
                json_decode(file_get_contents($rateLimitFile), true) ?? [];
        }

        // Clean old attempts
        $currentTime = time();
        $attempts = array_filter($attempts, function ($timestamp) use (
            $currentTime,
            $timeWindow,
        ) {
            return $currentTime - $timestamp < $timeWindow;
        });

        // Check if rate limit exceeded
        if (count($attempts) >= $maxAttempts) {
            $this->logSecurityEvent("Rate limit exceeded", $identifier);
            return false;
        }

        // Add current attempt
        $attempts[] = $currentTime;
        file_put_contents($rateLimitFile, json_encode($attempts), LOCK_EX);

        return true;
    }

    /**
     * Check login attempts for brute force protection
     */
    public function checkLoginAttempts($username)
    {
        $attemptsFile =
            SESSIONS_DIR . "/login_" . hash("sha256", $username) . ".json";

        if (file_exists($attemptsFile)) {
            $data = json_decode(file_get_contents($attemptsFile), true);

            // Check if locked out
            if ($data["locked_until"] > time()) {
                return false;
            }

            // Reset if outside time window
            if (time() - $data["first_attempt"] > LOGIN_LOCKOUT_TIME) {
                unlink($attemptsFile);
                return true;
            }
        }

        return true;
    }

    /**
     * Record failed login attempt
     */
    public function recordFailedLogin($username)
    {
        $attemptsFile =
            SESSIONS_DIR . "/login_" . hash("sha256", $username) . ".json";

        $data = [
            "attempts" => 1,
            "first_attempt" => time(),
            "locked_until" => 0,
        ];

        if (file_exists($attemptsFile)) {
            $data = json_decode(file_get_contents($attemptsFile), true);
            $data["attempts"]++;

            // Lock account if max attempts reached
            if ($data["attempts"] >= MAX_LOGIN_ATTEMPTS) {
                $data["locked_until"] = time() + LOGIN_LOCKOUT_TIME;
                $this->logSecurityEvent(
                    "Account locked due to failed login attempts",
                    $username,
                );
            }
        }

        file_put_contents($attemptsFile, json_encode($data), LOCK_EX);
    }

    /**
     * Clear login attempts on successful login
     */
    public function clearLoginAttempts($username)
    {
        $attemptsFile =
            SESSIONS_DIR . "/login_" . hash("sha256", $username) . ".json";
        if (file_exists($attemptsFile)) {
            unlink($attemptsFile);
        }
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated()
    {
        return isset($_SESSION["authenticated"]) &&
            $_SESSION["authenticated"] === true &&
            isset($_SESSION["user"]) &&
            $this->validateSessionFingerprint();
    }

    /**
     * Authenticate user
     */
    public function authenticate($username, $password)
    {
        // Check if account is locked
        if (!$this->checkLoginAttempts($username)) {
            return [
                "success" => false,
                "message" =>
                    "Account temporarily locked due to failed login attempts",
            ];
        }

        // Verify credentials
        if (
            $username === ADMIN_USERNAME &&
            $this->verifyPassword($password, ADMIN_PASSWORD_HASH)
        ) {
            // Clear failed attempts
            $this->clearLoginAttempts($username);

            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);

            // Set session variables
            $_SESSION["authenticated"] = true;
            $_SESSION["user"] = $username;
            $_SESSION["role"] = "admin";
            $_SESSION["login_time"] = time();
            $this->setSessionFingerprint();

            $this->logSecurityEvent("Successful login", $username);
            return ["success" => true, "message" => "Login successful"];
        }

        // File-based users (data/users/<username>.json)
        $safeUser = preg_replace("/[^a-zA-Z0-9_-]/", "", (string) $username);
        if ($safeUser !== "") {
            $userFile = USERS_DIR . "/" . $safeUser . ".json";
            if (is_file($userFile)) {
                $userData = json_decode(
                    (string) file_get_contents($userFile),
                    true,
                );
                if (
                    is_array($userData) &&
                    !empty($userData["password_hash"]) &&
                    $this->verifyPassword(
                        $password,
                        (string) $userData["password_hash"],
                    )
                ) {
                    $this->clearLoginAttempts($username);
                    session_regenerate_id(true);

                    $_SESSION["authenticated"] = true;
                    $_SESSION["user"] =
                        (string) ($userData["username"] ?? $safeUser);
                    $_SESSION["role"] =
                        (string) ($userData["role"] ?? "author");
                    $_SESSION["login_time"] = time();
                    $this->setSessionFingerprint();

                    $this->logSecurityEvent(
                        "Successful login (file user)",
                        $_SESSION["user"],
                    );
                    return ["success" => true, "message" => "Login successful"];
                }
            }
        }

        // Record failed attempt
        $this->recordFailedLogin($username);
        $this->logSecurityEvent("Failed login attempt", $username);

        return ["success" => false, "message" => "Invalid credentials"];
    }

    /**
     * Logout user
     */
    public function logout()
    {
        if (isset($_SESSION["user"])) {
            $this->logSecurityEvent("User logout", $_SESSION["user"]);
        }

        $_SESSION = [];

        if (isset($_COOKIE[SESSION_NAME])) {
            setcookie(SESSION_NAME, "", time() - 3600, "/", "", true, true);
        }

        session_destroy();
    }

    /**
     * Clean old session files
     */
    private function cleanOldSessions()
    {
        if (!is_dir(SESSIONS_DIR)) {
            return;
        }

        $files = glob(SESSIONS_DIR . "/*");
        $currentTime = time();

        foreach ($files as $file) {
            if (
                is_file($file) &&
                $currentTime - filemtime($file) > SESSION_LIFETIME
            ) {
                @unlink($file);
            }
        }
    }

    /**
     * Log security events
     */
    public function logSecurityEvent($event, $details = "")
    {
        if (!is_dir(LOGS_DIR)) {
            mkdir(LOGS_DIR, 0700, true);
        }

        $logFile = LOGS_DIR . "/security_" . date("Y-m-d") . ".log";
        $timestamp = date("Y-m-d H:i:s");
        $ip = $_SERVER["REMOTE_ADDR"] ?? "unknown";
        $userAgent = $_SERVER["HTTP_USER_AGENT"] ?? "unknown";

        $logEntry = sprintf(
            "[%s] IP: %s | Event: %s | Details: %s | User-Agent: %s\n",
            $timestamp,
            $ip,
            $event,
            $details,
            $userAgent,
        );

        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Validate file upload
     */
    public function validateFileUpload(
        $file,
        $allowedTypes = [],
        $maxSize = 5242880,
    ) {
        // Check if file was uploaded
        if (!isset($file["tmp_name"]) || !is_uploaded_file($file["tmp_name"])) {
            return ["valid" => false, "message" => "Invalid file upload"];
        }

        // Check file size
        if ($file["size"] > $maxSize) {
            return ["valid" => false, "message" => "File too large"];
        }

        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file["tmp_name"]);
        finfo_close($finfo);

        if (!empty($allowedTypes) && !in_array($mimeType, $allowedTypes)) {
            return ["valid" => false, "message" => "File type not allowed"];
        }

        return ["valid" => true, "mime_type" => $mimeType];
    }

    /**
     * Generate secure random string
     */
    public function generateRandomString($length = 32)
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Prevent directory traversal
     */
    public function sanitizePath($path)
    {
        // Remove any directory traversal attempts
        $path = str_replace(["../", "..\\", "..", "./"], "", $path);
        $path = preg_replace("/[^a-zA-Z0-9\/\-_.]/", "", $path);
        return $path;
    }
}
