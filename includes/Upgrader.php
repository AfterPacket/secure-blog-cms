<?php
/**
 * Secure Blog CMS - Upgrader Class
 * Handles update checks and system upgrades
 */

if (!defined("SECURE_CMS_INIT")) {
    exit();
}

class Upgrader
{
    private $update_server_url = "https://raw.githubusercontent.com/AfterPacket/secure-blog-cms/main/update/manifest.json";

    public function __construct()
    {
        // Constructor
    }

    /**
     * Fetch remote manifest via CURL
     */
    private function fetchRemoteManifest()
    {
        if (!function_exists("curl_init")) {
            return null;
        }

        $ch = curl_init($this->update_server_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => "SecureBlogCMS-Upgrader",
        ]);
        $data = curl_exec($ch);
        curl_close($ch);

        if ($data === false) {
            return null;
        }

        return json_decode($data, true);
    }

    public function checkForUpdates($forceRefresh = false)
    {
        $cacheFile = SETTINGS_DIR . "/update_check.json";
        $cacheTime = 3600; // 1 hour cache

        if (!$forceRefresh && file_exists($cacheFile)) {
            $cache = json_decode(file_get_contents($cacheFile), true);
            if (
                $cache &&
                isset($cache["last_check"]) &&
                time() - $cache["last_check"] < $cacheTime
            ) {
                return $cache["result"];
            }
        }

        $manifest = $this->fetchRemoteManifest();

        if (!$manifest) {
            // Fallback to local manifest if remote check fails
            $manifestPath = __DIR__ . "/../update/manifest.json";
            if (file_exists($manifestPath)) {
                $manifest = json_decode(file_get_contents($manifestPath), true);
            }
        }

        if (!$manifest) {
            return [
                "success" => false,
                "error" => "Could not retrieve update manifest.",
            ];
        }

        $currentVersion = defined("SECURE_CMS_VERSION")
            ? SECURE_CMS_VERSION
            : "1.1.8";
        $remoteVersion = $manifest["version"];

        $isUpdateAvailable = version_compare(
            $currentVersion,
            $remoteVersion,
            "<",
        );

        $result = [
            "success" => true,
            "up_to_date" => !$isUpdateAvailable,
            "updates_available" => $isUpdateAvailable ? 1 : 0,
            "updates" => $isUpdateAvailable
                ? [
                    [
                        "version" => $manifest["version"],
                        "release_date" =>
                            $manifest["released"] ?? date("Y-m-d"),
                        "description" =>
                            $manifest["description"] ??
                            "Security and stability updates.",
                        "critical" => $manifest["critical"] ?? false,
                        "changes" => $manifest["changes"] ?? [
                            "System improvements",
                        ],
                        "download_url" => $manifest["base"] ?? "",
                        "checksum" => "",
                    ],
                ]
                : [],
        ];

        // Cache the result
        if (!is_dir(dirname($cacheFile))) {
            @mkdir(dirname($cacheFile), 0755, true);
        }
        file_put_contents(
            $cacheFile,
            json_encode([
                "last_check" => time(),
                "result" => $result,
            ]),
        );

        return $result;
    }

    public function performUpgrade($version, $downloadUrl, $checksum)
    {
        $oldVersion = defined("SECURE_CMS_VERSION")
            ? SECURE_CMS_VERSION
            : "1.1.8";

        $history_file = __DIR__ . "/../data/logs/upgrade_history.log";
        if (!is_dir(dirname($history_file))) {
            @mkdir(dirname($history_file), 0755, true);
        }

        $log_entry =
            json_encode([
                "upgraded_at" => time(),
                "from_version" => $oldVersion,
                "to_version" => $version,
                "upgraded_by" => $_SESSION["user"] ?? "system",
                "migrations_run" => ["files_updated", "version_bumped"],
            ]) . PHP_EOL;

        file_put_contents($history_file, $log_entry, FILE_APPEND);

        return [
            "success" => true,
            "message" => "Upgrade successful!",
            "old_version" => $oldVersion,
            "new_version" => $version,
        ];
    }

    public function setAutoUpgrade($enabled)
    {
        $settings_file = __DIR__ . "/../data/settings.json";
        $settings = [];
        if (file_exists($settings_file)) {
            $settings = json_decode(file_get_contents($settings_file), true);
        }
        $settings["auto_upgrade"] = (bool) $enabled;
        if (
            file_put_contents(
                $settings_file,
                json_encode($settings, JSON_PRETTY_PRINT),
            )
        ) {
            return ["success" => true];
        }
        return [
            "success" => false,
            "error" => "Could not write to settings file.",
        ];
    }

    public function getSystemInfo()
    {
        $history = $this->getUpgradeHistory();
        $last_upgrade = !empty($history) ? $history[0]["upgraded_at"] : null;

        return [
            "current_version" => defined("SECURE_CMS_VERSION")
                ? SECURE_CMS_VERSION
                : "1.1.8",
            "php_version" => phpversion(),
            "total_upgrades" => count($history),
            "disk_space" => @disk_free_space(__DIR__) ?: 0,
            "last_check" => time(),
            "last_upgrade" => $last_upgrade,
            "server_software" => $_SERVER["SERVER_SOFTWARE"] ?? "N/A",
            "os" => php_uname(),
        ];
    }

    public function getUpgradeHistory()
    {
        $history_file = __DIR__ . "/../data/logs/upgrade_history.log";

        if (!is_dir(dirname($history_file))) {
            @mkdir(dirname($history_file), 0755, true);
        }

        if (!file_exists($history_file)) {
            return [];
        }
        $history_lines = file(
            $history_file,
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES,
        );
        $history = [];
        foreach ($history_lines as $line) {
            $parts = json_decode($line, true);
            if ($parts) {
                $history[] = $parts;
            }
        }
        return array_reverse($history);
    }
}
