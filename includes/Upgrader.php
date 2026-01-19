<?php
/**
 * Secure Blog CMS - Upgrader Class
 * Handles update checks and system upgrades
 */

class Upgrader
{
    private $update_server_url = "https://example.com/updates"; // Placeholder URL

    public function __construct()
    {
        // Constructor
    }

    public function checkForUpdates()
    {
        $local_version_file = __DIR__ . "/../data/version.json";
        $remote_version_file = $this->update_server_url . "/version.json";

        if (!file_exists($local_version_file)) {
            return [
                "success" => false,
                "error" => "Local version file not found.",
            ];
        }

        $local_version_data = json_decode(
            file_get_contents($local_version_file),
            true);
        if (!$local_version_data || !isset($local_version_data["version"])) {
            return [
                "success" => false,
                "error" => "Invalid local version file.",
            ];
        }

        $local_version = $local_version_data["version"];

        $remote_version_data = @file_get_contents($remote_version_file);
        if ($remote_version_data === false) {
            return [
                "success" => false,
                "error" => "Could not connect to the update server.",
            ];
        }

        $remote_version_data = json_decode($remote_version_data, true);
        if (!$remote_version_data || !isset($remote_version_data["version"])) {
            return [
                "success" => false,
                "error" => "Invalid remote version file.",
            ];
        }

        $remote_version = $remote_version_data["version"];

        if (version_compare($local_version, $remote_version, "<")) {
            return [
                "success" => true,
                "up_to_date" => false,
                "updates_available" => 1,
                "latest_version" => $remote_version,
                "download_url" =>
                    $this->update_server_url .
                    "/secure-blog-cms-" .
                    $remote_version .
                    ".zip",
                "checksum" => $remote_version_data["checksum"] ?? "",
            ];
        } else {
            return ["success" => true, "up_to_date" => true];
        }
    }

    public function performUpgrade($version, $downloadUrl, $checksum)
    {
        //
        // NOTE: This is a simplified example. A real-world implementation would need to be much more robust.
        // - Better error handling
        // - File permissions checks
        // - Backup and rollback functionality
        // - More secure file operations
        //

        $temp_dir = __DIR__ . "/../data/temp";
        if (!is_dir($temp_dir)) {
            mkdir($temp_dir, 0755, true);
        }

        $package_path = $temp_dir . "/" . basename($downloadUrl);

        // Download the update package
        $package_data = @file_get_contents($downloadUrl);
        if ($package_data === false) {
            return [
                "success" => false,
                "error" => "Failed to download the update package.",
            ];
        }

        // Verify the checksum
        if ($checksum && hash("sha256", $package_data) !== $checksum) {
            return [
                "success" => false,
                "error" =>
                    "Checksum verification failed. The update package may be corrupted.",
            ];
        }

        // Save the package to a temporary file
        if (file_put_contents($package_path, $package_data) === false) {
            return [
                "success" => false,
                "error" => "Failed to save the update package.",
            ];
        }

        // Unzip the package
        $zip = new ZipArchive();
        if ($zip->open($package_path) === true) {
            $zip->extractTo(__DIR__ . "/../");
            $zip->close();
        } else {
            return [
                "success" => false,
                "error" => "Failed to unzip the update package.",
            ];
        }

        // Clean up the temporary file
        unlink($package_path);

        // Update the version file
        $local_version_file = __DIR__ . "/../data/version.json";
        $version_data = ["version" => $version];
        file_put_contents($local_version_file, json_encode($version_data));

        return ["success" => true, "message" => "Upgrade successful!"];
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
                json_encode($settings, JSON_PRETTY_PRINT))
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
        return [
            "cms_version" => defined("SECURE_CMS_VERSION")
                ? SECURE_CMS_VERSION
                : "N/A",
            "php_version" => phpversion(),
            "server_software" => $_SERVER["SERVER_SOFTWARE"] ?? "N/A",
            "os" => php_uname(),
        ];
    }

    public function getUpgradeHistory()
    {
        $history_file = __DIR__ . "/../data/logs/upgrade_history.log";
        if (!file_exists($history_file)) {
            return [];
        }
        $history_lines = file(
            $history_file,
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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
