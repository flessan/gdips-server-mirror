<?php
chdir(dirname(__FILE__));
require "../lib/connection.php";
require "../../config/misc.php";
require_once "../lib/GJPCheck.php";
require_once "../lib/exploitPatch.php";
require_once "../lib/mainLib.php";
require_once "../lib/cron.php";
$gs = new mainLib();

$levelID = ExploitPatch::remove($_POST["levelID"]);
$accountID = GJPCheck::getAccountIDOrDie();

if(!is_numeric($levelID)) exit("-1");

$userID = $gs->getUserID($accountID);
$query = $db->prepare("SELECT * FROM levels WHERE levelID = :levelID AND userID = :userID");
$query->execute([':levelID' => $levelID, ':userID' => $userID]);
$getLevelData = $query->fetch();

if(!$getLevelData) exit("-1");

$db->beginTransaction();
try {
	$query = $db->prepare("DELETE FROM comments WHERE levelID = :levelID");
	$query->execute([':levelID' => $levelID]);
	$query = $db->prepare("DELETE FROM levelscores WHERE levelID = :levelID");
	$query->execute([':levelID' => $levelID]);
	$query = $db->prepare("DELETE FROM actions_downloads WHERE levelID = :levelID");
	$query->execute([':levelID' => $levelID]);
	$query = $db->prepare("DELETE FROM cpshares WHERE levelID = :levelID");
	$query->execute([':levelID' => $levelID]);
	$query = $db->prepare("DELETE FROM demonlist WHERE levelID = :levelID");
	$query->execute([':levelID' => $levelID]);
	$query = $db->prepare("DELETE FROM dlsubmits WHERE levelID = :levelID");
	$query->execute([':levelID' => $levelID]);
	$query = $db->prepare("DELETE FROM suggest WHERE suggestLevelId = :levelID");
	$query->execute([':levelID' => $levelID]);
	$query = $db->prepare("DELETE FROM levels WHERE levelID = :levelID AND userID = :userID LIMIT 1");
	$query->execute([':levelID' => $levelID, ':userID' => $userID]);
	$db->commit();
} catch (Throwable $deleteError) {
	if($db->inTransaction()) $db->rollBack();
	exit("-1");
}
$query->execute([':levelID' => $levelID, ':userID' => $userID]);
$levelFile = __DIR__ . "/../../data/levels/" . $levelID;
	$deletedDir = __DIR__ . "/../../data/levels/deleted";
	if (is_file($levelFile)) {
		if (!is_dir($deletedDir)) @mkdir($deletedDir, 0755, true);
		if (!@rename($levelFile, $deletedDir . "/" . $levelID)) @unlink($levelFile);
	}
echo "1";
$gs->logAction($accountID, 8, $getLevelData['levelName'], $getLevelData['levelDesc'], $getLevelData['extID'], $levelID, $getLevelData['starStars'], $getLevelData['starDifficulty']);
$gs->sendLogsLevelChangeWebhook($levelID, $accountID, $getLevelData);
if($automaticCron) {
	Cron::autoban($accountID, false);
	Cron::updateCreatorPoints($accountID, false);
	Cron::updateSongsUsage($accountID, false);
}
?>