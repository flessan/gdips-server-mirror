<?php
session_start();
require "../incl/dashboardLib.php";
require "../".$dbPath."incl/lib/connection.php";
require "../".$dbPath."config/dashboard.php";
require_once "../".$dbPath."incl/lib/exploitPatch.php";
require_once "../".$dbPath."incl/lib/mainLib.php";
$gs = new mainLib();
$dl = new dashboardLib();
$dl->title($dl->getLocalizedString("playersList"));
$dl->printFooter('../');
if(isset($_GET["page"]) AND is_numeric($_GET["page"]) AND $_GET["page"] > 0) {
	$page = ($_GET["page"] - 1) * 10;
	$actualpage = $_GET["page"];
} else {
	$page = 0;
	$actualpage = 1;
}
$pagelol = explode("/", $_SERVER["REQUEST_URI"]);
$pagelol = $pagelol[count($pagelol)-2]."/".$pagelol[count($pagelol)-1];
$pagelol = explode("?", $pagelol)[0];
if(!isset($_GET["search"])) $_GET["search"] = "";
if(!isset($_GET["type"])) $_GET["type"] = "";
if(!isset($_GET["ng"])) $_GET["ng"] = "";
$srcbtn = $members = "";
$searchValue = trim(ExploitPatch::remove($_GET["search"]));
if(empty($searchValue)) {
	$query = $db->prepare("SELECT * FROM accounts INNER JOIN users WHERE isActive = 1 AND accounts.accountID = users.extID ORDER BY accountID ASC LIMIT 10 OFFSET $page");
	$query->execute();
	$result = $query->fetchAll();
} else {
	$query = $db->prepare("SELECT * FROM accounts INNER JOIN users WHERE accounts.userName LIKE :search AND isActive = 1 AND accounts.accountID = users.extID ORDER BY accountID ASC LIMIT 10 OFFSET $page");
	$query->execute([':search' => "%".$searchValue."%"]);
	$result = $query->fetchAll();
}

$searchbar = '<form name="searchform" class="gd-searchbar" onsubmit="a(\''.$pagelol.'\', true, true, \'GET\', 69);return false;">
	<input type="text" name="search" value="'.htmlspecialchars($searchValue).'" placeholder="'.$dl->getLocalizedString("search").'" aria-label="'.$dl->getLocalizedString("search").'">
	<button type="submit" class="gd-btn gd-btn--secondary" title="'.$dl->getLocalizedString("search").'" aria-label="'.$dl->getLocalizedString("search").'"><i class="fa-solid fa-magnifying-glass"></i></button>'
	.(!empty($searchValue) ? '<button type="button" class="gd-btn gd-btn--ghost" title="'.$dl->getLocalizedString("searchCancel").'" aria-label="'.$dl->getLocalizedString("searchCancel").'" onclick="a(\''.$pagelol.'\', true, true, \'GET\')"><i class="fa-solid fa-xmark"></i></button>' : '').'
</form>';

