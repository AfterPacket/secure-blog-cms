<?php
/**
 * Secure Blog CMS - URL Shortener
 * Generate and manage short URLs for blog posts
 */

if (!defined('SECURE_CMS_INIT')) {
    die('Direct access not permitted');
}

class UrlShortener {

    private $mappingFile;
    private $statsFile;
    private $security;

    /**
     * Constructor
     */
    public function __construct() {
        $this->mappingFile = DATA_DIR . '/short-urls.json';
        $this->statsFile = DATA_DIR . '/short-urls-stats.json';
        $this->security = Security::getInstance();
    }

    /**
     * Generate short URL for a post
     */
    public function generateShortUrl($postSlug) {
        // Sanitize post slug
        $postSlug = $this->security->sanitizeInput($postSlug, 'slug');

        if (empty($postSlug)) {
            return ['success' => false, 'error' => 'Invalid post slug'];
        }

        // Check if short URL already exists for this slug
        $existingCode = $this->findExistingCode($postSlug);
        if ($existingCode) {
            return [
                'success' => true,
                'code' => $existingCode,
                'url' => SITE_URL . '/s/' . $existingCode,
                'existing' => true
            ];
        }

        // Generate unique short code
        $code = $this->generateUniqueCode();

        // Load existing mappings
        $mappings = $this->loadMappings();

        // Add new mapping
        $mappings[$code] = [
            'slug' => $postSlug,
            'created' => time(),
            'created_by' => $_SESSION['user'] ?? 'system',
            'clicks' => 0
        ];

        // Save mappings
        if (!$this->saveMappings($mappings)) {
            return ['success' => false, 'error' => 'Failed to save short URL'];
        }

        // Log creation
        $this->security->logSecurityEvent('Short URL created', $code . ' -> ' . $postSlug);

        return [
            'success' => true,
            'code' => $code,
            'url' => SITE_URL . '/s/' . $code,
            'existing' => false
        ];
    }

    /**
     * Generate unique short code
     */
    private function generateUniqueCode($length = 6) {
        $mappings = $this->loadMappings();
        $maxAttempts = 100;
        $attempt = 0;

        do {
            // Generate random code
            $code = $this->generateRandomCode($length);
            $attempt++;

            // Check if code is unique
            if (!isset($mappings[$code])) {
                return $code;
            }

        } while ($attempt < $maxAttempts);

        // If we can't generate unique code with current length, increase length
        return $this->generateRandomCode($length + 1);
    }

