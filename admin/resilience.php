<?php
/**
 * Resilience & Anti-Takedown Admin Page
 * Provides tools for static site generation and decentralization management.
 * @version 1.2.6.1 - Cache Reset
 */

define("SECURE_CMS_INIT", true);
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/Security.php";
require_once __DIR__ . "/../includes/Storage.php";
require_once __DIR__ . "/../includes/Resilience.php";

$security = Security::getInstance();
$storage = Storage::getInstance();
$resilience = Resilience::getInstance();

// Check authentication
if (!$security->isAuthenticated()) {
    header("Location: login.php");
    exit();
}

// Defensive check: handle cases where Security class might be cached without isAdmin() method
$isAdmin = method_exists($security, "isAdmin")
    ? $security->isAdmin()
    : isset($_SESSION["role"]) && $_SESSION["role"] === "admin";

if (!$isAdmin) {
    die("Access denied. Admin privileges required.");
}

$success = "";
$error = "";

// Handle static site generation
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["generate_static"])) {
    if (!$security->validateCSRFToken($_POST["csrf_token"] ?? "")) {
        $error = "Security token validation failed. Please try again.";
    } else {
        $result = $resilience->generateStaticSite();
        if ($result["success"]) {
            $success = $result["message"];
        } else {
            $error = $result["message"];
        }
    }
}

// Handle delete export
if (isset($_GET["delete"]) && !empty($_GET["delete"])) {
    if (!$security->validateCSRFToken($_GET["csrf_token"] ?? "")) {
        $error = "Security token validation failed.";
    } else {
        $exportName = basename($_GET["delete"]);
        $exportDir = DATA_DIR . "/exports/" . $exportName;
        $zipFile = DATA_DIR . "/exports/" . $exportName . ".zip";

        if (is_dir($exportDir)) {
            // Helper to delete dir recursively
            $deleteDirFunc = function ($dirPath) use (&$deleteDirFunc) {
                if (!is_dir($dirPath)) {
                    return;
                }
                $files = array_diff(scandir($dirPath), [".", ".."]);
                foreach ($files as $file) {
                    is_dir("$dirPath/$file")
                        ? $deleteDirFunc("$dirPath/$file")
                        : unlink("$dirPath/$file");
                }
                return rmdir($dirPath);
            };
            $deleteDirFunc($exportDir);
        }
        if (is_file($zipFile)) {
            unlink($zipFile);
        }
        $success = "Export bundle deleted successfully.";
    }
}

// Get existing exports
$exports = [];
$exportBaseDir = DATA_DIR . "/exports";
if (is_dir($exportBaseDir)) {
    $dirs = array_filter(glob($exportBaseDir . "/*"), "is_dir");
    foreach ($dirs as $dir) {
        $name = basename($dir);
        $zipName = $name . ".zip";
        $hasZip = is_file($exportBaseDir . "/" . $zipName);
        $timestamp = str_replace("static_", "", $name);

        $exports[] = [
            "name" => $name,
            "timestamp" => $timestamp,
            "has_zip" => $hasZip,
            "zip_name" => $zipName,
        ];
    }
}
// Sort by newest first
usort($exports, function ($a, $b) {
    return strcmp($b["timestamp"], $a["timestamp"]);
});

