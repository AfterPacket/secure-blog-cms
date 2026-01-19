<?php
/**
 * Secure Blog CMS - Image Upload Handler
 * Comprehensive security for image uploads - prevents backdoors, shells, and exploits
 */

if (!defined("SECURE_CMS_INIT")) {
    die("Direct access not permitted");
}

/**
 * ImageUpload Class - Build v19
 */
class ImageUpload
{
    private $allowedMimeTypes = [
        "image/jpeg",
        "image/jpg",
        "image/png",
        "image/gif",
        "image/webp",
    ];

    private $allowedExtensions = ["jpg", "jpeg", "png", "gif", "webp"];

    private $maxFileSize = 5242880; // 5MB
    private $uploadDir;
    private $security;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->uploadDir = DATA_DIR . "/uploads/images";
        $this->security = Security::getInstance();

        // Ensure upload directory exists
        if (!is_dir($this->uploadDir)) {
            if (!@mkdir($this->uploadDir, 0700, true)) {
                error_log(
                    "CRITICAL: Failed to create upload directory: " .
                        $this->uploadDir,
                );
            }
        }

        // Ensure .htaccess exists to prevent PHP execution
        $this->createSecurityFiles();
    }

    /**
     * Create security files in upload directory
     */
    private function createSecurityFiles()
    {
        // .htaccess to prevent PHP execution
        $htaccessFile = $this->uploadDir . "/.htaccess";
        if (!file_exists($htaccessFile)) {
            $htaccessContent = "# Prevent PHP execution\n";
            $htaccessContent .= "<FilesMatch \"\\.php$\">\n";
            $htaccessContent .= "    Order allow,deny\n";
            $htaccessContent .= "    Deny from all\n";
            $htaccessContent .= "</FilesMatch>\n\n";
            $htaccessContent .= "# Prevent access to all files by default\n";
            $htaccessContent .= "Deny from all\n";
            file_put_contents($htaccessFile, $htaccessContent, LOCK_EX);
        }

        // index.php to prevent directory listing
        $indexFile = $this->uploadDir . "/index.php";
        if (!file_exists($indexFile)) {
            file_put_contents(
                $indexFile,
                "<?php header('HTTP/1.0 403 Forbidden'); die('Access denied'); ?>",
                LOCK_EX,
            );
        }
    }

    /**
     * Handle file upload
     */
    public function handleUpload($file)
    {
        // Step 1: Validate upload
        error_log(
            "DEBUG: ImageUpload step 1: Validating upload for " .
                ($file["name"] ?? "unknown"),
        );
        $validation = $this->validateUpload($file);
        if (!$validation["valid"]) {
            error_log(
                "DEBUG: ImageUpload step 1 failed: " .
                    ($validation["error"] ?? "unknown"),
            );
            return [
                "success" => false,
                "error" => $validation["error"],
            ];
        }

        // Step 2: Security checks
        error_log("DEBUG: ImageUpload step 2: Performing security checks");
        $securityCheck = $this->performSecurityChecks(
            $file["tmp_name"],
            $file["name"],
        );
        if (!$securityCheck["safe"]) {
            error_log(
                "DEBUG: ImageUpload step 2 failed: " .
                    ($securityCheck["reason"] ?? "unknown security reason"),
            );
            $this->security->logSecurityEvent(
                "Malicious file upload blocked",
                $file["name"],
            );
            return [
                "success" => false,
                "error" =>
                    "Security violation detected (Build v19). Upload blocked: " .
                    ($securityCheck["reason"] ?? "Unknown"),
            ];
        }

        // Step 3: Generate safe filename
        $extension = $securityCheck["extension"];
        $safeFilename = $this->generateSafeFilename($extension);
        $targetPath = $this->uploadDir . "/" . $safeFilename;
        error_log("DEBUG: ImageUpload step 3: Target path " . $targetPath);

        // Step 4: Move uploaded file
        error_log(
            "DEBUG: ImageUpload step 4: Moving file from " . $file["tmp_name"],
        );
        if (!move_uploaded_file($file["tmp_name"], $targetPath)) {
            error_log(
                "DEBUG: ImageUpload step 4 failed: move_uploaded_file failed. Destination exists? " .
                    (file_exists($targetPath) ? "yes" : "no"),
            );
            return [
                "success" => false,
                "error" => "Failed to save uploaded file",
            ];
        }

        // Step 5: Set secure permissions
        chmod($targetPath, 0600);

        // Step 6: Get image dimensions
        $imageInfo = @getimagesize($targetPath);
        $dimensions = [
            "width" => $imageInfo[0] ?? 0,
            "height" => $imageInfo[1] ?? 0,
        ];

        // Step 7: Log successful upload
        $this->security->logSecurityEvent(
            "Image uploaded successfully",
            $safeFilename,
        );

        // Step 8: Return success with image URL
        return [
            "success" => true,
            "filename" => $safeFilename,
            // Use cms_path() so subfolder installs (e.g. /secure-blog-cms) work
            // even if SITE_URL is not perfectly configured.
            "url" =>
                cms_path("admin/serve-image.php") .
                "?img=" .
                urlencode($safeFilename),
            "path" => $targetPath,
            "size" => filesize($targetPath),
            "dimensions" => $dimensions,
        ];
    }

    /**
     * Validate file upload
     */
    private function validateUpload($file)
    {
        // Check if file was uploaded
        if (!isset($file["tmp_name"]) || !is_uploaded_file($file["tmp_name"])) {
            return ["valid" => false, "error" => "Invalid file upload"];
        }

        // Check for upload errors
        if ($file["error"] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => "File exceeds upload_max_filesize directive",
                UPLOAD_ERR_FORM_SIZE => "File exceeds MAX_FILE_SIZE directive",
                UPLOAD_ERR_PARTIAL => "File was only partially uploaded",
                UPLOAD_ERR_NO_FILE => "No file was uploaded",
                UPLOAD_ERR_NO_TMP_DIR => "Missing temporary folder",
                UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk",
                UPLOAD_ERR_EXTENSION => "File upload stopped by extension",
            ];

            return [
                "valid" => false,
                "error" =>
                    $errorMessages[$file["error"]] ?? "Unknown upload error",
            ];
        }

        // Check file size
        if ($file["size"] > $this->maxFileSize) {
            return [
                "valid" => false,
                "error" =>
                    "File too large. Maximum size: " .
                    $this->maxFileSize / 1024 / 1024 .
                    "MB",
            ];
        }

        // Check if file is empty
        if ($file["size"] == 0) {
            return ["valid" => false, "error" => "Uploaded file is empty"];
        }

        return ["valid" => true];
    }

    /**
     * Perform comprehensive security checks
     */
    private function performSecurityChecks($tmpFile, $originalName)
    {
        // Check 1: Verify MIME type using finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpFile);
        finfo_close($finfo);

        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            return [
                "safe" => false,
                "reason" => "Invalid MIME type: " . $mimeType,
            ];
        }

        // Check 2: Verify it's actually an image using getimagesize
        $imageInfo = @getimagesize($tmpFile);
        if ($imageInfo === false) {
            return [
                "safe" => false,
                "reason" => "Not a valid image file",
            ];
        }

        // Check 3: Validate file extension
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            // Default to jpg if extension is invalid but MIME type is valid
            $extension = "jpg";
        }

        // Check 4: Detect double extensions (e.g., file.php.jpg)
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        if (strpos($basename, ".") !== false) {
            $parts = explode(".", $basename);
            foreach ($parts as $part) {
                // Check if any part is an executable extension
                if (
                    in_array(strtolower($part), [
                        "php",
                        "phtml",
                        "php3",
                        "php4",
                        "php5",
                        "pht",
                        "phar",
                        "phps",
                        "cgi",
                        "pl",
                        "exe",
                        "sh",
                        "bat",
                        "com",
                    ])
                ) {
                    return [
                        "safe" => false,
                        "reason" => "Double extension detected",
                    ];
                }
            }
        }

        // Check 5: Scan for embedded PHP code and backdoors
        if ($this->detectBackdoor($tmpFile)) {
            error_log(
                "ImageUpload: detectBackdoor failed for " . $originalName,
            );
            return [
                "safe" => false,
                "reason" => "Malicious code detected in file",
            ];
        }

        // Check 6: Verify image dimensions (prevent decompression bombs)
        if ($imageInfo[0] > 10000 || $imageInfo[1] > 10000) {
            return [
                "safe" => false,
                "reason" => "Image dimensions too large",
            ];
        }

        // Check 7: Additional EXIF check for JPEG files
        if ($mimeType === "image/jpeg" && function_exists("exif_read_data")) {
            $exif = @exif_read_data($tmpFile);
            if ($exif !== false) {
                // Check for suspicious EXIF data
                $exifString = json_encode($exif);
                if (
                    preg_match(
                        "/<\?php|eval\(|base64_decode|system\(/i",
                        $exifString,
                    )
                ) {
                    return [
                        "safe" => false,
                        "reason" => "Malicious EXIF data detected",
                    ];
                }
            }
        }

        return [
            "safe" => true,
            "extension" => $extension,
            "mime_type" => $mimeType,
        ];
    }

    /**
     * Detect backdoors and malicious code in uploaded files
     */
    private function detectBackdoor($filePath)
    {
        $content = file_get_contents($filePath);

        // Check file size to prevent reading huge files
        if (strlen($content) > 10485760) {
            // 10MB
            return false; // Skip check for very large files
        }

        // Patterns to detect malicious code
        // Build v19: Minimal patterns to avoid binary false positives in image data
        $maliciousPatterns = [
            // PHP tags
            "/<\?php/i",
            "/<\?=/i",
            "/<script[\s>]/i",

            // Dangerous PHP functions (strict check with opening paren)
            "/eval\s*\(/i",
            "/system\s*\(/i",
            "/exec\s*\(/i",
            "/shell_exec\s*\(/i",
            "/passthru\s*\(/i",

            // Common backdoor patterns
            "/c99shell/i",
            "/r57shell/i",
            "/webshell/i",
            "/phpspy/i",
        ];

        foreach ($maliciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true; // Backdoor detected!
            }
        }

        // Check for suspicious binary sequences in image headers
        // JPEG files should start with FFD8
        // PNG files should start with 89504E47
        // GIF files should start with 474946

        $header = substr($content, 0, 10);
        $headerHex = bin2hex($header);

        // JPEG
        if (substr($headerHex, 0, 4) === "ffd8") {
            // Valid JPEG header
        }
        // PNG
        elseif (substr($headerHex, 0, 8) === "89504e47") {
            // Valid PNG header
        }
        // GIF
        elseif (substr($headerHex, 0, 6) === "474946") {
            // Valid GIF header
        }
        // WebP
        elseif (
            strpos($header, "RIFF") === 0 &&
            strpos($content, "WEBP") !== false
        ) {
            // Valid WebP header
        }

        return false;
    }

    /**
     * Generate safe, unique filename
     */
    private function generateSafeFilename($extension)
    {
        // Generate unique filename using hash
        $uniqueId = uniqid("img_", true);
        $random = bin2hex(random_bytes(8));
        $timestamp = time();

        // Create hash from all components
        $hash = hash("sha256", $uniqueId . $random . $timestamp);

        // Take first 32 characters and add extension
        $filename = substr($hash, 0, 32) . "." . $extension;

        // Ensure filename doesn't already exist (very unlikely but check anyway)
        $counter = 0;
        while (file_exists($this->uploadDir . "/" . $filename)) {
            $counter++;
            $filename =
                substr($hash, 0, 32) . "_" . $counter . "." . $extension;
        }

        return $filename;
    }

    /**
     * Delete image file
     */
    public function deleteImage($filename)
    {
        // Sanitize filename to prevent directory traversal
        $filename = basename($filename);
        $filePath = $this->uploadDir . "/" . $filename;

        if (!file_exists($filePath)) {
            return ["success" => false, "error" => "File not found"];
        }

        if (!unlink($filePath)) {
            return ["success" => false, "error" => "Failed to delete file"];
        }

        $this->security->logSecurityEvent("Image deleted", $filename);

        return ["success" => true, "message" => "Image deleted successfully"];
    }

    /**
     * Get all uploaded images
     */
    public function getImages($limit = 50, $offset = 0)
    {
        $images = [];
        $files = glob(
            $this->uploadDir . "/*.{jpg,jpeg,png,gif,webp}",
            GLOB_BRACE,
        );

        if ($files === false) {
            return [];
        }

        // Sort by modification time (newest first)
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        // Apply pagination
        $files = array_slice($files, $offset, $limit);

        foreach ($files as $file) {
            $filename = basename($file);
            $imageInfo = @getimagesize($file);

            $images[] = [
                "filename" => $filename,
                "url" =>
                    cms_path("admin/serve-image.php") .
                    "?img=" .
                    urlencode($filename),
                "size" => filesize($file),
                "uploaded" => filemtime($file),
                "dimensions" => [
                    "width" => $imageInfo[0] ?? 0,
                    "height" => $imageInfo[1] ?? 0,
                ],
            ];
        }

        return $images;
    }

    /**
     * Get total number of uploaded images
     */
    public function getImageCount()
    {
        $files = glob(
            $this->uploadDir . "/*.{jpg,jpeg,png,gif,webp}",
            GLOB_BRACE,
        );
        return $files === false ? 0 : count($files);
    }

    /**
     * Get image info
     */
    public function getImageInfo($filename)
    {
        $filename = basename($filename);
        $filePath = $this->uploadDir . "/" . $filename;

        if (!file_exists($filePath)) {
            return null;
        }

        $imageInfo = @getimagesize($filePath);

        return [
            "filename" => $filename,
            "url" =>
                cms_path("admin/serve-image.php") .
                "?img=" .
                urlencode($filename),
            "path" => $filePath,
            "size" => filesize($filePath),
            "uploaded" => filemtime($filePath),
            "dimensions" => [
                "width" => $imageInfo[0] ?? 0,
                "height" => $imageInfo[1] ?? 0,
            ],
            "mime_type" => $imageInfo["mime"] ?? "",
        ];
    }

    /**
     * Create thumbnail (optional feature)
     */
    public function createThumbnail(
        $filename,
        $maxWidth = 300,
        $maxHeight = 300,
    ) {
        $filename = basename($filename);
        $sourcePath = $this->uploadDir . "/" . $filename;

        if (!file_exists($sourcePath)) {
            return ["success" => false, "error" => "Source file not found"];
        }

        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            return ["success" => false, "error" => "Invalid image file"];
        }

        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];
        $mimeType = $imageInfo["mime"];

        // Calculate thumbnail dimensions
        $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
        $thumbWidth = intval($sourceWidth * $ratio);
        $thumbHeight = intval($sourceHeight * $ratio);

        // Create source image
        switch ($mimeType) {
            case "image/jpeg":
            case "image/jpg":
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case "image/png":
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case "image/gif":
                $sourceImage = imagecreatefromgif($sourcePath);
                break;
            case "image/webp":
                $sourceImage = imagecreatefromwebp($sourcePath);
                break;
            default:
                return [
                    "success" => false,
                    "error" => "Unsupported image type",
                ];
        }

        if ($sourceImage === false) {
            return [
                "success" => false,
                "error" => "Failed to create image resource",
            ];
        }

        // Create thumbnail image
        $thumbImage = imagecreatetruecolor($thumbWidth, $thumbHeight);

        // Preserve transparency for PNG and GIF
        if ($mimeType === "image/png" || $mimeType === "image/gif") {
            imagealphablending($thumbImage, false);
            imagesavealpha($thumbImage, true);
            $transparent = imagecolorallocatealpha(
                $thumbImage,
                255,
                255,
                255,
                127,
            );
            imagefilledrectangle(
                $thumbImage,
                0,
                0,
                $thumbWidth,
                $thumbHeight,
                $transparent,
            );
        }

        // Resize image
        imagecopyresampled(
            $thumbImage,
            $sourceImage,
            0,
            0,
            0,
            0,
            $thumbWidth,
            $thumbHeight,
            $sourceWidth,
            $sourceHeight,
        );

        // Generate thumbnail filename
        $pathInfo = pathinfo($filename);
        $thumbFilename =
            $pathInfo["filename"] . "_thumb." . $pathInfo["extension"];
        $thumbPath = $this->uploadDir . "/" . $thumbFilename;

        // Save thumbnail
        $success = false;
        switch ($mimeType) {
            case "image/jpeg":
            case "image/jpg":
                $success = imagejpeg($thumbImage, $thumbPath, 85);
                break;
            case "image/png":
                $success = imagepng($thumbImage, $thumbPath, 8);
                break;
            case "image/gif":
                $success = imagegif($thumbImage, $thumbPath);
                break;
            case "image/webp":
                $success = imagewebp($thumbImage, $thumbPath, 85);
                break;
        }

        // Free memory
        imagedestroy($sourceImage);
        imagedestroy($thumbImage);

        if (!$success) {
            return ["success" => false, "error" => "Failed to save thumbnail"];
        }

        chmod($thumbPath, 0600);

        return [
            "success" => true,
            "filename" => $thumbFilename,
            "url" =>
                cms_path("admin/serve-image.php") .
                "?img=" .
                urlencode($thumbFilename),
        ];
    }
}
