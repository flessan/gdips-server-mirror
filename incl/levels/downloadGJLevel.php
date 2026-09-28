<?php
// Geometry Dash's native client expects the response body to start immediately
// with the level object. Some legacy PHP includes can leak whitespace/newlines.
ob_start();
chdir(dirname(__FILE__));
require "../lib/connection.php";
require_once "../lib/XORCipher.php";
require_once "../lib/exploitPatch.php";
require_once "../lib/mainLib.php";
require_once "../lib/generateHash.php";
require_once "../lib/GJPCheck.php";
require_once "../lib/telegraphCloud.php";
require_once "../lib/levelID.php";
require "../../config/misc.php";

// Persistent, secret-free request tracing.
// Keep multiple requests because Geometry Dash can make more than one download
// request during a single click; a single "last request" snapshot can hide the
// request we actually need to diagnose.
$debugTraceTableReady = false;
$debugTraceRowId = 0;
try {
    $db->exec("CREATE TABLE IF NOT EXISTS debug_download_traces (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        payload LONGTEXT NOT NULL,
        PRIMARY KEY (id),
        KEY idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $debugTraceTableReady = true;
} catch (Throwable $traceSetupError) {
    // Diagnostics are optional and must never break live downloads.
}

$debugTrace = [
    "ok" => false,
    "stage" => "entered",
    "timestamp" => gmdate("c"),
    "method" => $_SERVER["REQUEST_METHOD"] ?? null,
    "requestUri" => $_SERVER["REQUEST_URI"] ?? null,
    "contentType" => $_SERVER["CONTENT_TYPE"] ?? null,
    "postKeys" => array_values(array_keys($_POST)),
];

$writeDebug = static function(array $patch) use (&$debugTrace, &$debugTraceRowId, $db, $debugTraceTableReady): void {
    if (!$debugTraceTableReady || $debugTraceRowId <= 0) return;

    $debugTrace = array_merge($debugTrace, $patch, ["updatedAt" => gmdate("c")]);
    try {
        $stmt = $db->prepare(
            "UPDATE debug_download_traces
             SET updated_at = NOW(), payload = :payload
             WHERE id = :id"
        );
        $stmt->execute([
            ":id" => $debugTraceRowId,
            ":payload" => json_encode(
                $debugTrace,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )
        ]);
    } catch (Throwable $ignored) {
        // Diagnostics must never break the actual endpoint.
    }
};

if ($debugTraceTableReady) {
    try {
        $stmt = $db->prepare(
            "INSERT INTO debug_download_traces
             (created_at, updated_at, payload)
             VALUES (NOW(), NOW(), :payload)"
        );
        $stmt->execute([
            ":payload" => json_encode(
                $debugTrace,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )
        ]);
        $debugTraceRowId = (int)$db->lastInsertId();
    } catch (Throwable $ignored) {
        $debugTraceRowId = 0;
    }
}

$writeDebug([
    "request" => [
        "gameVersion" => $_POST["gameVersion"] ?? null,
        "binaryVersion" => $_POST["binaryVersion"] ?? null,
        "levelID" => $_POST["levelID"] ?? null,
        "inc" => $_POST["inc"] ?? null,
        "extras" => $_POST["extras"] ?? null,
        "hasAccountID" => !empty($_POST["accountID"]),
        "hasSecret" => !empty($_POST["secret"]),
        "hasGjp" => !empty($_POST["gjp"]),
        "hasGjp2" => !empty($_POST["gjp2"]),
        "hasRs" => !empty($_POST["rs"]),
        "hasChk" => !empty($_POST["chk"]),
    ],
]);

register_shutdown_function(static function() use (&$debugTrace, $writeDebug): void {
    $error = error_get_last();
    if ($error && in_array($error["type"], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        $writeDebug([
            "stage" => ($debugTrace["stage"] ?? "unknown") . ":fatal",
            "fatalError" => [
                "type" => $error["type"],
                "message" => $error["message"],
                "file" => basename($error["file"]),
                "line" => $error["line"],
            ],
        ]);
    }
});

$gs = new mainLib();
$rawRequestedLevelID = $_POST["levelID"] ?? null;
if($rawRequestedLevelID === null || $rawRequestedLevelID === "" || !is_numeric($rawRequestedLevelID)) exit("-1");
$requestedLevelID = ExploitPatch::numbercolon($rawRequestedLevelID);
$zeroLevelFallback = false;

// Some GD clients can produce an online GJGameLevel with m_levelID == 0 for
// the first item immediately after refreshing Recent Levels. When that happens
// there is no useful ID in downloadGJLevel22.php itself, but we can safely
// recover the exact first item from the most recent level-list request made by
// the same client IP. Do not apply this to arbitrary/stale requests.
if((int)$requestedLevelID === 0) {
    try {
        $traceStmt = $db->prepare("SELECT payload FROM debug_gjlevels_trace WHERE id = 1 LIMIT 1");
        $traceStmt->execute();
        $tracePayload = $traceStmt->fetchColumn();
        $traceData = is_string($tracePayload) ? json_decode($tracePayload, true) : null;
        if(is_array($traceData)) {
            $traceTime = isset($traceData["timestamp"]) ? strtotime((string)$traceData["timestamp"]) : false;
            $traceIPHash = (string)($traceData["ipHash"] ?? "");
            $sameClient = $traceIPHash !== "" && hash_equals($traceIPHash, hash("sha256", (string)$gs->getIP()));
            $freshTrace = $traceTime !== false && $traceTime >= (time() - 20) && $traceTime <= (time() + 5);
            $firstLevel = $traceData["levels"][0] ?? null;
            if($sameClient && $freshTrace && is_array($firstLevel)) {
                $candidateClientID = (int)($firstLevel["clientLevelID"] ?? 0);
                $candidateInternalID = (int)($firstLevel["levelID"] ?? 0);
                if($candidateInternalID >= 1 && $candidateInternalID <= GD_CLIENT_LEVEL_ID_LOW_MAX && $candidateClientID === gdClientLevelID($candidateInternalID)) {
                    $requestedLevelID = (string)$candidateClientID;
                    $zeroLevelFallback = true;
                }
            }
        }
    } catch(Throwable $fallbackError) {
        // Compatibility fallback is best-effort; normal request handling continues.
    }
}
$levelID = gdInternalLevelID((int)$requestedLevelID);
$gameVersion = !empty($_POST["gameVersion"]) ? ExploitPatch::number($_POST["gameVersion"]) : 1;
$writeDebug([
    "stage" => "parsed_request",
    "parsed" => [
        "requestedLevelID" => $requestedLevelID,
        "originalRequestedLevelID" => (int)$rawRequestedLevelID,
        "zeroLevelFallback" => $zeroLevelFallback,
        "levelID" => $levelID,
        "clientLevelID" => gdClientLevelID($levelID),
        "gameVersion" => $gameVersion,
    ],
]);
$extras = !empty($_POST["extras"]) && $_POST["extras"];
$inc = !empty($_POST["inc"]) && $_POST["inc"];
$ip = $gs->getIP();
$binaryVersion = !empty($_POST["binaryVersion"]) ? ExploitPatch::number($_POST["binaryVersion"]) : 0;
$accountID = 0;
$feaID = 0;
switch($levelID) {
	case -1: // Daily level
		$query = $db->prepare("SELECT feaID, levelID FROM dailyfeatures WHERE timestamp < :time AND type = 0 ORDER BY timestamp DESC LIMIT 1");
		$query->execute([':time' => time()]);
		$feature = $query->fetch();
		if(!$feature) exit("-1");
		$levelID = $feature["levelID"];
		$feaID = $feature["feaID"];
		$daily = 1;
		break;
	case -2: // Weekly level
		$query = $db->prepare("SELECT feaID, levelID FROM dailyfeatures WHERE timestamp < :time AND type = 1 ORDER BY timestamp DESC LIMIT 1");
		$query->execute([':time' => time()]);
		$feature = $query->fetch();
		if(!$feature) exit("-1");
		$levelID = $feature["levelID"];
		$feaID = $feature["feaID"] + 100000;
		$daily = 1;
		break;
	case -3: // Event level
		$query = $db->prepare("SELECT feaID, levelID FROM events WHERE timestamp < :time AND duration >= :time ORDER BY timestamp DESC LIMIT 1");
		$query->execute([':time' => time()]);
		$feature = $query->fetch();
		if(!$feature) exit("-1");
		$levelID = $feature["levelID"];
		$feaID = $feature["feaID"] + 200000;
		$daily = 1;
		break;
	default:
		$daily = 0;
		break;
}
if($daily == 1) $query = $db->prepare("SELECT levels.*, users.userName, users.extID FROM levels LEFT JOIN users ON levels.userID = users.userID WHERE levelID = :levelID");
else $query = $db->prepare("SELECT * FROM levels WHERE levelID = :levelID");
$query->execute([':levelID' => $levelID]);
$result = $query->fetch();
$writeDebug([
    "stage" => $result ? "level_found" : "level_not_found",
    "database" => [
        "found" => (bool)$result,
        "levelID" => $levelID,
    ],
]);
if($result) {
	$isPlayerAnAdmin = false;
	if(!empty($_POST['accountID'])) {
		$accountID = GJPCheck::getAccountIDOrDie();
		if($unlistedLevelsForAdmins) {
			$checkAdmin = $db->prepare('SELECT isAdmin FROM accounts WHERE accountID = :accountID');
			$checkAdmin->execute([':accountID' => $accountID]);
			$checkAdmin = $checkAdmin->fetchColumn();
			if($checkAdmin) $isPlayerAnAdmin = true;
		}
	}
	if($result["unlisted2"] == 1) {
		if(empty($accountID)) exit("-1");
		if(!($result["extID"] == $accountID || $gs->isFriends($accountID, $result["extID"])) && !$isPlayerAnAdmin) exit("-1");
	} // Verifying friends-only unlisted levels
	// Adding the download
	$query6 = $db->prepare("SELECT count(*) FROM actions_downloads WHERE levelID=:levelID AND ip=INET6_ATON(:ip)");
	$query6->execute([':levelID' => $levelID, ':ip' => $ip]);
	if($inc && $query6->fetchColumn() < 2) {
		$query2=$db->prepare("UPDATE levels SET downloads = downloads + 1 WHERE levelID = :levelID");
		$query2->execute([':levelID' => $levelID]);
		$query6 = $db->prepare("INSERT INTO actions_downloads (levelID, ip) VALUES (:levelID,INET6_ATON(:ip))");
		$query6->execute([':levelID' => $levelID, ':ip' => $ip]);
	}
	$uploadDate = $gs->makeTime($result["uploadDate"]);
	$updateDate = $gs->makeTime($result["updateDate"]);
	$pass = $result["password"];
	$desc = ExploitPatch::translit(ExploitPatch::rucharclean(ExploitPatch::url_base64_decode($result["levelDesc"])));
	if($gs->checkModIPPermission("actionFreeCopy") == 1) $pass = "1";
	$xorPass = $pass;
	$writeDebug([
		"stage" => "before_storage_read",
		"storage" => [
			"enabled" => gdTelegraphCloud::enabled(),
			"storedLength" => strlen((string)($result["levelString"] ?? "")),
			"storedPrefix" => substr((string)($result["levelString"] ?? ""), 0, 12),
		],
	]);

	/*
	 * Prefer the exact wire payload saved by the upload handler. New GD 2.2
	 * uploads commonly arrive already encoded as URL-safe base64 + gzip
	 * (H4sIA...), and the local file preserves that representation byte-for-byte.
	 * Telegraph Cloud remains the fallback for hosts where the local object is
	 * unavailable.
	 */
	$localLevelFile = dirname(__DIR__, 2) . "/data/levels/" . $levelID;
	$storageSource = "telegraph-cloud";
	$levelstring = false;

	if(is_file($localLevelFile) && is_readable($localLevelFile)) {
		$localLevelstring = file_get_contents($localLevelFile);
		if($localLevelstring !== false && $localLevelstring !== "") {
			$levelstring = (string)$localLevelstring;
			$storageSource = "local-wire";
		}
	}

	if($levelstring === false || $levelstring === "") {
		try {
			$levelstring = gdTelegraphCloud::readLevel((string)($result["levelString"] ?? ""));
		} catch(Throwable $storageError) {
			$writeDebug([
				"stage" => "storage_read_failed",
				"storageSource" => $storageSource,
				"storageError" => get_class($storageError) . ": " . $storageError->getMessage(),
			]);
			exit("-1");
		}
	}

	$levelstring = (string)$levelstring;
	$writeDebug([
		"stage" => "storage_read_ok",
		"storageSource" => $storageSource,
		"payload" => [
			"length" => strlen($levelstring),
			"sha256" => hash("sha256", $levelstring),
			"prefix" => substr($levelstring, 0, 16),
		],
	]);
	if($levelstring === "") exit("-1");
	if($gameVersion > 18) {
		// GD 2.2 level payloads may arrive either already encoded/compressed
		// (H4sIA...) or as the raw kS* level string. The download protocol
		// requires field 4 to be URL-safe base64+gzip, so gzip any raw
		// kS payload instead of only the legacy kS1 format.
		if(preg_match('/^kS[0-9]+[,;]/', $levelstring) === 1) {
			$levelstring = ExploitPatch::url_base64_encode(gzencode($levelstring, 9, ZLIB_ENCODING_GZIP));
		}
		if($gameVersion > 19) {
			if($pass != 0) $xorPass = ExploitPatch::url_base64_encode(XORCipher::cipher($pass, 26364));
			$desc = ExploitPatch::url_base64_encode($desc);
		}
	}
	$writeDebug([
		"stage" => "building_response",
		"responseMode" => [
			"gameVersion" => $gameVersion,
			"binaryVersion" => $binaryVersion,
			"payloadLength" => strlen($levelstring),
			"payloadPrefix" => substr($levelstring, 0, 16),
		],
	]);
	$response = "1:".gdClientLevelID($result["levelID"]).":2:".ExploitPatch::translit($result["levelName"]).":3:".$desc.":4:".$levelstring.":5:".$result["levelVersion"].":6:".$result["userID"].":8:10:9:".$result["starDifficulty"].":10:".$result["downloads"].":11:1:12:".$result["audioTrack"].":13:".$result["gameVersion"].":14:".$result["likes"].":17:".$result["starDemon"].":43:".$result["starDemonDiff"].":25:".$result["starAuto"].":18:".$result["starStars"].":19:".$result["starFeatured"].":42:".$result["starEpic"].":45:".$result["objects"].":15:".$result["levelLength"].":30:".$result["original"].":31:".$result['twoPlayer'].":28:".$uploadDate. ":29:".$updateDate. ":35:".$result["songID"].":36:".$result["extraString"].":37:".$result["coins"].":38:".$result["starCoins"].":39:".$result["requestedStars"].":46:".$result["wt"].":47:".$result["wt2"].":48:".$result["settingsString"].":40:".$result["isLDM"].":27:$xorPass:52:".$result["songIDs"].":53:".$result["sfxIDs"].":57:".$result['ts'];
	if($daily == 1) $response .= ":41:".$feaID;
	if($extras) $response .= ":26:" . $result["levelInfo"];
	// 2.02 stuff
	$response .= "#" . GenerateHash::genSolo($levelstring) . "#";
	// 2.1 stuff
	$somestring = $result["userID"].",".$result["starStars"].",".$result["starDemon"].",".gdClientLevelID($result["levelID"]).",".$result["starCoins"].",".$result["starFeatured"].",".$pass.",".$feaID;
	$response .= GenerateHash::genSolo2($somestring);
	if($daily == 1) {
		$response .= "#" . $gs->getUserString($result);
	} elseif($binaryVersion == 30) {
		/*
			This was only part of the response for a brief time prior to GD 2.1's relase.
			This binary version corresponds to the original release of Geometry Dash World.
			It is currently unknown if it's required, so it is left in for now.
		*/
		$response .= "#" . $somestring;
	}
	// Keep a tiny, secret-free snapshot of the last successful download request
	// so an administrator can inspect what the Geometry Dash client actually sent
	// (especially gameVersion/binaryVersion) without exposing POST credentials.
	$debugSnapshot = [
		"timestamp" => gmdate("c"),
		"requestedLevelID" => (int)$requestedLevelID,
		"levelID" => (int)$levelID,
		"clientLevelID" => (int)gdClientLevelID($levelID),
		"gameVersion" => (int)$gameVersion,
		"binaryVersion" => (int)$binaryVersion,
		"extras" => (bool)$extras,
		"inc" => (bool)$inc,
		"accountIDProvided" => !empty($_POST["accountID"]),
		"storedGameVersion" => (int)($result["gameVersion"] ?? 0),
		"storedBinaryVersion" => (int)($result["binaryVersion"] ?? 0),
		"payload" => [
			"length" => strlen($levelstring),
			"sha256" => hash("sha256", $levelstring),
			"prefix" => substr($levelstring, 0, 16),
			"wireEncoding" => str_starts_with($levelstring, "H4sIA") ? "base64+gzip" : "plain",
		],
		"response" => [
			"length" => strlen($response),
			"sha256" => hash("sha256", $response),
			"contentLength" => strlen($response),
			"hashDelimiterCount" => substr_count($response, "#"),
		],
		"headers" => [
			"contentType" => "text/plain; charset=utf-8",
			"cacheControl" => "no-store",
		],
	];
	$writeDebug([
		"stage" => "response_ready",
		"ok" => true,
		"responseMode" => $debugSnapshot["response"],
		"responsePreview" => [
			"clientLevelID" => (int)gdClientLevelID($result["levelID"]),
			"prefix" => substr($response, 0, 220),
		],
	]);
	// Strip anything accidentally emitted by the legacy include chain.
	// The response must begin exactly with "1:<levelID>...".
	while (ob_get_level() > 0) {
		ob_end_clean();
	}
	header("Content-Type: text/plain; charset=utf-8");
	echo $response;
	exit;
} else exit('-1');
?>
