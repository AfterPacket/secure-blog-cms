<?php
/**
 * Secure Blog CMS - Settings Management
 * Configure site settings, appearance, and preferences
 */

// Initialize security constant
define("SECURE_CMS_INIT", true);

// Load configuration
require_once __DIR__ . "/../includes/config.php";

// Load required classes
require_once __DIR__ . "/../includes/Security.php";
require_once __DIR__ . "/../includes/Storage.php";

// Initialize security and storage
$security = Security::getInstance();
$storage = Storage::getInstance();

// Check authentication
if (!$security->isAuthenticated()) {
    header("Location: login.php");
    exit();
}

$message = "";
$messageType = "";

// Load current settings
$settingsFile = SITE_SETTINGS_FILE;

if (!is_dir(SETTINGS_DIR)) {
    @mkdir(SETTINGS_DIR, 0700, true);
}
$settings = [];

if (file_exists($settingsFile)) {
    $content = file_get_contents($settingsFile);
    $settings = json_decode($content, true) ?? [];
}

// Default settings
$defaultSettings = [
    "site_name" => SITE_NAME,
    "site_description" => SITE_DESCRIPTION,
    "site_url" => SITE_URL,
    "posts_per_page" => POSTS_PER_PAGE,
    "allow_search" => true,
    "allow_private_posts" => true,
    "allow_password_protected" => true,
    "enable_url_shortener" => true,
    "require_login_for_posts" => false,
    "timezone" => "UTC",
    "date_format" => "F j, Y",
    "time_format" => "g:i a",
];

$settings = array_merge($defaultSettings, $settings);

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrfToken = $_POST["csrf_token"] ?? "";

    if (!$security->validateCSRFToken($csrfToken, "settings_form")) {
        $message = "Invalid security token. Please try again.";
        $messageType = "error";
        $security->logSecurityEvent(
            "CSRF validation failed on settings update",
            $_SESSION["user"]);
    } else {
        // Get and sanitize form data
        $newSettings = [
            "site_name" => $security->getPostData("site_name", "string", ""),
            "site_description" => $security->getPostData(
                "site_description",
                "string",
                ""),
            "site_url" => $security->getPostData("site_url", "url", ""),
            "posts_per_page" => $security->getPostData(
                "posts_per_page",
                "int",
                10),
            "allow_search" => isset($_POST["allow_search"]),
            "allow_private_posts" => isset($_POST["allow_private_posts"]),
            "allow_password_protected" => isset(
                $_POST["allow_password_protected"]),
            "enable_url_shortener" => isset($_POST["enable_url_shortener"]),
            "require_login_for_posts" => isset(
                $_POST["require_login_for_posts"]),
            "timezone" => $security->getPostData("timezone", "string", "UTC"),
            "date_format" => $security->getPostData(
                "date_format",
                "string",
                "F j, Y"),
            "time_format" => $security->getPostData(
                "time_format",
                "string",
                "g:i a"),
            "updated_at" => time(),
            "updated_by" => $_SESSION["user"],
        ];

        // Validation
        $errors = [];

        if (
            empty($newSettings["site_name"]) ||
            strlen($newSettings["site_name"]) > 100
        ) {
            $errors[] = "Site name is required (max 100 characters)";
        }

        if (strlen($newSettings["site_description"]) > 500) {
            $errors[] = "Site description is too long (max 500 characters)";
        }

        if (
            $newSettings["posts_per_page"] < 1 ||
            $newSettings["posts_per_page"] > 100
        ) {
            $errors[] = "Posts per page must be between 1 and 100";
        }

        if (!empty($errors)) {
            $message = implode(", ", $errors);
            $messageType = "error";
        } else {
            // Save settings
            $jsonData = json_encode(
                $newSettings,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if (
                file_put_contents($settingsFile, $jsonData, LOCK_EX) !== false
            ) {
                $settings = $newSettings;
                $message = "Settings saved successfully!";
                $messageType = "success";

                $security->logSecurityEvent(
                    "Settings updated",
                    $_SESSION["user"]);

                // Create backup
                if (AUTO_BACKUP) {
                    $storage->createBackup("settings_updated", "settings");
                }
            } else {
                $message = "Failed to save settings. Check file permissions.";
                $messageType = "error";
            }
        }
    }
}

// Generate CSRF token
$csrfToken = $security->generateCSRFToken("settings_form");

