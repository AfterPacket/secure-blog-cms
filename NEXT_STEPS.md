# 🚀 Next Steps - Implementation Guide

This guide provides step-by-step instructions to implement the remaining requested features for your Secure Blog CMS.

---

## ✅ What's Already Complete

- ✅ **Secure installation wizard** (self-deletes after first login)
- ✅ **XSS, CSRF, and injection protection** (comprehensive security)
- ✅ **SQL-free file-based storage** (zero SQL injection risk)
- ✅ **Admin panel with post management**
- ✅ **Settings page** (configure site, enable features)
- ✅ **Security logging and audit trail**
- ✅ **Backup and restore system**
- ✅ **Search and pagination**
- ✅ **Responsive design**

---

## 🚧 Features to Implement

### 1️⃣ WYSIWYG Editor (TinyMCE Integration)
**Estimated Time:** 1-2 hours  
**Difficulty:** Easy

#### Step 1: Add TinyMCE CDN to create-post.php and edit-post.php

Add before closing `</head>` tag:

```html
<!-- TinyMCE -->
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
tinymce.init({
    selector: '#content',
    height: 500,
    menubar: true,
    plugins: [
        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
        'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
        'insertdatetime', 'media', 'table', 'help', 'wordcount'
    ],
    toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image | removeformat code',
    content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; }',
    
    // Security: Restrict content
    valid_elements: 'p,br,strong,em,u,h1,h2,h3,h4,ul,ol,li,a[href],blockquote,code,pre',
    invalid_elements: 'script,iframe,object,embed',
    
    // Image upload (we'll implement this next)
    images_upload_handler: function (blobInfo, success, failure) {
        // We'll add this functionality in step 2
        failure('Image upload not yet configured');
    }
});
</script>
```

#### Step 2: Update content sanitization

In `includes/Storage.php`, the 'html' sanitization already handles this, but verify ALLOWED_HTML_TAGS in config.php includes all needed tags.

**Test:** Create a post with formatting, verify HTML is properly sanitized.

---

### 2️⃣ Secure Image Upload System
**Estimated Time:** 3-4 hours  
**Difficulty:** Medium

#### Step 1: Create upload-image.php

```php
<?php
define('SECURE_CMS_INIT', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/ImageUpload.php';

$security = Security::getInstance();

// Only authenticated admins can upload
if (!$security->isAuthenticated()) {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized']));
}

// Validate CSRF token
$csrfToken = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
if (!$security->validateCSRFToken($csrfToken, 'image_upload')) {
    http_response_code(403);
    die(json_encode(['error' => 'Invalid CSRF token']));
}

// Check rate limiting
if (!$security->checkRateLimit('upload_' . $_SESSION['user'], 20, 3600)) {
    http_response_code(429);
    die(json_encode(['error' => 'Too many uploads. Wait 1 hour.']));
}

// Handle upload
if (isset($_FILES['file'])) {
    $imageUpload = new ImageUpload();
    $result = $imageUpload->handleUpload($_FILES['file']);
    
    header('Content-Type: application/json');
    echo json_encode($result);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
}
```

#### Step 2: Create includes/ImageUpload.php

