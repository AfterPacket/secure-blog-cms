<?php
/**
 * Secure Blog CMS - Installation Wizard
 * This file will self-delete after successful installation
 */

// Check if already installed
if (file_exists(__DIR__ . "/../data/installed.lock")) {
    die(
        '<!DOCTYPE html><html><head><title>Already Installed</title></head><body style="font-family: sans-serif; text-align: center; padding: 50px;"><h1>⚠️ Already Installed</h1><p>The CMS is already installed. Delete <code>data/installed.lock</code> to reinstall.</p><p><a href="../index.php">Go to Blog</a> | <a href="../admin/login.php">Admin Login</a></p></body></html>'
    );
}

session_start();

$errors = [];
$warnings = [];
$step = $_GET["step"] ?? 1;
$installComplete = false;

// System Requirements Check
function checkSystemRequirements()
{
    $checks = [
        "php_version" => version_compare(PHP_VERSION, "7.4.0", ">="),
        "json" => extension_loaded("json"),
        "mbstring" => extension_loaded("mbstring"),
        "fileinfo" => extension_loaded("fileinfo"),
        "writable_root" => is_writable(__DIR__ . "/../"),
    ];
    return $checks;
}

// Create directory structure
function createDirectories()
{
    $dirs = [
        "data",
        "data/posts",
        "data/users",
        "data/sessions",
        "data/logs",
        "data/backups",
        "data/uploads",
        "data/uploads/images",
        "data/settings",
    ];

    foreach ($dirs as $dir) {
        $path = __DIR__ . "/../" . $dir;
        if (!is_dir($path)) {
            if (!mkdir($path, 0700, true)) {
                return false;
            }
        }
        chmod($path, 0700);

        // Create .htaccess in data directories
        $htaccess = $path . "/.htaccess";
        if (!file_exists($htaccess)) {
            $content = "# Secure directory\n";
            $content .= "<IfModule mod_authz_core.c>\n";
            $content .= "    Require all denied\n";
            $content .= "</IfModule>\n";
            $content .= "<IfModule !mod_authz_core.c>\n";
            $content .= "    Order deny,allow\n";
            $content .= "    Deny from all\n";
            $content .= "</IfModule>\n";
            file_put_contents($htaccess, $content);
        }

        // Create index.php to prevent directory listing
        $index = $path . "/index.php";
        if (!file_exists($index)) {
            file_put_contents(
                $index,
                "<?php header('HTTP/1.0 403 Forbidden'); die('Access denied'); ?>");
        }
    }
    return true;
}

