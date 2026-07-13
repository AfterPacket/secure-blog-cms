<?php
/**
 * Index Template
 * Used for both dynamic rendering and static site generation.
 *
 * Variables available:
 * @var array $posts List of posts to display
 * @var bool $is_static Whether this is being generated for a static site
 */

if (!defined('SECURE_CMS_INIT')) {
    exit('No direct script access allowed');
}

$security = Security::getInstance();
$site_name = defined('SITE_NAME') ? SITE_NAME : 'Secure Blog';
$site_description = defined('SITE_DESCRIPTION') ? SITE_DESCRIPTION : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo $security->escapeHTML($site_description); ?>">
    <title><?php echo $security->escapeHTML($site_name); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; padding: 20px; }
        header { background: #2c3e50; color: white; padding: 40px 0; margin-bottom: 40px; text-align: center; }
        header h1 { margin-bottom: 10px; font-size: 2.5rem; }
        header p { opacity: 0.9; font-size: 1.1rem; }
        .post { background: white; padding: 30px; margin-bottom: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .post-title { color: #2c3e50; margin-bottom: 10px; font-size: 1.8rem; }
        .post-title a { color: inherit; text-decoration: none; }
        .post-title a:hover { color: #3498db; }
        .post-meta { color: #7f8c8d; font-size: 0.9rem; margin-bottom: 15px; display: flex; gap: 15px; }
        .post-excerpt { color: #555; margin-bottom: 15px; }
        .read-more { display: inline-block; color: #3498db; text-decoration: none; font-weight: 600; }
        .read-more:hover { text-decoration: underline; }
        .no-posts { text-align: center; padding: 50px; background: white; border-radius: 8px; }
        footer { text-align: center; padding: 40px 0; color: #7f8c8d; font-size: 0.9rem; }
        .security-badge { display: inline-block; background: #27ae60; color: white; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: 600; vertical-align: middle; margin-left: 10px; }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1><?php echo $security->escapeHTML($site_name); ?> <span class="security-badge">🔒 SECURED</span></h1>
            <p><?php echo $security->escapeHTML($site_description); ?></p>
        </div>
    </header>

    <div class="container">
        <?php if (empty($posts)): ?>
            <div class="no-posts">
                <h2>No posts found</h2>
                <p>Check back later for new content.</p>
            </div>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <?php if ($post['status'] !== 'published') continue; ?>
                <article class="post">
                    <h2 class="post-title">
                        <a href="post.php?slug=<?php echo $security->escapeURL($post['slug']); ?>">
                            <?php echo $security->escapeHTML($post['title']); ?>
                        </a>
                    </h2>
                    <div class="post-meta">
                        <span>📅 <?php echo date('F j, Y', $post['created_at']); ?></span>
                        <span>✍️ <?php echo $security->escapeHTML($post['author']); ?></span>
                        <span>👁️ <?php echo number_format($post['views']); ?> views</span>
                    </div>
                    <div class="post-excerpt">
                        <?php echo $security->escapeHTML($post['excerpt']); ?>
                    </div>
                    <a href="post.php?slug=<?php echo $security->escapeURL($post['slug']); ?>" class="read-more">Read More →</a>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo $security->escapeHTML($site_name); ?></p>
            <p>Powered by Secure Blog CMS <?php echo $is_static ? '(Static Edition)' : ''; ?> 🔒</p>
        </div>
    </footer>
</body>
</html>
