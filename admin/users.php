<?php
/**
 * Secure Blog CMS - User Management Page
 */

// Initialize security constant
define("SECURE_CMS_INIT", true);

// Load configuration
require_once __DIR__ . "/../includes/config.php";

// Load required classes
require_once __DIR__ . "/../includes/Security.php";
require_once __DIR__ . "/../includes/users.php";

// Initialize security and user management
$security = Security::getInstance();
$usersManager = Users::getInstance();

// Check authentication
if (!$security->isAuthenticated()) {
    header("Location: login.php");
    exit();
}

// Only admins can manage users
if (($_SESSION["role"] ?? "") !== "admin") {
    header("HTTP/1.1 403 Forbidden");
    echo '<!doctype html><meta charset="utf-8"><title>403 Forbidden</title><div style="font-family:system-ui;padding:20px"><h1>403 Forbidden</h1><p>Your account does not have permission to manage users.</p><p><a href="admin.php">Back to dashboard</a></p></div>';
    exit();
}

$message = "";
$messageType = "";

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrfToken = $_POST["csrf_token"] ?? "";
    $action = $_POST["action"] ?? "";

    if (!$security->validateCSRFToken($csrfToken, "users_form")) {
        $message = "Invalid security token. Please try again.";
        $messageType = "error";
        $security->logSecurityEvent(
            "CSRF validation failed on user management",
            $_SESSION["user"]);
    } else {
        switch ($action) {
            case "add_user":
                $username = $security->getPostData("username", "string", "");
                $password = $_POST["password"] ?? ""; // Don't sanitize password before hashing
                $role = $security->getPostData("role", "string", "");
                $result = $usersManager->addUser($username, $password, $role);
                $message = $result["message"];
                $messageType = $result["success"] ? "success" : "error";
                break;

            case "delete_user":
                $username = $security->getPostData("username", "string", "");
                if ($username === $_SESSION["user"]) {
                    $message = "You cannot delete your own account.";
                    $messageType = "error";
                } else {
                    $result = $usersManager->deleteUser($username);
                    $message = $result["message"];
                    $messageType = $result["success"] ? "success" : "error";
                }
                break;
        }
    }
}

// Generate new CSRF token for forms
$csrfToken = $security->generateCSRFToken("users_form");

// Get all users for display
$allUsers = $usersManager->getAllUsers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Manage Users - Secure Blog CMS</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: #f4f7f6; color: #333; margin: 0; line-height: 1.6; }
        .admin-header { background-color: #2c3e50; color: #fff; padding: 1rem 0; }
        .admin-header .container { display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto; padding: 0 1rem; }
        .admin-header h1 { margin: 0; font-size: 1.5rem; }
        .admin-header h1 .security-badge { font-size: 0.8rem; background: #27ae60; padding: 2px 6px; border-radius: 4px; vertical-align: middle; margin-left: 8px; }
        .admin-nav a { color: #fff; text-decoration: none; margin-left: 1rem; }
        .admin-nav a:hover { text-decoration: underline; }

        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
        .main-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; }
        .card { background-color: #fff; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); padding: 1.5rem; }
        h2 { border-bottom: 2px solid #ecf0f1; padding-bottom: 0.5rem; margin-top: 0; }

        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 0.5rem; }
        .form-group input[type="text"], .form-group input[type="password"], .form-group select { width: 100%; padding: 0.75rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { background-color: #3498db; color: #fff; padding: 0.75rem 1.5rem; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 1rem; }
        .btn:hover { background-color: #2980b9; }
        .btn-danger { background-color: #e74c3c; }
        .btn-danger:hover { background-color: #c0392b; }

        .users-table { width: 100%; border-collapse: collapse; }
        .users-table th, .users-table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #ecf0f1; }
        .users-table thead { background-color: #f9fafb; }
        .users-table .actions-cell { display: flex; gap: 0.5rem; }
        .users-table .actions-cell form { margin: 0; }
        .users-table .actions-cell .btn { padding: 0.3rem 0.6rem; font-size: 0.9rem; }
        .empty-state { text-align: center; color: #777; padding: 2rem; }

        .alert { padding: 1rem; margin-bottom: 1rem; border-radius: 4px; border: 1px solid transparent; }
        .alert-success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }

        @media (max-width: 900px) {
            .main-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container">
            <h1>👥 Manage Users <span class="security-badge">SECURED</span></h1>
            <div class="admin-nav">
                <a href="admin.php">← Back to Dashboard</a>
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

        <div class="main-grid">
            <div class="add-user-card">
                <div class="card">
                    <h2>Add New User</h2>
                    <form method="post" action="users.php">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="action" value="add_user">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" required pattern="[a-zA-Z0-9_-]{3,20}">
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                        </div>
                        <div class="form-group">
                            <label for="role">Role</label>
                            <select id="role" name="role" required>
                                <option value="author">Author</option>
                                <option value="editor">Editor</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        <button type="submit" class="btn">Add User</button>
                    </form>
                </div>
            </div>

            <div class="users-list-card">
                <div class="card">
                    <h2>Existing Users</h2>
                    <?php if (empty($allUsers)): ?>
                        <p class="empty-state">No users found.</p>
                    <?php else: ?>
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allUsers as $user): ?>
                                    <tr>
                                        <td><?php echo $security->escapeHTML(
                                            $user["username"]); ?></td>
                                        <td><?php echo $security->escapeHTML(
                                            ucfirst($user["role"])); ?></td>
                                        <td><?php echo date(
                                            "Y-m-d H:i",
                                            $user["created_at"]); ?></td>
                                        <td class="actions-cell">
                                            <!-- Edit button functionality to be added -->
                                            <!-- <a href="edit-user.php?username=<?php echo $security->escapeURL(
                                                $user["username"]); ?>" class="btn">Edit</a> -->
                                            <?php if (
                                                $_SESSION["user"] !==
                                                $user["username"]
                                            ): ?>
                                                <form method="post" action="users.php" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="username" value="<?php echo $security->escapeHTML(
                                                        $user["username"]); ?>">
                                                    <button type="submit" class="btn btn-danger">Delete</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php include APP_ROOT . '/templates/footer.php'; ?>
</body>
</html>
