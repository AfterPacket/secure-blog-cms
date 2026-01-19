<?php
/**
 * Secure Blog CMS - Edit Post Page
 * Secure post editing with CSRF protection and input validation
 */

// Initialize security constant
define("SECURE_CMS_INIT", true);

// Load configuration
require_once __DIR__ . "/../includes/config.php";

// Load required classes
require_once __DIR__ . "/../includes/Security.php";
require_once __DIR__ . "/../includes/Storage.php";
require_once __DIR__ . "/../includes/categories.php";

// Initialize security and storage
$security = Security::getInstance();
$storage = Storage::getInstance();
$categoriesManager = Categories::getInstance();

// Check authentication
if (!$security->isAuthenticated()) {
    header("Location: login.php");
    exit();
}

$message = "";
$messageType = "";
$post = null;

// Get post ID
$postId = $security->getGetData("id", "alphanumeric", "");

if (empty($postId)) {
    header("Location: admin.php");
    exit();
}

// Load categories
$allCategories = $categoriesManager->getAllCategories();

// Load existing post
$post = $storage->getPost($postId);

if (!$post) {
    header("Location: admin.php");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrfToken = $_POST["csrf_token"] ?? "";

    if (!$security->validateCSRFToken($csrfToken, "edit_post_form")) {
        $message = "Invalid security token. Please try again.";
        $messageType = "error";
        $security->logSecurityEvent(
            "CSRF validation failed on post edit",
            $_SESSION["user"],
        );
    } else {
        // Get form data
        $formData = [
            "title" => $security->getPostData("title", "string", ""),
            "content" => $security->getPostData("content", "html", ""),
            "excerpt" => $security->getPostData("excerpt", "string", ""),
            "slug" => $security->getPostData("slug", "slug", ""),
            "status" => $security->getPostData("status", "string", "draft"),
            "meta_description" => $security->getPostData(
                "meta_description",
                "string",
                "",
            ),
            "meta_keywords" => $security->getPostData(
                "meta_keywords",
                "string",
                "",
            ),
            "password_protected" => !empty($_POST["password_protected"]),
            "post_password" => $security->getPostData(
                "post_password",
                "string",
                "",
            ),
            "visibility" => $security->getPostData(
                "visibility",
                "string",
                "public",
            ),
            "categories" =>
                isset($_POST["categories"]) && is_array($_POST["categories"])
                    ? array_map("strip_tags", $_POST["categories"])
                    : [],
            "tags" => $security->getPostData("tags", "string", ""),
        ];

        // Validate required fields
        if (empty($formData["title"])) {
            $message = "Title is required";
            $messageType = "error";
        } elseif (empty($formData["content"])) {
            $message = "Content is required";
            $messageType = "error";
        } else {
            // Update post
            $result = $storage->updatePost($postId, $formData);
            $message = $result["message"];
            $messageType = $result["success"] ? "success" : "error";

            if ($result["success"]) {
                // Reload post data
                $post = $storage->getPost($postId);
                // Redirect after a short delay
                header("Refresh: 2; url=admin.php");
            }
        }
    }
}

// Generate CSRF token
$csrfToken = $security->generateCSRFToken("edit_post_form");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- CMS_BUILD_MARKER: v20 added novalidate to form -->
    <!-- CMS_BUILD_MARKER: v12 use images_upload_url (no custom handler) -->
    <!-- CMS_BUILD_MARKER: v11 images_upload_handler async -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Edit Post - <?php echo $security->escapeHTML(SITE_NAME); ?></title>


    <!-- TinyMCE WYSIWYG Editor -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/8.1.2/tinymce.min.js" referrerpolicy="origin"></script>