// Process installation
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["install"])) {
    // Validate CSRF token
    if (
        !isset($_POST["csrf_token"]) ||
        !isset($_SESSION["install_csrf"]) ||
        !hash_equals($_SESSION["install_csrf"], $_POST["csrf_token"])
    ) {
        $errors[] = "Invalid security token. Please refresh and try again.";
    } else {
        // Get form data
        $adminUsername = trim($_POST["admin_username"] ?? "");
        $adminPassword = $_POST["admin_password"] ?? "";
        $adminPasswordConfirm = $_POST["admin_password_confirm"] ?? "";
        $siteName = trim($_POST["site_name"] ?? "Secure Blog CMS");
        $siteDescription = trim(
            $_POST["site_description"] ?? "A secure blogging platform");
        $siteUrl = trim($_POST["site_url"] ?? "");

        // Validation
        if (
            empty($adminUsername) ||
            !preg_match('/^[a-zA-Z0-9_]{3,50}$/', $adminUsername)
        ) {
            $errors[] =
                "Username must be 3-50 characters (letters, numbers, underscore only)";
        }

        if (strlen($adminPassword) < 12) {
            $errors[] = "Password must be at least 12 characters long";
        }

        if ($adminPassword !== $adminPasswordConfirm) {
            $errors[] = "Passwords do not match";
        }

        if (empty($siteName) || strlen($siteName) > 100) {
            $errors[] = "Site name is required (max 100 characters)";
        }

        if (empty($errors)) {
            // Create directories
            if (!createDirectories()) {
                $errors[] =
                    "Failed to create required directories. Check permissions.";
            } else {
                // Hash password
                if (defined("PASSWORD_ARGON2ID")) {
                    $passwordHash = password_hash(
                        $adminPassword,
                        PASSWORD_ARGON2ID,
                        [
                            "memory_cost" => 65536,
                            "time_cost" => 4,
                            "threads" => 1,
                        ]);
                } else {
                    $passwordHash = password_hash(
                        $adminPassword,
                        PASSWORD_BCRYPT,
                        ["cost" => 12]);
                }

                // Update config.php
                $configPath = __DIR__ . "/../includes/config.php";
                $configContent = file_get_contents($configPath);

                // Replace default values
                // Use var_export to ensure values are written as safe PHP literals
                $configContent = str_replace(
                    '"REPLACE_ME_USERNAME"',
                    var_export($adminUsername, true),
                    $configContent);

                $configContent = str_replace(
                    '"REPLACE_ME_PASSWORD_HASH"',
                    var_export($passwordHash, true),
                    $configContent);

                $configContent = str_replace(
                    '"REPLACE_ME_SITE_NAME"',
                    var_export($siteName, true),
                    $configContent);

                $configContent = str_replace(
                    '"REPLACE_ME_SITE_DESCRIPTION"',
                    var_export($siteDescription, true),
                    $configContent);

                if (!empty($siteUrl)) {
                    $configContent = str_replace(
                        '"REPLACE_ME_SITE_URL"',
                        var_export($siteUrl, true),
                        $configContent);
                }

                if (
                    file_put_contents($configPath, $configContent, LOCK_EX) ===
                    false
                ) {
                    $errors[] =
                        "Failed to update config.php. Check file permissions.";
                } else {
                    // Create settings file
                    $settings = [
                        "site_name" => $siteName,
                        "site_description" => $siteDescription,
                        "site_url" => $siteUrl,
                        "posts_per_page" => 10,
                        "allow_comments" => false,
                        "installed" => true,
                        "installed_date" => date("Y-m-d H:i:s"),
                    ];

                    file_put_contents(
                        __DIR__ . "/../data/settings/site.json",
                        json_encode($settings, JSON_PRETTY_PRINT),
                        LOCK_EX);

                    // Create installation lock file
                    file_put_contents(
                        __DIR__ . "/../data/installed.lock",
                        time());

                    // Log installation
                    $logEntry = sprintf(
                        "[%s] Installation completed | Username: %s | IP: %s\n",
                        date("Y-m-d H:i:s"),
                        $adminUsername,
                        $_SERVER["REMOTE_ADDR"] ?? "unknown");
                    file_put_contents(
                        __DIR__ .
                            "/../data/logs/security_" .
                            date("Y-m-d") .
                            ".log",
                        $logEntry,
                        FILE_APPEND | LOCK_EX);

                    $installComplete = true;
                    $step = 3; // Success page
                }
            }
        }
    }
}

