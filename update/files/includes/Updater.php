<?php
if (!defined("SECURE_CMS_INIT")) {
    exit();
}

class Updater
{
    private $manifestUrl;
    private $backupDir;

    public function __construct($manifestUrl)
    {
        $this->manifestUrl = $manifestUrl;
        $this->backupDir = DATA_DIR . "/backups/update-" . date("YmdHis");
    }

    private function httpGet($url)
    {
        if (stripos($url, "https://") !== 0) {
            throw new Exception("Insecure update source");
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => "SecureBlogCMS-Updater",
        ]);
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($data === false) {
            throw new Exception(curl_error($ch));
        }

        if ($httpCode >= 400) {
            throw new Exception("HTTP Error $httpCode fetching update");
        }

        curl_close($ch);
        return $data;
    }

    public function check()
    {
        $json = $this->httpGet($this->manifestUrl);
        return json_decode($json, true);
    }

    public function apply($manifest)
    {
        if (!isset($manifest["files"])) {
            throw new Exception("Invalid manifest");
        }

        mkdir($this->backupDir, 0755, true);

        foreach ($manifest["files"] as $file => $info) {
            if (
                strpos($file, "data/") === 0 ||
                $file === "includes/config.php"
            ) {
                continue;
            }

            $target = APP_ROOT . "/" . $file;
            $tmp = $target . ".tmp";

            if (file_exists($target)) {
                $currentHash = hash_file("sha256", $target);
                if ($currentHash !== $info["sha256"]) {
                    mkdir(dirname($this->backupDir . "/" . $file), 0755, true);
                    copy($target, $this->backupDir . "/" . $file);
                }
            }

            $data = $this->httpGet($manifest["base"] . "/" . $file);
            file_put_contents($tmp, $data);

            if (hash_file("sha256", $tmp) !== $info["sha256"]) {
                unlink($tmp);
                throw new Exception("Hash mismatch for " . $file);
            }

            mkdir(dirname($target), 0755, true);
            rename($tmp, $target);
        }

        file_put_contents(
            DATA_DIR . "/settings/version.json",
            json_encode(
                [
                    "version" => $manifest["version"],
                    "updated" => date("c"),
                ],
                JSON_PRETTY_PRINT,
            ),
        );
    }
}