<style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            line-height: 1.6;
        }

        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .admin-header .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .admin-header h1 {
            font-size: 24px;
        }

        .admin-nav {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .admin-nav a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 4px;
            transition: background 0.3s;
            font-size: 14px;
        }

        .admin-nav a:hover {
            background: rgba(255,255,255,0.3);
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .post-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
            font-size: 13px;
            color: #555;
        }

        .post-info strong {
            color: #2c3e50;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input[type="text"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 16px;
            font-family: inherit;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .form-group input[type="text"]:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            min-height: 400px;
            resize: vertical;
            line-height: 1.6;
        }

        .form-help {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 6px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.3s, transform 0.2s;
            display: inline-block;
        }

        .btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #5568d3 0%, #64398b 100%);
        }

        .btn-success {
            background: #27ae60;
        }

        .btn-success:hover {
            background: #229954;
        }

        .btn-secondary {
            background: #95a5a6;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .btn-danger {
            background: #e74c3c;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .char-count {
            font-size: 12px;
            color: #7f8c8d;
            text-align: right;
            margin-top: 4px;
        }

        .char-count.warning {
            color: #f39c12;
        }

        .char-count.danger {
            color: #e74c3c;
        }

        .security-badge {
            display: inline-block;
            background: #27ae60;
            color: white;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }

        .allowed-tags {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 4px;
            margin-top: 8px;
            font-size: 12px;
            color: #555;
            line-height: 1.8;
        }

        .allowed-tags code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }

        @media (max-width: 768px) {
            .admin-header .container {
                flex-direction: column;
                align-items: flex-start;
            }

            .container {
                padding: 20px 10px;
            }

            .card {
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions .btn {
                width: 100%;
            }
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }
        .checkbox-item input[type="checkbox"] {
            width: auto;
            margin-right: 8px;
            flex-shrink: 0;
        }
        .checkbox-item label {
            font-weight: normal;
        }
    </style>

    <script>
        // CSRF token for image uploads (allows multiple uploads within token lifetime)
        const imageCsrfToken = '<?php echo $security->generateCSRFToken(
            "image_upload",
        ); ?>';

        console.log('%c CMS Debug: edit-post.php loaded (Build v20) ', 'background: #ff0000; color: #ffffff; font-weight: bold; font-size: 16px;');

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof tinymce === 'undefined') {
                console.error('TinyMCE failed to load. Check CSP / CDN access.');
                return;
            }
            tinymce.init({

  license_key: 'gpl',
selector: '#content',
                height: 500,
                menubar: true,
                branding: false,
                promotion: false,
                plugins: [
                    'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                    'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                    'insertdatetime', 'media', 'table', 'help', 'wordcount'
                ],
                toolbar: 'undo redo | blocks | bold italic underline strikethrough | ' +
                         'alignleft aligncenter alignright alignjustify | ' +
                         'bullist numlist outdent indent | link image | ' +
                         'removeformat code fullscreen | help',

                content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; }',

                valid_elements: 'p,br,strong,em,u,h1,h2,h3,h4,ul,ol,li,a[href|target],blockquote,code,pre,img[src|alt|title|width|height]',
                invalid_elements: 'script,iframe,object,embed,applet',

                // Image upload handler (TinyMCE 6+ compatible)
                images_upload_handler: function (blobInfo, progress) {
                    console.log('CMS Debug: images_upload_handler triggered (Promise-based)');
                    console.log('File:', blobInfo.filename(), blobInfo.blob().size, 'bytes');
                    return new Promise(function (resolve, reject) {
                        const xhr = new XMLHttpRequest();
                        xhr.withCredentials = true;
                        xhr.open('POST', '<?php echo cms_path(
                            "admin/upload-image.php",
                        ); ?>?csrf_token=' + encodeURIComponent(imageCsrfToken) + '&v=' + new Date().getTime());

                        xhr.upload.onprogress = function (e) {
                            progress(e.loaded / e.total * 100);
                        };

                        xhr.onload = function () {
                            console.log('CMS Debug: XHR response:', xhr.status, xhr.responseText);
                            if (xhr.status < 200 || xhr.status >= 300) {
                                reject('HTTP Error: ' + xhr.status);
                                return;
                            }

                            try {
                                const json = JSON.parse(xhr.responseText);
                                if (!json || typeof json.location !== 'string') {
                                    reject('Invalid response from server');
                                    return;
                                }
                                resolve(json.location);
                            } catch (err) {
                                reject('JSON Parse Error: ' + err.message);
                            }
                        };

                        xhr.onerror = function () {
                            reject('Image upload failed (Network Error)');
                        };

                        const formData = new FormData();
                        formData.append('file', blobInfo.blob(), blobInfo.filename());

                        xhr.send(formData);
                    });
                },
                automatic_uploads: true,



                paste_data_images: true,
                paste_as_text: false,
                relative_urls: false,
                remove_script_host: false,
                convert_urls: true
            });
        });
    </script>

