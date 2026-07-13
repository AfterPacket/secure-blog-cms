<?php
/**
 * Secure Blog CMS - Comment Moderation
 */

define("SECURE_CMS_INIT", true);

require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/Security.php";
require_once __DIR__ . "/../includes/Storage.php";
require_once __DIR__ . "/../includes/notifications.php";
require_once __DIR__ . "/../includes/comments.php";

$security = Security::getInstance();
$storage = Storage::getInstance();
$commentsManager = Comments::getInstance();

if (!$security->isAuthenticated()) {
    header("Location: login.php");
    exit();
}

if (($_SESSION["role"] ?? "") !== "admin") {
    header("HTTP/1.1 403 Forbidden");
    echo '<!doctype html><meta charset="utf-8"><title>403 Forbidden</title><div style="font-family:system-ui;padding:20px"><h1>403 Forbidden</h1><p>Your account does not have permission to moderate comments.</p><p><a href="admin.php">Back to dashboard</a></p></div>';
    exit();
}

$message = "";
$messageType = "";

// Handle moderation actions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrfToken = $_POST["csrf_token"] ?? "";
    if (!$security->validateCSRFToken($csrfToken, "comments_form")) {
        $message = "Invalid security token. Please try again.";
        $messageType = "error";
        $security->logSecurityEvent("CSRF validation failed on comment moderation", $_SESSION["user"] ?? "unknown");
    } else {
        $action = $security->getPostData("action", "string", "");
        $postId = $security->getPostData("post_id", "alphanumeric", "");
        $commentId = $security->getPostData("comment_id", "alphanumeric", "");

        if ($action === "delete") {
            $result = $commentsManager->deleteComment($postId, $commentId);
        } else {
            $map = [
                "approve" => "approved",
                "pending" => "pending",
                "spam" => "spam",
                "trash" => "trash",
            ];
            $newStatus = $map[$action] ?? "";
            if ($newStatus === "") {
                $result = ["success" => false, "message" => "Invalid action."];
            } else {
                $result = $commentsManager->updateCommentStatus($postId, $commentId, $newStatus);
            }
        }

        $message = $result["message"] ?? "";
        $messageType = !empty($result["success"]) ? "success" : "error";
    }
}

$csrfToken = $security->generateCSRFToken("comments_form");

// Filter
$validStatuses = ["pending", "approved", "spam", "trash", "all"];
$status = $security->getGetData("status", "string", "pending");
$status = in_array($status, $validStatuses, true) ? $status : "pending";

