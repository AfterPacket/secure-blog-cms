<?php
/**
 * Secure Blog CMS - Upgrade Management Page
 * Check for updates and upgrade the system
 */

// Initialize security constant
define("SECURE_CMS_INIT", true);

// Load configuration
require_once __DIR__ . "/../includes/config.php";

// Load required classes
require_once __DIR__ . "/../includes/Security.php";
require_once __DIR__ . "/../includes/Storage.php";
require_once __DIR__ . "/../includes/Upgrader.php";

// Initialize security, storage, and upgrader
$security = Security::getInstance();
$storage = Storage::getInstance();
$upgrader = new Upgrader();

// Check authentication
if (!$security->isAuthenticated()) {
    header("Location: login.php");
    exit();
}

$message = "";
$messageType = "";
$updateCheck = null;

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $security->getPostData("action", "alphanumeric", "");
    $csrfToken = $_POST["csrf_token"] ?? "";

    if (!$security->validateCSRFToken($csrfToken, "upgrade_action")) {
        $message = "Invalid security token. Please try again.";
        $messageType = "error";
        $security->logSecurityEvent(
            "CSRF validation failed on upgrade action",
            $_SESSION["user"],
        );
    } else {
        switch ($action) {
            case "check_updates":
                $updateCheck = $upgrader->checkForUpdates(true);
                if ($updateCheck["success"]) {
                    if ($updateCheck["up_to_date"]) {
                        $message = "Your system is up to date!";
                        $messageType = "success";
                    } else {
                        $message =
                            "Updates available! " .
                            $updateCheck["updates_available"] .
                            " update(s) found.";
                        $messageType = "info";
                    }
                } else {
                    $message =
                        $updateCheck["error"] ?? "Failed to check for updates";
                    $messageType = "error";
                }
                break;

            case "perform_upgrade":
                $version = $security->getPostData("version", "string", "");
                $downloadUrl = $security->getPostData(
                    "download_url",
                    "url",
                    "",
                );
                $checksum = $security->getPostData("checksum", "string", "");

                if (empty($version)) {
                    $message = "Version not specified";
                    $messageType = "error";
                } else {
                    $result = $upgrader->performUpgrade(
                        $version,
                        $downloadUrl,
                        $checksum,
                    );
                    $message =
                        $result["message"] ??
                        ($result["error"] ?? "Upgrade failed");
                    $messageType = $result["success"] ? "success" : "error";

                    if ($result["success"]) {
                        $message .=
                            " (Upgraded from " .
                            $result["old_version"] .
                            " to " .
                            $result["new_version"] .
                            ")";
                    }
                }
                break;

            case "toggle_auto_upgrade":
                $enabled = isset($_POST["auto_upgrade_enabled"]);
                $result = $upgrader->setAutoUpgrade($enabled);
                $message =
                    "Auto-upgrade " . ($enabled ? "enabled" : "disabled");
                $messageType = "success";
                break;
        }
    }
}

// Generate CSRF token
$csrfToken = $security->generateCSRFToken("upgrade_action");

