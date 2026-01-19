<?php
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Build update/files and update/manifest.json from the repo working tree.
 * - Excludes data/, .git/, tools/, docs/ (optional), includes/config.php
 * - Copies files into update/files/
 * - Writes manifest.json with sha256 + size
 */

$root = realpath(__DIR__ . "/..");
$updateDir = $root . "/update";
$filesDir = $updateDir . "/files";

$excludePrefixes = [
  "data/",
  ".git/",
  "update/",
];

$excludeExact = [
  "includes/config.php",
];

$version = $argv[1] ?? null;
if (!$version) {
  fwrite(STDERR, "Usage: php tools/build-update.php <version>\n");
  exit(1);
}

function rrmdir($dir) {
  if (!is_dir($dir)) return;
  $items = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
  );
  foreach ($items as $item) {
    $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
  }
  rmdir($dir);
}

rrmdir($filesDir);
@mkdir($filesDir, 0755, true);

$manifest = [
  "version" => $version,
  "released" => date("Y-m-d"),
  "base" => "https://raw.githubusercontent.com/AfterPacket/secure-blog-cms/main/update/files",
  "files" => []
];

$it = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($it as $file) {
  /** @var SplFileInfo $file */
  if (!$file->isFile()) continue;

  $abs = $file->getRealPath();
  $rel = str_replace("\\", "/", substr($abs, strlen($root) + 1));

  // Excludes
  $skip = false;
  foreach ($excludePrefixes as $pfx) {
    if (strpos($rel, $pfx) === 0) { $skip = true; break; }
  }
  if ($skip) continue;
  if (in_array($rel, $excludeExact, true)) continue;

  // Only ship php/css/js/html assets (adjust as needed)
  $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
  if (!in_array($ext, ["php","css","js","html","md","txt"], true)) {
    continue;
  }

  $dest = $filesDir . "/" . $rel;
  @mkdir(dirname($dest), 0755, true);
  copy($abs, $dest);

  $manifest["files"][$rel] = [
    "sha256" => hash_file("sha256", $abs),
    "size" => filesize($abs)
  ];
}

@mkdir($updateDir, 0755, true);
file_put_contents($updateDir . "/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "Built update package for version {$version}\n";
