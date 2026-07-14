<?php
/**
 * Secure Blog CMS - Manage Categories & Tags
 */

// Initialize security constant
define("SECURE_CMS_INIT", true);

// Load configuration
require_once __DIR__ . "/../includes/config.php";

// Load required classes
require_once __DIR__ . "/../includes/Security.php";
require_once __DIR__ . "/../includes/Storage.php";
require_once __DIR__ . "/../includes/categories.php";

// Initialize security, storage, and categories
$security = Security::getInstance();
$storage = Storage::getInstance();
$categoriesManager = Categories::getInstance();

// Check authentication
if (!$security->isAuthenticated()) {
    header("Location: login.php");
    exit();
}

$message = "";
$messageType = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrfToken = $_POST["csrf_token"] ?? "";
    $action = $_POST["action"] ?? "";

    if (!$security->validateCSRFToken($csrfToken, "taxonomy_form")) {
        $message = "Invalid security token. Please try again.";
        $messageType = "error";
        $security->logSecurityEvent(
            "CSRF validation failed on taxonomy management",
            $_SESSION["user"]);
    } else {
        if ($action === "add_category") {
            $categoryName = $security->getPostData(
                "category_name",
                "string",
                "");
            $result = $categoriesManager->addCategory($categoryName);
            $message = $result["message"];
            $messageType = $result["success"] ? "success" : "error";
        } elseif ($action === "add_tag") {
            $tagName = $security->getPostData("tag_name", "string", "");
            $result = $categoriesManager->addTag($tagName);
            $message = $result["message"];
            $messageType = $result["success"] ? "success" : "error";
        }
    }
}

// Generate CSRF token
$csrfToken = $security->generateCSRFToken("taxonomy_form");

// Get all categories and tags
$allCategories = $categoriesManager->getAllCategories();
$allTags = $categoriesManager->getAllTags();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Manage Categories & Tags - Secure Blog CMS</title>
    <style>
        /* Basic styles */
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

        /* Forms */
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 0.5rem; }
        .form-group input[type="text"] { width: 100%; padding: 0.75rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { background-color: #3498db; color: #fff; padding: 0.75rem 1.5rem; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 1rem; }
        .btn:hover { background-color: #2980b9; }

        /* Tables */
        .taxonomy-table { width: 100%; border-collapse: collapse; }
        .taxonomy-table th, .taxonomy-table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #ecf0f1; }
        .taxonomy-table thead { background-color: #f9fafb; }
        .taxonomy-table td code { background-color: #ecf0f1; padding: 2px 5px; border-radius: 3px; }
        .empty-state { text-align: center; color: #777; padding: 2rem; }

        /* Alerts */
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
            <h1>🏷️ Manage Categories & Tags <span class="security-badge">SECURED</span></h1>
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
            <div class="add-forms">
                <div class="card" style="margin-bottom: 2rem;">
                    <h2>Add New Category</h2>
                    <form method="post" action="categories.php">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="action" value="add_category">
                        <div class="form-group">
                            <label for="category_name">Category Name</label>
                            <input type="text" id="category_name" name="category_name" required>
                        </div>
                        <button type="submit" class="btn">Add Category</button>
                    </form>
                </div>

                <div class="card">
                    <h2>Add New Tag</h2>
                    <form method="post" action="categories.php">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="action" value="add_tag">
                        <div class="form-group">
                            <label for="tag_name">Tag Name</label>
                            <input type="text" id="tag_name" name="tag_name" required>
                        </div>
                        <button type="submit" class="btn">Add Tag</button>
                    </form>
                </div>
            </div>

            <div class="existing-taxonomies">
                <div class="card" style="margin-bottom: 2rem;">
                    <h2>Existing Categories</h2>
                    <?php if (empty($allCategories)): ?>
                        <p class="empty-state">No categories found.</p>
                    <?php else: ?>
                        <table class="taxonomy-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Slug</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allCategories as $category): ?>
                                    <tr>
                                        <td><?php echo $security->escapeHTML(
                                            $category["name"]); ?></td>
                                        <td><code><?php echo $security->escapeHTML(
                                            $category["slug"]); ?></code></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h2>Existing Tags</h2>
                     <?php if (empty($allTags)): ?>
                        <p class="empty-state">No tags found.</p>
                    <?php else: ?>
                        <table class="taxonomy-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Slug</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allTags as $tag): ?>
                                    <tr>
                                        <td><?php echo $security->escapeHTML(
                                            $tag["name"]); ?></td>
                                        <td><code><?php echo $security->escapeHTML(
                                            $tag["slug"]); ?></code></td>
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
