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
        return $slug;
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

        $taxonomy = $this->loadTaxonomy();
        $slug = $this->generateSlug($name);

        foreach ($taxonomy["categories"] as $category) {
            if (
                $category["slug"] === $slug ||
                strtolower($category["name"]) === strtolower($name)
            ) {
                return [
                    "success" => false,
                    "message" => "Category already exists.",
                ];
            }
        }

        $taxonomy["categories"][] = ["slug" => $slug, "name" => $name];

        usort($taxonomy["categories"], function ($a, $b) {
            return strcasecmp($a["name"], $b["name"]);
        });

        if ($this->saveTaxonomy($taxonomy)) {
            return [
                "success" => true,
                "message" => "Category added successfully.",
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

        $taxonomy = $this->loadTaxonomy();
        $slug = $this->generateSlug($name);

        foreach ($taxonomy["tags"] as $tag) {
            if (
                $tag["slug"] === $slug ||
                strtolower($tag["name"]) === strtolower($name)
            ) {
                return ["success" => false, "message" => "Tag already exists."];
            }
        }

        $taxonomy["tags"][] = ["slug" => $slug, "name" => $name];

        usort($taxonomy["tags"], function ($a, $b) {
            return strcasecmp($a["name"], $b["name"]);
        });

        if ($this->saveTaxonomy($taxonomy)) {
            return ["success" => true, "message" => "Tag added successfully."];
        }

        return ["success" => false, "message" => "Failed to save tag."];
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
