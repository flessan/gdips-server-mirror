<?php
/*
 * Lightweight administrator diagnostic for GDIPS -> Telegraph Cloud.
 * This endpoint intentionally reports only bounded, non-secret stage codes.
 */
$stage = "boot";

function tcOut(array $payload, int $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $stage = "session";
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();

    $stage = "connection";
    require_once __DIR__ . "/../../incl/lib/connection.php";

    $stage = "telegraph_config";
    require_once __DIR__ . "/../../config/telegraph.php";

    $stage = "telegraph_client";
    require_once __DIR__ . "/../../incl/lib/telegraphCloud.php";

    $stage = "auth";
    $accountID = (int)($_SESSION["accountID"] ?? 0);
    if ($accountID < 1) {
        $cookie = $_COOKIE["auth"] ?? "";
        if (is_string($cookie) && trim($cookie) !== "" && strtolower(trim($cookie)) !== "none") {
            $authQuery = $db->prepare(
                "SELECT accountID FROM accounts WHERE BINARY auth = BINARY :auth AND auth != '' LIMIT 1"
            );
            $authQuery->execute([":auth" => $cookie]);
            $accountID = (int)$authQuery->fetchColumn();
            if ($accountID > 0) $_SESSION["accountID"] = $accountID;
        }
    }
    if ($accountID < 1) tcOut(["ok" => false, "error" => "not_authenticated"], 401);

    $stage = "admin_check";
    $adminQuery = $db->prepare("SELECT isAdmin FROM accounts WHERE accountID = :accountID LIMIT 1");
    $adminQuery->execute([":accountID" => $accountID]);
    if ((int)$adminQuery->fetchColumn() !== 1) {
        tcOut(["ok" => false, "error" => "admin_required"], 403);
    }

    $stage = "level_query";
    $levelID = isset($_GET["levelID"]) ? (int)$_GET["levelID"] : 0;
    if ($levelID < 1) tcOut(["ok" => false, "error" => "invalid_level_id"], 400);

    $query = $db->prepare(
        "SELECT levelID, levelName, levelString FROM levels WHERE levelID = :levelID LIMIT 1"
    );
    $query->execute([":levelID" => $levelID]);
    $level = $query->fetch(PDO::FETCH_ASSOC);
    if (!$level) tcOut(["ok" => false, "error" => "level_not_found"], 404);

    $stored = (string)($level["levelString"] ?? "");

    $stage = "manifest_parse";
    $manifest = gdTelegraphCloud::parseManifest($stored);

    $stage = "config_read";
    $configState = [
        "enabled" => !empty($telegraphCloudEnabled),
        "baseUrl" => !empty($telegraphCloudBaseUrl),
        "projectId" => !empty($telegraphCloudProjectId),
        "apiKey" => !empty($telegraphCloudApiKey),
        "bucket" => (string)$telegraphCloudBucket,
        "chunkBytes" => (int)$telegraphCloudChunkBytes,
    ];

    $result = [
        "ok" => false,
        "levelID" => (int)$level["levelID"],
        "levelName" => $level["levelName"],
        "storage" => $manifest ? "telegraph-cloud" : "database",
        "storedLength" => strlen($stored),
        "config" => $configState,
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
    $result["contentSha256"] = (string)$manifest["sha256"];

    $base = rtrim((string)$telegraphCloudBaseUrl, "/");
    $bucket = (string)$telegraphCloudBucket;

    $result["remoteObjects"] = [];
    $allAvailable = true;

    foreach ($manifest["chunks"] as $index => $chunk) {
        $stage = "chunk_probe";
        if (!is_array($chunk) || !is_string($chunk["key"] ?? null) || !isset($chunk["size"])) {
            $allAvailable = false;
            $result["remoteObjects"][] = [
                "index" => $index,
                "status" => "invalid_manifest"
            ];
            continue;
        }

        $parts = array_merge([$bucket], explode("/", trim($chunk["key"], "/")));
        $url = $base . "/api/storage/" . implode("/", array_map("rawurlencode", $parts));

        if (!function_exists("curl_init")) {
            $allAvailable = false;
            $result["remoteObjects"][] = [
                "index" => $index,
                "status" => "curl_unavailable"
            ];
            break;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            $allAvailable = false;
            $result["remoteObjects"][] = [
                "index" => $index,
                "status" => "curl_init_failed"
            ];
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
            "networkError" => $errno ? $errno : null,
        ];
    }

    $result["ok"] = $allAvailable;
    if (!$allAvailable && empty($result["error"])) {
        $result["error"] = "one_or_more_remote_objects_unavailable";
    }

    tcOut($result);
} catch (Throwable $error) {
    // Never echo provider details, credentials, URLs, or file paths.
    tcOut([
        "ok" => false,
        "error" => "diagnostic_failed",
        "stage" => $stage,
        "errorType" => get_class($error),
    ], 500);
}
?>