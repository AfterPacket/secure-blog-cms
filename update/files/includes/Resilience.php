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
            @mkdir($exportDir, 0700, true);
        }

        if (!is_dir($exportDir) || !is_writable($exportDir)) {
            return [
                "success" => false,
                "message" =>
                    "Exports directory is missing or not writable. Check data folder permissions.",
            ];
        }

        $timestamp = date("Y-m-d_H-i-s");
        $this->export_path = $exportDir . "/static_" . $timestamp;

        if (!is_dir($this->export_path) && !@mkdir($this->export_path, 0700)) {
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

        $result = [
            "success" => true,
            "message" => "Static site generated successfully",
            "path" => $this->export_path,
            "zip" => $zipFile,
            "timestamp" => $timestamp,
        ];

        // 6. Pin to Pinata if configured
        $settings = $this->storage->getSettings();
        if (
            !empty($settings["pinata_jwt"]) ||
            (!empty($settings["pinata_api_key"]) &&
                !empty($settings["pinata_api_secret"]))
        ) {
            $pinResult = $this->pinToPinata(
                $this->export_path . ".zip",
                $timestamp,
            );
            if ($pinResult["success"]) {
                $result["ipfs_cid"] = $pinResult["cid"];
                $result["message"] .=
                    " and pinned to IPFS (CID: " . $pinResult["cid"] . ")";
            } else {
                $result["message"] .=
                    " but failed to pin to IPFS: " . $pinResult["error"];
            }
        }

        return $result;
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
            @mkdir($postExportDir, 0700, true);
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
        if (!is_dir($dst)) {
            @mkdir($dst, 0700, true);
        }
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

    /**
     * Pin the generated ZIP to Pinata IPFS
     */
    private function pinToPinata($filePath, $timestamp)
    {
        $settings = $this->storage->getSettings();
        $jwt = $settings["pinata_jwt"] ?? "";
        $apiKey = $settings["pinata_api_key"] ?? "";
        $apiSecret = $settings["pinata_api_secret"] ?? "";

        if (empty($jwt) && (empty($apiKey) || empty($apiSecret))) {
            return ["success" => false, "error" => "API keys missing"];
        }

        if (!file_exists($filePath)) {
            return ["success" => false, "error" => "File not found"];
        }

        if (!function_exists("curl_init")) {
            return [
                "success" => false,
                "error" => "CURL extension not enabled",
            ];
        }

        $url = "https://api.pinata.cloud/pinning/pinFileToIPFS";

        // Create CURLFile
        $cfile = new CURLFile(
            $filePath,
            "application/zip",
            basename($filePath),
        );

        $postData = [
            "file" => $cfile,
            "pinataMetadata" => json_encode([
                "name" => SITE_NAME . "_Export_" . $timestamp,
                "keyvalues" => [
                    "version" => SECURE_CMS_VERSION,
                    "timestamp" => $timestamp,
                ],
            ]),
            "pinataOptions" => json_encode([
                "cidVersion" => 1,
            ]),
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutes timeout for large ZIPs

        $headers = [];
        if (!empty($jwt)) {
            $headers[] = "Authorization: Bearer " . $jwt;
        } else {
            $headers[] = "pinata_api_key: " . $apiKey;
            $headers[] = "pinata_secret_api_key: " . $apiSecret;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ["success" => false, "error" => "CURL Error: " . $error];
        }

        if ($httpCode !== 200) {
            return [
                "success" => false,
                "error" =>
                    "Pinata API returned code " . $httpCode . ": " . $response,
            ];
        }

        $result = json_decode($response, true);
        if (isset($result["IpfsHash"])) {
            return ["success" => true, "cid" => $result["IpfsHash"]];
        }

        return [
            "success" => false,
            "error" => "No CID in response: " . $response,
        ];
    }
}
