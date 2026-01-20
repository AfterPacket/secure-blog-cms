<?php
/**
 * Secure Blog CMS - Comments Class
 * Manages comments on posts
 */

if (!defined("SECURE_CMS_INIT")) {
    die("Direct access not permitted");
}

class Comments
{
    private static $instance = null;
    private $commentsDir;
    private $security;
    private $notifications;
    private $storage;

    /**
     * Singleton pattern
     * @return Comments
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
        $this->commentsDir = DATA_DIR . "/comments";
        $this->security = Security::getInstance();
        $this->notifications = Notifications::getInstance();
        $this->storage = Storage::getInstance();

        if (!is_dir($this->commentsDir)) {
            if (!mkdir($this->commentsDir, 0700, true)) {
                error_log(
                    "Failed to create comments directory: " .
                        $this->commentsDir);
            }
        }
    }

    /**
     * Generates the path for a post's comments file.
     * @param string $postId
     * @return string
     */
    private function getCommentsFile($postId)
    {
        // Sanitize post ID to prevent directory traversal
        $postId = preg_replace("/[^a-zA-Z0-9_-]/", "", $postId);
        return $this->commentsDir . "/" . $postId . ".json";
    }

    /**
     * Loads all comments for a given post.
     * @param string $postId
     * @return array
     */
    private function loadComments($postId)
    {
        $file = $this->getCommentsFile($postId);
        if (!file_exists($file)) {
            return [];
        }
        $content = file_get_contents($file);
        return json_decode($content, true) ?: [];
    }