</head>
<body>
    <div class="admin-header">
        <div class="container">
            <h1>✏️ Edit Post <span class="security-badge">SECURED</span></h1>
            <div class="admin-nav">
                <a href="admin.php">← Back to Dashboard</a>
                <a href="<?php echo cms_path(
                    "post.php",
                ); ?>?slug=<?php echo $security->escapeURL(
    $post["slug"],
); ?>" target="_blank">👁️ View Post</a>
                <a href="<?php echo cms_path(
                    "index.php",
                ); ?>" target="_blank">👁️ View Blog</a>
                <?php if (($_SESSION["role"] ?? "") === "admin"): ?>
                    <a href="comments.php">💬 Comments</a>
                <?php endif; ?>
                <a href="logout.php">🚪 Logout</a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $security->escapeHTML(
                $messageType,
            ); ?>">
                <?php echo $security->escapeHTML($message); ?>
                <?php if ($messageType === "success"): ?>
                    <br><small>Redirecting to dashboard...</small>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="post-info">
                <strong>Post ID:</strong> <?php echo $security->escapeHTML(
                    $post["id"],
                ); ?> |
                <strong>Created:</strong> <?php echo date(
                    "Y-m-d H:i:s",
                    $post["created_at"],
                ); ?> |
                <strong>Last Updated:</strong> <?php echo date(
                    "Y-m-d H:i:s",
                    $post["updated_at"],
                ); ?> |
                <strong>Views:</strong> <?php echo number_format(
                    $post["views"],
                ); ?>
            </div>

            <form method="post" action="edit-post.php?id=<?php echo $security->escapeURL(
                $postId,
            ); ?>" id="postForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $security->escapeHTML(
                    $csrfToken,
                ); ?>">

                <!-- Title -->
                <div class="form-group">
                    <label for="title">Post Title *</label>
                    <input
                        type="text"
                        id="title"
                        name="title"
                        required
                        maxlength="<?php echo MAX_POST_TITLE_LENGTH; ?>"
                        value="<?php echo $security->escapeHTML(
                            $post["title"],
                        ); ?>"
                        placeholder="Enter post title..."
                    >
                    <div class="char-count" id="titleCount">0 / <?php echo MAX_POST_TITLE_LENGTH; ?></div>
                </div>

                <!-- Slug -->
                <div class="form-group">
                    <label for="slug">URL Slug</label>
                    <input
                        type="text"
                        id="slug"
                        name="slug"
                        pattern="[a-z0-9\-]+"
                        value="<?php echo $security->escapeHTML(
                            $post["slug"],
                        ); ?>"
                        placeholder="url-slug"
                    >
                    <div class="form-help">
                        Use only lowercase letters, numbers, and hyphens. Changing this will break existing links.
                    </div>
                </div>

                <!-- Content -->
                <div class="form-group">
                    <label for="content">Post Content *</label>
                    <textarea
                        id="content"
                        name="content"
                        maxlength="<?php echo MAX_POST_CONTENT_LENGTH; ?>"
                        placeholder="Write your post content here..."
                    ><?php echo $security->escapeHTML(
                        $post["content"],
                    ); ?></textarea>
                    <div class="char-count" id="contentCount">0 / <?php echo number_format(
                        MAX_POST_CONTENT_LENGTH,
                    ); ?></div>
                    <div class="allowed-tags">
                        <strong>Allowed HTML tags:</strong><br>
                        <?php echo $security->escapeHTML(ALLOWED_HTML_TAGS); ?>
                    </div>
                </div>

                <!-- Excerpt -->
                <div class="form-group">
                    <label for="excerpt">Excerpt</label>
                    <textarea
                        id="excerpt"
                        name="excerpt"
                        style="min-height: 100px;"
                        maxlength="<?php echo MAX_POST_EXCERPT_LENGTH; ?>"
                        placeholder="Brief summary of the post..."
                    ><?php echo $security->escapeHTML(
                        $post["excerpt"],
                    ); ?></textarea>
                    <div class="char-count" id="excerptCount">0 / <?php echo MAX_POST_EXCERPT_LENGTH; ?></div>
                </div>

                <!-- Categories & Tags -->
                <div class="form-group">
                    <label>Categories & Tags</label>
                    <div style="border: 1px solid #eee; padding: 15px; border-radius: 4px;">
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label for="categories" style="font-weight: bold;">Categories</label>
                            <div style="max-height: 150px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; border-radius: 4px;">
                                <?php if (!empty($allCategories)): ?>
                                    <?php foreach (
                                        $allCategories
                                        as $category
                                    ): ?>
                                        <div class="checkbox-item">
                                            <input type="checkbox" id="category_<?php echo $security->escapeHTML(
                                                $category["slug"],
                                            ); ?>" name="categories[]" value="<?php echo $security->escapeHTML(
    $category["slug"],
); ?>"
                                                <?php echo in_array(
                                                    $category["slug"],
                                                    $post["categories"] ?? [],
                                                )
                                                    ? "checked"
                                                    : ""; ?>>
                                            <label for="category_<?php echo $security->escapeHTML(
                                                $category["slug"],
                                            ); ?>"><?php echo $security->escapeHTML(
    $category["name"],
); ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p style="margin: 0; color: #777;">No categories found. <a href="categories.php">Add a category</a>.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="tags" style="font-weight: bold;">Tags</label>
                            <input type="text" id="tags" name="tags" value="<?php echo $security->escapeHTML(
                                $post["tags"] ?? "",
                            ); ?>" placeholder="tag1, tag2, tag3">
                            <div class="form-help">Comma-separated tags. New tags will be created automatically.</div>
                        </div>
                    </div>
                </div>

                <!-- Meta Information -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="meta_description">Meta Description (SEO)</label>
                        <input
                            type="text"
                            id="meta_description"
                            name="meta_description"
                            maxlength="160"
                            value="<?php echo $security->escapeHTML(
                                $post["meta_description"] ?? "",
                            ); ?>"
                            placeholder="SEO description..."
                        >
                        <div class="form-help">Recommended: 150-160 characters</div>
                    </div>

                    <div class="form-group">
                        <label for="meta_keywords">Meta Keywords (SEO)</label>
                        <input
                            type="text"
                            id="meta_keywords"
                            name="meta_keywords"
                            maxlength="200"
                            value="<?php echo $security->escapeHTML(
                                $post["meta_keywords"] ?? "",
                            ); ?>"
                            placeholder="keyword1, keyword2, keyword3..."
                        >
                        <div class="form-help">Comma-separated keywords</div>
                    </div>
                </div>

                <!-- Post Security Options -->
                <div class="form-group">
                    <label>Security & Visibility</label>
                    <div style="border: 1px solid #eee; padding: 15px; border-radius: 4px;">

                        <!-- Visibility -->
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label for="visibility" style="font-weight: bold; display: block; margin-bottom: 5px;">Visibility</label>
                            <select id="visibility" name="visibility">
                                <option value="public" <?php echo ($post[
                                    "visibility"
                                ] ??
                                    "public") ===
                                "public"
                                    ? "selected"
                                    : ""; ?>>
                                    🌍 Public (Visible to everyone)
                                </option>
                                <option value="private" <?php echo ($post[
                                    "visibility"
                                ] ??
                                    "") ===
                                "private"
                                    ? "selected"
                                    : ""; ?>>
                                    🔒 Private (Visible only to logged-in admins)
                                </option>
                            </select>
                        </div>

                        <!-- Password Protection -->
                        <div>
                            <label style="font-weight: bold; display: block; margin-bottom: 5px;">Password Protection</label>
                            <div>
                                <input type="checkbox" id="password_protected" name="password_protected" value="1" <?php echo !empty(
                                    $post["password_protected"]
                                )
                                    ? "checked"
                                    : ""; ?> onchange="document.getElementById('password-field-container').style.display = this.checked ? 'block' : 'none';" style="width: auto; margin-right: 8px;">
                                <label for="password_protected" style="font-weight: normal; display: inline;">Require a password to view this post</label>
                            </div>
                            <div id="password-field-container" style="display: <?php echo !empty(
                                $post["password_protected"]
                            )
                                ? "block"
                                : "none"; ?>; margin-top: 10px;">
                                <input type="text" name="post_password" placeholder="Enter new password to change, or leave blank..." value="">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status -->
                <div class="form-group">
                    <label for="status">Post Status *</label>
                    <select id="status" name="status" required>
                        <option value="draft" <?php echo $post["status"] ===
                        "draft"
                            ? "selected"
                            : ""; ?>>
                            📄 Draft (Not visible to public)
                        </option>
                        <option value="published" <?php echo $post["status"] ===
                        "published"
                            ? "selected"
                            : ""; ?>>
                            ✅ Published (Visible to public)
                        </option>
                    </select>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        💾 Update Post
                    </button>
                    <button type="submit" name="status" value="published" class="btn btn-success">
                        ✅ Update & Publish
                    </button>
                    <button type="submit" name="status" value="draft" class="btn btn-secondary">
                        📄 Update & Save as Draft
                    </button>
                    <a href="admin.php" class="btn btn-secondary">
                        ❌ Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Character counting for inputs
        function updateCharCount(inputId, countId, maxLength) {
            const input = document.getElementById(inputId);
            const counter = document.getElementById(countId);

            function update() {
                const length = input.value.length;
                counter.textContent = length.toLocaleString() + ' / ' + maxLength.toLocaleString();

                // Color coding
                const percentage = (length / maxLength) * 100;
                counter.classList.remove('warning', 'danger');

                if (percentage >= 90) {
                    counter.classList.add('danger');
                } else if (percentage >= 75) {
                    counter.classList.add('warning');
                }
            }

            input.addEventListener('input', update);
            update();
        }

        // Initialize character counters
        updateCharCount('title', 'titleCount', <?php echo MAX_POST_TITLE_LENGTH; ?>);
        updateCharCount('content', 'contentCount', <?php echo MAX_POST_CONTENT_LENGTH; ?>);
        updateCharCount('excerpt', 'excerptCount', <?php echo MAX_POST_EXCERPT_LENGTH; ?>);

        // Warn before leaving with unsaved changes
        let formChanged = false;
        const form = document.getElementById('postForm');
        const inputs = form.querySelectorAll('input, textarea, select');

        // Store original values
        const originalValues = {};
        inputs.forEach(input => {
            originalValues[input.name] = input.value;
            input.addEventListener('change', function() {
                formChanged = this.value !== originalValues[this.name];
            });
        });

        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                return e.returnValue;
            }
        });

        form.addEventListener('submit', function() {
            formChanged = false;
        });

        // Form validation
        form.addEventListener('submit', function(e) {
            // Trigger TinyMCE save to update the underlying textarea
            if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                tinymce.get('content').save();
            }

            const title = document.getElementById('title').value.trim();
            const content = document.getElementById('content').value.trim();

            if (!title) {
                alert('Title is required');
                e.preventDefault();
                return;
            }

            if (!content) {
                alert('Content is required');
                e.preventDefault();
                return;
            }

            // Disable submit button to prevent double submission
            const submitButtons = form.querySelectorAll('button[type="submit"]');
            submitButtons.forEach(btn => {
                btn.disabled = true;
                btn.textContent = '⏳ Updating...';
            });
        });
    </script>
<?php include APP_ROOT . "/templates/footer.php"; ?>
</body>
</html>
