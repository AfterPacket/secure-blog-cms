<?php
/**
 * Secure Blog CMS - Single Post View
 * Display individual blog post with full content
 */

// Initialize security constant
define("SECURE_CMS_INIT", true);

// Load configuration
require_once __DIR__ . "/includes/config.php";

// Load required classes
require_once __DIR__ . "/includes/Security.php";
require_once __DIR__ . "/includes/Storage.php";
require_once __DIR__ . "/includes/notifications.php";

// Check if installed
if (
    !file_exists(__DIR__ . "/data/installed.lock") &&
    file_exists(__DIR__ . "/install/index.php")
) {
    header("Location: install/index.php");
    exit();
}
require_once __DIR__ . "/includes/comments.php";
require_once __DIR__ . "/includes/Captcha.php";

// Initialize security and storage
$security = Security::getInstance();
$storage = Storage::getInstance();
$commentsManager = Comments::getInstance();

// Get post slug from URL
$slug = $security->getGetData("slug", "slug", "");

if (empty($slug)) {
    header("HTTP/1.1 404 Not Found");
    $post = null;
} else {
    // Get post by slug and increment views
    $post = $storage->getPostBySlug($slug, true);

    if (!$post) {
        header("HTTP/1.1 404 Not Found");
    } elseif (
        $post["status"] !== "published" &&
        !$security->isAuthenticated()
    ) {
        // Don't show draft posts to non-authenticated users
        header("HTTP/1.1 404 Not Found");
        $post = null;
    }
}

$commentMessage = "";
$commentMessageType = "";

// Handle comment form submission
if (
    $post &&
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["submit_comment"])
) {
    $csrfToken = $_POST["csrf_token"] ?? "";

    if (!$security->validateCSRFToken($csrfToken, "comment_form")) {
        $commentMessage = "Invalid security token. Please try again.";
        $commentMessageType = "error";
    } else {
		// hCaptcha (public comments). Only enforced when configured.
		if (defined('HCAPTCHA_ENABLED') && HCAPTCHA_ENABLED && !$security->isAuthenticated()) {
			$hc = $_POST['h-captcha-response'] ?? '';
			if (!Captcha::verify((string)$hc)) {
				$commentMessage = "Captcha verification failed. Please try again.";
				$commentMessageType = "error";
				// Stop here; do not attempt to save comment.
				goto comment_done;
			}
		}
        $commentData = [
            "author_name" => $security->getPostData(
                "author_name",
                "string",
                ""),
            "content" => $security->getPostData("content", "string", ""),
        ];

        $result = $commentsManager->addComment($post["id"], $commentData);
        $commentMessage = $result["message"];
        $commentMessageType = $result["success"] ? "success" : "error";
	}
	comment_done:
}

// Generate CSRF token for comment form
$commentCsrfToken = $security->generateCSRFToken("comment_form");