foreach($result as &$action){
	$clan = $own = '';
	$accUserID = $action["userID"];
	$accountIDText = $action["accountID"].' | '.$accUserID;
	if($action["accountID"] == $accUserID) $accountIDText = $action["accountID"];
	$accountID = $action["accountID"];
	$query = $db->prepare("SELECT roleID FROM roleassign WHERE accountID = :accid");
	$query->execute([':accid' => $accountID]);
	$resultPls = $query->fetch();
	if(!$resultPls) $resultRole = $dl->getLocalizedString("player");
	else {
		$resultRole = $resultPls["roleID"];
		if(empty($resultRole)) {
			$resultRole = $dl->getLocalizedString("player");
		} else {
			$query = $db->prepare("SELECT roleName FROM roles WHERE roleID = :id");
			$query->execute([':id' => $resultRole]);
			$resultRole = $query->fetch()["roleName"];
		}
	}
	if($action["clan"] != 0) {
		$claninfo = $gs->getClanInfo($action["clan"]);
		if($claninfo["clanOwner"] == $action["accountID"]) $own = '<i style="color:var(--kuning)" class="fa-solid fa-crown" title="Leader"></i>';
		$clan = '<span class="gd-chip" style="color:#'.htmlspecialchars($claninfo["color"]).'"><i class="fa-solid fa-dungeon"></i>'.htmlspecialchars($claninfo["clan"]).'</span>';
	}
	$iconType = ($action['iconType'] > 8) ? 0 : $action['iconType'];
	$iconTypeMap = [0 => ['type' => 'cube', 'value' => $action['accIcon']], 1 => ['type' => 'ship', 'value' => $action['accShip']], 2 => ['type' => 'ball', 'value' => $action['accBall']], 3 => ['type' => 'ufo', 'value' => $action['accBird']], 4 => ['type' => 'wave', 'value' => $action['accDart']], 5 => ['type' => 'robot', 'value' => $action['accRobot']], 6 => ['type' => 'spider', 'value' => $action['accSpider']], 7 => ['type' => 'swing', 'value' => $action['accSwing']], 8 => ['type' => 'jetpack', 'value' => $action['accJetpack']]];
	$iconValue = (isset($iconTypeMap[$iconType]) && $iconTypeMap[$iconType]['value'] > 0) ? $iconTypeMap[$iconType]['value'] : 1;
	$avatarImg = '<img src="'.$iconsRendererServer.'/icon.png?type='.$iconTypeMap[$iconType]['type'].'&value='.$iconValue.'&color1='.$action['color1'].'&color2='.$action['color2'].($action['accGlow'] != 0 ? '&glow='.$action['accGlow'].'&color3='.$action['color3'] : '').'" alt="" style="width:44px;height:44px;object-fit:contain" loading="lazy">';
	$badgeImg = '';
	$queryRoleID = $db->prepare("SELECT roleID FROM roleassign WHERE accountID = :accountID");
	$queryRoleID->execute([':accountID' => $accountID]);
	if($roleAssignData = $queryRoleID->fetch(PDO::FETCH_ASSOC)) {
		$queryBadgeLevel = $db->prepare("SELECT modBadgeLevel FROM roles WHERE roleID = :roleID");
		$queryBadgeLevel->execute([':roleID' => $roleAssignData['roleID']]);
		if(($modBadgeLevel = $queryBadgeLevel->fetchColumn() ?? 0) >= 1 && $modBadgeLevel <= 3) {
			$badgeImg = '<img src="https://raw.githubusercontent.com/Fenix668/GMDprivateServer/master/dashboard/modBadge_0'.$modBadgeLevel.'_001.png" alt="moderator" style="width: 24px; height: 24px; object-fit: contain;">';
		}
	}
	$stats = $dl->createProfileStats($action['stars'], $action['moons'], $action['diamonds'], $action['coins'], $action['userCoins'], $action['demons'], $action['creatorPoints'], 0);
	$registerDate = date("d.m.Y", $action["registerDate"]);
	if($action['userName'] == "Undefined") $action['userName'] = $gs->getAccountName($action["accountID"]);
	$displayName = htmlspecialchars($action["userName"]);
	$members .= '<div class="gd-account">
		'.$avatarImg.'
		<div style="min-width:0;flex:1">
			<div class="gd-account-head">
				<button type="button" onclick="a(\'profile/'.htmlspecialchars($action["userName"]).'\', true, true, \'GET\')" class="gd-account-name" style="color:rgb('.$gs->getAccountCommentColor($action["accountID"]).')">'.$displayName.'</button>'.$badgeImg.' '.$clan.'
				<span class="gd-chip gd-chip--ghost accresultrole">'.$resultRole.'</span>
			</div>
			<div class="gd-account-stats" style="margin-top:10px">'.$stats.'</div>
		</div>
		<div class="gd-account-foot" style="flex-direction:column;align-items:flex-end;gap:4px">
			<span>ID <b style="color:var(--tx-2)">'.$accountIDText.'</b></span>
			<span>'.$dl->getLocalizedString("registerDate").' <b style="color:var(--tx-2)">'.$registerDate.'</b></span>
		</div>
	</div>';
}
$pagel = '<div class="gd-pagehead">
	<p class="gd-eyebrow">GDIPS</p>
	<div class="gd-pagehead-row">
		<div>
			<h1 class="gd-display">'.$dl->getLocalizedString("playersList").'</h1>
			<p class="gd-pagehead-sub">'.$dl->getLocalizedString("accounts").'</p>
		</div>
	</div>
</div>
<div class="gd-toolbar">'.$searchbar.'</div>
<div class="gd-list">';
if(empty($result)) {
	$pagel .= '<div class="gd-empty"><i class="fa-solid fa-users"></i><p>'.(empty($searchValue) ? $dl->getLocalizedString("emptyPage") : $dl->getLocalizedString("noResults")).'</p>'
		.(!empty($searchValue) ? '<button type="button" class="gd-btn gd-btn--secondary" onclick="a(\''.$pagelol.'\', true, true, \'GET\')"><i class="fa-solid fa-xmark"></i>'.$dl->getLocalizedString("searchCancel").'</button>' : '')
		.'</div>';
} else {
	$pagel .= $members;
}
$pagel .= '</div>';
if(empty(trim(ExploitPatch::remove($_GET["search"])))) $query = $db->prepare("SELECT count(*) FROM accounts INNER JOIN users WHERE isActive = 1 AND accounts.accountID = users.extID");
else $query = $db->prepare("SELECT count(*) FROM accounts INNER JOIN users WHERE accounts.userName LIKE :search AND isActive = 1 AND accounts.accountID = users.extID");
$query->execute(empty($searchValue) ? [] : [':search' => "%".$searchValue."%"]);
$packcount = $query->fetchColumn();
$pagecount = ceil($packcount / 10);
$bottomrow = $dl->generateBottomRow($pagecount, $actualpage);
$dl->printPage($pagel.$bottomrow, true, "players");
?>
