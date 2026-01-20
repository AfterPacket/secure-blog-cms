<?php
// Debug script to analyze hash mismatches
// Run: php debug_hash.php

$files = [
    'local_source' => __DIR__ . '/admin/admin.php',
    'local_generated' => __DIR__ . '/update/files/admin/admin.php'
];

$manifestUrl = 'https://raw.githubusercontent.com/AfterPacket/secure-blog-cms/main/update/files/admin/admin.php';

echo "--- Debugging Hash Mismatch ---\n\n";

// 1. Analyze Local Source
echo "1. Local Source File (admin/admin.php):\n";
if (file_exists($files['local_source'])) {
    $content = file_get_contents($files['local_source']);
    echo "   - Size: " . strlen($content) . " bytes\n";
    echo "   - SHA256: " . hash('sha256', $content) . "\n";
    echo "   - Line Endings: " . detect_line_endings($content) . "\n";
} else {
    echo "   - File NOT FOUND\n";
}

// 2. Analyze Local Generated File
echo "\n2. Generated Update File (update/files/admin/admin.php):\n";
if (file_exists($files['local_generated'])) {
    $content = file_get_contents($files['local_generated']);
    echo "   - Size: " . strlen($content) . " bytes\n";
    echo "   - SHA256: " . hash('sha256', $content) . "\n";
    echo "   - Line Endings: " . detect_line_endings($content) . "\n";
} else {
    echo "   - File NOT FOUND\n";
}

// 3. Analyze Remote File (What the updater downloads)
echo "\n3. Remote File on GitHub:\n";
echo "   - Fetching from: $manifestUrl\n";
$ch = curl_init($manifestUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT => 'DebugScript',
    CURLOPT_FOLLOWLOCATION => true
]);
$remoteContent = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    echo "   - Size: " . strlen($remoteContent) . " bytes\n";
    echo "   - SHA256: " . hash('sha256', $remoteContent) . "\n";
    echo "   - Line Endings: " . detect_line_endings($remoteContent) . "\n";
} else {
    echo "   - FAILED to download (HTTP $httpCode)\n";
}

echo "\n--------------------------------\n";

if (isset($remoteContent) && file_exists($files['local_generated'])) {
    $genContent = file_get_contents($files['local_generated']);
    if ($genContent === $remoteContent) {
        echo "✅ SUCCESS: Generated file matches Remote file.\n";
    } else {
        echo "❌ FAILURE: Generated file DOES NOT MATCH Remote file.\n";

        // Simple diff of first mismatch
        $len = min(strlen($genContent), strlen($remoteContent));
        for ($i = 0; $i < $len; $i++) {
            if ($genContent[$i] !== $remoteContent[$i]) {
                echo "   Mismatch at byte $i: Local=" . ord($genContent[$i]) . " Remote=" . ord($remoteContent[$i]) . "\n";
                break;
            }
        }
    }
}

function detect_line_endings($str) {
    if (strpos($str, "\r\n") !== false) return "CRLF (Windows)";
    if (strpos($str, "\n") !== false) return "LF (Unix/Linux)";
    return "Unknown/Mixed";
}
