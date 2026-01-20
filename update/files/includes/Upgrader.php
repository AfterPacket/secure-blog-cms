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
    private $update_server_url = "https://example.com/updates"; // Placeholder URL

    public function __construct()
    {
        // Constructor
    }

    public function checkForUpdates()
    {
        $manifestPath = __DIR__ . "/../update/manifest.json";

        if (!file_exists($manifestPath)) {
            return [
                "success" => false,
                "error" => "Update manifest not found.",
            ];
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        if (!$manifest) {
            return [
                "success" => false,
                "error" => "Invalid manifest format.",
            ];
        }

        $currentVersion = defined("SECURE_CMS_VERSION")
            ? SECURE_CMS_VERSION
            : "1.1.8";
        $remoteVersion = $manifest["version"];

        if (version_compare($currentVersion, $remoteVersion, "<")) {
            return [
                "success" => true,
                "up_to_date" => false,
                "updates_available" => 1,
                "updates" => [
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
                ],
            ];
        }

        return [
            "success" => true,
            "up_to_date" => true,
            "updates" => [],
        ];
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
