<?php
session_start();
require "../incl/dashboardLib.php";
require "../".$dbPath."incl/lib/connection.php";
require "../".$dbPath."config/dashboard.php";
require_once "../".$dbPath."incl/lib/mainLib.php";
require_once "../".$dbPath."incl/lib/badgeLib.php";
require_once "../".$dbPath."incl/lib/exploitPatch.php";
$gs = new mainLib();
$dl = new dashboardLib();
$dl->title($dl->getLocalizedString("modActions"));
$dl->printFooter('../');
$modtable = "";
$pagelol = explode("/", $_SERVER["REQUEST_URI"]);
$pagelol = $pagelol[count($pagelol)-2]."/".$pagelol[count($pagelol)-1];
$pagelol = explode("?", $pagelol)[0];
if(!isset($_GET["search"])) $_GET["search"] = "";
$srcbtn = "";
if(!empty($_GET["search"])) {
	$query = $db->prepare("SELECT * FROM accounts INNER JOIN users INNER JOIN roleassign WHERE isActive = 1 AND accounts.accountID = users.extID AND accounts.accountID = roleassign.accountID AND accounts.userName LIKE '%".ExploitPatch::remove($_GET["search"])."%' ORDER BY roleassign.roleID ASC, accounts.userName ASC");
	$srcbtn = '<button type="button" onclick="a(\''.$pagelol.'\', true, true, \'GET\')" class="gd-btn gd-btn--ghost" title="'.$dl->getLocalizedString("searchCancel").'" aria-label="'.$dl->getLocalizedString("searchCancel").'"><i class="fa-solid fa-xmark"></i></button>';
} else $query = $db->prepare("SELECT * FROM accounts INNER JOIN users INNER JOIN roleassign WHERE isActive = 1 AND accounts.accountID = users.extID AND accounts.accountID = roleassign.accountID ORDER BY roleassign.roleID ASC, accounts.userName ASC");
$query->execute();
$result = $query->fetchAll();
$row = 0;
if(empty($result)) {
	$dl->printSong('<div class="form">
    <h1>'.$dl->getLocalizedString("errorGeneric").'</h1>
    <form class="form__inner" method="post" action=".">
		<p id="dashboard-error-text">'.$dl->getLocalizedString("emptyPage").'</p>
        <button type="button" onclick="a(\'\', true, false, \'GET\')" class="btn-primary">'.$dl->getLocalizedString("dashboard").'</button>
    </form>
</div>', 'stats');
	die();
} 
foreach($result as &$action) {
	$clan = $own = '';
	$query = $db->prepare("SELECT * FROM (SELECT count(*) AS actionCount FROM modactions WHERE account = :id) actionCount JOIN (SELECT count(*) AS levelsRated FROM modactions WHERE account = :id AND type = 1) levelsRated");
	$query->execute([':id' => $action["accountID"]]);
	$counts = $query->fetch();
	$accUserID = $gs->getUserID($action["accountID"]);
	$accountIDText = $action["accountID"].' | '.$accUserID;
	if($action["accountID"] == $accUserID) $accountIDText = $action["accountID"];
	$accountID = $action["accountID"];
	$resultRole = $action["roleID"];
	$query = $db->prepare("SELECT roleName FROM roles WHERE roleID = :id");
	$query->execute([':id' => $resultRole]);
	$resultRole = $query->fetch()["roleName"];
	// Avatar management
	$iconType = ($action['iconType'] > 8) ? 0 : $action['iconType'];
    $iconTypeMap = [0 => ['type' => 'cube', 'value' => $action['accIcon']], 1 => ['type' => 'ship', 'value' => $action['accShip']], 2 => ['type' => 'ball', 'value' => $action['accBall']], 3 => ['type' => 'ufo', 'value' => $action['accBird']], 4 => ['type' => 'wave', 'value' => $action['accDart']], 5 => ['type' => 'robot', 'value' => $action['accRobot']], 6 => ['type' => 'spider', 'value' => $action['accSpider']], 7 => ['type' => 'swing', 'value' => $action['accSwing']], 8 => ['type' => 'jetpack', 'value' => $action['accJetpack']]];
    $iconValue = (isset($iconTypeMap[$iconType]) && $iconTypeMap[$iconType]['value'] > 0) ? $iconTypeMap[$iconType]['value'] : 1;
    $avatarImg = '<img src="'.$iconsRendererServer.'/icon.png?type=' . $iconTypeMap[$iconType]['type'] . '&value=' . $iconValue . '&color1=' . $action['color1'] . '&color2=' . $action['color2'] . ($action['accGlow'] != 0 ? '&glow=' . $action['accGlow'] . '&color3=' . $action['color3'] : '') . '" alt="" style="width:44px;height:44px;object-fit:contain" loading="lazy">';
   // Badge management
	$badgeImg = '';
	$queryRoleID = $db->prepare("SELECT roleID FROM roleassign WHERE accountID = :accountID");
	$queryRoleID->execute([':accountID' => $accountID]);
	if($roleAssignData = $queryRoleID->fetch(PDO::FETCH_ASSOC)) {
		$queryBadgeLevel = $db->prepare("SELECT modBadgeLevel FROM roles WHERE roleID = :roleID");
		$queryBadgeLevel->execute([':roleID' => $roleAssignData['roleID']]);
		$badgeImg = gdBadgeLib::render((int)($queryBadgeLevel->fetchColumn() ?? 0), '', 34);
	}
	$ac = '<span class="gd-chip gd-chip--ghost">'.$counts["actionCount"].' <i class="fa-solid fa-circle-play" aria-hidden="true"></i></span>';
	$lr = '<span class="gd-chip gd-chip--ghost">'.$counts["levelsRated"].' <i class="fa-regular fa-star" aria-hidden="true"></i></span>';
	$stats = $dl->createProfileStats($action['stars'], $action['moons'], $action['diamonds'], $action['coins'], $action['userCoins'], $action['demons'], $action['creatorPoints'], 0, false);
	$registerDate = $dl->convertToDate($action["registerDate"], true);
	if($action["clan"] != 0) {
		$claninfo = $gs->getClanInfo($action["clan"]);
		if($claninfo["clanOwner"] == $action["accountID"]) $own = '<i style="color:var(--kuning)" class="fa-solid fa-crown" title="Leader"></i>';
		$clan = '<span class="gd-chip" style="color:#'.htmlspecialchars($claninfo["color"]).'"><i class="fa-solid fa-dungeon"></i>'.htmlspecialchars($claninfo["clan"]).'</span>';
	}
	$members .= '<div class="gd-account">
		'.$avatarImg.'
		<div style="min-width:0;flex:1">
			<div class="gd-account-head">
				<button type="button" onclick="a(\'profile/'.$action["userName"].'\', true, true, \'GET\')" class="gd-account-name" style="color:rgb('.$gs->getAccountCommentColor($action["accountID"]).')">'.$action["userName"].'</button>'.$badgeImg.' '.$clan.'
				<span class="gd-chip gd-chip--ghost accresultrole">'.$resultRole.'</span>
			</div>
			<div class="gd-account-stats" style="margin-top:10px">'.$stats.$ac.$lr.'</div>
		</div>
		<div class="gd-account-foot" style="flex-direction:column;align-items:flex-end;gap:4px">
			<span>ID <b style="color:var(--tx-2)">'.$accountIDText.'</b></span>
			<span>'.$dl->getLocalizedString("registerDate").' <b style="color:var(--tx-2)">'.$registerDate.'</b></span>
		</div>
	</div>';
	$x++;
}
$searchbar = '<form name="searchform" class="gd-searchbar" onsubmit="a(\''.$pagelol.'\', true, true, \'GET\', 69);return false;">
	<input type="text" name="search" value="'.htmlspecialchars($_GET["search"]).'" placeholder="'.$dl->getLocalizedString("search").'" aria-label="'.$dl->getLocalizedString("search").'">
	<button type="submit" class="gd-btn gd-btn--secondary" title="'.$dl->getLocalizedString("search").'" aria-label="'.$dl->getLocalizedString("search").'"><i class="fa-solid fa-magnifying-glass"></i></button>'
	.$srcbtn.'
</form>';
$pagel = '<div class="gd-pagehead">
	<p class="gd-eyebrow">GDIPS</p>
	<div class="gd-pagehead-row">
		<div>
			<h1 class="gd-display">'.$dl->getLocalizedString("modActions").'</h1>
		</div>
	</div>
</div>
<div class="gd-toolbar">'.$searchbar.'</div>
<div class="gd-list">'.$members.'</div>';
$dl->printPage($pagel.$bottomrow, true, "stats");
?>