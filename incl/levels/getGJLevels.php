<?php
// Keep the Geometry Dash level-list response byte-clean. Any whitespace/BOM
// emitted by legacy includes would become part of the first level object and
// can make its key "1" fail to parse, yielding m_levelID=0 for the first item.
ob_start();
chdir(dirname(__FILE__));
require "../lib/connection.php";
require "../../config/misc.php";
require_once "../lib/GJPCheck.php";
require_once "../lib/exploitPatch.php";
require_once "../lib/mainLib.php";
require_once "../lib/generateHash.php";
require_once "../lib/levelID.php";
$gs = new mainLib();
// Initializing variables
$lvlstring = $userstring = $songsstring = $suggestColumn = $suggestJoin = $str = $morejoins = "";
$lvlsmultistring = $epicParams = [];
$order = "uploadDate";
$orderenabled = $ordergauntlet = $isIDSearch = false;
$accountID = 0;
$params = ["unlisted = 0"];
if(!empty($_POST['accountID'])) {
	$accountID = GJPCheck::getAccountIDOrDie();
	if($unlistedLevelsForAdmins) {
		$checkAdmin = $db->prepare('SELECT isAdmin FROM accounts WHERE accountID = :accountID');
		$checkAdmin->execute([':accountID' => $accountID]);
		$checkAdmin = $checkAdmin->fetchColumn();
		if($checkAdmin) $params = [];
	}
}

$gameVersion = ExploitPatch::number($_POST["gameVersion"] ?? 0) ?: 0;
$binaryVersion = ExploitPatch::number($_POST["binaryVersion"] ?? 0) ?: 0;
$type = ExploitPatch::number($_POST["type"] ?? 0) ?: 0;
$diff = ExploitPatch::numbercolon($_POST["diff"] ?? '-') ?: '-';

// Additional search parameters

if(!$showAllLevels) {
	if($gameVersion == 0) $params[] = "levels.gameVersion <= 18";
	else $params[] = "levels.gameVersion <= '$gameVersion'";
}
if(isset($_POST["original"]) && $_POST["original"] == 1) $params[] = "original = 0";
if(isset($_POST["coins"]) && $_POST["coins"] == 1) $params[] = "starCoins = 1 AND NOT levels.coins = 0";
if((isset($_POST["uncompleted"]) || isset($_POST["onlyCompleted"])) && ($_POST["uncompleted"] == 1 || $_POST["onlyCompleted"] == 1)) {
	$completedLevels = ExploitPatch::numbercolon($_POST["completedLevels"] ?? "");
	if($completedLevels !== '') {
		$params[] = ($_POST['uncompleted'] == 1 ? 'NOT ' : '')."levelID IN ($completedLevels)";
	} else {
		$params[] = $_POST['uncompleted'] == 1 ? "1 = 1" : "1 = 0";
	}
}
if(isset($_POST["song"]) && $_POST["song"] > 0) {
	$song = ExploitPatch::number($_POST["song"]);
	if(!isset($_POST["customSong"])) {
		$song = $song - 1;
		$params[] = "audioTrack = '$song' AND songID = 0";
	} else $params[] = "songID = '$song'";
}
if(isset($_POST["twoPlayer"]) && $_POST["twoPlayer"] == 1) $params[] = "twoPlayer = 1";
if(isset($_POST["star"]) && $_POST["star"] == 1) $params[] = "NOT starStars = 0";
if(isset($_POST["noStar"]) && $_POST["noStar"] == 1) $params[] = "starStars = 0";
if(isset($_POST["gauntlet"]) && $_POST["gauntlet"] != 0) {
	$ordergauntlet = true;
	$order = "starStars";
	$gauntlet = ExploitPatch::number($_POST["gauntlet"]);
	$query = $db->prepare("SELECT * FROM gauntlets WHERE ID = :gauntlet");
	$query->execute([':gauntlet' => $gauntlet]);
	$actualgauntlet = $query->fetch();
	if(!$actualgauntlet) {
		$params[] = "1 = 0";
		$str = "";
	} else {
		$str = $actualgauntlet["level1"].",".$actualgauntlet["level2"].",".$actualgauntlet["level3"].",".$actualgauntlet["level4"].",".$actualgauntlet["level5"];
		$params[] = "levelID IN ($str)";
	}
	$type = -1;
}
$len = ExploitPatch::numbercolon($_POST["len"]) ?: '-';
if($len != "-" AND !empty($len)) $params[] = "levelLength IN ($len)";
if(isset($_POST["featured"]) && $_POST["featured"] == 1) $epicParams[] = "starFeatured > 0";
if(isset($_POST["epic"]) && $_POST["epic"] == 1) $epicParams[] = "starEpic = 1";
if(isset($_POST["mythic"]) && $_POST["mythic"] == 1) $epicParams[] = "starEpic = 2"; // The reason why Mythic and Legendary ratings are swapped because RobTop accidentally swapped them in-game
if(isset($_POST["legendary"]) && $_POST["legendary"] == 1) $epicParams[] = "starEpic = 3";
$epicFilter = implode(" OR ", $epicParams);
if(!empty($epicFilter)) $params[] = $epicFilter;

