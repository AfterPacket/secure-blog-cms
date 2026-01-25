<?php
/**
 * Secure Blog CMS - Admin Dashboard
 * Secure admin panel for managing blog posts
 */

// Initialize security constant
define("SECURE_CMS_INIT", true);

// Load configuration
require_once __DIR__ . "/../includes/config.php";

// Load required classes
require_once __DIR__ . "/../includes/Security.php";
require_once __DIR__ . "/../includes/Storage.php";
require_once __DIR__ . "/../includes/notifications.php";
require_once __DIR__ . "/../includes/comments.php";
require_once __DIR__ . "/../includes/Upgrader.php";

// Initialize security and storage
$security = Security::getInstance();
$storage = Storage::getInstance();
$upgrader = new Upgrader();

// Check authentication
if (!$security->isAuthenticated()) {
    header("Location: login.php");
    exit();
}

$isAdmin = ($_SESSION["role"] ?? "") === "admin";

// Check for updates (only for admins)
$updateAvailable = false;
if ($isAdmin) {
    $updateCheck = $upgrader->checkForUpdates();
    $updateAvailable =
        isset($updateCheck["success"]) &&
        $updateCheck["success"] &&
        isset($updateCheck["up_to_date"]) &&
        !$updateCheck["up_to_date"];
}

// Handle post actions
$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $security->getPostData("action", "alphanumeric", "");
    $csrfToken = $_POST["csrf_token"] ?? "";

    if (!$security->validateCSRFToken($csrfToken, "admin_action")) {
        $message = "Invalid security token. Please try again.";
        $messageType = "error";
        $security->logSecurityEvent(
            "CSRF validation failed on admin action",
            $action,
        );
    } else {
        switch ($action) {
            case "delete":
                $postId = $security->getPostData("post_id", "alphanumeric", "");
                $result = $storage->deletePost($postId);
                $message = $result["message"];
                $messageType = $result["success"] ? "success" : "error";
                break;

            case "backup":
                if ($storage->createBackup("manual", "admin_request")) {
                    $message = "Backup created successfully";
                    $messageType = "success";
                } else {
                    $message = "Failed to create backup";
                    $messageType = "error";
                }
                break;

            case "restore":
                $backupFile = $security->getPostData(
                    "backup_file",
                    "filename",
                    "",
                );
                $result = $storage->restoreBackup($backupFile);
                $message = $result["message"];
                $messageType = $result["success"] ? "success" : "error";
                break;
        }
    }
}

// Generate CSRF token
$csrfToken = $security->generateCSRFToken("admin_action");

// Get all posts
$allPosts = $storage->getAllPosts("all");