// Get approved comments
if ($post) {
    $comments = $commentsManager->getCommentsByPostId($post["id"], "approved");
} else {
    $comments = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($post): ?>
        <meta name="description" content="<?php echo $security->escapeHTML(
            $post["meta_description"] ?: $post["excerpt"]); ?>">
        <meta name="keywords" content="<?php echo $security->escapeHTML(
            $post["meta_keywords"] ?? ""); ?>">
        <meta name="author" content="<?php echo $security->escapeHTML(
            $post["author"]); ?>">
        <meta property="og:title" content="<?php echo $security->escapeHTML(
            $post["title"]); ?>">
        <meta property="og:description" content="<?php echo $security->escapeHTML(
            $post["excerpt"]); ?>">
        <meta property="og:type" content="article">
        <meta name="robots" content="index, follow">
        <title><?php echo $security->escapeHTML(
            $post["title"]); ?> - <?php echo $security->escapeHTML(SITE_NAME); ?></title>
    <?php else: ?>
        <meta name="robots" content="noindex, nofollow">
        <title>Post Not Found - <?php echo $security->escapeHTML(
            SITE_NAME); ?></title>
    <?php endif; ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            background: #2c3e50;
            color: white;
            padding: 30px 0;
            margin-bottom: 40px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        header h1 {
            margin-bottom: 10px;
            font-size: 28px;
        }

        header h1 a {
            color: white;
            text-decoration: none;
        }

        header h1 a:hover {
            opacity: 0.8;
        }

        .back-link {
            display: inline-block;
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            margin-top: 10px;
            transition: color 0.3s;
        }

        .back-link:hover {
            color: white;
        }

        .admin-link {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 4px;
            color: white;
            text-decoration: none;
            transition: background 0.3s;
        }

        .admin-link:hover {
            background: rgba(255,255,255,0.3);
        }

        .post-container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .post-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #ecf0f1;
        }

        .post-title {
            color: #2c3e50;
            font-size: 36px;
            margin-bottom: 15px;
            line-height: 1.3;
        }

        .post-meta {
            color: #7f8c8d;
            font-size: 14px;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .post-meta span {
            display: inline-flex;
            align-items: center;
        }

        .post-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .post-status.draft {
            background: #f39c12;
            color: white;
        }

        .post-status.published {
            background: #27ae60;
            color: white;
        }

        .post-content {
            color: #444;
            font-size: 18px;
            line-height: 1.8;
        }

        .post-content h1 {
            font-size: 32px;
            margin-top: 30px;
            margin-bottom: 15px;
            color: #2c3e50;
            line-height: 1.3;
        }

        .post-content h2 {
            font-size: 28px;
            margin-top: 25px;
            margin-bottom: 12px;
            color: #2c3e50;
            line-height: 1.3;
        }

        .post-content h3 {
            font-size: 24px;
            margin-top: 20px;
            margin-bottom: 10px;
            color: #2c3e50;
            line-height: 1.3;
        }

        .post-content h4 {
            font-size: 20px;
            margin-top: 18px;
            margin-bottom: 8px;
            color: #2c3e50;
            line-height: 1.3;
        }

        .post-content p {
            margin-bottom: 20px;
        }

        .post-content ul,
        .post-content ol {
            margin-left: 30px;
            margin-bottom: 20px;
        }

        .post-content li {
            margin-bottom: 8px;
        }

        .post-content a {
            color: #3498db;
            text-decoration: none;
            border-bottom: 1px solid transparent;
            transition: border-color 0.3s;
        }

        .post-content a:hover {
            border-bottom-color: #3498db;
        }

        .post-content blockquote {
            border-left: 4px solid #3498db;
            padding-left: 20px;
            margin: 20px 0;
            font-style: italic;
            color: #555;
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 4px;
        }

        .post-content code {
            background: #f4f4f4;
            padding: 3px 8px;
            border-radius: 3px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 16px;
            color: #e74c3c;
        }

        .post-content pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 20px;
            border-radius: 6px;
            overflow-x: auto;
            margin: 20px 0;
            line-height: 1.6;
        }

        .post-content pre code {
            background: transparent;
            padding: 0;
            color: #ecf0f1;
            font-size: 14px;
        }

        .post-content strong {
            font-weight: 700;
            color: #2c3e50;
        }

        .post-content em {
            font-style: italic;
        }

        .post-footer {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #ecf0f1;
        }

        .post-footer-meta {
            color: #7f8c8d;
            font-size: 14px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }

        .edit-link {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            margin-top: 20px;
            transition: background 0.3s;
        }

        .edit-link:hover {
            background: #2980b9;
        }

        .not-found {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .not-found h1 {
            color: #e74c3c;
            font-size: 72px;
            margin-bottom: 20px;
        }

        .not-found h2 {
            color: #7f8c8d;
            margin-bottom: 20px;
        }

        .not-found p {
            color: #95a5a6;
            margin-bottom: 30px;
        }

        .not-found a {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 12px 30px;
            border-radius: 4px;
            text-decoration: none;
            transition: background 0.3s;
        }

        .not-found a:hover {
            background: #2980b9;
        }

        /* Comments Section */
        .comments-section {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 40px;
        }
        .comments-section h2 {
            color: #2c3e50;
            border-bottom: 2px solid #ecf0f1;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }
        .comment {
            border-bottom: 1px solid #ecf0f1;
            padding: 20px 0;
        }
        .comment:last-child {
            border-bottom: none;
        }
        .comment-author {
            font-weight: bold;
            color: #3498db;
            margin-bottom: 5px;
        }
        .comment-date {
            font-size: 13px;
            color: #95a5a6;
            margin-bottom: 10px;
        }
        .comment-content {
            line-height: 1.7;
        }
        .no-comments {
            color: #7f8c8d;
            text-align: center;
            padding: 20px 0;
        }

        /* Comment Form */
        .comment-form {
            margin-top: 30px;
        }
        .comment-form h3 {
            color: #2c3e50;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border-radius: 4px;
            border: 1px solid #ccc;
            font-family: inherit;
            font-size: 16px;
        }
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        .comment-form .btn {
            background: #27ae60;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s;
        }
        .comment-form .btn:hover {
            background: #229954;
        }
        .comment-alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
            border: 1px solid transparent;
        }
        .comment-alert.success {
            background-color: #d4edda; color: #155724; border-color: #c3e6cb;
        }
        .comment-alert.error {
            background-color: #f8d7da; color: #721c24; border-color: #f5c6cb;
        }

        /* Comments Section */
        .comments-section {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 40px;
        }
        .comments-section h2 {
            color: #2c3e50;
            border-bottom: 2px solid #ecf0f1;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }
        .comment {
            border-bottom: 1px solid #ecf0f1;
            padding: 20px 0;
        }
        .comment:last-child {
            border-bottom: none;
        }
        .comment-author {
            font-weight: bold;
            color: #3498db;
            margin-bottom: 5px;
        }
        .comment-date {
            font-size: 13px;
            color: #95a5a6;
            margin-bottom: 10px;
        }
        .comment-content {
            line-height: 1.7;
        }
        .no-comments {
            color: #7f8c8d;
            text-align: center;
            padding: 20px 0;
        }

        /* Comment Form */
        .comment-form {
            margin-top: 30px;
        }
        .comment-form h3 {
            color: #2c3e50;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border-radius: 4px;
            border: 1px solid #ccc;
            font-family: inherit;
            font-size: 16px;
        }
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        .comment-form .btn {
            background: #27ae60;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s;
        }
        .comment-form .btn:hover {
            background: #229954;
        }
        .comment-alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
            border: 1px solid transparent;
        }
        .comment-alert.success {
            background-color: #d4edda; color: #155724; border-color: #c3e6cb;
        }
        .comment-alert.error {
            background-color: #f8d7da; color: #721c24; border-color: #f5c6cb;
        }

        footer {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
            margin-top: 40px;
        }

        .security-badge {
            display: inline-block;
            background: #27ae60;
            color: white;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .post-container {
                padding: 25px;
            }

            .post-title {
                font-size: 28px;
            }

            .post-content {
                font-size: 16px;
            }

            .post-content h1 {
                font-size: 26px;
            }

            .post-content h2 {
                font-size: 22px;
            }

            .post-content h3 {
                font-size: 20px;
            }

            header h1 {
                font-size: 24px;
            }

            .admin-link {
                position: static;
                display: inline-block;
                margin-top: 15px;
            }

            .post-footer-meta {
                flex-direction: column;
            }

            .not-found h1 {
                font-size: 48px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container" style="position: relative;">
            <h1>
                <a href="index.php"><?php echo $security->escapeHTML(
                    SITE_NAME); ?></a>
                <span class="security-badge">🔒 SECURED</span>
            </h1>
            <a href="index.php" class="back-link">← Back to Blog</a>
            <?php if ($security->isAuthenticated()): ?>
                <a href="<?php echo cms_path('admin/admin.php'); ?>" class="admin-link">🔑 Admin Panel</a>
            <?php endif; ?>
        </div>
    </header>

    <div class="container">
        <?php if ($post): ?>
            <article class="post-container">
                <div class="post-header">
                    <h1 class="post-title"><?php echo $security->escapeHTML(
                        $post["title"]); ?></h1>

                    <div class="post-meta">
                        <span>📅 Published: <?php echo date(
                            "F j, Y",
                            $post["created_at"]); ?></span>
                        <?php if ($post["updated_at"] > $post["created_at"]): ?>
                            <span>✏️ Updated: <?php echo date(
                                "F j, Y",
                                $post["updated_at"]); ?></span>
                        <?php endif; ?>
                        <span>✍️ Author: <?php echo $security->escapeHTML(
                            $post["author"]); ?></span>
                        <span>👁️ <?php echo number_format(
                            $post["views"]); ?> views</span>
                        <?php if ($security->isAuthenticated()): ?>
                            <span class="post-status <?php echo $security->escapeHTML(
                                $post["status"]); ?>">
                                <?php echo $security->escapeHTML(
                                    $post["status"]); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="post-content">
                    <?php // Output sanitized HTML content

            // Content is already sanitized during storage, but we double-check
                    echo $post["content"]; ?>
                </div>

                <div class="post-footer">
                    <div class="post-footer-meta">
                        <div>
                            <strong>Post ID:</strong> <?php echo $security->escapeHTML(
                                $post["id"]); ?>
                        </div>
                        <div>
                            <strong>Slug:</strong> <?php echo $security->escapeHTML(
                                $post["slug"]); ?>
                        </div>
                    </div>

                    <?php if ($security->isAuthenticated()): ?>
                        <a href="admin/edit-post.php?id=<?php echo $security->escapeURL(
                            $post["id"]); ?>" class="edit-link">
                            ✏️ Edit This Post
                        </a>
                    <?php endif; ?>
                </div>
            </article>

            <!-- Comments Section -->
            <section class="comments-section">
                <h2>Comments (<?php echo count($comments); ?>)</h2>
                <div id="comments">
                    <?php if (empty($comments)): ?>
                        <p class="no-comments">Be the first to leave a comment!</p>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
                            <div class="comment">
                                <p class="comment-author"><?php echo $security->escapeHTML(
                                    $comment["author_name"]); ?></p>
                                <p class="comment-date"><?php echo date(
                                    'F j, Y \a\t H:i',
                                    $comment["created_at"]); ?></p>
                                <div class="comment-content">
                                    <p><?php echo nl2br(
                                        $security->escapeHTML(
                                            $comment["content"])); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="comment-form" id="comment-form">
                    <h3>Leave a Comment</h3>

                    <?php if ($commentMessage): ?>
                        <div class="comment-alert <?php echo $commentMessageType; ?>">
                            <?php echo $security->escapeHTML(
                                $commentMessage); ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="post.php?slug=<?php echo $security->escapeURL(
                        $post["slug"]); ?>#comment-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $commentCsrfToken; ?>">
                        <div class="form-group">
                            <label for="author_name">Name *</label>
                            <input type="text" id="author_name" name="author_name" required>
                        </div>
                        <div class="form-group">
                            <label for="content">Comment *</label>
                            <textarea id="content" name="content" required></textarea>
                        </div>
						<?php if (defined('HCAPTCHA_ENABLED') && HCAPTCHA_ENABLED && !$security->isAuthenticated()): ?>
							<script src="https://js.hcaptcha.com/1/api.js" async defer></script>
							<div class="form-group">
								<div class="h-captcha" data-sitekey="<?php echo $security->escapeHTML(HCAPTCHA_SITEKEY); ?>"></div>
							</div>
						<?php endif; ?>
                        <button type="submit" name="submit_comment" class="btn">Post Comment</button>
                    </form>
                </div>
            </section>
        <?php else: ?>
            <div class="not-found">
                <h1>404</h1>
                <h2>Post Not Found</h2>
                <p>The post you're looking for doesn't exist or has been removed.</p>
                <a href="index.php">← Return to Blog Home</a>
            </div>
        <?php endif; ?>
    </div>

    <footer>
        <p>
            &copy; <?php echo date("Y"); ?> <?php echo $security->escapeHTML(
     SITE_NAME); ?> |
            Powered by Secure Blog CMS v<?php echo $security->escapeHTML(
                SECURE_CMS_VERSION); ?> 🔒
        </p>
        <p style="margin-top: 10px; font-size: 14px;">
            Protected against XSS, CSRF, and Injection Attacks | SQL-Free Architecture
        </p>
    </footer>
<?php include APP_ROOT . '/templates/footer.php'; ?>
</body>
</html>
