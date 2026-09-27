<?php
/**
 * GDIPS Telegraph Cloud level diagnostic.
 *
 * This endpoint is intentionally kept under a dedicated /debug/ tree and is
 * administrator-only. It never returns the raw level payload, Telegram file
 * IDs, bot tokens, API keys, or object keys.
 *
 * Usage:
 *   /debug/telegraph/level.php?levelID=1
 */

session_start();

require __DIR__ . "/../../incl/lib/connection.php";
require_once __DIR__ . "/../../incl/lib/adminLib.php";
require_once __DIR__ . "/../../incl/lib/mainLib.php";
require_once __DIR__ . "/../../incl/lib/exploitPatch.php";
require_once __DIR__ . "/../../incl/lib/generateHash.php";
require_once __DIR__ . "/../../incl/lib/XORCipher.php";
require_once __DIR__ . "/../../incl/lib/telegraphCloud.php";
require __DIR__ . "/../../config/misc.php";

gdAdminLib::requireAdmin($db);

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");
header("X-Content-Type-Options: nosniff");

$levelID = isset($_GET["levelID"]) && is_numeric($_GET["levelID"])
    ? (int)$_GET["levelID"]
    : 1;
$requestGameVersion = isset($_GET["gameVersion"]) && is_numeric($_GET["gameVersion"])
    ? (int)$_GET["gameVersion"]
    : null;
$requestBinaryVersion = isset($_GET["binaryVersion"]) && is_numeric($_GET["binaryVersion"])
    ? (int)$_GET["binaryVersion"]
    : null;

if ($levelID < 1) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "error" => "invalid_level_id"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$query = $db->prepare("SELECT * FROM levels WHERE levelID = :levelID LIMIT 1");
$query->execute([":levelID" => $levelID]);
$level = $query->fetch(PDO::FETCH_ASSOC);