// Get system information
$systemInfo = $upgrader->getSystemInfo();
$upgradeHistory = $upgrader->getUpgradeHistory();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>System Upgrade - <?php echo $security->escapeHTML(
        SITE_NAME,
    ); ?></title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
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

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .section {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ecf0f1;
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-header h2 {
            color: #2c3e50;
            font-size: 22px;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background 0.3s, transform 0.2s;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #27ae60;
        }

        .btn-success:hover {
            background: #229954;
        }

        .btn-warning {
            background: #f39c12;
        }

        .btn-warning:hover {
            background: #e67e22;
        }

        .btn-danger {
            background: #e74c3c;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-large {
            padding: 15px 30px;
            font-size: 16px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            border-left: 4px solid #3498db;
        }

        .info-card .label {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .info-card .value {
            font-size: 20px;
            font-weight: 600;
            color: #2c3e50;
        }

        .version-badge {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 5px 12px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
        }

        .version-badge.latest {
            background: #27ae60;
        }

        .version-badge.outdated {
            background: #e74c3c;
        }

        .update-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 15px;
            transition: border-color 0.3s;
        }

        .update-card:hover {
            border-color: #3498db;
        }

        .update-card.critical {
            border-color: #e74c3c;
            background: #fff5f5;
        }

        .update-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .update-version {
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
        }

        .critical-badge {
            background: #e74c3c;
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .update-description {
            color: #555;
            margin-bottom: 15px;
            line-height: 1.6;
        }

        .changes-list {
            list-style: none;
            margin-bottom: 15px;
        }

        .changes-list li {
            padding: 5px 0;
            padding-left: 25px;
            position: relative;
        }

        .changes-list li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #27ae60;
            font-weight: 600;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
        }

        .history-table thead {
            background: #34495e;
            color: white;
        }

        .history-table th,
        .history-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }

        .history-table tbody tr:hover {
            background: #f8f9fa;
        }

        .no-updates {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }

        .checkbox-group {
            margin: 20px 0;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .checkbox-item label {
            cursor: pointer;
            font-weight: 500;
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

            .info-grid {
                grid-template-columns: 1fr;
            }

            .section {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container">
            <h1>🔄 System Upgrade <span class="security-badge">SECURED</span></h1>
            <div class="admin-nav">
                <a href="admin.php">← Back to Dashboard</a>
                <a href="settings.php">⚙️ Settings</a>
                <a href="logout.php">🚪 Logout</a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $security->escapeHTML(
                $messageType,
            ); ?>">
                <?php echo $security->escapeHTML($message); ?>
            </div>
        <?php endif; ?>

        <!-- Current System Information -->
        <div class="section">
            <div class="section-header">
                <h2>📊 System Information</h2>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $security->escapeHTML(
                        $csrfToken,
                    ); ?>">
                    <input type="hidden" name="action" value="check_updates">
                    <button type="submit" class="btn btn-success">
                        🔍 Check for Updates
                    </button>
                </form>
            </div>

            <div class="info-grid">
                <div class="info-card">
                    <div class="label">Current Version</div>
                    <div class="value">
                        <span class="version-badge">v<?php echo $security->escapeHTML(
                            $systemInfo["current_version"],
                        ); ?></span>
                    </div>
                </div>

                <div class="info-card">
                    <div class="label">PHP Version</div>
                    <div class="value"><?php echo $security->escapeHTML(
                        $systemInfo["php_version"],
                    ); ?></div>
                </div>

                <div class="info-card">
                    <div class="label">Total Upgrades</div>
                    <div class="value"><?php echo number_format(
                        $systemInfo["total_upgrades"],
                    ); ?></div>
                </div>

                <div class="info-card">
                    <div class="label">Disk Space Available</div>
                    <div class="value"><?php echo number_format(
                        $systemInfo["disk_space"] / 1024 / 1024 / 1024,
                        2,
                    ); ?> GB</div>
                </div>

                <?php if ($systemInfo["last_check"]): ?>
                <div class="info-card">
                    <div class="label">Last Update Check</div>
                    <div class="value" style="font-size: 14px;">
                        <?php echo date(
                            "Y-m-d H:i:s",
                            $systemInfo["last_check"],
                        ); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($systemInfo["last_upgrade"]): ?>
                <div class="info-card">
                    <div class="label">Last Upgrade</div>
                    <div class="value" style="font-size: 14px;">
                        <?php echo date(
                            "Y-m-d H:i:s",
                            $systemInfo["last_upgrade"],
                        ); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="checkbox-group">
                <form method="post" id="autoUpgradeForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $security->escapeHTML(
                        $csrfToken,
                    ); ?>">
                    <input type="hidden" name="action" value="toggle_auto_upgrade">
                    <div class="checkbox-item">
                        <input type="checkbox"
                               id="auto_upgrade_enabled"
                               name="auto_upgrade_enabled"
                               <?php echo $systemInfo["auto_upgrade_enabled"]
                                   ? "checked"
                                   : ""; ?>
                               onchange="document.getElementById('autoUpgradeForm').submit()">
                        <label for="auto_upgrade_enabled">
                            <strong>Enable Automatic Updates</strong>
                            <div style="font-size: 12px; color: #7f8c8d; margin-top: 4px;">
                                Automatically install minor updates and security patches (recommended)
                            </div>
                        </label>
                    </div>
                </form>
            </div>
        </div>

        <!-- Available Updates -->
        <?php if ($updateCheck && isset($updateCheck["updates"])): ?>
        <div class="section">
            <div class="section-header">
                <h2>📦 Available Updates</h2>
                <?php if ($updateCheck["up_to_date"]): ?>
                    <span class="version-badge latest">✓ Up to Date</span>
                <?php else: ?>
                    <span class="version-badge outdated"><?php echo count(
                        $updateCheck["updates"],
                    ); ?> Update(s) Available</span>
                <?php endif; ?>
            </div>

            <?php if (empty($updateCheck["updates"])): ?>
                <div class="no-updates">
                    <h3>🎉 You're all set!</h3>
                    <p>Your system is running the latest version.</p>
                </div>
            <?php else: ?>
                <?php foreach ($updateCheck["updates"] as $update): ?>
                <div class="update-card <?php echo $update["critical"]
                    ? "critical"
                    : ""; ?>">
                    <div class="update-header">
                        <div>
                            <span class="update-version">Version <?php echo $security->escapeHTML(
                                $update["version"],
                            ); ?></span>
                            <?php if ($update["critical"]): ?>
                                <span class="critical-badge">Critical Security Update</span>
                            <?php endif; ?>
                        </div>
                        <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to upgrade to version <?php echo $security->escapeHTML(
                            $update["version"],
                        ); ?>? A backup will be created automatically.');">
                            <input type="hidden" name="csrf_token" value="<?php echo $security->escapeHTML(
                                $csrfToken,
                            ); ?>">
                            <input type="hidden" name="action" value="perform_upgrade">
                            <input type="hidden" name="version" value="<?php echo $security->escapeHTML(
                                $update["version"],
                            ); ?>">
                            <?php if ($update["download_url"]): ?>
                                <input type="hidden" name="download_url" value="<?php echo $security->escapeHTML(
                                    $update["download_url"],
                                ); ?>">
                            <?php endif; ?>
                            <?php if ($update["checksum"]): ?>
                                <input type="hidden" name="checksum" value="<?php echo $security->escapeHTML(
                                    $update["checksum"],
                                ); ?>">
                            <?php endif; ?>
                            <button type="submit" class="btn <?php echo $update[
                                "critical"
                            ]
                                ? "btn-danger"
                                : "btn-success"; ?>">
                                ⬆️ Upgrade Now
                            </button>
                        </form>
                    </div>

                    <?php if ($update["release_date"]): ?>
                        <p style="font-size: 12px; color: #7f8c8d; margin-bottom: 10px;">
                            Released: <?php echo $security->escapeHTML(
                                $update["release_date"],
                            ); ?>
                        </p>
                    <?php endif; ?>

                    <?php if ($update["description"]): ?>
                        <div class="update-description">
                            <?php echo $security->escapeHTML(
                                $update["description"],
                            ); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($update["changes"])): ?>
                        <strong style="font-size: 14px; color: #2c3e50;">What's New:</strong>
                        <ul class="changes-list">
                            <?php foreach ($update["changes"] as $change): ?>
                                <li><?php echo $security->escapeHTML(
                                    $change,
                                ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Upgrade History -->
        <?php if (!empty($upgradeHistory)): ?>
        <div class="section">
            <div class="section-header">
                <h2>📜 Upgrade History</h2>
            </div>

            <div style="overflow-x: auto;">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>From Version</th>
                            <th>To Version</th>
                            <th>Performed By</th>
                            <th>Migrations</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (
                            array_reverse($upgradeHistory)
                            as $history
                        ): ?>
                        <tr>
                            <td><?php echo date(
                                "Y-m-d H:i:s",
                                $history["upgraded_at"],
                            ); ?></td>
                            <td><?php echo $security->escapeHTML(
                                $history["from_version"],
                            ); ?></td>
                            <td><strong><?php echo $security->escapeHTML(
                                $history["to_version"],
                            ); ?></strong></td>
                            <td><?php echo $security->escapeHTML(
                                $history["upgraded_by"],
                            ); ?></td>
                            <td><?php echo count(
                                $history["migrations_run"],
                            ); ?> migration(s)</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Important Notes -->
        <div class="section">
            <div class="section-header">
                <h2>⚠️ Important Notes</h2>
            </div>

            <div style="background: #fff3cd; padding: 20px; border-radius: 6px; border-left: 4px solid #f39c12;">
                <strong style="color: #856404;">Before Upgrading:</strong>
                <ul style="margin-top: 10px; margin-left: 20px; line-height: 1.8; color: #856404;">
                    <li>A backup is created automatically before each upgrade</li>
                    <li>The upgrade process may take a few minutes</li>
                    <li>Do not close your browser during the upgrade</li>
                    <li>You may need to clear your browser cache after upgrading</li>
                    <li>Critical security updates should be installed immediately</li>
                </ul>
            </div>

            <div style="background: #d1ecf1; padding: 20px; border-radius: 6px; border-left: 4px solid #3498db; margin-top: 20px;">
                <strong style="color: #0c5460;">Rollback Information:</strong>
                <p style="margin-top: 10px; color: #0c5460; line-height: 1.6;">
                    If an upgrade fails, the system will automatically attempt to rollback to the previous version.
                    You can also manually restore from a backup in the admin dashboard.
                </p>
            </div>
        </div>
    </div>

    <script>
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
<?php include APP_ROOT . "/templates/footer.php"; ?>
</body>
</html>
