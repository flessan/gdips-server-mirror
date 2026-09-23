<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
session_start();
require "incl/dashboardLib.php";
$dl = new dashboardLib();
require $dbPath."incl/lib/connection.php";
require $dbPath."incl/lib/mainLib.php";
$gs = new mainLib();
require $dbPath."config/dashboard.php";
$install = '';
if(!$installed) header('Location: install.php');
if(isset($_GET["installed"])) $install = '<div class="gd-alert gd-alert--ok" style="margin-bottom:16px"><i class="fa-solid fa-circle-check"></i><p style="margin:0">'.$dl->getLocalizedString("tipsAfterInstalling").'</p></div>';
$logged = isset($_SESSION["accountID"]) AND $_SESSION["accountID"] != 0;
$dl->title($dl->getLocalizedString("homeNavbar"));

/* ------------------------------------------------------------------ *
 *  Greeting                                                          *
 * ------------------------------------------------------------------ */
// GDIPS greeting time
$timezone = new DateTimeZone("Asia/Makassar"); // WITA
$now = new DateTimeImmutable("now", $timezone);

$hour = (int)$now->format("G");
$minute = (int)$now->format("i");
$totalMinutes = ($hour * 60) + $minute;

if ($totalMinutes >= 5 * 60 && $totalMinutes < 11 * 60) {
    $greet = $dl->getLocalizedString("greetMorning");
} elseif ($totalMinutes >= 11 * 60 && $totalMinutes < 15 * 60) {
    $greet = $dl->getLocalizedString("greetAfternoon");
} elseif ($totalMinutes >= 15 * 60 && $totalMinutes < 19 * 60) {
    $greet = $dl->getLocalizedString("greetEvening");
} else {
    $greet = $dl->getLocalizedString("greetNight");
}

$name = $logged
    ? htmlspecialchars(
        $gs->getAccountName($_SESSION["accountID"]),
        ENT_QUOTES,
        "UTF-8"
    )
    : "";

$heroTitle = $logged
    ? $greet . ', <span style="color:var(--merah-strong)">' . $name . '</span>'
    : $dl->getLocalizedString("homeHeroTitleGuest");

$heroSub = $dl->getLocalizedString(
    $logged ? "homeHeroSubAuth" : "homeHeroSubGuest"
);

/* ------------------------------------------------------------------ *
 *  Server numbers (one slim band - not a wall of stats)              *
 * ------------------------------------------------------------------ */
$count = function($sql, $params = []) use ($db) {
	$q = $db->prepare($sql);
	$q->execute($params);
	return (int)$q->fetchColumn();
};
$levelCount = $count("SELECT count(*) FROM levels WHERE unlisted = 0");
$songCount = $count("SELECT count(*) FROM songs WHERE isDisabled = 0");
$playerCount = $count("SELECT count(*) FROM accounts WHERE isActive = 1");
$clanCount = $clansEnabled ? $count("SELECT count(*) FROM clans") : 0;

/* ------------------------------------------------------------------ *
 *  Content queries                                                   *
 * ------------------------------------------------------------------ */
$featuredCards = $recentCards = $playerRows = $songRows = $clanCards = '';

$q = $db->prepare("SELECT * FROM levels WHERE unlisted = 0 AND (starFeatured > 0 OR starEpic > 0) ORDER BY uploadDate DESC LIMIT 4");
$q->execute();
$featured = $q->fetchAll();
foreach($featured as $level) $featuredCards .= $dl->generateMiniLevelCard($level);

$q = $db->prepare("SELECT * FROM levels WHERE unlisted = 0 ORDER BY uploadDate DESC LIMIT 6");
$q->execute();
$recent = $q->fetchAll();
foreach($recent as $level) $recentCards .= $dl->generateMiniLevelCard($level);

$q = $db->prepare("SELECT userName, stars, iconType, color1, color2, color3, accGlow, accIcon, accShip, accBall, accBird, accDart, accRobot, accSpider, accSwing, accJetpack FROM users WHERE stars > 0 AND extID != '' AND extID != '0' ORDER BY stars DESC LIMIT 5");
$q->execute();
$x = 0;
foreach($q->fetchAll() as $user) $playerRows .= $dl->generateMiniPlayerRow(++$x, $user);

$q = $db->prepare("SELECT * FROM songs WHERE isDisabled = 0 AND reuploadID > 0 ORDER BY reuploadTime DESC LIMIT 5");
$q->execute();
foreach($q->fetchAll() as $song) $songRows .= $dl->generateMiniSongRow($song);

if($clansEnabled) {
	$q = $db->prepare("SELECT clans.*, COUNT(users.clan) AS members FROM clans LEFT JOIN users ON clans.id = users.clan GROUP BY clans.id ORDER BY members DESC LIMIT 3");
	$q->execute();
	foreach($q->fetchAll() as $clan) $clanCards .= $dl->generateMiniClanCard($clan, $clan["members"]);
}