// Difficulty filters
switch($diff) {
	case -1:
		$params[] = "starDifficulty = '0'";
		break;
	case -3:
		$params[] = "starAuto = '1'";
		break;
	case -2:
		$demonFilter = ExploitPatch::number($_POST["demonFilter"]) ?: 0;
		$params[] = "starDemon = 1";
		switch($demonFilter) {
			case 1:
				$params[] = "starDemonDiff = '3'";
				break;
			case 2:
				$params[] = "starDemonDiff = '4'";
				break;
			case 3:
				$params[] = "starDemonDiff = '0'";
				break;
			case 4:
				$params[] = "starDemonDiff = '5'";
				break;
			case 5:
				$params[] = "starDemonDiff = '6'";
				break;
		}
		break;
	case "-";
		break;
	default:
		if($diff) {
			$diff = str_replace(",", "0,", $diff) . "0";
			$params[] = "starDifficulty IN ($diff) AND starAuto = '0' AND starDemon = '0'";
		}
		break;
}
// Type detection
// TODO: the 2 non-friend types that send GJP in 2.11
if(isset($_POST["str"])) $str = ExploitPatch::rucharclean($_POST["str"]) ?: '';
$offset = is_numeric($_POST["page"] ?? null) ? ExploitPatch::number($_POST["page"]) . "0" : 0;
switch($type){
	case 0: // Search
		$order = "likes";
		if(!empty($str)) {
			if(is_numeric($str)) {
				$params = array("levelID = '$str'");
				$isIDSearch = true;
			} else $params[] = "levelName LIKE '%$str%'";
		}
		break;
	case 4: // Recent
		$order = "GREATEST(uploadDate, updateDate)";
		break;
	case 15: // Most liked, changed to 15 in GDW for whatever reason
		$order = "likes";
		break;
	case 1: // Most downloaded
		$order = "downloads";
		break;
	case 2: // Most liked
		$order = "likes";
		break;
	case 3: // Trending
		$uploadDate = time() - (7 * 24 * 60 * 60);
		$params[] = "uploadDate > $uploadDate ";
		$order = "likes";
		break;
	case 5: // Levels per user
		if($accountID && $gs->getUserID($accountID, $gs->getAccountName($accountID)) == $str) $params = [];
		$params[] = "levels.userID = '$str'";
		break;
	case 6: // Featured
	case 17: // Featured in GDW
		if($gameVersion > 21) $params[] = "NOT starFeatured = 0 OR NOT starEpic = 0";
		else $params[] = "NOT starFeatured = 0";
		$order = "starFeatured DESC, rateDate DESC, uploadDate";
		break;
	case 16: // Hall of Fame
		$params[] = "NOT starEpic = 0";
		$order = "starFeatured DESC, rateDate DESC, uploadDate";
		break;
	case 7: // Magic
        $params[] = "objects > 9999"; // L
		break;
	case 10: // Map Packs
	case 19: // Unknown, but same as Map Packs (on real GD type 10 has star rated filter and 19 doesn't)
		$order = false;
		$params[] = $str !== '' ? "levelID IN ($str)" : "1 = 0";
		break;
	case 11: // Awarded
		$params[] = "NOT starStars = 0";
		$order = "rateDate DESC,uploadDate";
		break;
	case 12: // Followed
		$followed = ExploitPatch::numbercolon($_POST["followed"] ?? "");
		$params[] = $followed !== '' ? "users.extID IN ($followed)" : "1 = 0";
		break;
	case 13: // Friends
		if(!isset($accountID)) $accountID = GJPCheck::getAccountIDOrDie();
		$peoplearray = $gs->getFriends($accountID);
		if(empty($peoplearray)) {
			$params[] = "1 = 0";
		} else {
			$peoplearray = array_values(array_filter($peoplearray, 'is_numeric'));
			$params[] = !empty($peoplearray) ? "users.extID IN (".implode(",", $peoplearray).")" : "1 = 0";
		}
		break;
	case 21: // Daily safe
		$morejoins = "INNER JOIN dailyfeatures ON levels.levelID = dailyfeatures.levelID";
		$params[] = "dailyfeatures.type = 0 AND timestamp < ".time();
		$order = "dailyfeatures.feaID";
		break;
	case 22: // Weekly safe
		$morejoins = "INNER JOIN dailyfeatures ON levels.levelID = dailyfeatures.levelID";
		$params[] = "dailyfeatures.type = 1 AND timestamp < ".time();
		$order = "dailyfeatures.feaID";
		break;
	case 23: // Event safe
		$morejoins = "INNER JOIN events ON levels.levelID = events.levelID";
		$params[] = "timestamp < ".time();
		$order = "events.feaID";
		break;
	case 25: // List levels
		$listLevels = $gs->getListLevels($str);
		$params = !empty($listLevels) ? array("levelID IN (".$listLevels.")") : array("1 = 0");
		break;
	case 27: // Sent levels
		$suggestColumn = ", s.max_timestamp";
		$suggestJoin = "LEFT JOIN (SELECT suggestLevelId, MAX(timestamp) as max_timestamp FROM suggest GROUP BY suggestLevelId) s ON levels.levelID = s.suggestLevelId";
		$params[] = "s.suggestLevelId > 0";
		if(!$ratedLevelsInSent) $params[] = "starStars = 0";
		$order = 's.max_timestamp';
		break;
}
// Actual query execution
$querybase = "FROM levels LEFT JOIN songs ON levels.songID = songs.ID LEFT JOIN users ON levels.userID = users.userID $suggestJoin $morejoins";
if(!empty($params)) $querybase .= " WHERE (" . implode(" ) AND ( ", $params) . ")";
$query = "SELECT levels.*, songs.ID, songs.name, songs.authorID, songs.authorName, songs.size, songs.isDisabled, songs.download, users.userName, users.extID, users.clan$suggestColumn $querybase";
if($order) $query .= "ORDER BY $order ".($ordergauntlet ? 'ASC' : 'DESC');
$query .= " LIMIT 10 OFFSET $offset";
$countquery = "SELECT count(*) $querybase";
$query = $db->prepare($query);
$query->execute();
$countquery = $db->prepare($countquery);
$countquery->execute();
$totallvlcount = $countquery->fetchColumn();
$result = $query->fetchAll();
$levelcount = $query->rowCount();

