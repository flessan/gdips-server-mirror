<?php
session_start();
require "../incl/dashboardLib.php";
require "../".$dbPath."incl/lib/connection.php";
$dl = new dashboardLib();
require_once "../".$dbPath."incl/lib/mainLib.php";
require_once "../".$dbPath."incl/lib/exploitPatch.php";
$gs = new mainLib();
$dl->title($dl->getLocalizedString("levels"));
$dl->printFooter('../');
if(isset($_GET["page"]) AND is_numeric($_GET["page"]) AND $_GET["page"] > 0){
	$page = ($_GET["page"] - 1) * 10;
	$actualpage = $_GET["page"];
} else {
	$page = 0;
	$actualpage = 1;
}
if(!isset($_GET["search"])) $_GET["search"] = "";
if(!isset($_GET["type"])) $_GET["type"] = "";
if(!isset($_GET["ng"])) $_GET["ng"] = "";
if(!isset($_GET["sort"])) $_GET["sort"] = "";
$srcbtn = $levels = "";
$pagelol = explode("/", $_SERVER["REQUEST_URI"]);
$pagelol = $pagelol[count($pagelol)-2]."/".$pagelol[count($pagelol)-1];
$pagelol = explode("?", $pagelol)[0];
$modcheck = $gs->checkPermission($_SESSION["accountID"], "dashboardModTools");
$searchValue = trim(ExploitPatch::rucharclean($_GET["search"]));

/* Sort whitelist - presentation-level ordering only. */
switch($_GET["sort"]) {
	case "downloads": $orderBy = "downloads DESC"; break;
	case "likes": $orderBy = "likes DESC"; break;
	case "featured": $orderBy = "starEpic DESC, starFeatured DESC, uploadDate DESC"; break;
	default: $_GET["sort"] = ""; $orderBy = "uploadDate DESC"; break;
}
$where = "unlisted = 0";
$params = [];
if(!empty($searchValue)) {
	$where .= is_numeric($searchValue) ? " AND levelID LIKE :search" : " AND levelName LIKE :search";
	$params[':search'] = "%".$searchValue."%";
}
$query = $db->prepare("SELECT * FROM levels WHERE $where ORDER BY $orderBy LIMIT 10 OFFSET $page");
$query->execute($params);
$result = $query->fetchAll();

/* search form (a(..., 69) reads form[name=searchform]) */
$searchbar = '<form name="searchform" class="gd-searchbar" onsubmit="a(\''.$pagelol.'\', true, true, \'GET\', 69);return false;">
	<input type="text" name="search" value="'.htmlspecialchars($searchValue).'" placeholder="'.$dl->getLocalizedString("search").'" aria-label="'.$dl->getLocalizedString("search").'">
	<button type="submit" class="gd-btn gd-btn--secondary" title="'.$dl->getLocalizedString("search").'" aria-label="'.$dl->getLocalizedString("search").'"><i class="fa-solid fa-magnifying-glass"></i></button>'
	.(!empty($searchValue) ? '<button type="button" class="gd-btn gd-btn--ghost" title="'.$dl->getLocalizedString("searchCancel").'" aria-label="'.$dl->getLocalizedString("searchCancel").'" onclick="a(\''.$pagelol.'\', true, true, \'GET\')"><i class="fa-solid fa-xmark"></i></button>' : '').'
</form>';

/* sort filter chips (each carries the current search term) */
$chip = function($sortKey, $label, $icon) use ($pagelol, $searchValue, $_GET) {
	$qs = http_build_query(array_filter(["search" => $searchValue, "sort" => $sortKey]));
	$href = $pagelol.(!empty($qs) ? "?".$qs : "");
	$on = ($_GET["sort"] == $sortKey OR ($sortKey == "" && $_GET["sort"] == "")) ? " is-on" : "";
	return '<a class="gd-filter'.$on.'" href="'.htmlspecialchars($href).'" onclick="a(\''.htmlspecialchars($href).'\', true, true);return false;"><i class="fa-solid '.$icon.'"></i>'.$label.'</a>';
};
$filters = $chip("", $dl->getLocalizedString("sortNewest"), "fa-clock")
	.$chip("downloads", $dl->getLocalizedString("sortDownloads"), "fa-download")
	.$chip("likes", $dl->getLocalizedString("sortLikes"), "fa-thumbs-up")
	.$chip("featured", $dl->getLocalizedString("featuredOnly"), "fa-star");

foreach($result as &$action) $levels .= $dl->generateLevelsCard($action, $modcheck);

/* total count for pagination */
$query = $db->prepare("SELECT count(*) FROM levels WHERE $where");
$query->execute($params);
$packcount = $query->fetchColumn();
$pagecount = ceil($packcount / 10);

$pagel = '<div class="gd-pagehead">
	<p class="gd-eyebrow">GDIPS</p>
	<div class="gd-pagehead-row">
		<div>
			<h1 class="gd-display">'.$dl->getLocalizedString("levels").'</h1>
			<p class="gd-pagehead-sub">'.number_format($packcount).' '.$dl->getLocalizedString("levels").'</p>
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
	$pagel .= '<div class="gd-empty"><i class="fa-solid fa-magnifying-glass"></i><p>'.(empty($searchValue) ? $dl->getLocalizedString("emptyPage") : $dl->getLocalizedString("noResults")).'</p>'
		.(!empty($searchValue) ? '<button type="button" class="gd-btn gd-btn--secondary" onclick="a(\''.$pagelol.'\', true, true, \'GET\')"><i class="fa-solid fa-xmark"></i>'.$dl->getLocalizedString("searchCancel").'</button>' : '')
		.'</div>';
} else {
	$pagel .= $levels;
}
$pagel .= '</div>';

$bottomrow = $dl->generateBottomRow($pagecount, $actualpage);
$dl->printPage($pagel.$bottomrow, true, "levels");
?>