/* ------------------------------------------------------------------ *
 *  Shortcuts                                                         *
 * ------------------------------------------------------------------ */
$shortcut = function($href, $icon, $label, $badge = '') {
	return '<a class="gd-shortcut" href="'.$href.'" onclick="a(\''.$href.'\', true, true);return false;"><i class="fa-solid '.$icon.' gd-sc-ico"></i><span>'.$label.'</span>'.$badge.'</a>';
};
$shortcuts = '';
if($logged) {
	$unread = $db->prepare("SELECT count(*) FROM messages WHERE toAccountID = :acc AND isNew = 0");
	$unread->execute([':acc' => $_SESSION["accountID"]]);
	$unread = (int)$unread->fetchColumn();
	$shortcuts .= $shortcut('dashboard/profile/'.$name, 'fa-id-badge', $dl->getLocalizedString("yourProfile"));
	$shortcuts .= $shortcut('dashboard/messenger', 'fa-comments', $dl->getLocalizedString("messenger"), $unread > 0 ? '<span class="new-messages-notify">'.$unread.'</span>' : '');
	if(strpos($songEnabled, '1') !== false) $shortcuts .= $shortcut('dashboard/songs', 'fa-file-audio', $dl->getLocalizedString("songAdd"));
	if($lrEnabled == 1) $shortcuts .= $shortcut('dashboard/levels/levelReupload.php', 'fa-cloud-arrow-down', $dl->getLocalizedString("levelReupload"));
	$userClan = $gs->isPlayerInClan($_SESSION["accountID"]);
	if($userClan) {
		$clanInfo = $gs->getClanInfo($userClan);
		$shortcuts .= $shortcut('dashboard/clan/'.htmlspecialchars($clanInfo["clan"]), 'fa-dungeon', htmlspecialchars($clanInfo["clan"]));
	} elseif($clansEnabled) $shortcuts .= $shortcut('dashboard/clans/create.php', 'fa-dungeon', $dl->getLocalizedString("createClan"));
	$shortcuts .= $shortcut('dashboard/stats/unlisted.php', 'fa-eye-slash', $dl->getLocalizedString("unlistedLevels"));
} else {
	$shortcuts .= $shortcut('dashboard/login/login.php', 'fa-sign-in', $dl->getLocalizedString("login"));
	$shortcuts .= $shortcut('dashboard/login/register.php', 'fa-user-plus', $dl->getLocalizedString("createAcc"));
	$shortcuts .= $shortcut('dashboard/stats/levelsList.php', 'fa-gamepad', $dl->getLocalizedString("levels"));
	$shortcuts .= $shortcut('dashboard/stats/songList.php', 'fa-music', $dl->getLocalizedString("songs"));
	if($clansEnabled) $shortcuts .= $shortcut('dashboard/clans', 'fa-dungeon', $dl->getLocalizedString("clans"));
}

/* ------------------------------------------------------------------ *
 *  Open-source strip                                                 *
 * ------------------------------------------------------------------ */
$repo = $dl->gdProjectRepo();
$ossStrip = '<section class="gd-section"><div class="gd-oss">
	<div>
		<h2><i class="fa-brands fa-github" aria-hidden="true"></i>'.$dl->getLocalizedString("ossStripTitle").'</h2>
		<p>'.$dl->getLocalizedString("ossStripBody").'</p>
		<div class="gd-oss-links">
			<a class="gd-btn gd-btn--primary" href="'.htmlspecialchars($repo).'" target="_blank" rel="noopener"><i class="fa-brands fa-github"></i>'.$dl->getLocalizedString("sourceCode").'</a>
			<a class="gd-btn gd-btn--secondary" href="'.$dl->gdProjectUrl().'">'.$dl->getLocalizedString("aboutProject").'</a>
		</div>
	</div>
	<ul class="gd-oss-points">
		<li><i class="fa-solid fa-circle-check"></i>'.$dl->getLocalizedString("ossPoint1").'</li>
		<li><i class="fa-solid fa-circle-check"></i>'.$dl->getLocalizedString("ossPoint2").'</li>
		<li><i class="fa-solid fa-circle-check"></i>'.$dl->getLocalizedString("ossPoint3").'</li>
		<li><i class="fa-solid fa-circle-check"></i>'.$dl->getLocalizedString("ossPoint4").'</li>
	</ul>
</div></section>';

$install = '';

/* ------------------------------------------------------------------ *
 *  Page assembly                                                     *
 * ------------------------------------------------------------------ */
$content = $install.'
<section class="gd-hero gd-kawung-band">
	<p class="gd-hero-kicker"><span style="display:inline-flex;align-items:center;gap:6px"><i class="fa-solid fa-circle" style="font-size:0.5rem;color:var(--ok)"></i>'.$dl->getLocalizedString("homeServerOnline").'</span></p>
	<h1 class="gd-display">'.$heroTitle.'</h1>
	<p class="gd-hero-sub">'.$heroSub.'</p>
	<div class="gd-hero-actions">
		<button type="button" class="gd-btn gd-btn--primary" onclick="a(\'dashboard/stats/levelsList.php\')"><i class="fa-solid fa-gamepad"></i>'.$dl->getLocalizedString("levels").'</button>
		<button type="button" class="gd-btn gd-btn--secondary" onclick="a(\'project/\')"><i class="fa-solid fa-circle-info"></i>'.$dl->getLocalizedString("aboutProject").'</button>
	</div>
	<p class="gd-hero-note"><i class="fa-solid fa-star" aria-hidden="true"></i>'.$dl->getLocalizedString("footerBuilt").'</p>