$comments = $commentsManager->getAllComments($status);
$pendingCount = $commentsManager->getPendingCount();

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function excerpt($s, $len = 140) {
    $s = trim((string)$s);
    // mbstring isn't guaranteed on all hosts; fall back safely.
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($s) <= $len) return $s;
        return mb_substr($s, 0, $len - 1) . "…";
    }
    if (strlen($s) <= $len) return $s;
    return substr($s, 0, $len - 1) . "...";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Comment Moderation - <?php echo $security->escapeHTML(SITE_NAME); ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: #f4f7f6; color: #333; margin: 0; line-height: 1.6; }
        .admin-header { background-color: #2c3e50; color: #fff; padding: 1rem 0; }
        .admin-header .container { display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto; padding: 0 1rem; gap: 10px; flex-wrap: wrap; }
        .admin-header h1 { margin: 0; font-size: 1.5rem; }
        .admin-header h1 .badge { font-size: 0.8rem; background: #f39c12; padding: 2px 8px; border-radius: 999px; vertical-align: middle; margin-left: 8px; }
        .admin-nav a { color: #fff; text-decoration: none; margin-left: 1rem; }
        .admin-nav a:hover { text-decoration: underline; }

        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
        .card { background-color: #fff; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); padding: 1.5rem; }

        .tabs { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px; }
        .tab { display: inline-block; padding: 8px 12px; border-radius: 999px; text-decoration: none; background: #ecf0f1; color: #2c3e50; font-weight: 600; font-size: 14px; }
        .tab.active { background: #3498db; color: #fff; }

        .alert { padding: 1rem; margin-bottom: 1rem; border-radius: 4px; border: 1px solid transparent; }
        .alert-success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #ecf0f1; vertical-align: top; }
        thead { background-color: #f9fafb; }

        .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .pill-pending { background:#fff3cd; color:#856404; }
        .pill-approved { background:#d4edda; color:#155724; }
        .pill-spam { background:#f8d7da; color:#721c24; }
        .pill-trash { background:#e2e3e5; color:#383d41; }

        .actions { display: flex; gap: 6px; flex-wrap: wrap; }
        .btn { padding: 6px 10px; border: 0; border-radius: 6px; cursor: pointer; font-weight: 700; font-size: 13px; }
        .btn-approve { background:#27ae60; color:#fff; }
        .btn-pending { background:#f39c12; color:#fff; }
        .btn-spam { background:#e74c3c; color:#fff; }
        .btn-trash { background:#7f8c8d; color:#fff; }
        .btn-delete { background:#c0392b; color:#fff; }
        .btn:hover { opacity: 0.92; }

        .muted { color:#666; font-size: 13px; }
        .empty { text-align:center; padding: 2rem; color:#777; }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container">
            <h1>💬 Comment Moderation <?php if ($pendingCount > 0): ?><span class="badge"><?php echo number_format($pendingCount); ?> pending</span><?php endif; ?></h1>
            <div class="admin-nav">
                <a href="admin.php">← Dashboard</a>
                <a href="<?php echo cms_path('index.php'); ?>" target="_blank">👁️ View Blog</a>
                <a href="logout.php">🚪 Logout</a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $security->escapeHTML($messageType); ?>"><?php echo $security->escapeHTML($message); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="tabs">
                <a class="tab <?php echo $status==='pending'?'active':''; ?>" href="comments.php?status=pending">Pending</a>
                <a class="tab <?php echo $status==='approved'?'active':''; ?>" href="comments.php?status=approved">Approved</a>
                <a class="tab <?php echo $status==='spam'?'active':''; ?>" href="comments.php?status=spam">Spam</a>
                <a class="tab <?php echo $status==='trash'?'active':''; ?>" href="comments.php?status=trash">Trash</a>
                <a class="tab <?php echo $status==='all'?'active':''; ?>" href="comments.php?status=all">All</a>
            </div>

            <?php if (empty($comments)): ?>
                <div class="empty">No comments in this view.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Post</th>
                            <th>Author</th>
                            <th>Comment</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comments as $c): ?>
                            <?php $p = $c['_post'] ?? []; ?>
                            <tr>
                                <td>
                                    <div><strong><?php echo h($p['title'] ?? '(Unknown post)'); ?></strong></div>
                                    <?php if (!empty($p['slug'])): ?>
                                        <div class="muted"><a href="<?php echo cms_path('post.php'); ?>?slug=<?php echo $security->escapeURL($p['slug']); ?>" target="_blank">View post</a></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><strong><?php echo h($c['author_name'] ?? ''); ?></strong></div>
                                    <?php if (!empty($c['author_email'])): ?><div class="muted"><?php echo h($c['author_email']); ?></div><?php endif; ?>
                                    <div class="muted"><?php echo h(date('Y-m-d H:i', (int)($c['created_at'] ?? time()))); ?></div>
                                </td>
                                <td><?php echo h(excerpt($c['content'] ?? '')); ?></td>
                                <td>
                                    <?php
                                        $st = $c['status'] ?? 'pending';
                                        $pill = 'pill-pending';
                                        if ($st==='approved') $pill='pill-approved';
                                        elseif ($st==='spam') $pill='pill-spam';
                                        elseif ($st==='trash') $pill='pill-trash';
                                    ?>
                                    <span class="pill <?php echo h($pill); ?>"><?php echo h($st); ?></span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <form method="post" action="comments.php?status=<?php echo h($status); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                            <input type="hidden" name="post_id" value="<?php echo h($c['post_id'] ?? ''); ?>">
                                            <input type="hidden" name="comment_id" value="<?php echo h($c['id'] ?? ''); ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button class="btn btn-approve" type="submit">Approve</button>
                                        </form>
                                        <form method="post" action="comments.php?status=<?php echo h($status); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                            <input type="hidden" name="post_id" value="<?php echo h($c['post_id'] ?? ''); ?>">
                                            <input type="hidden" name="comment_id" value="<?php echo h($c['id'] ?? ''); ?>">
                                            <input type="hidden" name="action" value="pending">
                                            <button class="btn btn-pending" type="submit">Pending</button>
                                        </form>
                                        <form method="post" action="comments.php?status=<?php echo h($status); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                            <input type="hidden" name="post_id" value="<?php echo h($c['post_id'] ?? ''); ?>">
                                            <input type="hidden" name="comment_id" value="<?php echo h($c['id'] ?? ''); ?>">
                                            <input type="hidden" name="action" value="spam">
                                            <button class="btn btn-spam" type="submit">Spam</button>
                                        </form>
                                        <form method="post" action="comments.php?status=<?php echo h($status); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                            <input type="hidden" name="post_id" value="<?php echo h($c['post_id'] ?? ''); ?>">
                                            <input type="hidden" name="comment_id" value="<?php echo h($c['id'] ?? ''); ?>">
                                            <input type="hidden" name="action" value="trash">
                                            <button class="btn btn-trash" type="submit">Trash</button>
                                        </form>
                                        <form method="post" action="comments.php?status=<?php echo h($status); ?>" onsubmit="return confirm('Permanently delete this comment?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                            <input type="hidden" name="post_id" value="<?php echo h($c['post_id'] ?? ''); ?>">
                                            <input type="hidden" name="comment_id" value="<?php echo h($c['id'] ?? ''); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button class="btn btn-delete" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
<?php include APP_ROOT . '/templates/footer.php'; ?>
</body>
</html>
