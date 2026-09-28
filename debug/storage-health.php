<?php
/**
 * Administrator-only storage health probe.
 * Does not expose secrets or raw level payloads.
 */

session_start();

require __DIR__ . "/../incl/lib/connection.php";
require_once __DIR__ . "/../incl/lib/adminLib.php";
require_once __DIR__ . "/../../incl/lib/telegraphCloud.php";

gdAdminLib::requireAdmin($db);

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");
header("X-Content-Type-Options: nosniff");

$result = [
    "ok" => true,
    "phpVersion" => PHP_VERSION,
    "extensions" => [
        "curl" => extension_loaded("curl"),
        "zlib" => extension_loaded("zlib"),
        "pdo" => extension_loaded("PDO"),
        "pdoMysql" => extension_loaded("pdo_mysql"),
    ],
    "mysql" => [
        "ok" => false,
    ],
    "telegraphCloud" => [
        "enabled" => false,
        "level2" => [
            "found" => false,
            "manifest" => false,
            "readOk" => false,
        ],
    ],
    "timestamp" => gmdate("c"),
];

try {
    $result["mysql"]["value"] = (int)$db->query("SELECT 1")->fetchColumn();
    $result["mysql"]["ok"] = ($result["mysql"]["value"] === 1);
} catch(Throwable $e) {
    $result["mysql"]["error"] = get_class($e);
}

try {
    $result["telegraphCloud"]["enabled"] = gdTelegraphCloud::enabled();

    if($result["telegraphCloud"]["enabled"]) {
        $q = $db->prepare("SELECT levelID, levelName, levelString FROM levels WHERE levelID = 2 LIMIT 1");
        $q->execute();
        $row = $q->fetch(PDO::FETCH_ASSOC);

        if($row) {
            $result["telegraphCloud"]["level2"]["found"] = true;
            $stored = (string)($row["levelString"] ?? "");
            $result["telegraphCloud"]["level2"]["manifest"] = gdTelegraphCloud::isManifest($stored);
            $result["telegraphCloud"]["level2"]["storedLength"] = strlen($stored);

            if($result["telegraphCloud"]["level2"]["manifest"]) {
                $payload = gdTelegraphCloud::readLevel($stored);
                $result["telegraphCloud"]["level2"]["readOk"] = is_string($payload) && $payload !== "";
                $result["telegraphCloud"]["level2"]["payloadLength"] = strlen((string)$payload);
                $result["telegraphCloud"]["level2"]["payloadSha256"] = hash("sha256", (string)$payload);
                $result["telegraphCloud"]["level2"]["payloadLooksGzip"] = str_starts_with((string)$payload, "H4sIA");
            }
        }
    }
} catch(Throwable $e) {
    $result["telegraphCloud"]["error"] = get_class($e) . ": " . $e->getMessage();
}

$result["ok"] =
    $result["mysql"]["ok"] &&
    $result["extensions"]["curl"] &&
    (!$result["telegraphCloud"]["enabled"] || $result["telegraphCloud"]["level2"]["readOk"]);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>