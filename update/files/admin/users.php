<?php
/**
 * Secure Blog CMS - User Management Page
 */

// Initialize security constant
define("SECURE_CMS_INIT", true);

// Prevent caching — CSRF tokens must be fresh per request
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

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

// Role descriptions for display
$roleDescriptions = [
    "admin"   => "Full access — manage users, settings, resilience, comments, all posts",
    "editor"  => "Create, edit, and publish any post. Moderate comments.",
    "author"  => "Create and edit own posts. Cannot publish or manage others.",
];

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

            case "edit_user":
                $editUsername = $security->getPostData("edit_username", "string", "");
                $newRole = $security->getPostData("edit_role", "string", "");
                $newPassword = $_POST["edit_password"] ?? "";
                $adminPassword = $_POST["admin_password"] ?? "";

                // Verify admin password for confirmation
                $currentUser = $usersManager->getUser($_SESSION["user"]);
                if (!$currentUser || !password_verify($adminPassword, $currentUser["password_hash"])) {
                    $message = "Current admin password is incorrect.";
                    $messageType = "error";
                    $security->logSecurityEvent(
                        "Edit user failed — invalid admin password",
                        $_SESSION["user"]);
                    break;
                }

                // Cannot demote yourself
                if ($editUsername === $_SESSION["user"] && $newRole !== "admin") {
                    $message = "You cannot demote yourself from administrator.";
                    $messageType = "error";
                    break;
                }

                $editUser = $usersManager->getUser($editUsername);
                if (!$editUser) {
                    $message = "User not found.";
                    $messageType = "error";
                    break;
                }

                $updateData = [];
                if (!empty($newRole)) {
                    $updateData["role"] = $newRole;
                }
                if (!empty($newPassword)) {
                    $updateData["password"] = $newPassword;
                }
                $updateData["current_username"] = $_SESSION["user"];

                if (empty($updateData["role"]) && empty($updateData["password"])) {
                    $message = "No changes submitted.";
                    $messageType = "error";
                } else {
                    $result = $usersManager->updateUser($editUsername, $updateData);
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

// Prepare edit modal data if requested via GET
$editUser = null;
if (isset($_GET["edit"]) && is_string($_GET["edit"])) {
    $editUsername = $security->sanitizeInput($_GET["edit"], "string");
    if ($usersManager->userExists($editUsername)) {
        $editUser = $usersManager->getUser($editUsername);
    }
}
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
        .btn-secondary { background-color: #7f8c8d; color: #fff; padding: 0.4rem 0.8rem; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem; text-decoration: none; display: inline-block; }
        .btn-secondary:hover { background-color: #6c7a7a; }

        /* Password policy list */
        .password-policy { margin: 0.5rem 0; padding: 0; list-style: none; }
        .password-policy li { font-size: 0.85rem; color: #888; padding: 2px 0; padding-left: 1.2rem; position: relative; }
        .password-policy li::before { content: "\2713"; position: absolute; left: 0; color: #ccc; }
        .password-policy li.passed { color: #27ae60; }
        .password-policy li.passed::before { color: #27ae60; }
        .password-policy li.failed { color: #e74c3c; }

        /* Password strength meter */
        .strength-meter { margin: 0.5rem 0; }
        .strength-meter-bar { height: 6px; border-radius: 3px; background-color: #ecf0f1; overflow: hidden; }
        .strength-meter-fill { height: 100%; border-radius: 3px; transition: width 0.3s ease, background-color 0.3s ease; width: 0%; }
        .strength-meter-label { font-size: 0.85rem; margin-top: 4px; font-weight: bold; }
        .strength-weak .strength-meter-fill { background-color: #e74c3c; width: 25%; }
        .strength-weak .strength-meter-label { color: #e74c3c; }
        .strength-fair .strength-meter-fill { background-color: #e67e22; width: 50%; }
        .strength-fair .strength-meter-label { color: #e67e22; }
        .strength-good .strength-meter-fill { background-color: #3498db; width: 75%; }
        .strength-good .strength-meter-label { color: #3498db; }
        .strength-strong .strength-meter-fill { background-color: #27ae60; width: 100%; }
        .strength-strong .strength-meter-label { color: #27ae60; }

        /* Role descriptions */
        .role-option-description { font-size: 0.8rem; color: #7f8c8d; margin-top: 2px; }

        /* Role badges */
        .role-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; text-transform: uppercase; }
        .role-badge-admin { background-color: #e74c3c; color: #fff; }
        .role-badge-editor { background-color: #3498db; color: #fff; }
        .role-badge-author { background-color: #27ae60; color: #fff; }

        .users-table { width: 100%; border-collapse: collapse; }
        .users-table th, .users-table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #ecf0f1; }
        .users-table thead { background-color: #f9fafb; }
        .users-table .actions-cell { display: flex; gap: 0.5rem; align-items: center; }
        .users-table .actions-cell form { margin: 0; }
        .users-table .actions-cell .btn { padding: 0.3rem 0.6rem; font-size: 0.9rem; }
        .empty-state { text-align: center; color: #777; padding: 2rem; }

        .alert { padding: 1rem; margin-bottom: 1rem; border-radius: 4px; border: 1px solid transparent; }
        .alert-success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }

        /* Edit modal overlay */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .modal { background-color: #fff; border-radius: 8px; box-shadow: 0 8px 30px rgba(0,0,0,0.3); padding: 2rem; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
        .modal h2 { margin-top: 0; }
        .modal .form-group { margin-bottom: 1rem; }
        .modal-actions { display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1.5rem; }

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
            <div class="alert alert-<?php echo $security->escapeHTML($messageType); ?>">
                <?php echo $security->escapeHTML($message); ?>
            </div>
        <?php endif; ?>

        <div class="main-grid">
            <div class="add-user-card">
                <div class="card">
                    <h2>Add New User</h2>
                    <form method="post" action="users.php" id="add-user-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="action" value="add_user">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" required pattern="[a-zA-Z0-9_-]{3,20}">
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" autocomplete="new-password" oninput="updateStrengthMeter(this.value)">
                            <ul class="password-policy" id="password-policy">
                                <li id="policy-length" data-regex=".{12,}">Minimum 12 characters</li>
                                <li id="policy-uppercase" data-regex="[A-Z]">At least one uppercase letter</li>
                                <li id="policy-lowercase" data-regex="[a-z]">At least one lowercase letter</li>
                                <li id="policy-digit" data-regex="[0-9]">At least one digit</li>
                                <li id="policy-special" data-regex="[^a-zA-Z0-9]">At least one special character</li>
                            </ul>
                            <div class="strength-meter" id="strength-meter">
                                <div class="strength-meter-bar">
                                    <div class="strength-meter-fill"></div>
                                </div>
                                <div class="strength-meter-label"></div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="role">Role</label>
                            <select id="role" name="role" required>
                                <option value="author">Author</option>
                                <option value="editor">Editor</option>
                                <option value="admin">Administrator</option>
                            </select>
                            <div class="role-option-description" id="role-description">
                                <?php echo $security->escapeHTML($roleDescriptions["author"]); ?>
                            </div>
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
                                    <th>Created</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allUsers as $user): ?>
                                    <tr>
                                        <td><?php echo $security->escapeHTML($user["username"]); ?></td>
                                        <td>
                                            <?php
                                            $roleClass = "role-badge-" . $user["role"];
                                            ?>
                                            <span class="role-badge <?php echo $roleClass; ?>">
                                                <?php echo $security->escapeHTML(ucfirst($user["role"])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date("Y-m-d H:i", $user["created_at"]); ?></td>
                                        <td><?php echo isset($user["last_login"]) && $user["last_login"] ? date("Y-m-d H:i", $user["last_login"]) : "N/A"; ?></td>
                                        <td class="actions-cell">
                                            <button type="button" class="btn btn-secondary" onclick="openEditModal(<?php echo htmlspecialchars(json_encode([
                                                'username' => $user['username'],
                                                'role' => $user['role']
                                            ]), ENT_QUOTES, 'UTF-8'); ?>)">Edit</button>
                                            <?php if ($_SESSION["user"] !== $user["username"]): ?>
                                                <form method="post" action="users.php" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="username" value="<?php echo $security->escapeHTML($user["username"]); ?>">
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

    <!-- Edit User Modal -->
    <div class="modal-overlay" id="edit-modal">
        <div class="modal">
            <h2>Edit User: <span id="edit-username-display"></span></h2>
            <form method="post" action="users.php" id="edit-user-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="edit_username" id="edit-username-input" value="">

                <div class="form-group">
                    <label for="edit-role">Role</label>
                    <select id="edit-role" name="edit_role">
                        <option value="author">Author</option>
                        <option value="editor">Editor</option>
                        <option value="admin">Administrator</option>
                    </select>
                    <div class="role-option-description" id="edit-role-description"></div>
                </div>

                <div class="form-group">
                    <label for="edit-password">New Password</label>
                    <input type="password" id="edit-password" name="edit_password" placeholder="Leave blank to keep current password" minlength="12" autocomplete="new-password">
                </div>

                <div class="form-group">
                    <label for="admin-password">Your Admin Password</label>
                    <input type="password" id="admin-password" name="admin_password" required autocomplete="current-password">
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Role descriptions map
        var roleDescriptions = <?php echo json_encode($roleDescriptions); ?>;

        // Update role description on the add-user form
        var roleSelect = document.getElementById('role');
        if (roleSelect) {
            roleSelect.addEventListener('change', function() {
                var desc = roleDescriptions[this.value] || '';
                document.getElementById('role-description').textContent = desc;
            });
        }

        // Password strength meter
        function updateStrengthMeter(password) {
            var checks = {
                length: password.length >= 12,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                digit: /[0-9]/.test(password),
                special: /[^a-zA-Z0-9]/.test(password)
            };

            // Update policy list items
            var policyItems = document.querySelectorAll('#password-policy li');
            policyItems.forEach(function(item) {
                var regex = new RegExp(item.getAttribute('data-regex'));
                if (regex.test(password)) {
                    item.classList.add('passed');
                    item.classList.remove('failed');
                } else {
                    item.classList.remove('passed');
                    if (password.length > 0) {
                        item.classList.add('failed');
                    } else {
                        item.classList.remove('failed');
                    }
                }
            });

            // Calculate strength
            var passed = 0;
            for (var key in checks) {
                if (checks[key]) passed++;
            }

            var meter = document.getElementById('strength-meter');
            meter.className = 'strength-meter';
            var label = meter.querySelector('.strength-meter-label');

            if (password.length === 0) {
                label.textContent = '';
            } else if (passed <= 1) {
                meter.classList.add('strength-weak');
                label.textContent = 'Weak';
            } else if (passed <= 2) {
                meter.classList.add('strength-fair');
                label.textContent = 'Fair';
            } else if (passed <= 3) {
                meter.classList.add('strength-good');
                label.textContent = 'Good';
            } else if (passed >= 4) {
                meter.classList.add('strength-strong');
                label.textContent = 'Strong';
            }
        }

        // Edit modal functions
        function openEditModal(userData) {
            document.getElementById('edit-username-display').textContent = userData.username;
            document.getElementById('edit-username-input').value = userData.username;
            document.getElementById('edit-role').value = userData.role;
            document.getElementById('edit-password').value = '';
            document.getElementById('admin-password').value = '';
            updateEditRoleDescription();
            document.getElementById('edit-modal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('edit-modal').classList.remove('active');
        }

        // Update role description in edit modal
        var editRoleSelect = document.getElementById('edit-role');
        if (editRoleSelect) {
            editRoleSelect.addEventListener('change', updateEditRoleDescription);
        }

        function updateEditRoleDescription() {
            var role = document.getElementById('edit-role').value;
            var desc = roleDescriptions[role] || '';
            document.getElementById('edit-role-description').textContent = desc;
        }

        // Validate edit password if provided
        var editForm = document.getElementById('edit-user-form');
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                var editPassword = document.getElementById('edit-password').value;
                if (editPassword.length > 0) {
                    var errors = [];
                    if (editPassword.length < 12) errors.push('at least 12 characters');
                    if (!/[A-Z]/.test(editPassword)) errors.push('an uppercase letter');
                    if (!/[a-z]/.test(editPassword)) errors.push('a lowercase letter');
                    if (!/[0-9]/.test(editPassword)) errors.push('a digit');
                    if (!/[^a-zA-Z0-9]/.test(editPassword)) errors.push('a special character');
                    if (errors.length > 0) {
                        e.preventDefault();
                        alert('New password must contain: ' + errors.join(', ') + '.');
                        return false;
                    }
                }
            });
        }

        // Close modal on overlay click
        document.getElementById('edit-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEditModal();
            }
        });

        // Auto-open edit modal if edit parameter present
        <?php if ($editUser !== null): ?>
        (function() {
            var userData = {
                username: <?php echo json_encode($editUser['username']); ?>,
                role: <?php echo json_encode($editUser['role']); ?>
            };
            openEditModal(userData);
        })();
        <?php endif; ?>
    </script>
<?php include APP_ROOT . '/templates/footer.php'; ?>
</body>
</html>