```php
<?php
class ImageUpload {
    
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private $maxSize = 5242880; // 5MB
    
    public function handleUpload($file) {
        // Validate upload
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'error' => 'Invalid upload'];
        }
        
        // Check file size
        if ($file['size'] > $this->maxSize) {
            return ['success' => false, 'error' => 'File too large (max 5MB)'];
        }
        
        // Verify MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $this->allowedTypes)) {
            return ['success' => false, 'error' => 'Invalid file type'];
        }
        
        // Verify it's actually an image
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return ['success' => false, 'error' => 'Not a valid image'];
        }
        
        // Check for embedded PHP/backdoors
        if ($this->detectBackdoor($file['tmp_name'])) {
            // LOG THIS - potential attack!
            Security::getInstance()->logSecurityEvent('Backdoor detected in upload', $file['name']);
            return ['success' => false, 'error' => 'Security violation detected'];
        }
        
        // Generate safe filename
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            $extension = 'jpg';
        }
        
        $filename = hash('sha256', uniqid() . $file['name'] . time()) . '.' . $extension;
        $uploadPath = DATA_DIR . '/uploads/images/' . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            return ['success' => false, 'error' => 'Failed to save file'];
        }
        
        // Set permissions
        chmod($uploadPath, 0600);
        
        // Log upload
        Security::getInstance()->logSecurityEvent('Image uploaded', $filename);
        
        // Return URL
        $url = SITE_URL . '/serve-image.php?img=' . urlencode($filename);
        
        return [
            'success' => true,
            'location' => $url,
            'filename' => $filename
        ];
    }
    
    private function detectBackdoor($file) {
        $content = file_get_contents($file);
        
        $patterns = [
            '/<\?php/i',
            '/eval\s*\(/i',
            '/base64_decode/i',
            '/system\s*\(/i',
            '/exec\s*\(/i',
            '/passthru/i',
            '/shell_exec/i',
            '/assert\s*\(/i',
            '/create_function/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
}
```

#### Step 3: Create serve-image.php (safe image delivery)

```php
<?php
define('SECURE_CMS_INIT', true);
require_once __DIR__ . '/config.php';

$filename = $_GET['img'] ?? '';
$filename = basename($filename); // Prevent directory traversal

$imagePath = DATA_DIR . '/uploads/images/' . $filename;

if (!file_exists($imagePath)) {
    http_response_code(404);
    die('Image not found');
}

// Get MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $imagePath);
finfo_close($finfo);

// Security: Force download if not image
if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
    http_response_code(403);
    die('Invalid file type');
}

// Serve image
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($imagePath));
header('Cache-Control: public, max-age=31536000');
readfile($imagePath);
```

#### Step 4: Update TinyMCE image upload handler

```javascript
images_upload_handler: function (blobInfo, success, failure) {
    const formData = new FormData();
    formData.append('file', blobInfo.blob(), blobInfo.filename());
    formData.append('csrf_token', '<?php echo $csrfToken; ?>');
    
    fetch('upload-image.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            success(result.location);
        } else {
            failure(result.error || 'Upload failed');
        }
    })
    .catch(error => {
        failure('Upload failed: ' + error);
    });
}
```

#### Step 5: Add to data/uploads/.htaccess

```apache
# Prevent PHP execution in uploads
<FilesMatch "\.php$">
    Order allow,deny
    Deny from all
</FilesMatch>
```

**Test:** Upload image via TinyMCE, verify it displays in post. Try uploading PHP file (should be blocked).

---

### 3️⃣ Password-Protected Posts
**Estimated Time:** 2-3 hours  
**Difficulty:** Medium

#### Step 1: Update Storage.php post schema

In `createPost()` and `updatePost()`, add fields:

```php
'password_protected' => isset($data['password_protected']) && $data['password_protected'],
'post_password' => !empty($data['post_password']) ? 
    password_hash($data['post_password'], PASSWORD_ARGON2ID) : '',
'password_hint' => $data['password_hint'] ?? ''
```

#### Step 2: Update create-post.php and edit-post.php

Add before status field:

```html
<!-- Password Protection -->
<fieldset style="border: 2px solid #e0e0e0; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
    <legend style="font-weight: 600; color: #2c3e50;">🔒 Password Protection</legend>
    
    <div class="checkbox-item" style="margin-bottom: 15px;">
        <input type="checkbox" id="password_protected" name="password_protected" 
               <?php echo ($post['password_protected'] ?? false) ? 'checked' : ''; ?>>
        <label for="password_protected">
            <strong>Require password to view this post</strong>
        </label>
    </div>
    
    <div id="password-fields" style="display: none;">
        <div class="form-group">
            <label for="post_password">Post Password</label>
            <input type="password" id="post_password" name="post_password" minlength="8">
            <div class="form-help">Minimum 8 characters (leave empty to keep existing)</div>
        </div>
        
        <div class="form-group">
            <label for="password_hint">Password Hint (optional)</label>
            <input type="text" id="password_hint" name="password_hint" 
                   value="<?php echo $security->escapeHTML($post['password_hint'] ?? ''); ?>">
        </div>
    </div>
</fieldset>

<script>
document.getElementById('password_protected').addEventListener('change', function() {
    document.getElementById('password-fields').style.display = 
        this.checked ? 'block' : 'none';
});

// Show on page load if checked
if (document.getElementById('password_protected').checked) {
    document.getElementById('password-fields').style.display = 'block';
}
</script>
```