</section>

<div class="gd-statband" role="list">
	<div class="gd-stat" role="listitem"><span class="gd-stat-value">'.number_format($levelCount).'</span><span class="gd-stat-label"><i class="fa-solid fa-gamepad"></i> '.$dl->getLocalizedString("statLevels").'</span></div>
	<div class="gd-stat" role="listitem"><span class="gd-stat-value">'.number_format($songCount).'</span><span class="gd-stat-label"><i class="fa-solid fa-music"></i> '.$dl->getLocalizedString("statSongs").'</span></div>
	<div class="gd-stat" role="listitem"><span class="gd-stat-value">'.number_format($playerCount).'</span><span class="gd-stat-label"><i class="fa-solid fa-users"></i> '.$dl->getLocalizedString("statPlayers").'</span></div>
	<div class="gd-stat" role="listitem"><span class="gd-stat-value">'.number_format($clanCount).'</span><span class="gd-stat-label"><i class="fa-solid fa-dungeon"></i> '.$dl->getLocalizedString("statClans").'</span></div>
</div>

<div class="gd-motif" aria-hidden="true"><i class="fa-solid fa-gem"></i></div>

<section class="gd-section">
	<div class="gd-section-head">
		<h2 class="gd-display">'.$dl->getLocalizedString("yourShortcuts").'</h2>
	</div>
	<div class="gd-shortcuts">'.$shortcuts.'</div>
</section>

	<section class="gd-section">
	<div class="gd-section-head">
		<h2 class="gd-display">'.$dl->getLocalizedString("featuredLevels").'</h2>
		<a class="gd-link-more" href="dashboard/stats/levelsList.php" onclick="a(\'dashboard/stats/levelsList.php?sort=featured\', true, true);return false;">'.$dl->getLocalizedString("viewAll").' <i class="fa-solid fa-chevron-right"></i></a>
	</div>
	<div class="gd-levelgrid">'.($featuredCards !== '' ? $featuredCards : '<div class="gd-empty" style="grid-column:1/-1"><i class="fa-regular fa-face-smile-beam"></i><p>'.$dl->getLocalizedString("empty").'</p></div>').'</div>
</section>

<section class="gd-section">
	<div class="gd-section-head">
		<h2 class="gd-display">'.$dl->getLocalizedString("recentLevels").'</h2>
		<a class="gd-link-more" href="dashboard/stats/levelsList.php" onclick="a(\'dashboard/stats/levelsList.php\', true, true);return false;">'.$dl->getLocalizedString("viewAll").' <i class="fa-solid fa-chevron-right"></i></a>
	</div>
	<div class="gd-levelgrid">'.$recentCards.'</div>
</section>

<div class="gd-grid gd-grid--2">
	<section class="gd-section">
		<div class="gd-section-head"><h2 class="gd-display">'.$dl->getLocalizedString("topPlayers").'</h2></div>
		<div class="gd-playerlist">'.($playerRows !== '' ? $playerRows : '<div class="gd-empty"><i class="fa-solid fa-user-slash"></i><p>'.$dl->getLocalizedString("empty").'</p></div>').'</div>
	</section>
	<section class="gd-section">
		<div class="gd-section-head">
			<h2 class="gd-display">'.$dl->getLocalizedString("newSongs").'</h2>
			<a class="gd-link-more" href="dashboard/stats/songList.php" onclick="a(\'dashboard/stats/songList.php\', true, true);return false;">'.$dl->getLocalizedString("viewAll").' <i class="fa-solid fa-chevron-right"></i></a>
		</div>
		<div class="gd-playerlist">'.($songRows !== '' ? $songRows : '<div class="gd-empty"><i class="fa-solid fa-music"></i><p>'.$dl->getLocalizedString("empty").'</p></div>').'</div>
	</section>
</div>

'.($clansEnabled && $clanCards !== '' ? '<section class="gd-section">
	<div class="gd-section-head">
		<h2 class="gd-display">'.$dl->getLocalizedString("activeClans").'</h2>
		<a class="gd-link-more" href="dashboard/clans" onclick="a(\'dashboard/clans\', true, true);return false;">'.$dl->getLocalizedString("viewAll").' <i class="fa-solid fa-chevron-right"></i></a>
	</div>
	<div class="gd-grid gd-grid--3">'.$clanCards.'</div>
</section>' : '').'

'.$ossStrip;

$dl->printPage($content, false, "home");
$dl->printFooter();
?>

