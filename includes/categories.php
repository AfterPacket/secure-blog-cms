<?php
/**
 * Secure Blog CMS - Categories & Tags Management
 *
 * @package SecureBlogCMS
 */

if (!defined("SECURE_CMS_INIT")) {
    die("Direct access not permitted");
}

class Categories
{
    private static $instance = null;
    private $taxonomyFile;

    /**
     * Singleton pattern
     * @return Categories
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
        $this->taxonomyFile = DATA_DIR . "/taxonomy.json";
        $this->initializeTaxonomyFile();
    }

    /**
     * Ensures the taxonomy file exists.
     */
    private function initializeTaxonomyFile()
    {
        if (!file_exists($this->taxonomyFile)) {
            $initialData = [
                "categories" => [],
                "tags" => [],
            ];
            file_put_contents(
                $this->taxonomyFile,
                json_encode($initialData, JSON_PRETTY_PRINT),
                LOCK_EX);
            chmod($this->taxonomyFile, 0600);
        }
    }

    /**
     * Loads the taxonomy data from the JSON file.
     *
     * @return array The decoded taxonomy data.
     */
    private function loadTaxonomy()
    {
        if (!file_exists($this->taxonomyFile)) {
            return ["categories" => [], "tags" => []];
        }
        $content = file_get_contents($this->taxonomyFile);
        return json_decode($content, true) ?: [
                "categories" => [],
                "tags" => [],
            ];
    }

    /**
     * Saves the taxonomy data to the JSON file.
     *
     * @param array $data The data to save.
     * @return bool True on success, false on failure.
     */
    private function saveTaxonomy($data)
    {
        $jsonData = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return file_put_contents($this->taxonomyFile, $jsonData, LOCK_EX) !==
            false;
    }

    /**
     * Generates a slug from a name.
     *
     * @param string $name The name to slugify.
     * @return string The generated slug.
     */
    private function generateSlug($name)
    {
        $slug = strtolower($name);
        $slug = preg_replace("/[^a-z0-9]+/", "-", $slug);
        $slug = trim($slug, "-");
        // Fallback if name had no alphanumeric characters
        if (empty($slug)) {
            $slug = "item-" . substr(md5($name . time()), 0, 6);
        }
        return $slug;
    }

    /**
     * Generates a unique slug by appending a number if needed.
     *
     * @param string $baseSlug The desired slug.
     * @param array $existingSlugs Slugs already in use.
     * @return string A unique slug.
     */
    private function generateUniqueSlug($baseSlug, $existingSlugs)
    {
        if (!in_array($baseSlug, $existingSlugs, true)) {
            return $baseSlug;
        }
        $counter = 2;
        while (in_array($baseSlug . "-" . $counter, $existingSlugs, true)) {
            $counter++;
        }
        return $baseSlug . "-" . $counter;
    }

    /**
     * Returns all categories.
     *
     * @return array
     */
    public function getAllCategories()
    {
        $taxonomy = $this->loadTaxonomy();
        return $taxonomy["categories"] ?? [];
    }

    /**
     * Returns all tags.
     *
     * @return array
     */
    public function getAllTags()
    {
        $taxonomy = $this->loadTaxonomy();
        return $taxonomy["tags"] ?? [];
    }

    /**
     * Adds a new category.
     *
     * @param string $name The name of the category to add.
     * @return array Result array with success status and message.
     */
    public function addCategory($name)
    {
        $name = trim(strip_tags($name));
        if (empty($name)) {
            return [
                "success" => false,
                "message" => "Category name cannot be empty.",
            ];
        }

        if (mb_strlen($name) > 100) {
            return ["success" => false, "message" => "Category name must be 100 characters or less."];
        }

        $taxonomy = $this->loadTaxonomy();
        $slug = $this->generateSlug($name);

        // Check for exact duplicate (same name, case-insensitive)
        foreach ($taxonomy["categories"] as $category) {
            if (strtolower($category["name"]) === strtolower($name)) {
                return [
                    "success" => false,
                    "message" => "A category named \"" . $name . "\" already exists.",
                ];
            }
        }

        // Ensure slug is unique (append number if needed)
        $existingSlugs = array_column($taxonomy["categories"], "slug");
        $slug = $this->generateUniqueSlug($slug, $existingSlugs);

        $taxonomy["categories"][] = ["slug" => $slug, "name" => $name];

        usort($taxonomy["categories"], function ($a, $b) {
            return strcasecmp($a["name"], $b["name"]);
        });

        if ($this->saveTaxonomy($taxonomy)) {
            return [
                "success" => true,
                "message" => "Category \"" . $name . "\" added successfully.",
            ];
        }

        return ["success" => false, "message" => "Failed to save category."];
    }

    /**
     * Adds a new tag.
     *
     * @param string $name The name of the tag to add.
     * @return array Result array with success status and message.
     */
    public function addTag($name)
    {
        $name = trim(strip_tags($name));
        if (empty($name)) {
            return [
                "success" => false,
                "message" => "Tag name cannot be empty.",
            ];
        }

        if (mb_strlen($name) > 100) {
            return ["success" => false, "message" => "Tag name must be 100 characters or less."];
        }

        $taxonomy = $this->loadTaxonomy();
        $slug = $this->generateSlug($name);

        // Check for exact duplicate (same name, case-insensitive)
        foreach ($taxonomy["tags"] as $tag) {
            if (strtolower($tag["name"]) === strtolower($name)) {
                return [
                    "success" => false,
                    "message" => "A tag named \"" . $name . "\" already exists.",
                ];
            }
        }

        // Ensure slug is unique (append number if needed)
        $existingSlugs = array_column($taxonomy["tags"], "slug");
        $slug = $this->generateUniqueSlug($slug, $existingSlugs);

        $taxonomy["tags"][] = ["slug" => $slug, "name" => $name];

        usort($taxonomy["tags"], function ($a, $b) {
            return strcasecmp($a["name"], $b["name"]);
        });

        if ($this->saveTaxonomy($taxonomy)) {
            return ["success" => true, "message" => "Tag \"" . $name . "\" added successfully."];
        }

        return ["success" => false, "message" => "Failed to save tag."];
    }