#### Step 3: Create check-password.php

```php
<?php
define('SECURE_CMS_INIT', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/Storage.php';

$security = Security::getInstance();
$storage = Storage::getInstance();

$postId = $security->getGetData('id', 'alphanumeric', '');
$post = $storage->getPost($postId);

if (!$post || !$post['password_protected']) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $password = $_POST['post_password'] ?? '';
    
    if (!$security->validateCSRFToken($csrfToken, 'post_password')) {
        $error = 'Invalid security token';
    } elseif (password_verify($password, $post['post_password'])) {
        // Correct password - store in session
        $_SESSION['post_passwords'][$postId] = true;
        header('Location: post.php?slug=' . urlencode($post['slug']));
        exit;
    } else {
        $error = 'Incorrect password';
        $security->logSecurityEvent('Failed password attempt for post', $postId);
    }
}

$csrfToken = $security->generateCSRFToken('post_password');
?>
<!-- Create password entry form here (similar to login page) -->
```

#### Step 4: Update post.php

Add at the beginning (after loading post):

```php
// Check if password protected
if ($post && $post['password_protected']) {
    $hasAccess = $_SESSION['post_passwords'][$post['id']] ?? false;
    
    if (!$hasAccess && !$security->isAuthenticated()) {
        header('Location: check-password.php?id=' . urlencode($post['id']));
        exit;
    }
}
```

**Test:** Create password-protected post, verify password is required to view.

---

### 4️⃣ Private Posts (Admin Only)
**Estimated Time:** 1 hour  
**Difficulty:** Easy

#### Step 1: Update post schema in Storage.php

Add to post array:

```php
'visibility' => in_array($data['visibility'] ?? 'public', ['public', 'private']) 
    ? ($data['visibility'] ?? 'public') : 'public'
```

#### Step 2: Add to create-post.php and edit-post.php

```html
<div class="form-group">
    <label for="visibility">Visibility</label>
    <select id="visibility" name="visibility">
        <option value="public" <?php echo ($post['visibility'] ?? 'public') === 'public' ? 'selected' : ''; ?>>
            🌐 Public (Everyone can see)
        </option>
        <option value="private" <?php echo ($post['visibility'] ?? '') === 'private' ? 'selected' : ''; ?>>
            🔒 Private (Admin only)
        </option>
    </select>
</div>
```

#### Step 3: Filter in index.php

```php
// After getting posts
if ($post['visibility'] === 'private' && !$security->isAuthenticated()) {
    continue; // Skip private posts for non-admins
}
```

**Test:** Create private post, verify it's hidden from public, visible when logged in.

---

### 5️⃣ Categories & Tags
**Estimated Time:** 4-5 hours  
**Difficulty:** Medium-Hard

This requires creating a full taxonomy system. Key steps:

1. Create `data/categories.json` structure
2. Create `categories.php` (admin management)
3. Add category selector to post editor
4. Create `category.php` (public category archive)
5. Update Storage.php to filter by category
6. Add category links to post display

Detailed implementation available in separate guide if needed.

---

### 6️⃣ URL Shortener
**Estimated Time:** 2 hours  
**Difficulty:** Easy

#### Step 1: Create includes/UrlShortener.php

