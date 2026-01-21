<?php
/**
 * Secure Blog CMS - Public Index Page
 * Displays published blog posts with security measures
 */

// Initialize security constant
define("SECURE_CMS_INIT", true);

// Load configuration
require_once __DIR__ . "/includes/config.php";

// Load required classes
require_once __DIR__ . "/includes/Security.php";
require_once __DIR__ . "/includes/Storage.php";

// Check if installed
if (
    !file_exists(__DIR__ . "/data/installed.lock") &&
    file_exists(__DIR__ . "/install/index.php")
) {
    header("Location: install/index.php");
    exit();
}

// Initialize security and storage
$security = Security::getInstance();
$storage = Storage::getInstance();

// Get current page number
$page = $security->getGetData("page", "int", 1);
$page = max(1, $page);

// Get search query if exists
$searchQuery = $security->getGetData("q", "string", "");
if (!ALLOW_SEARCH) {
    $searchQuery = "";
}

// Get posts
if (!empty($searchQuery)) {
    $postsData = [
        "posts" => $storage->searchPosts($searchQuery),
        "pagination" => [
            "current_page" => 1,
            "total_pages" => 1,
            "total_posts" => 0,
        ],
    ];
} else {
    $postsData = $storage->getPaginatedPosts($page);
}

$posts = $postsData["posts"];
$pagination = $postsData["pagination"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo $security->escapeHTML(
        SITE_DESCRIPTION,
    ); ?>">
    <meta name="robots" content="index, follow">
    <title><?php echo $security->escapeHTML(SITE_NAME); ?></title>
    <link rel="alternate" type="application/rss+xml" title="RSS Feed for <?php echo $security->escapeHTML(
        SITE_NAME,
    ); ?>" href="rss.php" />
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
        }

        header p {
            opacity: 0.9;
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

        .search-form {
            margin-bottom: 30px;
            display: flex;
            gap: 10px;
        }

        .search-form input[type="text"] {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }

        .search-form button {
            padding: 12px 24px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s;
        }

        .search-form button:hover {
            background: #2980b9;
        }

        .post {
            background: white;
            padding: 30px;
            margin-bottom: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .post:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }

        .post-title {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .post-title a {
            color: inherit;
            text-decoration: none;
        }

        .post-title a:hover {
            color: #3498db;
        }

        .post-meta {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 15px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .post-meta span {
            display: inline-flex;
            align-items: center;
        }

        .post-excerpt {
            color: #555;
            line-height: 1.8;
            margin-bottom: 15px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .post-content {
            color: #555;
            line-height: 1.8;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .post-content img, .post-excerpt img {
            max-width: 100%;
            height: auto !important;
            display: block;
            margin: 20px auto;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .post-content h1, .post-content h2, .post-content h3 {
            margin-top: 20px;
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .post-content p {
            margin-bottom: 15px;
        }

        .post-content ul, .post-content ol {
            margin-left: 30px;
            margin-bottom: 15px;
        }

        .post-content code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }

        .post-content pre {
            background: #f4f4f4;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            margin-bottom: 15px;
        }

        .read-more {
            display: inline-block;
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }

        .read-more:hover {
            color: #2980b9;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 40px;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            padding: 10px 15px;
            background: white;
            color: #333;
            text-decoration: none;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: background 0.3s, transform 0.2s;
        }

        .pagination a:hover {
            background: #3498db;
            color: white;
            transform: translateY(-2px);
        }

        .pagination .current {
            background: #3498db;
            color: white;
        }

        .no-posts {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .no-posts h2 {
            color: #7f8c8d;
            margin-bottom: 10px;
        }

        footer {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
            margin-top: 40px;
        }

        .rss-link {
            color: #7f8c8d;
            text-decoration: none;
            transition: color 0.3s;
        }

        .rss-link:hover {
            color: #3498db;
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

            .post {
                padding: 20px;
            }

            .post-title {
                font-size: 24px;
            }

            header h1 {
                font-size: 28px;
            }

            .admin-link {
                position: static;
                display: inline-block;
                margin-top: 15px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container" style="position: relative;">
            <h1><?php echo $security->escapeHTML(
                SITE_NAME,
            ); ?> <span class="security-badge">🔒 SECURED</span></h1>
            <p><?php echo $security->escapeHTML(SITE_DESCRIPTION); ?></p>
            <?php if ($security->isAuthenticated()): ?>
                <a href="admin/admin.php" class="admin-link">🔑 Admin Panel</a>
            <?php endif; ?>
        </div>
    </header>

    <div class="container">
        <!-- Search Form -->
        <?php if (defined("ALLOW_SEARCH") ? ALLOW_SEARCH : true): ?>
<form method="get" action="index.php" class="search-form">
            <input type="text" name="q" placeholder="Search posts..." value="<?php echo $security->escapeHTML(
                $searchQuery,
            ); ?>">
            <button type="submit">🔍 Search</button>
        </form>
<?php endif; ?>


        <?php if (!empty($searchQuery)): ?>
            <p style="margin-bottom: 20px; color: #7f8c8d;">
                Search results for: <strong><?php echo $security->escapeHTML(
                    $searchQuery,
                ); ?></strong>
                (<?php echo count($posts); ?> result<?php echo count($posts) !==
 1
     ? "s"
     : ""; ?>)
                | <a href="index.php" style="color: #3498db;">Clear search</a>
            </p>
        <?php endif; ?>

        <?php if (empty($posts)): ?>
            <div class="no-posts">
                <h2>📝 No Posts Found</h2>
                <p>There are no published posts yet. Check back soon!</p>
            </div>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <article class="post">
                    <h2 class="post-title">
                        <a href="post.php?slug=<?php echo $security->escapeURL(
                            $post["slug"],
                        ); ?>">
                            <?php echo $security->escapeHTML($post["title"]); ?>
                        </a>
                    </h2>

                    <div class="post-meta">
                        <span>📅 <?php echo date(
                            "F j, Y",
                            $post["created_at"],
                        ); ?></span>
                        <span>✍️ <?php echo $security->escapeHTML(
                            $post["author"],
                        ); ?></span>
                        <span>👁️ <?php echo number_format(
                            $post["views"],
                        ); ?> views</span>
                    </div>

                    <div class="post-excerpt">
                        <?php echo $security->escapeHTML($post["excerpt"]); ?>
                    </div>

                    <a href="post.php?slug=<?php echo $security->escapeURL(
                        $post["slug"],
                    ); ?>" class="read-more">
                        Read More →
                    </a>
                </article>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($pagination["total_pages"] > 1 && empty($searchQuery)): ?>
                <div class="pagination">
                    <?php if ($pagination["has_previous"]): ?>
                        <a href="?page=1">« First</a>
                        <a href="?page=<?php echo $pagination["current_page"] -
                            1; ?>">‹ Previous</a>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $pagination["current_page"] - 2);
                    $end = min(
                        $pagination["total_pages"],
                        $pagination["current_page"] + 2,
                    );

                    for ($i = $start; $i <= $end; $i++):
                        if ($i == $pagination["current_page"]): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif;
                    endfor;
                    ?>

                    <?php if ($pagination["has_next"]): ?>
                        <a href="?page=<?php echo $pagination["current_page"] +
                            1; ?>">Next ›</a>
                        <a href="?page=<?php echo $pagination[
                            "total_pages"
                        ]; ?>">Last »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <footer>
        <p>
            <a href="rss.php" class="rss-link">📡 RSS Feed</a> |
            &copy; <?php echo date("Y"); ?> <?php echo $security->escapeHTML(
     SITE_NAME,
 ); ?> |
            Powered by Secure Blog CMS v<?php echo $security->escapeHTML(
                SECURE_CMS_VERSION,
            ); ?> 🔒
        </p>
        <p style="margin-top: 10px; font-size: 14px;">
            Protected against XSS, CSRF, and Injection Attacks | SQL-Free Architecture
        </p>
    </footer>
<?php include APP_ROOT . "/templates/footer.php"; ?>
</body>
</html>
