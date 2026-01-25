<?php
/**
 * Resilience Class - Anti-Takedown and Decentralization Features
 *
 * Provides tools for static site generation, mirror management,
 * and export capabilities to ensure blog content remains accessible
 * even if the primary host is taken down.
 */

if (!defined("SECURE_CMS_INIT")) {
    exit("No direct script access allowed");
}

class Resilience
{
    private static $instance = null;
    private $storage;
    private $security;
    private $export_path;

    /**
     * Get singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->storage = Storage::getInstance();
        $this->security = Security::getInstance();
    }

    /**
     * Generate a static version of the blog
     * This allows the blog to be hosted on IPFS, GitHub Pages, or other static hosts
     */
    public function generateStaticSite()
    {
        $exportDir = DATA_DIR . "/exports";
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0700, true);
        }

        $timestamp = date("Y-m-d_H-i-s");
        $this->export_path = $exportDir . "/static_" . $timestamp;

        if (!mkdir($this->export_path, 0700)) {
            return [
                "success" => false,
                "message" => "Failed to create export directory",
            ];
        }

        // 1. Copy assets and uploads
        $this->copyAssets();

        // 2. Generate Index Page (static_index.php template needed)
        $this->generateStaticIndex();

        // 3. Generate Individual Post Pages (static_post.php template needed)
        $this->generateStaticPosts();

        // 4. Generate RSS Feed
        $this->generateStaticRSS();

        // 5. Create a ZIP archive for easy distribution
        $zipFile = $this->createZipArchive();

        return [
            "success" => true,
            "message" => "Static site generated successfully",
            "path" => $this->export_path,
            "zip" => $zipFile,
            "timestamp" => $timestamp,
        ];
    }

    /**
     * Copy necessary assets for the static site
     */
    private function copyAssets()
    {
        // Copy uploaded images if they exist
        $uploadDir = DATA_DIR . "/uploads";
        if (is_dir($uploadDir)) {
            $this->recurseCopy(
                $uploadDir,
                $this->export_path . "/data/uploads",
            );
        }

        // Copy public CSS/JS if they were in separate files (currently inline in templates)
    }

    /**
     * Generate static index.html
     */
    private function generateStaticIndex()
    {
        $postsData = $this->storage->getAllPosts();

        // Buffer output using a static-optimized template
        ob_start();
        $posts = $postsData;
        $is_static = true;
        include APP_ROOT . "/templates/index_template.php";
        $content = ob_get_clean();

        // Fix links for static hosting (e.g., post.php?slug=title -> post/title.html)
        $content = preg_replace(
            "/post\.php\?slug=([a-zA-Z0-9\-]+)/",
            'post/$1.html',
            $content,
        );

        file_put_contents($this->export_path . "/index.html", $content);
    }

    /**
     * Generate static pages for each post
     */
    private function generateStaticPosts()
    {
        $posts = $this->storage->getAllPosts();

        $postExportDir = $this->export_path . "/post";
        if (!is_dir($postExportDir)) {
            mkdir($postExportDir, 0700);
        }

        foreach ($posts as $post) {
            if ($post["status"] !== "published") {
                continue;
            }

            ob_start();
            $current_post = $post;
            $is_static = true;
            include APP_ROOT . "/templates/post_template.php";
            $content = ob_get_clean();

            // Fix links in the post content
            $content = preg_replace(
                "/post\.php\?slug=([a-zA-Z0-9\-]+)/",
                '$1.html',
                $content,
            );
            $content = str_replace("index.php", "../index.html", $content);

            file_put_contents(
                $postExportDir . "/" . $post["slug"] . ".html",
                $content,
            );
        }
    }

    /**
     * Generate static RSS feed
     */
    private function generateStaticRSS()
    {
        ob_start();
        include APP_ROOT . "/rss.php";
        $content = ob_get_clean();
        file_put_contents($this->export_path . "/rss.xml", $content);
    }

    /**
     * Helper to recursively copy directories
     */
    private function recurseCopy($src, $dst)
    {
        if (!is_dir($src)) {
            return;
        }
        $dir = opendir($src);
        @mkdir($dst, 0700, true);
        while (false !== ($file = readdir($dir))) {
            if ($file != "." && $file != ".." && $file != ".htaccess") {
                if (is_dir($src . "/" . $file)) {
                    $this->recurseCopy($src . "/" . $file, $dst . "/" . $file);
                } else {
                    copy($src . "/" . $file, $dst . "/" . $file);
                }
            }
        }
        closedir($dir);
    }

    /**
     * Create a ZIP of the generated static site
     */
    private function createZipArchive()
    {
        if (!class_exists("ZipArchive")) {
            return null;
        }

        $zipPath = $this->export_path . ".zip";
        $zip = new ZipArchive();

        if (
            $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) ===
            true
        ) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->export_path),
                RecursiveIteratorIterator::LEAVES_ONLY,
            );

            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr(
                        $filePath,
                        strlen(realpath($this->export_path)) + 1,
                    );
                    $zip->addFile($filePath, $relativePath);
                }
            }
            $zip->close();
            return basename($zipPath);
        }
        return null;
    }
}