if (!$level) {
    http_response_code(404);
    echo json_encode([
        "ok" => false,
        "error" => "level_not_found",
        "levelID" => $levelID
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$stored = (string)($level["levelString"] ?? "");
$manifest = gdTelegraphCloud::parseManifest($stored);

$result = [
    "ok" => false,
    "levelID" => (int)$level["levelID"],
    "levelName" => (string)$level["levelName"],
    "metadata" => [
        "gameVersion" => (int)($level["gameVersion"] ?? 0),
        "binaryVersion" => (int)($level["binaryVersion"] ?? 0),
        "levelVersion" => (int)($level["levelVersion"] ?? 0),
        "levelLength" => (int)($level["levelLength"] ?? 0),
        "objects" => (int)($level["objects"] ?? 0),
        "songID" => (int)($level["songID"] ?? 0),
        "starStars" => (int)($level["starStars"] ?? 0),
        "unlisted" => (int)($level["unlisted"] ?? 0),
        "unlisted2" => (int)($level["unlisted2"] ?? 0),
        "passwordSet" => !empty($level["password"]),
    ],
    "storage" => gdTelegraphCloud::enabled() && $manifest !== null
        ? "telegraph-cloud"
        : "database",
    "stored" => [
        "length" => strlen($stored),
        "prefix" => substr($stored, 0, 12),
        "isManifest" => $manifest !== null,
        "sha256" => hash("sha256", $stored),
    ],
    "request" => [
        "gameVersion" => $requestGameVersion,
        "binaryVersion" => $requestBinaryVersion,
        "usesStoredGameVersionForProbe" => $requestGameVersion === null,
    ],
    "telegraph" => null,
    "payload" => null,
    "gdResponse" => null,
];

if ($manifest !== null) {
    $result["telegraph"] = [
        "version" => $manifest["v"],
        "provider" => $manifest["provider"],
        "bucket" => $manifest["bucket"],
        "size" => $manifest["size"],
        "sha256" => $manifest["sha256"],
        "chunkCount" => count($manifest["chunks"]),
        "chunks" => array_map(static function($chunk) {
            return [
                "size" => (int)$chunk["size"]
            ];
        }, $manifest["chunks"]),
    ];
}

try {
    $levelString = gdTelegraphCloud::readLevel($stored);
} catch (Throwable $error) {
    $result["error"] = "level_payload_read_failed";
    http_response_code(502);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$levelString = (string)$levelString;

if ($levelString === "") {
    $result["error"] = "empty_level_payload";
    http_response_code(502);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$decodedPayload = null;
$decodedPayload = base64_decode(strtr($levelString, '-_', '+/'), true);
$decompressedPayload = null;
if($decodedPayload !== false && $decodedPayload !== null) {
    $decompressedPayload = @gzdecode($decodedPayload);
    if($decompressedPayload === false) $decompressedPayload = @gzuncompress($decodedPayload);
}

$result["payload"] = [
    "length" => strlen($levelString),
    "sha256" => hash("sha256", $levelString),
    "prefix" => substr($levelString, 0, 32),
    "suffix" => strlen($levelString) > 32 ? substr($levelString, -32) : $levelString,
    "wireEncoding" => (
        $decodedPayload !== false && $decodedPayload !== null && strlen($decodedPayload) > 2
        && substr($decodedPayload, 0, 2) === "\x1f\x8b"
    ) ? "base64url+gzip" : "unknown",
    "compressedBytes" => $decodedPayload === false || $decodedPayload === null ? null : strlen($decodedPayload),
    "decompressed" => is_string($decompressedPayload) ? [
        "length" => strlen($decompressedPayload),
        "sha256" => hash("sha256", $decompressedPayload),
        "prefix" => substr($decompressedPayload, 0, 48),
        "suffix" => strlen($decompressedPayload) > 48 ? substr($decompressedPayload, -48) : $decompressedPayload,
        "startsWithK" => preg_match('/^kS[0-9]+[,;]/', $decompressedPayload) === 1,
    ] : null,
    "containsLevelSignature" => (
        strpos($levelString, "kS1") === 0 ||
        strpos($levelString, "kS2") === 0
    ),
];

$gs = new mainLib();
$uploadDate = $gs->makeTime($level["uploadDate"]);
$updateDate = $gs->makeTime($level["updateDate"]);
$pass = $level["password"];
$desc = ExploitPatch::translit(
    ExploitPatch::rucharclean(
        ExploitPatch::url_base64_decode($level["levelDesc"])
    )
);

$gameVersionForResponse = $requestGameVersion !== null
    ? $requestGameVersion
    : (int)($level["gameVersion"] ?? 0);
$xorPass = $pass;

if ($gameVersionForResponse > 18) {
    if (substr($levelString, 0, 3) === "kS1") {
        $levelStringForResponse = ExploitPatch::url_base64_encode(gzcompress($levelString));
    } else {
        $levelStringForResponse = $levelString;
    }

    if ($gameVersionForResponse > 19) {
        if ($pass != 0) {
            $xorPass = ExploitPatch::url_base64_encode(XORCipher::cipher($pass, 26364));
        }
        $desc = ExploitPatch::url_base64_encode($desc);
    }
} else {
    $levelStringForResponse = $levelString;
}

$response = "1:".$level["levelID"].
    ":2:".ExploitPatch::translit($level["levelName"]).
    ":3:".$desc.
    ":4:".$levelStringForResponse.
    ":5:".$level["levelVersion"].
    ":6:".$level["userID"].
    ":8:10:9:".$level["starDifficulty"].
    ":10:".$level["downloads"].
    ":11:1:12:".$level["audioTrack"].
    ":13:".$level["gameVersion"].
    ":14:".$level["likes"].
    ":17:".$level["starDemon"].
    ":43:".$level["starDemonDiff"].
    ":25:".$level["starAuto"].
    ":18:".$level["starStars"].
    ":19:".$level["starFeatured"].
    ":42:".$level["starEpic"].
    ":45:".$level["objects"].
    ":15:".$level["levelLength"].
    ":30:".$level["original"].
    ":31:".$level["twoPlayer"].
    ":28:".$uploadDate.
    ":29:".$updateDate.
    ":35:".$level["songID"].
    ":36:".$level["extraString"].
    ":37:".$level["coins"].
    ":38:".$level["starCoins"].
    ":39:".$level["requestedStars"].
    ":46:".$level["wt"].
    ":47:".$level["wt2"].
    ":48:".$level["settingsString"].
    ":40:".$level["isLDM"].
    ":27:".$xorPass.
    ":52:".$level["songIDs"].
    ":53:".$level["sfxIDs"].
    ":57:".$level["ts"];

$response .= "#" . GenerateHash::genSolo($levelStringForResponse);

$somestring = $level["userID"].",".
    $level["starStars"].",".
    $level["starDemon"].",".
    $level["levelID"].",".
    $level["starCoins"].",".
    $level["starFeatured"].",".
    $pass.",".
    "0";

$response .= GenerateHash::genSolo2($somestring);

$result["gdResponse"] = [
    "length" => strlen($response),
    "sha256" => hash("sha256", $response),
    "prefix" => substr($response, 0, 180),
    "payloadHash" => GenerateHash::genSolo($levelStringForResponse),
    "secondaryHash" => GenerateHash::genSolo2($somestring),
    "payloadLengthInResponse" => strlen($levelStringForResponse),
];

$result["ok"] = true;

echo json_encode(
    $result,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
?>