    /**
     * Saves comments for a given post.
     * @param string $postId
     * @param array $comments
     * @return bool
     */
    private function saveComments($postId, $comments)
    {
        $file = $this->getCommentsFile($postId);
        $jsonData = json_encode(
            $comments,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($jsonData === false) {
            return false;
        }
        return file_put_contents($file, $jsonData, LOCK_EX) !== false;
    }

    /**
     * Generates a unique ID for a comment.
     * @return string
     */
    private function generateID()
    {
        return time() . "_" . bin2hex(random_bytes(4));
    }

    /**
     * Gets comments for a specific post, filtered by status.
     * @param string $postId
     * @param string $status 'approved', 'pending', 'spam', or 'all'
     * @return array
     */
    public function getCommentsByPostId($postId, $status = "approved")
    {
        $allComments = $this->loadComments($postId);

        if ($status === "all") {
            return $allComments;
        }

        $filteredComments = array_filter($allComments, function ($comment) use (
            $status) {
            return isset($comment["status"]) && $comment["status"] === $status;
        });

        // Sort by creation time (oldest first)
        usort($filteredComments, function ($a, $b) {
            return ($a["created_at"] ?? 0) <=> ($b["created_at"] ?? 0);
        });

        return $filteredComments;
    }

    /**
     * Adds a new comment to a post.
     * @param string $postId
     * @param array $data Comment data (author_name, content, etc.)
     * @return array Result of the operation.
     */
    public function addComment($postId, $data)
    {
        if (
            empty($postId) ||
            empty($data["author_name"]) ||
            empty($data["content"])
        ) {
            return [
                "success" => false,
                "message" => "Name and comment content are required.",
            ];
        }

        $comments = $this->loadComments($postId);

        $newComment = [
            "id" => $this->generateID(),
            "post_id" => $postId,
            "author_name" => $this->security->sanitizeInput(
                $data["author_name"],
                "string"),
            "author_email" => $this->security->sanitizeInput(
                $data["author_email"] ?? "",
                "email"),
            "author_website" => $this->security->sanitizeInput(
                $data["author_website"] ?? "",
                "url"),
            "content" => $this->security->sanitizeInput(
                $data["content"],
                "string"),
            "created_at" => time(),
            "ip_address" => $_SERVER["REMOTE_ADDR"] ?? "unknown",
            "user_agent" => $_SERVER["HTTP_USER_AGENT"] ?? "unknown",
            "status" => "pending", // Default to pending for moderation
            "parent_id" => $this->security->sanitizeInput(
                $data["parent_id"] ?? null,
                "alphanumeric"),
        ];

        $comments[] = $newComment;

        if ($this->saveComments($postId, $comments)) {
            $post = $this->storage->getPost($postId);
            if ($post) {
                $this->notifications->sendNewCommentNotification(
                    $post,
                    $newComment);
            }
            return [
                "success" => true,
                "message" =>
                    "Your comment has been submitted and is awaiting moderation.",
            ];
        } else {
            return [
                "success" => false,
                "message" => "An error occurred while saving your comment.",
            ];
        }
    }

    /**
     * Updates the status of a specific comment.
     * @param string $postId
     * @param string $commentId
     * @param string $newStatus
     * @return array
     */
    public function updateCommentStatus($postId, $commentId, $newStatus)
    {
        $validStatuses = ["approved", "pending", "spam", "trash"];
        if (!in_array($newStatus, $validStatuses)) {
            return [
                "success" => false,
                "message" => "Invalid comment status provided.",
            ];
        }

        $comments = $this->loadComments($postId);
        $commentFound = false;

        foreach ($comments as &$comment) {
            if ($comment["id"] === $commentId) {
                $comment["status"] = $newStatus;
                $commentFound = true;
                break;
            }
        }

        if (!$commentFound) {
            return ["success" => false, "message" => "Comment not found."];
        }

        if ($this->saveComments($postId, $comments)) {
            return [
                "success" => true,
                "message" => "Comment status updated successfully.",
            ];
        } else {
            return [
                "success" => false,
                "message" => "Failed to update comment status.",
            ];
        }
    }

    /**
     * Deletes a comment permanently.
     * @param string $postId
     * @param string $commentId
     * @return array
     */
    public function deleteComment($postId, $commentId)
    {
        $comments = $this->loadComments($postId);

        $initialCount = count($comments);
        $newComments = array_filter($comments, function ($comment) use (
            $commentId) {
            return $comment["id"] !== $commentId;
        });

        if (count($newComments) === $initialCount) {
            return ["success" => false, "message" => "Comment not found."];
        }

        if ($this->saveComments($postId, array_values($newComments))) {
            return [
                "success" => true,
                "message" => "Comment permanently deleted.",
            ];
        } else {
            return [
                "success" => false,
                "message" => "Failed to delete comment.",
            ];
        }
    }

    /**
     * Gets the number of comments for a post.
     * @param string $postId
     * @param string $status
     * @return int
     */
    public function getCommentCountForPost($postId, $status = "approved")
    {
        return count($this->getCommentsByPostId($postId, $status));
    }

    /**
     * Gets comments across all posts, filtered by status.
     * @param string $status 'approved', 'pending', 'spam', 'trash', or 'all'
     * @return array
     */
    public function getAllComments($status = "pending")
    {
        $results = [];
        $files = glob($this->commentsDir . "/*.json");
        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            $postId = basename($file, ".json");
            $comments = $this->loadComments($postId);

            foreach ($comments as $comment) {
                $cStatus = $comment["status"] ?? "pending";
                if ($status !== "all" && $cStatus !== $status) {
                    continue;
                }

                $post = $this->storage->getPost($postId);
                $comment["_post"] = [
                    "id" => $postId,
                    "title" => $post["title"] ?? "(Unknown post)",
                    "slug" => $post["slug"] ?? "",
                ];

                $results[] = $comment;
            }
        }

        // Newest first
        usort($results, function ($a, $b) {
            return ($b["created_at"] ?? 0) <=> ($a["created_at"] ?? 0);
        });

        return $results;
    }


    /**
     * Gets the total number of pending comments across all posts.
     * @return int
     */
    public function getPendingCount()
    {
        return count($this->getAllComments("pending"));
    }

}