    /**
     * Deletes a category by slug.
     * Also removes the category from any posts that reference it.
     *
     * @param string $slug The slug of the category to delete.
     * @return array Result array with success status and message.
     */
    public function deleteCategory($slug)
    {
        $slug = trim($slug);
        if (empty($slug)) {
            return ["success" => false, "message" => "Invalid category slug."];
        }

        $taxonomy = $this->loadTaxonomy();
        $found = false;
        $name = "";

        foreach ($taxonomy["categories"] as $key => $category) {
            if ($category["slug"] === $slug) {
                $name = $category["name"];
                unset($taxonomy["categories"][$key]);
                $found = true;
                break;
            }
        }

        if (!$found) {
            return ["success" => false, "message" => "Category not found."];
        }

        // Re-index array after unset
        $taxonomy["categories"] = array_values($taxonomy["categories"]);

        // Remove this category from any posts that reference it
        $this->removeCategoryFromPosts($slug);

        if ($this->saveTaxonomy($taxonomy)) {
            return [
                "success" => true,
                "message" => "Category \"" . $name . "\" deleted successfully.",
            ];
        }

        return ["success" => false, "message" => "Failed to delete category."];
    }

    /**
     * Deletes a tag by slug.
     * Also removes the tag from any posts that reference it.
     *
     * @param string $slug The slug of the tag to delete.
     * @return array Result array with success status and message.
     */
    public function deleteTag($slug)
    {
        $slug = trim($slug);
        if (empty($slug)) {
            return ["success" => false, "message" => "Invalid tag slug."];
        }

        $taxonomy = $this->loadTaxonomy();
        $found = false;
        $name = "";

        foreach ($taxonomy["tags"] as $key => $tag) {
            if ($tag["slug"] === $slug) {
                $name = $tag["name"];
                unset($taxonomy["tags"][$key]);
                $found = true;
                break;
            }
        }

        if (!$found) {
            return ["success" => false, "message" => "Tag not found."];
        }

        // Re-index array after unset
        $taxonomy["tags"] = array_values($taxonomy["tags"]);

        // Remove this tag from any posts that reference it
        $this->removeTagFromPosts($slug);

        if ($this->saveTaxonomy($taxonomy)) {
            return [
                "success" => true,
                "message" => "Tag \"" . $name . "\" deleted successfully.",
            ];
        }

        return ["success" => false, "message" => "Failed to delete tag."];
    }

    /**
     * Removes a category slug from all posts.
     *
     * @param string $slug The category slug to remove.
     */
    private function removeCategoryFromPosts($slug)
    {
        $files = glob(POSTS_DIR . "/*.json");
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $post = json_decode($content, true);
            if (!$post || !isset($post["categories"]) || !is_array($post["categories"])) {
                continue;
            }
            $key = array_search($slug, $post["categories"]);
            if ($key !== false) {
                unset($post["categories"][$key]);
                $post["categories"] = array_values($post["categories"]);
                file_put_contents(
                    $file,
                    json_encode($post, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                    LOCK_EX
                );
            }
        }
    }

    /**
     * Removes a tag slug from all posts.
     *
     * @param string $slug The tag slug to remove.
     */
    private function removeTagFromPosts($slug)
    {
        $files = glob(POSTS_DIR . "/*.json");
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $post = json_decode($content, true);
            if (!$post || !isset($post["tags"]) || !is_array($post["tags"])) {
                continue;
            }
            $key = array_search($slug, $post["tags"]);
            if ($key !== false) {
                unset($post["tags"][$key]);
                $post["tags"] = array_values($post["tags"]);
                file_put_contents(
                    $file,
                    json_encode($post, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                    LOCK_EX
                );
            }
        }
    }

    /**
     * Returns an array of posts that have the specified category slug.
     *
     * @param string $categorySlug
     * @return array
     */
    public function getPostsByCategory($categorySlug)
    {
        $storage = Storage::getInstance();
        $allPosts = $storage->getAllPosts("published");
        $filteredPosts = [];

        foreach ($allPosts as $post) {
            if (
                isset($post["categories"]) &&
                is_array($post["categories"]) &&
                in_array($categorySlug, $post["categories"])
            ) {
                $filteredPosts[] = $post;
            }
        }

        return $filteredPosts;
    }

    /**
     * Returns an array of posts that have the specified tag slug.
     *
     * @param string $tagSlug
     * @return array
     */
    public function getPostsByTag($tagSlug)
    {
        $storage = Storage::getInstance();
        $allPosts = $storage->getAllPosts("published");
        $filteredPosts = [];

        foreach ($allPosts as $post) {
            if (
                isset($post["tags"]) &&
                is_array($post["tags"]) &&
                in_array($tagSlug, $post["tags"])
            ) {
                $filteredPosts[] = $post;
            }
        }

        return $filteredPosts;
    }
}