$pendingCommentsCount = 0;
if ($isAdmin) {
    $commentsManager = Comments::getInstance();
    foreach ($allPosts as $__p) {
        $pendingCommentsCount += $commentsManager->getCommentCountForPost(
            $__p["id"],
            "pending",
        );
    }
}
$stats = $storage->getStatistics();
$backups = $storage->getBackups();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Dashboard - <?php echo $security->escapeHTML(
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


        .nav-badge {
            display: inline-block;
            margin-left: 6px;
            padding: 2px 8px;
            border-radius: 999px;
            background: rgba(0,0,0,0.25);
            font-size: 12px;
            line-height: 1.2;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }

        .stat-card .stat-icon {
            font-size: 36px;
            margin-bottom: 10px;
        }

        .stat-card .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-card .stat-label {
            color: #7f8c8d;
            font-size: 14px;
        }

        .section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
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

        .btn-danger {
            background: #e74c3c;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }

        .posts-table {
            width: 100%;
            border-collapse: collapse;
            overflow-x: auto;
        }

        .posts-table thead {
            background: #34495e;
            color: white;
        }

        .posts-table th,
        .posts-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }

        .posts-table tbody tr:hover {
            background: #f8f9fa;
        }

        .posts-table th {
            font-weight: 600;
            font-size: 13px;
        }

        .posts-table td {
            font-size: 14px;
        }

        .post-title-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .post-title-cell a {
            color: #2c3e50;
            text-decoration: none;
            font-weight: 600;
        }

        .post-title-cell a:hover {
            color: #3498db;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.published {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.draft {
            background: #fff3cd;
            color: #856404;
        }

        .actions-cell {
            white-space: nowrap;
        }

        .actions-cell a,
        .actions-cell button {
            margin-right: 8px;
        }

        .no-posts {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }

        .backup-list {
            list-style: none;
        }

        .backup-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #ecf0f1;
        }

        .backup-item:last-child {
            border-bottom: none;
        }

        .backup-info {
            flex: 1;
        }

        .backup-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .backup-meta {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 4px;
        }

        .backup-actions {
            display: flex;
            gap: 8px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        @media (max-width: 768px) {
            .admin-header .container {
                flex-direction: column;
                align-items: flex-start;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .section {
                padding: 15px;
            }

            .posts-table {
                font-size: 12px;
            }

            .posts-table th,
            .posts-table td {
                padding: 8px;
            }

            .post-title-cell {
                max-width: 150px;
            }
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

        .delete-form {
            display: inline;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container">
            <h1>🔐 Admin Dashboard <span class="security-badge">SECURED</span></h1>
            <div class="admin-nav">
                <a href="<?php echo cms_path(
                    "index.php",
                ); ?>" target="_blank">👁️ View Blog</a>
                <a href="create-post.php">➕ New Post</a>
                <a href="categories.php">🏷️ Categories & Tags</a>
                <?php if ($isAdmin): ?>
                    <a href="users.php">👥 Manage Users</a>
                    <a href="comments.php">💬 Comments<?php if (
                        $pendingCommentsCount > 0
                    ): ?><span class="nav-badge"><?php echo number_format(
    $pendingCommentsCount,
); ?></span><?php endif; ?></a>
                    <a href="upgrade.php">🆙 Upgrade<?php if (
                        $updateAvailable
                    ): ?><span class="nav-badge" style="background: #e74c3c;">!</span><?php endif; ?></a>
                    <a href="resilience.php">🛡️ Resilience</a>
                    <a href="settings.php">⚙️ Settings</a>
                <?php endif; ?>
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

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📝</div>
                <div class="stat-value"><?php echo number_format(
                    $stats["total_posts"],
                ); ?></div>
                <div class="stat-label">Total Posts</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?php echo number_format(
                    $stats["published_posts"],
                ); ?></div>
                <div class="stat-label">Published Posts</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📄</div>
                <div class="stat-value"><?php echo number_format(
                    $stats["draft_posts"],
                ); ?></div>
                <div class="stat-label">Draft Posts</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">👁️</div>
                <div class="stat-value"><?php echo number_format(
                    $stats["total_views"],
                ); ?></div>
                <div class="stat-label">Total Views</div>
            </div>
        </div>

        <!-- Posts Management -->
        <div class="section">
            <div class="section-header">
                <h2>📚 Manage Posts</h2>
                <a href="create-post.php" class="btn btn-success">➕ Create New Post</a>
            </div>

            <?php if (empty($allPosts)): ?>
                <div class="no-posts">
                    <p>No posts yet. Create your first post to get started!</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="posts-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Author</th>
                                <th>Views</th>
                                <th>Created</th>
                                <th>Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allPosts as $post): ?>
                                <tr>
                                    <td class="post-title-cell">
                                        <a href="<?php echo cms_path(
                                            "post.php",
                                        ); ?>?slug=<?php echo $security->escapeURL(
    $post["slug"],
); ?>" target="_blank">
                                            <?php echo $security->escapeHTML(
                                                $post["title"],
                                            ); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $security->escapeHTML(
                                            $post["status"],
                                        ); ?>">
                                            <?php echo $security->escapeHTML(
                                                $post["status"],
                                            ); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $security->escapeHTML(
                                        $post["author"],
                                    ); ?></td>
                                    <td><?php echo number_format(
                                        $post["views"],
                                    ); ?></td>
                                    <td><?php echo date(
                                        "Y-m-d",
                                        $post["created_at"],
                                    ); ?></td>
                                    <td><?php echo date(
                                        "Y-m-d",
                                        $post["updated_at"],
                                    ); ?></td>
                                    <td class="actions-cell">
                                        <a href="edit-post.php?id=<?php echo $security->escapeURL(
                                            $post["id"],
                                        ); ?>" class="btn btn-small">
                                            ✏️ Edit
                                        </a>
                                        <form method="post" class="delete-form" onsubmit="return confirm('Are you sure you want to delete this post? This action cannot be undone.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $security->escapeHTML(
                                                $csrfToken,
                                            ); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="post_id" value="<?php echo $security->escapeHTML(
                                                $post["id"],
                                            ); ?>">
                                            <button type="submit" class="btn btn-danger btn-small">
                                                🗑️ Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Backup Management -->
        <div class="section">
            <div class="section-header">
                <h2>💾 Backup & Restore</h2>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $security->escapeHTML(
                        $csrfToken,
                    ); ?>">
                    <input type="hidden" name="action" value="backup">
                    <button type="submit" class="btn btn-success">💾 Create Backup</button>
                </form>
            </div>

            <?php if (empty($backups)): ?>
                <div class="no-posts">
                    <p>No backups available. Create a backup to secure your data.</p>
                </div>
            <?php else: ?>
                <ul class="backup-list">
                    <?php foreach ($backups as $backup): ?>
                        <li class="backup-item">
                            <div class="backup-info">
                                <div class="backup-name">
                                    📦 <?php echo $security->escapeHTML(
                                        $backup["filename"],
                                    ); ?>
                                </div>
                                <div class="backup-meta">
                                    Size: <?php echo number_format(
                                        $backup["size"] / 1024,
                                        2,
                                    ); ?> KB |
                                    Date: <?php echo $security->escapeHTML(
                                        $backup["date"],
                                    ); ?>
                                </div>
                            </div>
                            <div class="backup-actions">
                                <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to restore this backup? Current data will be replaced.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo $security->escapeHTML(
                                        $csrfToken,
                                    ); ?>">
                                    <input type="hidden" name="action" value="restore">
                                    <input type="hidden" name="backup_file" value="<?php echo $security->escapeHTML(
                                        $backup["filename"],
                                    ); ?>">
                                    <button type="submit" class="btn btn-small">🔄 Restore</button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- System Information -->
        <div class="section">
            <div class="section-header">
                <h2>⚙️ System Information</h2>
            </div>
            <table style="width: 100%;">
                <tr>
                    <td style="padding: 8px; font-weight: 600;">CMS Version:</td>
                    <td style="padding: 8px;"><?php echo $security->escapeHTML(
                        SECURE_CMS_VERSION,
                    ); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px; font-weight: 600;">PHP Version:</td>
                    <td style="padding: 8px;"><?php echo $security->escapeHTML(
                        PHP_VERSION,
                    ); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px; font-weight: 600;">Data Storage:</td>
                    <td style="padding: 8px;">File-based (SQL-Free)</td>
                </tr>
                <tr>
                    <td style="padding: 8px; font-weight: 600;">Security Features:</td>
                    <td style="padding: 8px;">XSS Protection, CSRF Protection, Input Sanitization, Rate Limiting</td>
                </tr>
                <tr>
                    <td style="padding: 8px; font-weight: 600;">Session Timeout:</td>
                    <td style="padding: 8px;"><?php echo SESSION_LIFETIME /
                        60; ?> minutes</td>
                </tr>
                <tr>
                    <td style="padding: 8px; font-weight: 600;">Logged in as:</td>
                    <td style="padding: 8px;"><?php echo $security->escapeHTML(
                        $_SESSION["user"],
                    ); ?></td>
                </tr>
            </table>
        </div>
    </div>

    <script>
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Add confirmation to restore buttons
        document.querySelectorAll('form[onsubmit]').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm(this.getAttribute('onsubmit').replace('return ', '').replace(/'/g, ''))) {
                    e.preventDefault();
                }
            });
        });
    </script>
<?php include APP_ROOT . "/templates/footer.php"; ?>
</body>
</html>