// Available timezones
$timezones = [
    "UTC" => "UTC",
    "America/New_York" => "Eastern Time",
    "America/Chicago" => "Central Time",
    "America/Denver" => "Mountain Time",
    "America/Los_Angeles" => "Pacific Time",
    "Europe/London" => "London",
    "Europe/Paris" => "Paris",
    "Asia/Tokyo" => "Tokyo",
    "Asia/Shanghai" => "Shanghai",
    "Australia/Sydney" => "Sydney",
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Settings - <?php echo $security->escapeHTML(SITE_NAME); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            line-height: 1.6;
        }

        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .admin-header .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .admin-header h1 {
            font-size: 24px;
        }

        .admin-nav {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .admin-nav a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 4px;
            transition: background 0.3s;
            font-size: 14px;
        }

        .admin-nav a:hover {
            background: rgba(255,255,255,0.3);
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .section-title {
            font-size: 20px;
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ecf0f1;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input[type="text"],
        .form-group input[type="url"],
        .form-group input[type="number"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 16px;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-help {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 6px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .checkbox-group {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .checkbox-item {
            margin-bottom: 15px;
            display: flex;
            align-items: start;
            gap: 10px;
        }

        .checkbox-item:last-child {
            margin-bottom: 0;
        }

        .checkbox-item input[type="checkbox"] {
            margin-top: 3px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox-item label {
            flex: 1;
            cursor: pointer;
            font-weight: 500;
        }

        .checkbox-item .checkbox-help {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 4px;
        }

        .btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #95a5a6;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .security-badge {
            display: inline-block;
            background: #27ae60;
            color: white;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }

        @media (max-width: 768px) {
            .admin-header .container {
                flex-direction: column;
                align-items: flex-start;
            }

            .container {
                padding: 20px 10px;
            }

            .card {
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container">
            <h1>⚙️ Settings <span class="security-badge">SECURED</span></h1>
            <div class="admin-nav">
                <a href="admin.php">← Back to Dashboard</a>
                <a href="<?php echo cms_path('index.php'); ?>" target="_blank">👁️ View Blog</a>
                <?php if (($_SESSION["role"] ?? "") === "admin"): ?>
                    <a href="comments.php">💬 Comments</a>
                <?php endif; ?>
                <a href="logout.php">🚪 Logout</a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $security->escapeHTML(
                $messageType); ?>">
                <?php echo $security->escapeHTML($message); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="settings.php" id="settingsForm">
            <input type="hidden" name="csrf_token" value="<?php echo $security->escapeHTML(
                $csrfToken); ?>">

            <!-- General Settings -->
            <div class="card">
                <h2 class="section-title">🌐 General Settings</h2>

                <div class="form-group">
                    <label for="site_name">Site Name *</label>
                    <input type="text" id="site_name" name="site_name" required maxlength="100"
                           value="<?php echo $security->escapeHTML(
                               $settings["site_name"]); ?>">
                    <div class="form-help">The name of your blog</div>
                </div>

                <div class="form-group">
                    <label for="site_description">Site Description</label>
                    <textarea id="site_description" name="site_description" maxlength="500"><?php echo $security->escapeHTML(
                        $settings["site_description"]); ?></textarea>
                    <div class="form-help">Brief description of your blog (used in meta tags)</div>
                </div>

                <div class="form-group">
                    <label for="site_url">Site URL</label>
                    <input type="url" id="site_url" name="site_url" placeholder="https://yourdomain.com"
                           value="<?php echo $security->escapeHTML(
                               $settings["site_url"]); ?>">
                    <div class="form-help">Full URL including https://</div>
                </div>
            </div>

            <!-- Display Settings -->
            <div class="card">
                <h2 class="section-title">📄 Display Settings</h2>

                <div class="form-group">
                    <label for="posts_per_page">Posts Per Page</label>
                    <input type="number" id="posts_per_page" name="posts_per_page" min="1" max="100"
                           value="<?php echo $security->escapeHTML(
                               $settings["posts_per_page"]); ?>">
                    <div class="form-help">Number of posts to display per page (1-100)</div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="date_format">Date Format</label>
                        <select id="date_format" name="date_format">
                            <option value="F j, Y" <?php echo $settings[
                                "date_format"
                            ] === "F j, Y"
                                ? "selected"
                                : ""; ?>>January 1, 2025</option>
                            <option value="Y-m-d" <?php echo $settings[
                                "date_format"
                            ] === "Y-m-d"
                                ? "selected"
                                : ""; ?>>2025-01-01</option>
                            <option value="m/d/Y" <?php echo $settings[
                                "date_format"
                            ] === "m/d/Y"
                                ? "selected"
                                : ""; ?>>01/01/2025</option>
                            <option value="d/m/Y" <?php echo $settings[
                                "date_format"
                            ] === "d/m/Y"
                                ? "selected"
                                : ""; ?>>01/01/2025</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="time_format">Time Format</label>
                        <select id="time_format" name="time_format">
                            <option value="g:i a" <?php echo $settings[
                                "time_format"
                            ] === "g:i a"
                                ? "selected"
                                : ""; ?>>12-hour (3:30 pm)</option>
                            <option value="H:i" <?php echo $settings[
                                "time_format"
                            ] === "H:i"
                                ? "selected"
                                : ""; ?>>24-hour (15:30)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="timezone">Timezone</label>
                    <select id="timezone" name="timezone">
                        <?php foreach ($timezones as $value => $label): ?>
                            <option value="<?php echo $security->escapeHTML(
                                $value); ?>"
                                    <?php echo $settings["timezone"] === $value
                                        ? "selected"
                                        : ""; ?>>
                                <?php echo $security->escapeHTML($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Feature Settings -->
            <div class="card">
                <h2 class="section-title">🔧 Feature Settings</h2>

                <div class="checkbox-group">
                    <div class="checkbox-item">
                        <input type="checkbox" id="allow_search" name="allow_search"
                               <?php echo $settings["allow_search"]
                                   ? "checked"
                                   : ""; ?>>
                        <label for="allow_search">
                            <div>Enable Search</div>
                            <div class="checkbox-help">Allow visitors to search posts</div>
                        </label>
                    </div>

                    <div class="checkbox-item">
                        <input type="checkbox" id="allow_private_posts" name="allow_private_posts"
                               <?php echo $settings["allow_private_posts"]
                                   ? "checked"
                                   : ""; ?>>
                        <label for="allow_private_posts">
                            <div>Enable Private Posts</div>
                            <div class="checkbox-help">Allow posts to be marked as private (admin only)</div>
                        </label>
                    </div>

                    <div class="checkbox-item">
                        <input type="checkbox" id="allow_password_protected" name="allow_password_protected"
                               <?php echo $settings["allow_password_protected"]
                                   ? "checked"
                                   : ""; ?>>
                        <label for="allow_password_protected">
                            <div>Enable Password Protected Posts</div>
                            <div class="checkbox-help">Allow posts to be protected with a password</div>
                        </label>
                    </div>

                    <div class="checkbox-item">
                        <input type="checkbox" id="enable_url_shortener" name="enable_url_shortener"
                               <?php echo $settings["enable_url_shortener"]
                                   ? "checked"
                                   : ""; ?>>
                        <label for="enable_url_shortener">
                            <div>Enable URL Shortener</div>
                            <div class="checkbox-help">Generate short URLs for easy sharing</div>
                        </label>
                    </div>

                    <div class="checkbox-item">
                        <input type="checkbox" id="require_login_for_posts" name="require_login_for_posts"
                               <?php echo $settings["require_login_for_posts"]
                                   ? "checked"
                                   : ""; ?>>
                        <label for="require_login_for_posts">
                            <div>Require Login to View Posts</div>
                            <div class="checkbox-help">Make all posts private - only logged in users can view</div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn">💾 Save Settings</button>
                <a href="admin.php" class="btn btn-secondary">❌ Cancel</a>
            </div>
        </form>
    </div>

    <script>
        // Warn before leaving with unsaved changes
        let formChanged = false;
        const form = document.getElementById('settingsForm');
        const inputs = form.querySelectorAll('input, textarea, select');

        const originalValues = {};
        inputs.forEach(input => {
            if (input.type === 'checkbox') {
                originalValues[input.name] = input.checked;
            } else {
                originalValues[input.name] = input.value;
            }

            input.addEventListener('change', function() {
                if (this.type === 'checkbox') {
                    formChanged = this.checked !== originalValues[this.name];
                } else {
                    formChanged = this.value !== originalValues[this.name];
                }
            });
        });

        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                return e.returnValue;
            }
        });

        form.addEventListener('submit', function() {
            formChanged = false;
        });
    </script>
<?php include APP_ROOT . '/templates/footer.php'; ?>
</body>
</html>
