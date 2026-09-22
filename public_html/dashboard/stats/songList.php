<?php
session_start();
require "../incl/dashboardLib.php";
require "../".$dbPath."incl/lib/connection.php";
require_once "../".$dbPath."incl/lib/mainLib.php";
require_once "../".$dbPath."incl/lib/exploitPatch.php";
$gs = new mainLib();
$dl = new dashboardLib();
$dl->title($dl->getLocalizedString("songs"));
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
$srcbtn = $favs = $meta = $songs = "";
$ngw = $_GET["ng"] == 1 ? '' : 'AND reuploadID > 0';
$where = "isDisabled = 0 $ngw";
$params = [];
$searchValue = trim(ExploitPatch::rucharclean($_GET["search"]));
if(!empty($searchValue)) {
	$where .= is_numeric($searchValue) ? " AND ID LIKE :search" : " AND (name LIKE :search OR authorName LIKE :search)";
	$params[':search'] = "%".$searchValue."%";
}
$query = $db->prepare("SELECT * FROM songs WHERE $where ORDER BY reuploadTime DESC LIMIT 10 OFFSET $page");
$query->execute($params);
$result = $query->fetchAll();

$searchbar = '<form name="searchform" class="gd-searchbar" onsubmit="a(\''.$pagelol.'\', true, true, \'GET\', 69);return false;">
	<input type="text" name="search" value="'.htmlspecialchars($searchValue).'" placeholder="'.$dl->getLocalizedString("search").'" aria-label="'.$dl->getLocalizedString("search").'">
	<button type="submit" class="gd-btn gd-btn--secondary" title="'.$dl->getLocalizedString("search").'" aria-label="'.$dl->getLocalizedString("search").'"><i class="fa-solid fa-magnifying-glass"></i></button>'
	.(!empty($searchValue) ? '<button type="button" class="gd-btn gd-btn--ghost" title="'.$dl->getLocalizedString("searchCancel").'" aria-label="'.$dl->getLocalizedString("searchCancel").'" onclick="a(\''.$pagelol.'\', true, true, \'GET\')"><i class="fa-solid fa-xmark"></i></button>' : '').'
</form>';

/* Newgrounds library filter */
$ngOn = $_GET["ng"] == 1;
$ngQs = http_build_query(array_filter(["search" => $searchValue, "ng" => $ngOn ? "" : "1"]));
$filters = '<a class="gd-filter'.($ngOn ? ' is-on' : '').'" href="'.htmlspecialchars($pagelol.(!empty($ngQs) ? "?".$ngQs : "")).'" onclick="a(\''.htmlspecialchars($pagelol.(!empty($ngQs) ? "?".$ngQs : "")).'\', true, true);return false;"><i class="fa-solid fa-record-vinyl"></i>'.($ngOn ? 'Newgrounds ✕' : 'Newgrounds?').'</a>';

foreach($result as &$action) $songs .= $dl->generateSongCard($action);

$query = $db->prepare("SELECT count(*) FROM songs WHERE $where");
$query->execute($params);
$packcount = $query->fetchColumn();
$pagecount = ceil($packcount / 10);

$pagel = '<div class="gd-pagehead">
	<p class="gd-eyebrow">GDIPS</p>
	<div class="gd-pagehead-row">
		<div>
			<h1 class="gd-display">'.$dl->getLocalizedString("songs").'</h1>
			<p class="gd-pagehead-sub">'.number_format($packcount).' '.$dl->getLocalizedString("songs").'</p>
		</div>
	</div>
</div>
<div class="gd-toolbar">
	'.$searchbar.'
	<div class="gd-toolbar-spacer"></div>
	<div class="gd-inlineform">'.$filters.'</div>
</div>
<div class="gd-list">';
if(empty($result)) {
	$pagel .= '<div class="gd-empty"><i class="fa-solid fa-music"></i><p>'.(empty($searchValue) ? $dl->getLocalizedString("emptyPage") : $dl->getLocalizedString("noResults")).'</p>'
		.(!empty($searchValue) ? '<button type="button" class="gd-btn gd-btn--secondary" onclick="a(\''.$pagelol.'\', true, true, \'GET\')"><i class="fa-solid fa-xmark"></i>'.$dl->getLocalizedString("searchCancel").'</button>' : '')
		.'</div>';
} else {
	$pagel .= $songs;
}
$pagel .= '</div>';

$bottomrow = $dl->generateBottomRow($pagecount, $actualpage);
$dl->printPage($pagel.$bottomrow, true, "songs");
?>