    /**
     * Generate random alphanumeric code
     */
    private function generateRandomCode($length) {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $charactersLength = strlen($characters);
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, $charactersLength - 1)];
        }

        return $code;
    }

    /**
     * Find existing short code for a slug
     */
    private function findExistingCode($postSlug) {
        $mappings = $this->loadMappings();

        foreach ($mappings as $code => $data) {
            if ($data['slug'] === $postSlug) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Resolve short code to post slug
     */
    public function resolveShortUrl($code) {
        // Sanitize code
        $code = $this->security->sanitizeInput($code, 'alphanumeric');

        if (empty($code)) {
            return null;
        }

        // Load mappings
        $mappings = $this->loadMappings();

        // Check if code exists
        if (!isset($mappings[$code])) {
            return null;
        }

        // Increment click counter
        $this->incrementClicks($code);

        // Return slug
        return $mappings[$code]['slug'];
    }

    /**
     * Increment click counter
     */
    private function incrementClicks($code) {
        $mappings = $this->loadMappings();

        if (isset($mappings[$code])) {
            $mappings[$code]['clicks'] = ($mappings[$code]['clicks'] ?? 0) + 1;
            $mappings[$code]['last_click'] = time();
            $this->saveMappings($mappings);

            // Track detailed stats
            $this->trackClickStats($code);
        }
    }

    /**
     * Track detailed click statistics
     */
    private function trackClickStats($code) {
        $stats = $this->loadStats();

        $timestamp = time();
        $date = date('Y-m-d');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        if (!isset($stats[$code])) {
            $stats[$code] = [
                'total_clicks' => 0,
                'daily_clicks' => [],
                'recent_clicks' => []
            ];
        }

        // Increment total
        $stats[$code]['total_clicks']++;

        // Track daily clicks
        if (!isset($stats[$code]['daily_clicks'][$date])) {
            $stats[$code]['daily_clicks'][$date] = 0;
        }
        $stats[$code]['daily_clicks'][$date]++;

        // Track recent clicks (last 100)
        if (!isset($stats[$code]['recent_clicks'])) {
            $stats[$code]['recent_clicks'] = [];
        }

        $stats[$code]['recent_clicks'][] = [
            'timestamp' => $timestamp,
            'ip' => hash('sha256', $ip), // Hash IP for privacy
            'user_agent' => substr($userAgent, 0, 100)
        ];

        // Keep only last 100 clicks
        if (count($stats[$code]['recent_clicks']) > 100) {
            $stats[$code]['recent_clicks'] = array_slice($stats[$code]['recent_clicks'], -100);
        }

        // Clean old daily stats (keep last 90 days)
        $cutoffDate = date('Y-m-d', strtotime('-90 days'));
        foreach ($stats[$code]['daily_clicks'] as $statsDate => $count) {
            if ($statsDate < $cutoffDate) {
                unset($stats[$code]['daily_clicks'][$statsDate]);
            }
        }

        $this->saveStats($stats);
    }

    /**
     * Get statistics for a short code
     */
    public function getStatistics($code) {
        $code = $this->security->sanitizeInput($code, 'alphanumeric');

        $mappings = $this->loadMappings();
        $stats = $this->loadStats();

        if (!isset($mappings[$code])) {
            return null;
        }

        $mapping = $mappings[$code];
        $clickStats = $stats[$code] ?? [
            'total_clicks' => 0,
            'daily_clicks' => [],
            'recent_clicks' => []
        ];

        return [
            'code' => $code,
            'slug' => $mapping['slug'],
            'url' => SITE_URL . '/s/' . $code,
            'created' => $mapping['created'],
            'created_by' => $mapping['created_by'],
            'total_clicks' => $clickStats['total_clicks'],
            'daily_clicks' => $clickStats['daily_clicks'] ?? [],
            'recent_clicks_count' => count($clickStats['recent_clicks'] ?? [])
        ];
    }

    /**
     * Get all short URLs
     */
    public function getAllShortUrls() {
        $mappings = $this->loadMappings();
        $stats = $this->loadStats();
        $result = [];

        foreach ($mappings as $code => $mapping) {
            $clickStats = $stats[$code] ?? ['total_clicks' => 0];

            $result[] = [
                'code' => $code,
                'slug' => $mapping['slug'],
                'url' => SITE_URL . '/s/' . $code,
                'created' => $mapping['created'],
                'created_by' => $mapping['created_by'],
                'clicks' => $clickStats['total_clicks'] ?? 0
            ];
        }

        // Sort by creation date (newest first)
        usort($result, function($a, $b) {
            return $b['created'] - $a['created'];
        });

        return $result;
    }

    /**
     * Delete short URL
     */
    public function deleteShortUrl($code) {
        $code = $this->security->sanitizeInput($code, 'alphanumeric');

        $mappings = $this->loadMappings();

        if (!isset($mappings[$code])) {
            return ['success' => false, 'error' => 'Short URL not found'];
        }

        unset($mappings[$code]);

        if (!$this->saveMappings($mappings)) {
            return ['success' => false, 'error' => 'Failed to delete short URL'];
        }

        // Also remove stats
        $stats = $this->loadStats();
        if (isset($stats[$code])) {
            unset($stats[$code]);
            $this->saveStats($stats);
        }

        $this->security->logSecurityEvent('Short URL deleted', $code);

        return ['success' => true, 'message' => 'Short URL deleted successfully'];
    }

    /**
     * Load URL mappings from file
     */
    private function loadMappings() {
        if (!file_exists($this->mappingFile)) {
            return [];
        }

        $content = file_get_contents($this->mappingFile);
        if ($content === false) {
            return [];
        }

        $mappings = json_decode($content, true);
        return $mappings ?? [];
    }

    /**
     * Save URL mappings to file
     */
    private function saveMappings($mappings) {
        $jsonData = json_encode($mappings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($jsonData === false) {
            return false;
        }

        $result = file_put_contents($this->mappingFile, $jsonData, LOCK_EX);

        if ($result !== false) {
            chmod($this->mappingFile, 0600);
            return true;
        }

        return false;
    }

    /**
     * Load statistics from file
     */
    private function loadStats() {
        if (!file_exists($this->statsFile)) {
            return [];
        }

        $content = file_get_contents($this->statsFile);
        if ($content === false) {
            return [];
        }

        $stats = json_decode($content, true);
        return $stats ?? [];
    }

    /**
     * Save statistics to file
     */
    private function saveStats($stats) {
        $jsonData = json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($jsonData === false) {
            return false;
        }

        $result = file_put_contents($this->statsFile, $jsonData, LOCK_EX);

        if ($result !== false) {
            chmod($this->statsFile, 0600);
            return true;
        }

        return false;
    }

    /**
     * Generate QR code for short URL (optional - requires external library or API)
     */
    public function generateQRCode($code) {
        $code = $this->security->sanitizeInput($code, 'alphanumeric');
        $shortUrl = SITE_URL . '/s/' . $code;

        // Use Google Charts API (no key required for QR codes)
        $qrUrl = 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' . urlencode($shortUrl);

        return [
            'success' => true,
            'qr_url' => $qrUrl,
            'short_url' => $shortUrl
        ];
    }

    /**
     * Cleanup old unused short URLs
     */
    public function cleanupUnusedUrls($daysOld = 365, $maxClicks = 0) {
        $mappings = $this->loadMappings();
        $stats = $this->loadStats();
        $cutoffTime = time() - ($daysOld * 86400);
        $deletedCount = 0;

        foreach ($mappings as $code => $mapping) {
            $clicks = $stats[$code]['total_clicks'] ?? 0;
            $created = $mapping['created'];

            // Delete if old and has no clicks or very few clicks
            if ($created < $cutoffTime && $clicks <= $maxClicks) {
                unset($mappings[$code]);
                if (isset($stats[$code])) {
                    unset($stats[$code]);
                }
                $deletedCount++;
            }
        }

        if ($deletedCount > 0) {
            $this->saveMappings($mappings);
            $this->saveStats($stats);
            $this->security->logSecurityEvent('Cleaned up old short URLs', $deletedCount . ' URLs removed');
        }

        return [
            'success' => true,
            'deleted_count' => $deletedCount
        ];
    }
}
