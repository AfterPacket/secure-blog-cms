<?php
/**
 * Post Template
 * Used for both dynamic rendering and static site generation.
 *
 * Variables available:
 * @var array $current_post The post data array
 * @var bool $is_static Whether this is being generated for a static site
 */

if (!defined('SECURE_CMS_INIT')) {
    exit('No direct script access allowed');
}

$security = Security::getInstance();
$site_name = defined('SITE_NAME') ? SITE_NAME : 'Secure Blog';
$post = isset($current_post) ? $current_post : null;

if (!$post) {
    exit('Post data missing');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo $security->escapeHTML($post['meta_description'] ?? $post['excerpt']); ?>">
    <title><?php echo $security->escapeHTML($post['title']); ?> - <?php echo $security->escapeHTML($site_name); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        header { background: #2c3e50; color: white; padding: 20px 0; margin-bottom: 40px; text-align: center; }
        header h1 { font-size: 1.5rem; }
        header h1 a { color: white; text-decoration: none; }
        .post-container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .post-header { margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px; }
        .post-title { color: #2c3e50; font-size: 2.5rem; line-height: 1.2; margin-bottom: 15px; }
        .post-meta { color: #7f8c8d; font-size: 0.9rem; display: flex; gap: 20px; flex-wrap: wrap; }
        .post-content { line-height: 1.8; color: #2c3e50; font-size: 1.1rem; }
        .post-content p { margin-bottom: 20px; }
        .post-content h1, .post-content h2, .post-content h3 { margin: 30px 0 15px; color: #2c3e50; }
        .post-content img { max-width: 100%; height: auto; border-radius: 4px; display: block; margin: 20px auto; }
        .post-content blockquote { border-left: 4px solid #3498db; padding-left: 20px; font-style: italic; color: #555; margin: 20px 0; }
        .post-content code { background: #f8f8f8; padding: 2px 5px; border-radius: 3px; font-family: monospace; }
        .post-content pre { background: #2c3e50; color: #ecf0f1; padding: 20px; border-radius: 6px; overflow-x: auto; margin-bottom: 20px; }
        .post-content ul, .post-content ol { margin-bottom: 20px; margin-left: 25px; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #3498db; text-decoration: none; font-weight: 600; }
        .back-link:hover { text-decoration: underline; }
        footer { text-align: center; padding: 40px 0; color: #7f8c8d; font-size: 0.9rem; }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1><a href="<?php echo $is_static ? '../index.html' : 'index.php'; ?>"><?php echo $security->escapeHTML($site_name); ?></a></h1>
        </div>
    </header>

    <div class="container">
        <a href="<?php echo $is_static ? '../index.html' : 'index.php'; ?>" class="back-link">← Back to Home</a>

        <article class="post-container">
            <header class="post-header">
                <h1 class="post-title"><?php echo $security->escapeHTML($post['title']); ?></h1>
                <div class="post-meta">
                    <span>📅 <?php echo date('F j, Y', $post['created_at']); ?></span>
                    <span>✍️ <?php echo $security->escapeHTML($post['author']); ?></span>
                    <span>👁️ <?php echo number_format($post['views']); ?> views</span>
                </div>
            </header>

            <div class="post-content">
                <?php
                // In a static site, we assume the content is already sanitized HTML from the editor
                // In dynamic mode, Storage/Security handles this, but for the template we output the content
                echo $post['content'];
                ?>
            </div>
        </article>
    </div>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo $security->escapeHTML($site_name); ?></p>
            <p>Powered by Secure Blog CMS <?php echo $is_static ? '(Static Edition)' : ''; ?> 🔒</p>
        </div>
    </footer>
</body>
</html>
