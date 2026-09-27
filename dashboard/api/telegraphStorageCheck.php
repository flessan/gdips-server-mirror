<?php
session_start();

require "../incl/dashboardLib.php";
require "../".$dbPath."incl/lib/connection.php";
require "../".$dbPath."incl/lib/adminLib.php";
require "../".$dbPath."incl/lib/telegraphCloud.php";

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (empty($_SESSION["accountID"])) {
    http_response_code(401);
    exit(json_encode(["ok" => false, "error" => "not_authenticated"]));
}

$adminQuery = $db->prepare("SELECT isAdmin FROM accounts WHERE accountID = :accountID");
$adminQuery->execute([":accountID" => $_SESSION["accountID"]]);
if ((int)$adminQuery->fetchColumn() !== 1) {
    http_response_code(403);
    exit(json_encode(["ok" => false, "error" => "admin_required"]));
}

$levelID = isset($_GET["levelID"]) ? (int)$_GET["levelID"] : 0;
if ($levelID < 1) {
    http_response_code(400);
    exit(json_encode(["ok" => false, "error" => "invalid_level_id"]));
}

$query = $db->prepare("SELECT levelID, levelName, levelString FROM levels WHERE levelID = :levelID LIMIT 1");
$query->execute([":levelID" => $levelID]);
$level = $query->fetch();

if (!$level) {
    http_response_code(404);
    exit(json_encode(["ok" => false, "error" => "level_not_found"]));
}

$stored = (string)($level["levelString"] ?? "");
$manifest = gdTelegraphCloud::parseManifest($stored);

$result = [
    "ok" => false,
    "levelID" => (int)$level["levelID"],
    "levelName" => $level["levelName"],
    "telegraphConfigured" => gdTelegraphCloud::enabled(),
    "databaseStorage" => $manifest ? "telegraph-cloud" : "database",
    "databaseStorageLength" => strlen($stored),
];

if ($manifest) {
    $result["chunkCount"] = count($manifest["chunks"]);
    $result["remoteSize"] = (int)$manifest["size"];
}

try {
    $payload = gdTelegraphCloud::readLevel($stored);
    $result["ok"] = is_string($payload) && $payload !== "";
    $result["payloadLength"] = is_string($payload) ? strlen($payload) : 0;
    $result["payloadSha256"] = is_string($payload) ? hash("sha256", $payload) : null;
} catch (Throwable $e) {
    $result["error"] = "telegraph_storage_read_failed";
    error_log("GDIPS Telegraph Cloud diagnostic read failed.");
}

if (!$result["ok"] && empty($result["error"])) {
    $result["error"] = "empty_level_payload";
}

exit(json_encode($result, JSON_UNESCAPED_SLASHES));
?>