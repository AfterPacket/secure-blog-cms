<?php
/**
 * Secure Blog CMS - RSS Feed Generator
 */

// Initialize security constant
if (!defined("SECURE_CMS_INIT")) {
    define("SECURE_CMS_INIT", true);
}

// Load configuration
require_once __DIR__ . "/includes/config.php";

// Load required classes
require_once __DIR__ . "/includes/Security.php";
require_once __DIR__ . "/includes/Storage.php";

// Initialize security and storage
$security = Security::getInstance();
$storage = Storage::getInstance();

// Fetch the latest published posts (e.g., the last 15)
$posts = array_slice(
    $storage->getAllPosts("published", "created_at", "DESC"),
    0,
    15,
);

// Set the content type header to let the browser know it's an XML file
header("Content-Type: application/rss+xml; charset=utf-8");

// Start XML output
echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title><?php echo $security->escapeHTML(SITE_NAME); ?></title>
    <link><?php echo $security->escapeHTML(SITE_URL); ?></link>
    <description><?php echo $security->escapeHTML(
        SITE_DESCRIPTION,
    ); ?></description>
    <language>en-us</language>
    <lastBuildDate><?php echo date(DATE_RSS, time()); ?></lastBuildDate>
    <atom:link href="<?php echo $security->escapeHTML(
        SITE_URL . cms_path("rss.php"),
    ); ?>" rel="self" type="application/rss+xml" />

    <?php if (!empty($posts)): ?>
        <?php foreach ($posts as $post): ?>
            <item>
                <title><?php
                if (!empty($post["password_protected"]) && !$security->isAuthenticated()) {
                    echo $security->escapeHTML("🔒 " . $post["title"]);
                } else {
                    echo $security->escapeHTML($post["title"]);
                }
                ?></title>
                <link><?php echo $security->escapeHTML(
                    SITE_URL . cms_path("post/" . $post["slug"]),
                ); ?></link>
                <description><![CDATA[<?php
                if (!empty($post["password_protected"]) && !$security->isAuthenticated()) {
                    echo "This post is password protected.";
                } else {
                    echo $security->escapeHTML($post["excerpt"]);
                }
                ?>]]></description>
                <pubDate><?php echo date(
                    DATE_RSS,
                    $post["created_at"],
                ); ?></pubDate>
                <guid isPermaLink="true"><?php echo $security->escapeHTML(
                    SITE_URL . cms_path("post/" . $post["slug"]),
                ); ?></guid>
                <author><?php echo $security->escapeHTML(
                    $post["author"],
                ); ?></author>
                <?php if (!empty($post["categories"]) && is_array($post["categories"])): ?>
                    <?php foreach ($post["categories"] as $catSlug): ?>
                        <category><?php echo $security->escapeHTML($catSlug); ?></category>
                    <?php endforeach; ?>
                <?php endif; ?>
            </item>
        <?php endforeach; ?>
    <?php endif; ?>

</channel>
</rss>