```php
<?php
class UrlShortener {
    
    public function generateShortUrl($postSlug) {
        $hash = substr(hash('sha256', $postSlug . time()), 0, 6);
        
        // Store mapping
        $mappingFile = DATA_DIR . '/short-urls.json';
        $mappings = file_exists($mappingFile) 
            ? json_decode(file_get_contents($mappingFile), true) 
            : [];
        
        $mappings[$hash] = $postSlug;
        file_put_contents($mappingFile, json_encode($mappings, JSON_PRETTY_PRINT), LOCK_EX);
        
        return SITE_URL . '/s/' . $hash;
    }
    
    public function resolveShortUrl($hash) {
        $mappingFile = DATA_DIR . '/short-urls.json';
        if (!file_exists($mappingFile)) {
            return null;
        }
        
        $mappings = json_decode(file_get_contents($mappingFile), true);
        return $mappings[$hash] ?? null;
    }
}
```

#### Step 2: Create s.php

```php
<?php
define('SECURE_CMS_INIT', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/UrlShortener.php';

$code = $_GET['code'] ?? '';
$shortener = new UrlShortener();
$slug = $shortener->resolveShortUrl($code);

if ($slug) {
    header('Location: post.php?slug=' . urlencode($slug));
} else {
    header('HTTP/1.0 404 Not Found');
    echo '404 - Short URL not found';
}
```

#### Step 3: Add to .htaccess

```apache
# Short URL rewriting
RewriteRule ^s/([a-zA-Z0-9]+)$ s.php?code=$1 [L]
```

#### Step 4: Add share button to post.php

```html
<button onclick="copyShortUrl()">📋 Copy Short URL</button>
<script>
function copyShortUrl() {
    // Generate and copy short URL
}
</script>
```

**Test:** Generate short URL, verify it redirects correctly.

---

### 7️⃣ Require Login to View Posts
**Estimated Time:** 30 minutes  
**Difficulty:** Easy

#### Already implemented in settings.php!

Just enable in: **Admin > Settings > Feature Settings > "Require Login to View Posts"**

Then update index.php and post.php:

```php
// Load settings
$settingsFile = DATA_DIR . '/settings/site.json';
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true);
    
    if ($settings['require_login_for_posts'] && !$security->isAuthenticated()) {
        header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}
```

**Test:** Enable setting, verify redirect to login when not authenticated.

---

## 📋 Implementation Order

**Recommended sequence:**

1. ✅ **WYSIWYG Editor** (1-2 hours) - Improves content creation
2. ✅ **Image Upload** (3-4 hours) - Completes content management
3. ✅ **Private Posts** (1 hour) - Quick win
4. ✅ **Password Protection** (2-3 hours) - High-value feature
5. ✅ **URL Shortener** (2 hours) - Nice to have
6. ✅ **Require Login** (30 min) - Already mostly done
7. ⏭️ **Categories** (4-5 hours) - Complex, do last if needed

**Total Time Estimate:** 14-17 hours for all features

---

## 🧪 Testing Checklist

After implementing each feature:

- [ ] Test normal operation
- [ ] Test with XSS attempts
- [ ] Test with invalid input
- [ ] Test rate limiting
- [ ] Test file upload exploits (for image upload)
- [ ] Verify security logs
- [ ] Test mobile responsiveness
- [ ] Test with different browsers

---

## 🛡️ Security Reminders

1. **Always validate CSRF tokens**
2. **Always sanitize input**
3. **Always escape output**
4. **Always rate limit uploads/attempts**
5. **Always log security events**
6. **Never trust file extensions**
7. **Never execute uploaded files**
8. **Always use HTTPS in production**

---

## 📚 Resources

- TinyMCE Docs: https://www.tiny.cloud/docs/
- PHP Image Functions: https://www.php.net/manual/en/ref.image.php
- OWASP Upload Guide: https://owasp.org/www-community/vulnerabilities/Unrestricted_File_Upload

---

## 🎯 Quick Start

**To get started right now:**

1. Open `create-post.php`
2. Add TinyMCE script (from section 1)
3. Test post creation with formatting
4. Move to image upload (section 2)

**Need Help?** All security infrastructure is already in place. Just follow the patterns established in existing code.

---

**Good luck! You've got 95% of a production-ready CMS. These additions will make it 100%! 🚀**