// Secret-free trace of the level-list response that Geometry Dash parses.
// This helps correlate a visible level with the levelID sent later to
// downloadGJLevel22.php.
try {
	$db->exec("CREATE TABLE IF NOT EXISTS debug_gjlevels_trace (
		id TINYINT UNSIGNED NOT NULL,
		updated_at DATETIME NOT NULL,
		payload LONGTEXT NOT NULL,
		PRIMARY KEY (id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	$traceLevelIds = [];
	foreach($result as $traceLevel) {
		$traceLevelIds[] = [
			"levelID" => (int)($traceLevel["levelID"] ?? 0),
			"clientLevelID" => gdClientLevelID((int)($traceLevel["levelID"] ?? 0)),
			"levelName" => (string)($traceLevel["levelName"] ?? ""),
			"gameVersion" => (int)($traceLevel["gameVersion"] ?? 0),
			"userID" => (int)($traceLevel["userID"] ?? 0),
			"userName" => (string)($traceLevel["userName"] ?? ""),
			"extID" => (int)($traceLevel["extID"] ?? 0),
			"starStars" => (int)($traceLevel["starStars"] ?? 0),
			"starCoins" => (int)($traceLevel["starCoins"] ?? 0)
		];
	}

	$trace = [
		"timestamp" => gmdate("c"),
		"requestUri" => $_SERVER["REQUEST_URI"] ?? null,
		"ipHash" => hash("sha256", (string)$gs->getIP()),
		"gameVersion" => $gameVersion,
		"binaryVersion" => $binaryVersion,
		"type" => $type,
		"page" => (int)($offset / 10),
		"strProvided" => isset($_POST["str"]),
		"strIsNumeric" => isset($_POST["str"]) && is_numeric($_POST["str"]),
		"resultCount" => count($result),
		"totalLevelCount" => (int)$totallvlcount,
		"levels" => $traceLevelIds
	];

	$traceStmt = $db->prepare(
		"REPLACE INTO debug_gjlevels_trace (id, updated_at, payload)
		 VALUES (1, NOW(), :payload)"
	);
	$traceStmt->execute([
		":payload" => json_encode(
			$trace,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		)
	]);
} catch(Throwable $traceError) {
	// Never affect the live Geometry Dash level list.
}

foreach($result as &$level1) {
	if(empty($level1["levelID"])) continue;
	if($isIDSearch && $level1['unlisted'] > 0) {
		if(!isset($accountID)) $accountID = GJPCheck::getAccountIDOrDie();
		if($level1['unlisted'] == 1 && (!$gs->isFriends($accountID, $level1['extID']) && $accountID != $level1['extID'])) break;
	}
	if($gameVersion < 20) $level1['levelDesc'] = ExploitPatch::gd_escape(ExploitPatch::url_base64_decode($level1['levelDesc']));
	$lvlsmultistring[] = ["levelID" => gdClientLevelID($level1["levelID"]), "stars" => $level1["starStars"], 'coins' => $level1["starCoins"]];
	$likes = $level1["likes"]; // - $level1["dislikes"]; // Yeah, my GDPS has dislikes separated
	if(isset($gauntlet)) $lvlstring .= "44:$gauntlet:";
	$level1["starCoins"] = $level1["starCoins"] ? 1 : 0;
	$lvlstring .= "1:".gdClientLevelID($level1["levelID"]).":2:".ExploitPatch::translit($level1["levelName"]).":5:".$level1["levelVersion"].":6:".$level1["userID"].":8:10:9:".$level1["starDifficulty"].":10:".$level1["downloads"].":12:".$level1["audioTrack"].":13:".$level1["gameVersion"].":14:".$likes.":17:".$level1["starDemon"].":43:".$level1["starDemonDiff"].":25:".$level1["starAuto"].":18:".$level1["starStars"].":19:".$level1["starFeatured"].":42:".$level1["starEpic"].":45:".$level1["objects"].":3:".ExploitPatch::translit($level1["levelDesc"]).":15:".$level1["levelLength"].":30:".$level1["original"].":31:".$level1['twoPlayer'].":37:".$level1["coins"].":38:".$level1["starCoins"].":39:".$level1["requestedStars"].":46:1:47:2:40:".$level1["isLDM"].":35:".$level1["songID"]."|";
	if($level1["songID"] != 0) {
		$song = $gs->getSongString($level1);
		if($song) $songsstring .= $song . "~:~";
	}
	$userstring .= $gs->getUserString($level1)."|";
}
$lvlstring = substr($lvlstring, 0, -1);
$userstring = substr($userstring, 0, -1);
$songsstring = substr($songsstring, 0, -3);

$finalResponse = $lvlstring."#".$userstring;
if($gameVersion > 18) $finalResponse .= "#".$songsstring;
$finalResponse .= "#".$totallvlcount.":".$offset.":10";
$finalResponse .= "#";
$finalResponse .= GenerateHash::genMulti($lvlsmultistring);

// Persist a safe view of the exact response we are about to send.
// The full response is not stored; only enough of the first level section
// is retained to diagnose client-side parsing.
try {
	$traceQuery = $db->prepare("SELECT payload FROM debug_gjlevels_trace WHERE id = 1 LIMIT 1");
	$traceQuery->execute();
	$existingTrace = $traceQuery->fetchColumn();
	$traceData = is_string($existingTrace) ? json_decode($existingTrace, true) : null;
	if(!is_array($traceData)) $traceData = [];

	$firstSection = explode("#", $finalResponse, 2)[0];
	$traceData["response"] = [
		"length" => strlen($finalResponse),
		"sha256" => hash("sha256", $finalResponse),
		"hashDelimiterCount" => substr_count($finalResponse, "#"),
		"prefix" => substr($firstSection, 0, 1200),
		"startsWithLevelOne" => str_starts_with($firstSection, "1:1:"),
		"firstSectionLength" => strlen($firstSection)
	];
	$responseSections = explode("#", $finalResponse);
	$traceData["response"]["sectionLengths"] = array_map("strlen", $responseSections);
	$traceData["response"]["sectionPrefixes"] = array_map(
		static function($section) { return substr($section, 0, 500); },
		$responseSections
	);
	$traceStmt = $db->prepare(
		"UPDATE debug_gjlevels_trace
		 SET updated_at = NOW(), payload = :payload
		 WHERE id = 1"
	);
	$traceStmt->execute([
		":payload" => json_encode(
			$traceData,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		)
	]);
} catch(Throwable $traceError) {
	// Never affect the live Geometry Dash level list.
}

// Strip accidental output from the include chain so the first byte is always the level object.
while (ob_get_level() > 0) {
	ob_end_clean();
}
header("Content-Type: text/plain; charset=utf-8");
echo $finalResponse;
?>