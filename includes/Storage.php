<?php
/**
 * Storage Class - File-based Data Persistence (SQL-Free)
 * Handles all data storage operations with security and integrity
 */

if (!defined("SECURE_CMS_INIT")) {
    die("Direct access not permitted");
}

require_once __DIR__ . "/categories.php";

class Storage
{
    private static $instance = null;
    private $security;
    private $categoriesManager;

    /**
     * Singleton pattern
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Initialize storage directories
     */
    public function __construct()
    {
        $this->security = Security::getInstance();
        $this->categoriesManager = Categories::getInstance();
        $this->initializeDirectories();
    }

    /**
     * Initialize required directories with proper permissions
     */
    private function initializeDirectories()
    {
        $directories = [
            DATA_DIR,
            POSTS_DIR,
            USERS_DIR,
            SESSIONS_DIR,
            LOGS_DIR,
            BACKUP_DIR,
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0700, true)) {
                    die("Failed to create directory: " . $dir);
                }
            }

            // Ensure proper permissions
            chmod($dir, 0700);

            // Create .htaccess to prevent direct access
            $htaccessFile = $dir . "/.htaccess";
            if (!file_exists($htaccessFile)) {
                file_put_contents($htaccessFile, "Deny from all\n", LOCK_EX);
            }

            // Create index.php to prevent directory listing
            $indexFile = $dir . "/index.php";
            if (!file_exists($indexFile)) {
                file_put_contents(
                    $indexFile,
                    "<?php\nheader('HTTP/1.0 403 Forbidden');\ndie('Access denied');\n",
                    LOCK_EX,
                );
            }
        }
    }

    /**
     * Generate unique ID for posts
     */
    private function generateID()
    {
        return time() . "_" . bin2hex(random_bytes(8));
    }

    /**
     * Create a new post
     */
    public function createPost($data)
    {
        try {
            // Validate required fields
            if (empty($data["title"]) || empty($data["content"])) {
                return [
                    "success" => false,
                    "message" => "Title and content are required",
                ];
            }

            // Sanitize data
            $post = [
                "id" => $this->generateID(),
                "title" => $this->security->sanitizeInput(
                    $data["title"],
                    "string",
                ),
                "content" => $this->security->sanitizeInput(
                    $data["content"],
                    "html",
                ),
                "excerpt" => $this->security->sanitizeInput(
                    $data["excerpt"] ?? "",
                    "string",
                ),
                "slug" => $this->security->sanitizeInput(
                    $data["slug"] ?? "",
                    "slug",
                ),
                "author" => $_SESSION["user"] ?? "admin",
                "status" => in_array($data["status"] ?? "draft", [
                    "draft",
                    "published",
                ])
                    ? $data["status"]
                    : "draft",
                "created_at" => time(),
                "updated_at" => time(),
                "views" => 0,
                "meta_description" => $this->security->sanitizeInput(
                    $data["meta_description"] ?? "",
                    "string",
                ),
                "meta_keywords" => $this->security->sanitizeInput(
                    $data["meta_keywords"] ?? "",
                    "string",
                ),
                "visibility" => in_array($data["visibility"] ?? "public", [
                    "public",
                    "private",
                ])
                    ? $data["visibility"]
                    : "public",
                "password_protected" => !empty($data["password_protected"]),
                "post_password" => "",
                "categories" => $data["categories"] ?? [],
                "tags" => $data["tags"] ?? "",
            ];

            // Handle tags
            if (!empty($data["tags"])) {
                $tagNames = array_map("trim", explode(",", $data["tags"]));
                $tagNames = array_filter($tagNames); // remove empty
                foreach ($tagNames as $tagName) {
                    // This will add the tag to the global list if it doesn't exist.
                    $this->categoriesManager->addTag($tagName);
                }
                // We store the sanitized comma-separated string.
                $post["tags"] = implode(", ", $tagNames);
            }

            // Handle password protection
            if ($post["password_protected"]) {
                if (empty($data["post_password"])) {
                    return [
                        "success" => false,
                        "message" => "Password is required for protected posts",
                    ];
                }
                $post["post_password"] = password_hash(
                    $data["post_password"],
                    PASSWORD_DEFAULT,
                );
            }

            // Validate lengths
            if (strlen($post["title"]) > MAX_POST_TITLE_LENGTH) {
                return ["success" => false, "message" => "Title too long"];
            }

            if (strlen($post["content"]) > MAX_POST_CONTENT_LENGTH) {
                return ["success" => false, "message" => "Content too long"];
            }

            // Generate slug if not provided
            if (empty($post["slug"])) {
                $post["slug"] = $this->generateSlug($post["title"]);
            }

            // Ensure slug is unique
            $post["slug"] = $this->ensureUniqueSlug($post["slug"]);

            // Generate excerpt if not provided
            if (empty($post["excerpt"])) {
                $post["excerpt"] = $this->generateExcerpt($post["content"]);
            }

            // Save post to file
            $filename = POSTS_DIR . "/" . $post["id"] . ".json";
            $jsonData = json_encode(
                $post,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
            );

            if ($jsonData === false) {
                return [
                    "success" => false,
                    "message" => "Failed to encode post data",
                ];
            }

            if (file_put_contents($filename, $jsonData, LOCK_EX) === false) {
                return ["success" => false, "message" => "Failed to save post"];
            }

            // Set proper permissions
            chmod($filename, 0600);

            // Update index
            $this->updatePostIndex();

            // Create backup if enabled
            if (AUTO_BACKUP) {
                $this->createBackup("post_created", $post["id"]);
            }

            $this->security->logSecurityEvent("Post created", $post["id"]);

            return [
                "success" => true,
                "message" => "Post created successfully",
                "post_id" => $post["id"],
            ];
        } catch (Exception $e) {
            $this->security->logSecurityEvent(
                "Error creating post",
                $e->getMessage(),
            );
            return ["success" => false, "message" => "An error occurred"];
        }
    }

    /**
     * Update existing post
     */
    public function updatePost($id, $data)
    {
        try {
            // Sanitize ID
            $id = $this->security->sanitizeInput($id, "alphanumeric");

            // Get existing post
            $existingPost = $this->getPost($id);
            if (!$existingPost) {
                return ["success" => false, "message" => "Post not found"];
            }

            // Sanitize and merge data
            $post = [
                "id" => $id,
                "title" => $this->security->sanitizeInput(
                    $data["title"] ?? $existingPost["title"],
                    "string",
                ),
                "content" => $this->security->sanitizeInput(
                    $data["content"] ?? $existingPost["content"],
                    "html",
                ),
                "excerpt" => $this->security->sanitizeInput(
                    $data["excerpt"] ?? $existingPost["excerpt"],
                    "string",
                ),
                "slug" => $this->security->sanitizeInput(
                    $data["slug"] ?? $existingPost["slug"],
                    "slug",
                ),
                "author" => $existingPost["author"],
                "status" => in_array(
                    $data["status"] ?? $existingPost["status"],
                    ["draft", "published"],
                )
                    ? $data["status"] ?? $existingPost["status"]
                    : "draft",
                "created_at" => $existingPost["created_at"],
                "updated_at" => time(),
                "views" => $existingPost["views"] ?? 0,
                "meta_description" => $this->security->sanitizeInput(
                    $data["meta_description"] ??
                        ($existingPost["meta_description"] ?? ""),
                    "string",
                ),
                "meta_keywords" => $this->security->sanitizeInput(
                    $data["meta_keywords"] ??
                        ($existingPost["meta_keywords"] ?? ""),
                    "string",
                ),
                "visibility" => in_array(
                    $data["visibility"] ??
                        ($existingPost["visibility"] ?? "public"),
                    ["public", "private"],
                )
                    ? $data["visibility"] ??
                        ($existingPost["visibility"] ?? "public")
                    : "public",
                "password_protected" => !empty($data["password_protected"]),
                "post_password" => $existingPost["post_password"] ?? "", // Keep old password by default
                "categories" =>
                    $data["categories"] ?? ($existingPost["categories"] ?? []),
                "tags" => $data["tags"] ?? ($existingPost["tags"] ?? ""),
            ];

            // Handle tags
            if (isset($data["tags"])) {
                $tagNames = array_map("trim", explode(",", $data["tags"]));
                $tagNames = array_filter($tagNames); // remove empty
                foreach ($tagNames as $tagName) {
                    // This will add the tag to the global list if it doesn't exist.
                    $this->categoriesManager->addTag($tagName);
                }
                // We store the sanitized comma-separated string.
                $post["tags"] = implode(", ", $tagNames);
            }

            // Handle password protection
            if ($post["password_protected"]) {
                // If a new password is provided, hash it. Otherwise, keep the old one.
                if (!empty($data["post_password"])) {
                    $post["post_password"] = password_hash(
                        $data["post_password"],
                        PASSWORD_DEFAULT,
                    );
                }
            } else {
                // Remove password if protection is disabled
                $post["post_password"] = "";
            }

            // Validate lengths
            if (strlen($post["title"]) > MAX_POST_TITLE_LENGTH) {
                return ["success" => false, "message" => "Title too long"];
            }

            if (strlen($post["content"]) > MAX_POST_CONTENT_LENGTH) {
                return ["success" => false, "message" => "Content too long"];
            }

            // Ensure slug is unique (excluding current post)
            if ($post["slug"] !== $existingPost["slug"]) {
                $post["slug"] = $this->ensureUniqueSlug($post["slug"], $id);
            }

            // Save post
            $filename = POSTS_DIR . "/" . $id . ".json";
            $jsonData = json_encode(
                $post,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
            );

            if ($jsonData === false) {
                return [
                    "success" => false,
                    "message" => "Failed to encode post data",
                ];
            }

            if (file_put_contents($filename, $jsonData, LOCK_EX) === false) {
                return [
                    "success" => false,
                    "message" => "Failed to update post",
                ];
            }

            // Update index
            $this->updatePostIndex();

            // Create backup
            if (AUTO_BACKUP) {
                $this->createBackup("post_updated", $id);
            }

            $this->security->logSecurityEvent("Post updated", $id);

            return [
                "success" => true,
                "message" => "Post updated successfully",
            ];
        } catch (Exception $e) {
            $this->security->logSecurityEvent(
                "Error updating post",
                $e->getMessage(),
            );
            return ["success" => false, "message" => "An error occurred"];
        }
    }

    /**
     * Delete post
     */
    public function deletePost($id)
    {
        try {
            // Sanitize ID
            $id = $this->security->sanitizeInput($id, "alphanumeric");

            $filename = POSTS_DIR . "/" . $id . ".json";

            if (!file_exists($filename)) {
                return ["success" => false, "message" => "Post not found"];
            }

            // Create backup before deletion
            if (AUTO_BACKUP) {
                $this->createBackup("post_deleted", $id);
            }

            if (!unlink($filename)) {
                return [
                    "success" => false,
                    "message" => "Failed to delete post",
                ];
            }

            // Update index
            $this->updatePostIndex();

            $this->security->logSecurityEvent("Post deleted", $id);

            return [
                "success" => true,
                "message" => "Post deleted successfully",
            ];
        } catch (Exception $e) {
            $this->security->logSecurityEvent(
                "Error deleting post",
                $e->getMessage(),
            );
            return ["success" => false, "message" => "An error occurred"];
        }
    }

    /**
     * Get single post by ID
     */
    public function getPost($id, $incrementViews = false)
    {
        $id = $this->security->sanitizeInput($id, "alphanumeric");
        $filename = POSTS_DIR . "/" . $id . ".json";

        if (!file_exists($filename)) {
            return null;
        }

        $content = file_get_contents($filename);
        if ($content === false) {
            return null;
        }

        $post = json_decode($content, true);
        if ($post === null) {
            return null;
        }

        // Increment views if requested
        if ($incrementViews && !$this->security->isAuthenticated()) {
            $post["views"]++;
            file_put_contents(
                $filename,
                json_encode($post, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                LOCK_EX,
            );
        }

        return $post;
    }

    /**
     * Get post by slug
     */
    public function getPostBySlug($slug, $incrementViews = false)
    {
        $slug = $this->security->sanitizeInput($slug, "slug");
        $posts = $this->getAllPosts();

        foreach ($posts as $post) {
            if ($post["slug"] === $slug) {
                if ($incrementViews && !$this->security->isAuthenticated()) {
                    $this->incrementPostViews($post["id"]);
                    $post["views"]++;
                }
                return $post;
            }
        }

        return null;
    }

    /**
     * Increment post views
     */
    private function incrementPostViews($id)
    {
        $post = $this->getPost($id);
        if ($post) {
            $post["views"]++;
            $filename = POSTS_DIR . "/" . $id . ".json";
            file_put_contents(
                $filename,
                json_encode($post, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                LOCK_EX,
            );
        }
    }

    /**
     * Get all posts
     */
    public function getAllPosts(
        $status = "all",
        $orderBy = "created_at",
        $order = "DESC",
    ) {
        if (
            defined("REQUIRE_LOGIN_FOR_POSTS") &&
            REQUIRE_LOGIN_FOR_POSTS &&
            !$this->security->isAuthenticated()
        ) {
            return [];
        }
        $posts = [];
        $files = glob(POSTS_DIR . "/*.json");

        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $post = json_decode($content, true);
            if ($post === null) {
                continue;
            }

            // Filter by status
            if ($status !== "all" && $post["status"] !== $status) {
                continue;
            }

            $posts[] = $post;
        }

        // Sort posts
        usort($posts, function ($a, $b) use ($orderBy, $order) {
            $aVal = $a[$orderBy] ?? 0;
            $bVal = $b[$orderBy] ?? 0;

            if ($order === "DESC") {
                return $bVal <=> $aVal;
            }
            return $aVal <=> $bVal;
        });

        return $posts;
    }

    /**
     * Get paginated posts
     */
    public function getPaginatedPosts(
        $page = 1,
        $perPage = POSTS_PER_PAGE,
        $status = "published",
    ) {
        $allPosts = $this->getAllPosts($status);
        $totalPosts = count($allPosts);
        $totalPages = ceil($totalPosts / $perPage);

        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $posts = array_slice($allPosts, $offset, $perPage);

        return [
            "posts" => $posts,
            "pagination" => [
                "current_page" => $page,
                "total_pages" => $totalPages,
                "total_posts" => $totalPosts,
                "per_page" => $perPage,
                "has_previous" => $page > 1,
                "has_next" => $page < $totalPages,
            ],
        ];
    }

    /**
     * Search posts
     */
    public function searchPosts($query, $status = "published")
    {
        $query = $this->security->sanitizeInput($query, "string");
        $query = strtolower($query);

        $allPosts = $this->getAllPosts($status);
        $results = [];

        foreach ($allPosts as $post) {
            $searchableText = strtolower(
                $post["title"] .
                    " " .
                    $post["content"] .
                    " " .
                    $post["excerpt"] .
                    " " .
                    ($post["meta_keywords"] ?? ""),
            );

            if (strpos($searchableText, $query) !== false) {
                $results[] = $post;
            }
        }

        return $results;
    }

    /**
     * Generate slug from title
     */
    private function generateSlug($title)
    {
        $slug = strtolower($title);
        $slug = preg_replace("/[^a-z0-9]+/", "-", $slug);
        $slug = trim($slug, "-");
        return $slug;
    }

    /**
     * Ensure slug is unique
     */
    private function ensureUniqueSlug($slug, $excludeId = null)
    {
        $originalSlug = $slug;
        $counter = 1;
        $allPosts = $this->getAllPosts();

        while (true) {
            $exists = false;
            foreach ($allPosts as $post) {
                if ($post["slug"] === $slug && $post["id"] !== $excludeId) {
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                break;
            }

            $slug = $originalSlug . "-" . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Generate excerpt from content
     */
    private function generateExcerpt(
        $content,
        $length = MAX_POST_EXCERPT_LENGTH,
    ) {
        $text = strip_tags($content);
        $text = preg_replace("/\s+/", " ", $text);
        $text = trim($text);

        if (strlen($text) <= $length) {
            return $text;
        }

        $excerpt = substr($text, 0, $length);
        $lastSpace = strrpos($excerpt, " ");

        if ($lastSpace !== false) {
            $excerpt = substr($excerpt, 0, $lastSpace);
        }

        return $excerpt . "...";
    }

    /**
     * Update post index for faster queries
     */
    private function updatePostIndex()
    {
        $posts = $this->getAllPosts();
        $index = [];

        foreach ($posts as $post) {
            $index[] = [
                "id" => $post["id"],
                "title" => $post["title"],
                "slug" => $post["slug"],
                "status" => $post["status"],
                "created_at" => $post["created_at"],
                "updated_at" => $post["updated_at"],
            ];
        }

        $indexFile = DATA_DIR . "/post_index.json";
        file_put_contents(
            $indexFile,
            json_encode($index, JSON_PRETTY_PRINT),
            LOCK_EX,
        );
    }

    /**
     * Create backup
     */
    public function createBackup($reason = "manual", $relatedId = "")
    {
        try {
            if (!is_dir(BACKUP_DIR)) {
                mkdir(BACKUP_DIR, 0700, true);
            }

            $timestamp = date("Y-m-d_H-i-s");
            $backupFile = BACKUP_DIR . "/backup_" . $timestamp . ".json";

            $backupData = [
                "timestamp" => time(),
                "date" => $timestamp,
                "reason" => $reason,
                "related_id" => $relatedId,
                "posts" => $this->getAllPosts("all"),
            ];

            $jsonData = json_encode(
                $backupData,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
            );
            file_put_contents($backupFile, $jsonData, LOCK_EX);
            chmod($backupFile, 0600);

            // Clean old backups
            $this->cleanOldBackups();

            return true;
        } catch (Exception $e) {
            $this->security->logSecurityEvent(
                "Backup failed",
                $e->getMessage(),
            );
            return false;
        }
    }

    /**
     * Clean old backups
     */
    private function cleanOldBackups()
    {
        $backups = glob(BACKUP_DIR . "/backup_*.json");

        if (count($backups) > MAX_BACKUPS) {
            usort($backups, function ($a, $b) {
                return filemtime($a) - filemtime($b);
            });

            $toDelete = array_slice($backups, 0, count($backups) - MAX_BACKUPS);
            foreach ($toDelete as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * Restore from backup
     */
    public function restoreBackup($backupFile)
    {
        try {
            $backupFile = $this->security->sanitizeInput(
                $backupFile,
                "filename",
            );
            $fullPath = BACKUP_DIR . "/" . $backupFile;

            if (!file_exists($fullPath)) {
                return [
                    "success" => false,
                    "message" => "Backup file not found",
                ];
            }

            $content = file_get_contents($fullPath);
            $backupData = json_decode($content, true);

            if ($backupData === null || !isset($backupData["posts"])) {
                return ["success" => false, "message" => "Invalid backup file"];
            }

            // Clear existing posts
            $files = glob(POSTS_DIR . "/*.json");
            foreach ($files as $file) {
                @unlink($file);
            }

            // Restore posts
            foreach ($backupData["posts"] as $post) {
                $filename = POSTS_DIR . "/" . $post["id"] . ".json";
                file_put_contents(
                    $filename,
                    json_encode(
                        $post,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
                    ),
                    LOCK_EX,
                );
                chmod($filename, 0600);
            }

            // Update index
            $this->updatePostIndex();

            $this->security->logSecurityEvent("Backup restored", $backupFile);

            return [
                "success" => true,
                "message" => "Backup restored successfully",
            ];
        } catch (Exception $e) {
            $this->security->logSecurityEvent(
                "Restore failed",
                $e->getMessage(),
            );
            return [
                "success" => false,
                "message" => "Failed to restore backup",
            ];
        }

        /**
         * Get site settings
         */
        public function getSettings()
        {
            $settingsFile = SITE_SETTINGS_FILE;
            if (!file_exists($settingsFile)) {
                return [];
            }
            $content = @file_get_contents($settingsFile);
            if ($content === false) {
                return [];
            }
            return json_decode($content, true) ?? [];
        }
    }

    /**
     * Get all backups
     */
    public function getBackups()
    {
        $backups = [];
        $files = glob(BACKUP_DIR . "/backup_*.json");

        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            $backups[] = [
                "filename" => basename($file),
                "size" => filesize($file),
                "date" => date("Y-m-d H:i:s", filemtime($file)),
            ];
        }

        usort($backups, function ($a, $b) {
            return strtotime($b["date"]) - strtotime($a["date"]);
        });

        return $backups;
    }

    /**
     * Get statistics
     */
    public function getStatistics()
    {
        $allPosts = $this->getAllPosts("all");
        $published = 0;
        $draft = 0;
        $totalViews = 0;

        foreach ($allPosts as $post) {
            if ($post["status"] === "published") {
                $published++;
            } else {
                $draft++;
            }
            $totalViews += $post["views"] ?? 0;
        }

        return [
            "total_posts" => count($allPosts),
            "published_posts" => $published,
            "draft_posts" => $draft,
            "total_views" => $totalViews,
        ];
    }
}
