<?php
/*
 * Lightweight Telegraph Cloud diagnostic for administrators.
 * Deliberately does not download/reassemble a level payload, so a huge level
 * cannot exhaust PHP memory merely by opening this diagnostic URL.
 */
session_start();

require_once "../../config/connection.php";
require_once "../../config/misc.php";
require_once "../../config/telegraph.php";
require_once "../../incl/lib/telegraphCloud.php";

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function tcOut(array $payload, int $status = 200) {
    http_response_code($status);
    exit(json_encode($payload, JSON_UNESCAPED_SLASHES));
}

try {
    if (empty($_SESSION["accountID"])) {
        $cookie = $_COOKIE["auth"] ?? "";
        if (is_string($cookie) && trim($cookie) !== "") {
            $authQuery = $db->prepare("SELECT accountID FROM accounts WHERE BINARY auth = BINARY :auth AND auth != '' LIMIT 1");
            $authQuery->execute([":auth" => $cookie]);
            $sessionAccount = $authQuery->fetchColumn();
            if ($sessionAccount) $_SESSION["accountID"] = (int)$sessionAccount;
        }
    }

    if (empty($_SESSION["accountID"])) {
        tcOut(["ok" => false, "error" => "not_authenticated"], 401);
    }

    $adminQuery = $db->prepare("SELECT isAdmin FROM accounts WHERE accountID = :accountID LIMIT 1");
    $adminQuery->execute([":accountID" => $_SESSION["accountID"]]);
    if ((int)$adminQuery->fetchColumn() !== 1) {
        tcOut(["ok" => false, "error" => "admin_required"], 403);
    }

    $levelID = isset($_GET["levelID"]) ? (int)$_GET["levelID"] : 0;
    if ($levelID < 1) tcOut(["ok" => false, "error" => "invalid_level_id"], 400);

    $query = $db->prepare("SELECT levelID, levelName, levelString FROM levels WHERE levelID = :levelID LIMIT 1");
    $query->execute([":levelID" => $levelID]);
    $level = $query->fetch(PDO::FETCH_ASSOC);
    if (!$level) tcOut(["ok" => false, "error" => "level_not_found"], 404);

    $stored = (string)($level["levelString"] ?? "");
    $manifest = gdTelegraphCloud::parseManifest($stored);

    $result = [
        "ok" => false,
        "levelID" => (int)$level["levelID"],
        "levelName" => $level["levelName"],
        "telegraphEnabled" => gdTelegraphCloud::enabled(),
        "storage" => $manifest ? "telegraph-cloud" : "database",
        "storedLength" => strlen($stored),
    ];

    if (!$manifest) {
        $result["payloadPresent"] = $stored !== "";
        $result["payloadLength"] = strlen($stored);
        $result["ok"] = $stored !== "";
        if (!$result["ok"]) $result["error"] = "empty_level_payload";
        tcOut($result);
    }

    $result["chunkCount"] = count($manifest["chunks"]);
    $result["remoteSize"] = (int)$manifest["size"];
    $result["contentSha256"] = $manifest["sha256"];

    $config = [
        "baseUrlPresent" => !empty($telegraphCloudBaseUrl),
        "projectIdPresent" => !empty($telegraphCloudProjectId),
        "apiKeyPresent" => !empty($telegraphCloudApiKey),
        "bucket" => (string)$telegraphCloudBucket,
    ];

    if (!$config["baseUrlPresent"] || !$config["projectIdPresent"] || !$config["apiKeyPresent"]) {
        $result["error"] = "telegraph_not_configured";
        $result["config"] = [
            "baseUrlPresent" => $config["baseUrlPresent"],
            "projectIdPresent" => $config["projectIdPresent"],
            "apiKeyPresent" => $config["apiKeyPresent"],
            "bucket" => $config["bucket"],
        ];
        tcOut($result);
    }

    $result["remoteObjects"] = [];
    $allAvailable = true;

    foreach ($manifest["chunks"] as $index => $chunk) {
        if (!is_array($chunk) || !is_string($chunk["key"] ?? null) || !isset($chunk["size"])) {
            $allAvailable = false;
            $result["remoteObjects"][] = ["index" => $index, "status" => "invalid_manifest"];
            continue;
        }

        // HEAD-only probe: verifies Telegram can resolve the object pointer
        // through the Telegraph Cloud API without downloading the bytes.
        $base = rtrim((string)$telegraphCloudBaseUrl, "/");
        $bucket = (string)$telegraphCloudBucket;
        $parts = array_merge([$bucket], explode("/", trim($chunk["key"], "/")));
        $url = $base . "/api/storage/" . implode("/", array_map("rawurlencode", $parts));

        $ch = curl_init($url);
        if ($ch === false) {
            $allAvailable = false;
            $result["remoteObjects"][] = ["index" => $index, "status" => "curl_init_failed"];
            continue;
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => "HEAD",
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $telegraphCloudApiKey,
                "Accept: application/json",
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);
        curl_exec($ch);
        $errno = curl_errno($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $available = ($errno === 0 && $http >= 200 && $http < 300);
        if (!$available) $allAvailable = false;

        $result["remoteObjects"][] = [
            "index" => $index,
            "expectedBytes" => (int)$chunk["size"],
            "status" => $available ? "available" : "unavailable",
            "httpStatus" => $http ?: null,
        ];
    }

    $result["ok"] = $allAvailable;
    if (!$allAvailable) $result["error"] = "one_or_more_remote_objects_unavailable";

    tcOut($result);
} catch (Throwable $error) {
    error_log("GDIPS Telegraph Cloud diagnostic failed.");
    tcOut(["ok" => false, "error" => "diagnostic_failed"], 500);
}
?>