<?php
session_start();
require "../incl/dashboardLib.php";
require "../".$dbPath."incl/lib/connection.php";
require "../".$dbPath."config/dashboard.php";
require_once "../".$dbPath."incl/lib/exploitPatch.php";
require_once "../".$dbPath."incl/lib/mainLib.php";
$gs = new mainLib();
$dl = new dashboardLib();
if(!$clansEnabled) exit($dl->printSong('<div class="form">
	<h1>'.$dl->getLocalizedString("errorGeneric").'</h1>
	<form class="form__inner" method="post" action=".">
	<p id="dashboard-error-text">'.$dl->getLocalizedString("pageDisabled").'</p>
	<button type="button" onclick="a(\'\', true, false, \'GET\')" class="btn-song">'.$dl->getLocalizedString("dashboard").'</button>
	</form>
</div>', 'browse'));
$isPlayerInClan = $gs->isPlayerInClan($_SESSION["accountID"]);
$dl->printFooter('../');
$dl->title($dl->getLocalizedString("clans"));
$clans = $db->prepare("SELECT clans.*, COUNT(users.clan) AS members FROM clans LEFT JOIN users ON clans.id = users.clan GROUP BY clans.id ORDER BY members DESC;");
$clans->execute();
$clans = $clans->fetchAll();
$options = "";
foreach($clans as &$clan) {
	$name = htmlspecialchars(base64_decode($clan["clan"]));
	$tag = htmlspecialchars(base64_decode($clan["tag"]));
	$desc = $dl->parseMessage(htmlspecialchars(base64_decode($clan["desc"])));
	if(empty($desc)) $desc = $dl->getLocalizedString("noClanDesc");
	$members = $db->prepare("SELECT count(clan) FROM users WHERE clan = :id");
	$members->execute([':id' => $clan["ID"]]);
	$members = $members->fetchColumn() - 1;
	$dontmind = mb_substr($members, -1);
	if($dontmind == 1) $dm = 0; elseif($dontmind < 5 AND $dontmind > 0) $dm = 1; else $dm = 2;
	if($members > 9 AND $members < 20) $dm = 2;
	$locked = $clan["isClosed"] == 1 ? ' <i class="fa-solid fa-lock" aria-hidden="true"></i>' : '';
	$options .= '<div class="gd-clan" style="--clan-color:#'.htmlspecialchars($clan["color"]).'">
		<div class="gd-clan-head">
			<span class="gd-clan-tag">'.$tag.'</span>
			<div style="min-width:0;flex:1">
				<h2 class="gd-clan-name" style="color:#'.htmlspecialchars($clan["color"]).'">'.$name.$locked.'</h2>
				<button type="button" class="gd-clan-owner" onclick="a(\'profile/'.htmlspecialchars($gs->getAccountName($clan["clanOwner"])).'\', true, true)"><i class="fa-solid fa-crown"></i>'.htmlspecialchars($gs->getAccountName($clan["clanOwner"])).'</button>
			</div>
			<div class="gd-clan-actions"><button type="button" class="gd-btn gd-btn--secondary" onclick="a(\'clan/'.htmlspecialchars($name).'\', true, true)"><i class="fa-solid fa-magnifying-glass"></i>'.$dl->getLocalizedString("viewAll").'</button></div>
		</div>
		<p class="gd-clan-desc">'.$desc.'</p>
		<div class="gd-clan-foot">
			<span class="gd-chip gd-chip--blue"><i class="fa-solid fa-user-group"></i>'.sprintf($dl->getLocalizedString("members".$dm), max(0, $members)).'</span>
		</div>
	</div>';
}
$create = '';
if($_SESSION["accountID"] != 0 AND !$isPlayerInClan) $create = '<button type="button" class="gd-btn gd-btn--primary" onclick="a(\'clans/create.php\')"><i class="fa-solid fa-plus"></i>'.$dl->getLocalizedString("createClan").'</button>';
elseif($_SESSION["accountID"] == 0) $create = '';

$pagel = '<div class="gd-pagehead">
	<p class="gd-eyebrow">GDIPS</p>
	<div class="gd-pagehead-row">
		<div>
			<h1 class="gd-display">'.$dl->getLocalizedString("clans").'</h1>
			<p class="gd-pagehead-sub">'.$dl->getLocalizedString("clanOptDesc").'</p>
		</div>
		<div class="gd-pagehead-actions">'.$create.'</div>
	</div>
</div>
<div class="gd-grid gd-grid--2">';
if(empty($options)) {
	$pagel .= '<div class="gd-empty" style="grid-column:1/-1"><i class="fa-solid fa-dungeon"></i><p>'.$dl->getLocalizedString("noClans").'</p>'
		.($_SESSION["accountID"] != 0 ? '<button type="button" class="gd-btn gd-btn--primary" onclick="a(\'clans/create.php\')"><i class="fa-solid fa-plus"></i>'.$dl->getLocalizedString("createClan").'</button>' : '')
		.'</div>';
} else {
	$pagel .= $options;
}
$pagel .= '</div>';
$dl->printSong($pagel, 'clans');
?>