$csrf_token = $security->generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resilience & Anti-Takedown - Admin Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; background: #f5f5f5; }
        .admin-header { background: #2c3e50; color: white; padding: 20px 0; margin-bottom: 30px; }
        .container { max-width: 1000px; margin: 0 auto; padding: 0 20px; }
        .admin-header .container { display: flex; justify-content: space-between; align-items: center; }
        .admin-nav a { color: white; text-decoration: none; margin-left: 20px; font-size: 14px; }
        .admin-nav a:hover { text-decoration: underline; }
        .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 30px; }
        h1, h2, h3 { color: #2c3e50; margin-bottom: 20px; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .btn { display: inline-block; padding: 10px 20px; border-radius: 4px; border: none; cursor: pointer; font-size: 16px; font-weight: 600; text-decoration: none; transition: background 0.3s; text-align: center; }
        .btn-primary { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-danger:hover { background: #c0392b; }
        .btn-small { padding: 5px 12px; font-size: 13px; margin-right: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { text-align: left; padding: 15px; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; color: #7f8c8d; text-transform: uppercase; font-size: 12px; }
        .info-box { background: #e8f4fd; border-left: 4px solid #3498db; padding: 20px; margin-bottom: 25px; border-radius: 0 4px 4px 0; }
        .status-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; background: #eee; }
        .status-badge.active { background: #27ae60; color: white; }
        .guide-section p { margin-bottom: 15px; color: #555; }
        .guide-section ul { margin-left: 20px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container">
            <h1>🛡️ Resilience Center</h1>
            <div class="admin-nav">
                <a href="admin.php">🔙 Dashboard</a>
                <a href="settings.php">⚙️ Settings</a>
                <a href="logout.php">🚪 Logout</a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $security->escapeHTML(
                $success,
            ); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $security->escapeHTML(
                $error,
            ); ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>Static Site Generation</h2>
            <div class="info-box">
                <p>Convert your dynamic blog into a collection of static HTML files. This allows you to host your blog on "anti-takedown" platforms or decentralized storage where dynamic PHP isn't supported.</p>
                <p style="margin-top:10px; font-size: 0.9em; color: #666;"><strong>Note:</strong> Static versions do not support dynamic features like comments, search, or private post passwords.</p>
            </div>

            <form method="POST" onsubmit="return confirm('Generate a new static bundle? This may take a few seconds.')">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <button type="submit" name="generate_static" class="btn btn-primary">🚀 Generate Static Site Bundle</button>
            </form>
        </div>

        <div class="card">
            <h2>Generated Export Bundles</h2>
            <?php if (empty($exports)): ?>
                <p style="color: #7f8c8d; font-style: italic;">No static bundles generated yet.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Generation Date</th>
                                <th>Format</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exports as $export): ?>
                                <tr>
                                    <td><strong><?php echo $security->escapeHTML(
                                        $export["timestamp"],
                                    ); ?></strong></td>
                                    <td>
                                        <span class="status-badge <?php echo $export[
                                            "has_zip"
                                        ]
                                            ? "active"
                                            : ""; ?>">
                                            <?php echo $export["has_zip"]
                                                ? "ZIP ARCHIVE"
                                                : "DIRECTORY"; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($export["has_zip"]): ?>
                                            <a href="../data/exports/<?php echo urlencode(
                                                $export["zip_name"],
                                            ); ?>" class="btn btn-primary btn-small" download>⬇️ Download ZIP</a>
                                        <?php endif; ?>
                                        <a href="?delete=<?php echo urlencode(
                                            $export["name"],
                                        ); ?>&csrf_token=<?php echo $csrf_token; ?>"
                                           class="btn btn-danger btn-small"
                                           onclick="return confirm('Permanently delete this bundle and its ZIP?')">🗑️ Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="card guide-section">
            <h2>Decentralization Guide</h2>
            <h3>1. IPFS (InterPlanetary File System)</h3>
            <p>Upload your static bundle to an IPFS pinning service (like Pinata). This gives your blog a unique CID (Content Identifier) that cannot be "taken down" in the traditional sense as long as at least one node is pinning it.</p>

            <h3>2. Tor Hidden Service (.onion)</h3>
            <p>Because this CMS is SQL-free, you can host the dynamic version directly on a Tor hidden service with minimal configuration. This hides your server's physical location.</p>

            <h3>3. P2P Networks (ZeroNet / Freenet)</h3>
            <p>Static files can be easily imported into ZeroNet sites, allowing users to serve the content to each other without a central server.</p>
        </div>
    </div>
</body>
</html>
