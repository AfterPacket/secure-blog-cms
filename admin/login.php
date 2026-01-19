<?php
/**
 * Secure Blog CMS - Login Page
 * Secure authentication with CSRF protection, rate limiting, and brute force prevention
 */

// Initialize security constant
define("SECURE_CMS_INIT", true);

// Load configuration
require_once __DIR__ . "/../includes/config.php";

// Load required classes
require_once __DIR__ . "/../includes/Security.php";
require_once __DIR__ . "/../includes/Storage.php";

// Initialize security
$security = Security::getInstance();

// Redirect if already logged in
if ($security->isAuthenticated()) {
    header('Location: ' . cms_path('admin/admin.php'));
    exit();
}

$error = "";
$success = "";

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Rate limiting check
    $clientIP = $_SERVER["REMOTE_ADDR"] ?? "unknown";
    if (!$security->checkRateLimit("login_" . $clientIP, 10, 600)) {
        $error = "Too many login attempts. Please try again in 10 minutes.";
        $security->logSecurityEvent("Login rate limit exceeded", $clientIP);
    } else {
        // Validate CSRF token
        $csrfToken = $_POST["csrf_token"] ?? "";
        if (!$security->validateCSRFToken($csrfToken, "login_form")) {
            $error = "Invalid security token. Please try again.";
            $security->logSecurityEvent(
                "CSRF token validation failed on login",
                $clientIP);
        } else {
            // Get and sanitize credentials
            $username = $security->getPostData("username", "alphanumeric", "");
            $password = $_POST["password"] ?? ""; // Don't sanitize password before verification

            // Validate input
            if (empty($username) || empty($password)) {
                $error = "Username and password are required.";
            } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
                $error = "Invalid credentials.";
                $security->recordFailedLogin($username);
            } else {
                // Attempt authentication
                $result = $security->authenticate($username, $password);

                if ($result["success"]) {
                    // Delete install.php on first successful login
                    $installFile = __DIR__ . "/../install.php";
                    if (file_exists($installFile)) {
                        @unlink($installFile);
                        $security->logSecurityEvent(
                            "Install file deleted on first login",
                            $username);
                    }

                    // Successful login - redirect to admin
                    header('Location: ' . cms_path('admin/admin.php'));
                    exit();
                } else {
                    $error = $result["message"];
                }
            }
        }
    }
}

// Generate new CSRF token
$csrfToken = $security->generateCSRFToken("login_form");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Login - <?php echo $security->escapeHTML(SITE_NAME); ?></title>
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

        .login-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 400px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .security-badge {
            display: inline-block;
            background: #27ae60;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 10px;
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

        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
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

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group input:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
        }

        .password-requirements {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 6px;
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            transform: none;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }

        .back-link a:hover {
            color: #764ba2;
        }

        .security-info {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ecf0f1;
            text-align: center;
        }

        .security-info p {
            font-size: 12px;
            color: #7f8c8d;
            line-height: 1.6;
        }

        .security-features {
            display: flex;
            justify-content: space-around;
            margin-top: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .security-feature {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            color: #27ae60;
            font-weight: 600;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }

            .login-header h1 {
                font-size: 24px;
            }

            .security-features {
                flex-direction: column;
                align-items: center;
            }
        }

        .input-icon {
            position: relative;
        }

        .input-icon input {
            padding-left: 40px;
        }

        .input-icon::before {
            content: '👤';
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
        }

        .password-icon {
            position: relative;
        }

        .password-icon input {
            padding-left: 40px;
        }

        .password-icon::before {
            content: '🔒';
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🔐 Admin Login</h1>
            <p style="color: #7f8c8d; font-size: 14px; margin-top: 5px;">
                <?php echo $security->escapeHTML(SITE_NAME); ?>
            </p>
            <div class="security-badge">🔒 SECURED</div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                ⚠️ <?php echo $security->escapeHTML($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                ✓ <?php echo $security->escapeHTML($success); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="login.php" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo $security->escapeHTML(
                $csrfToken); ?>">

            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-icon">
                    <input
                        type="text"
                        id="username"
                        name="username"
                        required
                        autocomplete="off"
                        maxlength="50"
                        pattern="[a-zA-Z0-9]+"
                        title="Username must contain only letters and numbers"
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-icon">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        autocomplete="off"
                        minlength="<?php echo PASSWORD_MIN_LENGTH; ?>"
                    >
                </div>
                <div class="password-requirements">
                    Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters required
                </div>
            </div>

            <button type="submit" class="btn">
                🔓 Login to Admin Panel
            </button>
        </form>

        <div class="back-link">
            <a href="../index.php">← Back to Blog</a>
        </div>

        <div class="security-info">
            <p><strong>Security Features Enabled:</strong></p>
            <div class="security-features">
                <div class="security-feature">
                    <span>✓</span> CSRF Protection
                </div>
                <div class="security-feature">
                    <span>✓</span> Rate Limiting
                </div>
                <div class="security-feature">
                    <span>✓</span> Brute Force Prevention
                </div>
                <div class="security-feature">
                    <span>✓</span> Session Security
                </div>
            </div>
            <p style="margin-top: 15px;">
                All login attempts are logged and monitored for security.
            </p>
        </div>
    </div>

    <script>
        // Auto-focus username field
        document.getElementById('username').focus();

        // Add visual feedback for form submission
        document.querySelector('form').addEventListener('submit', function(e) {
            const btn = document.querySelector('.btn');
            btn.textContent = '🔄 Authenticating...';
            btn.disabled = true;
        });

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
<?php include APP_ROOT . '/templates/footer.php'; ?>
</body>
</html>