// Generate CSRF token
if (!isset($_SESSION["install_csrf"])) {
    $_SESSION["install_csrf"] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION["install_csrf"];

// Run system checks
$systemChecks = checkSystemRequirements();
$allChecksPassed = !in_array(false, $systemChecks, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Install Secure Blog CMS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .installer {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 600px;
        }

        .installer-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .installer-header h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .installer-header p {
            color: #7f8c8d;
            font-size: 14px;
        }

        .steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #ecf0f1;
        }

        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }

        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #ecf0f1;
            color: #7f8c8d;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .step.active .step-number {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .step.completed .step-number {
            background: #27ae60;
            color: white;
        }

        .step-label {
            font-size: 12px;
            color: #7f8c8d;
        }

        .step.active .step-label {
            color: #2c3e50;
            font-weight: 600;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        .alert-warning {
            background: #ffc;
            color: #990;
            border: 1px solid #ff9;
        }

        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }

        .check-item {
            display: flex;
            justify-content: space-between;
            padding: 12px;
            border-bottom: 1px solid #ecf0f1;
        }

        .check-item:last-child {
            border-bottom: none;
        }

        .check-status {
            font-weight: 600;
        }

        .check-status.pass {
            color: #27ae60;
        }

        .check-status.fail {
            color: #e74c3c;
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

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 16px;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-help {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 6px;
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
            width: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            transform: none;
        }

        .success-icon {
            font-size: 64px;
            text-align: center;
            margin: 30px 0;
        }

        .success-message {
            text-align: center;
            margin-bottom: 20px;
        }

        .success-message h2 {
            color: #27ae60;
            margin-bottom: 10px;
        }

        .success-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .success-actions a {
            flex: 1;
            padding: 12px;
            text-align: center;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-secondary {
            background: #ecf0f1;
            color: #2c3e50;
        }

        .password-strength {
            height: 4px;
            background: #ecf0f1;
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            transition: width 0.3s, background 0.3s;
            width: 0;
        }

        .strength-weak { width: 33%; background: #e74c3c; }
        .strength-medium { width: 66%; background: #f39c12; }
        .strength-strong { width: 100%; background: #27ae60; }
    </style>
</head>
<body>
    <div class="installer">
        <div class="installer-header">
            <h1>🔒 Secure Blog CMS</h1>
            <p>Installation Wizard</p>
        </div>

        <!-- Step Indicator -->
        <div class="steps">
            <div class="step <?php echo $step == 1
                ? "active"
                : ($step > 1
                    ? "completed"
                    : ""); ?>">
                <div class="step-number">1</div>
                <div class="step-label">Requirements</div>
            </div>
            <div class="step <?php echo $step == 2
                ? "active"
                : ($step > 2
                    ? "completed"
                    : ""); ?>">
                <div class="step-number">2</div>
                <div class="step-label">Configuration</div>
            </div>
            <div class="step <?php echo $step == 3 ? "active" : ""; ?>">
                <div class="step-number">3</div>
                <div class="step-label">Complete</div>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <strong>⚠️ Errors:</strong><br>
                <?php foreach ($errors as $error): ?>
                    • <?php echo htmlspecialchars($error); ?><br>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($warnings)): ?>
            <div class="alert alert-warning">
                <strong>⚠️ Warnings:</strong><br>
                <?php foreach ($warnings as $warning): ?>
                    • <?php echo htmlspecialchars($warning); ?><br>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Step 1: System Requirements -->
        <?php if ($step == 1): ?>
            <h2 style="margin-bottom: 20px; color: #2c3e50;">System Requirements Check</h2>

            <div style="background: #f8f9fa; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
                <div class="check-item">
                    <span>PHP Version (>= 7.4)</span>
                    <span class="check-status <?php echo $systemChecks[
                        "php_version"
                    ]
                        ? "pass"
                        : "fail"; ?>">
                        <?php echo $systemChecks["php_version"]
                            ? "✓ " . PHP_VERSION
                            : "✗ " . PHP_VERSION; ?>
                    </span>
                </div>
                <div class="check-item">
                    <span>JSON Extension</span>
                    <span class="check-status <?php echo $systemChecks["json"]
                        ? "pass"
                        : "fail"; ?>">
                        <?php echo $systemChecks["json"]
                            ? "✓ Enabled"
                            : "✗ Missing"; ?>
                    </span>
                </div>
                <div class="check-item">
                    <span>MBString Extension</span>
                    <span class="check-status <?php echo $systemChecks[
                        "mbstring"
                    ]
                        ? "pass"
                        : "fail"; ?>">
                        <?php echo $systemChecks["mbstring"]
                            ? "✓ Enabled"
                            : "✗ Missing"; ?>
                    </span>
                </div>
                <div class="check-item">
                    <span>FileInfo Extension</span>
                    <span class="check-status <?php echo $systemChecks[
                        "fileinfo"
                    ]
                        ? "pass"
                        : "fail"; ?>">
                        <?php echo $systemChecks["fileinfo"]
                            ? "✓ Enabled"
                            : "✗ Missing"; ?>
                    </span>
                </div>
                <div class="check-item">
                    <span>Write Permissions</span>
                    <span class="check-status <?php echo $systemChecks[
                        "writable_root"
                    ]
                        ? "pass"
                        : "fail"; ?>">
                        <?php echo $systemChecks["writable_root"]
                            ? "✓ Writable"
                            : "✗ Not Writable"; ?>
                    </span>
                </div>
            </div>

            <?php if ($allChecksPassed): ?>
                <div class="alert alert-success">
                    ✓ All system requirements met! Ready to proceed.
                </div>
                <a href="?step=2" class="btn">Continue to Configuration →</a>
            <?php else: ?>
                <div class="alert alert-error">
                    ✗ Some requirements are not met. Please fix the issues above before continuing.
                </div>
                <button class="btn" disabled>Cannot Continue</button>
            <?php endif; ?>

        <!-- Step 2: Configuration -->
        <?php elseif ($step == 2): ?>
            <h2 style="margin-bottom: 20px; color: #2c3e50;">Configure Your Blog</h2>

            <form method="post" action="index.php?step=2" id="installForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(
                    $csrfToken); ?>">
                <input type="hidden" name="install" value="1">

                <fieldset style="border: none; margin-bottom: 30px;">
                    <legend style="font-size: 18px; font-weight: 600; color: #2c3e50; margin-bottom: 15px;">👤 Admin Account</legend>

                    <div class="form-group">
                        <label for="admin_username">Username *</label>
                        <input type="text" id="admin_username" name="admin_username" required
                               pattern="[a-zA-Z0-9_]{3,50}" minlength="3" maxlength="50"
                               value="<?php echo htmlspecialchars(
                                   $_POST["admin_username"] ?? "admin"); ?>">
                        <div class="form-help">3-50 characters, letters, numbers, and underscore only</div>
                    </div>

                    <div class="form-group">
                        <label for="admin_password">Password *</label>
                        <input type="password" id="admin_password" name="admin_password" required minlength="12">
                        <div class="password-strength">
                            <div class="password-strength-bar" id="strengthBar"></div>
                        </div>
                        <div class="form-help">Minimum 12 characters, use mix of letters, numbers, and symbols</div>
                    </div>

                    <div class="form-group">
                        <label for="admin_password_confirm">Confirm Password *</label>
                        <input type="password" id="admin_password_confirm" name="admin_password_confirm" required minlength="12">
                    </div>
                </fieldset>

                <fieldset style="border: none; margin-bottom: 30px;">
                    <legend style="font-size: 18px; font-weight: 600; color: #2c3e50; margin-bottom: 15px;">🌐 Site Information</legend>

                    <div class="form-group">
                        <label for="site_name">Site Name *</label>
                        <input type="text" id="site_name" name="site_name" required maxlength="100"
                               value="<?php echo htmlspecialchars(
                                   $_POST["site_name"] ?? "Secure Blog CMS"); ?>">
                    </div>

                    <div class="form-group">
                        <label for="site_description">Site Description</label>
                        <textarea id="site_description" name="site_description" rows="3" maxlength="500"><?php echo htmlspecialchars(
                            $_POST["site_description"] ??
                                "A secure blogging platform"); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="site_url">Site URL (optional)</label>
                        <input type="url" id="site_url" name="site_url" placeholder="https://yourdomain.com"
                               value="<?php echo htmlspecialchars(
                                   $_POST["site_url"] ?? ""); ?>">
                        <div class="form-help">Full URL including https://</div>
                    </div>
                </fieldset>

                <button type="submit" class="btn">🚀 Install Now</button>
            </form>

        <!-- Step 3: Success -->
        <?php elseif ($step == 3 && $installComplete): ?>
            <div class="success-icon">🎉</div>
            <div class="success-message">
                <h2>Installation Complete!</h2>
                <p>Your secure blog is ready to use.</p>
            </div>

            <div class="alert alert-success">
                <strong>✓ Installation successful!</strong><br>
                • Admin account created<br>
                • Directories configured<br>
                • Security features enabled<br>
                • Install file will be deleted on first login
            </div>

            <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                <strong>📝 Important Notes:</strong><br>
                <ul style="margin-left: 20px; margin-top: 10px; line-height: 1.8;">
                    <li>Save your admin credentials securely</li>
                    <li>For production, enable HTTPS in .htaccess</li>
                    <li>Regularly check security logs in data/logs/</li>
                    <li>Create backups frequently</li>
                </ul>
            </div>

            <div class="success-actions">
                <a href="../admin/login.php" class="btn-primary">🔐 Go to Admin Login</a>
                <a href="../index.php" class="btn-secondary">👁️ View Blog</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Password strength checker
        const passwordInput = document.getElementById('admin_password');
        const strengthBar = document.getElementById('strengthBar');

        if (passwordInput && strengthBar) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;

                if (password.length >= 12) strength++;
                if (password.length >= 16) strength++;
                if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
                if (/[0-9]/.test(password)) strength++;
                if (/[^a-zA-Z0-9]/.test(password)) strength++;

                strengthBar.className = 'password-strength-bar';
                if (strength <= 2) {
                    strengthBar.classList.add('strength-weak');
                } else if (strength <= 4) {
                    strengthBar.classList.add('strength-medium');
                } else {
                    strengthBar.classList.add('strength-strong');
                }
            });
        }

        // Form validation
        const form = document.getElementById('installForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const password = document.getElementById('admin_password').value;
                const confirm = document.getElementById('admin_password_confirm').value;

                if (password !== confirm) {
                    alert('Passwords do not match!');
                    e.preventDefault();
                    return false;
                }

                if (password.length < 12) {
                    alert('Password must be at least 12 characters long!');
                    e.preventDefault();
                    return false;
                }

                // Disable submit button
                const btn = form.querySelector('button[type="submit"]');
                btn.disabled = true;
                btn.textContent = '⏳ Installing...';
            });
        }
    </script>
</body>
</html>
