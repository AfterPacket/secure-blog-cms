<?php
define('SECURE_CMS_INIT', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Security.php';
require_once __DIR__ . '/../includes/Updater.php';

$security = Security::getInstance();
if (!$security->isAuthenticated()) {
    header('Location: ' . cms_path('admin/login.php'));
    exit();
}

$manifestUrl = 'https://raw.githubusercontent.com/AfterPacket/secure-blog-cms/main/update/manifest.json';
$updater = new Updater($manifestUrl);
$message = '';

try {
    $manifest = $updater->check();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $updater->apply($manifest);
        $message = 'Update applied successfully';
    }
} catch (Exception $e) {
    $message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head><title>Updates</title></head>
<body>
<h1>CMS Updates</h1>
<p><?php echo htmlspecialchars($message); ?></p>
<form method="post">
<button type="submit">Apply Update</button>
</form>
</body>
</html>
