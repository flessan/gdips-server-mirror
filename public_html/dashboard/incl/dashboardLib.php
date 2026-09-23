<?php
$dbPath = '../'; // Path to main directory. It needs to point to main endpoint files. If you didn't change dashboard place, don't change this value. Usually it's '../' (cuz dashboard folder is inside main endpoints folder) (https://imgur.com/a/P8LdhzY).
require __DIR__."/../".$dbPath."config/dashboard.php";
require_once "auth.php";
$au = new au();
$dashCheck = $au->auth($dbPath);
// Dashboard library - presentation layer.
//
// UI architecture (see incl/ui/ for the stylesheets and incl/gdips.js for
// behavior):
//   printHeader()  â†’ <head>: fonts, tokens/base/layout/components/pages CSS
//   printNavbar()  â†’ the app shell inside #navbarepta: sidebar navigation,
//                    topbar, mobile drawer, audio player, GDIPS config script
//   printPage()/printSong() â†’ <span id="htmlpage"><main class="gd-content">
//   printFooter()  â†’ static footer (links are absolute so depth never matters)
//
// The SPA contract lives in gdips.js: navigation swaps #htmlpage and
// #navbarepta, so keep those two ids and #isSubdirectory intact.
class dashboardLib {

	/* ------------------------------------------------------------------ *
	 *  Head                                                              *
	 * ------------------------------------------------------------------ */
	public function printHeader($isSubdirectory = true){
		$this->handleLangStart();
		global $gdps;
		global $dashboardFavicon;
		$lang = strtolower(substr($this->gdLang(), 0, 2));
		$v = function($rel){ return file_exists(__DIR__."/".$rel) ? filemtime(__DIR__."/".$rel) : "1"; };
		echo '<!DOCTYPE html>
				<html lang="'.$lang.'">
					<head>
						<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta/css/bootstrap.min.css" integrity="sha384-/Y6pD6FV/Vv2HJnA6t+vslU6fwYXjCFtcEpHbNJ0lyAFsXTsjBbfaDjzALeQsN6M" crossorigin="anonymous">
						<link rel="icon" type="image/png" sizes="64x64" href="'.$dashboardFavicon.'">
						<meta charset="utf-8">
						<meta name="color-scheme" content="dark">
						<meta name="theme-color" content="#100f13">
						<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">';
		if($isSubdirectory) echo '<base href="../">'; else echo '<base href=".">';
		echo '<link rel="preconnect" href="https://fonts.googleapis.com">
						<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
						<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Chakra+Petch:wght@500;600;700&display=swap">
						<script src="/dashboard/incl/jq.js"></script>
						<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.11.0/umd/popper.min.js" integrity="sha384-b/U6ypiBEHpOf/4+1nzFpr53nxSS+GLCkfwBdFNTxtclqqenISfwAzpKaMNFNmj4" crossorigin="anonymous"></script>
						<script async src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta/js/bootstrap.min.js" integrity="sha384-h0AbiXch4ZDo7tp9hKZ4TsHbi047NrKGLO3SEJAg45jXxnGIfYzk4Si90RDIqNm1" crossorigin="anonymous"></script>
						<script src="/dashboard/incl/jsmediatags.js"></script>
						<script src="/dashboard/incl/imgcolr.js"></script>
						<link href="/dashboard/incl/fontawesome/css/fontawesome.css" rel="stylesheet">
						<link href="/dashboard/incl/fontawesome/css/brands.css" rel="stylesheet">
						<link href="/dashboard/incl/fontawesome/css/solid.css" rel="stylesheet">
						<link href="/dashboard/incl/fontawesome/css/regular.css" rel="stylesheet">
						<link rel="stylesheet" href="/dashboard/incl/ui/tokens.css?'.$v("ui/tokens.css").'">
						<link rel="stylesheet" href="/dashboard/incl/ui/base.css?'.$v("ui/base.css").'">
						<link rel="stylesheet" href="/dashboard/incl/ui/layout.css?'.$v("ui/layout.css").'">
						<link rel="stylesheet" href="/dashboard/incl/ui/components.css?'.$v("ui/components.css").'">
						<link rel="stylesheet" href="/dashboard/incl/ui/pages.css?'.$v("ui/pages.css").'">
						<script src="/dashboard/incl/gdips.js?'.$v("gdips.js").'"></script>
						<title>'.$gdps.'</title>';
		echo '</head>
				<body><div class="gd-holder">';
	}

	/* Resolves the active language code (cookie â†’ browser â†’ EN). */
	public function gdLang() {
		if(!empty($_COOKIE["lang"]) AND ctype_alpha($_COOKIE["lang"])) return strtoupper(substr($_COOKIE["lang"], 0, 2));
		$browser = strtoupper(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2));
		if(ctype_alpha($browser) AND file_exists(__DIR__.'/lang/locale'.$browser.'.php')) return $browser;
		return "EN";
	}

	public function getLocalizedString($stringName, $lang = '') {
		if(empty($lang)) {
			if(!isset($_COOKIE["lang"]) OR !ctype_alpha($_COOKIE["lang"])) {
				if(file_exists(__DIR__.'/lang/locale'.strtoupper(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2))).'.php') $lang = strtoupper(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2));
				else $lang = "EN";
			} else $lang = $_COOKIE["lang"];
		}
		$lang = substr($lang, 0, 2);
		$locale = __DIR__."/lang/locale".$lang.".php";
		if(file_exists($locale)) require $locale;
		else require __DIR__."/lang/localeEN.php";
		if(isset($string[$stringName])) return $string[$stringName];
		else {
			require __DIR__."/lang/localeEN.php";
			if(isset($string[$stringName])) return $string[$stringName];
			else return "lnf:$stringName";
		}
	}

	/* ------------------------------------------------------------------ *
	 *  Legacy box page (errors / simple confirmations)                   *
	 * ------------------------------------------------------------------ */
	public function printBoxBody(){
		echo '<span id="htmlpage" style="display: contents;"><main class="gd-content gd-content--narrow"><div class="container container-box">
					<div class="card">
						<div class="card-block buffer">';
	}
	public function printBox($content, $active = "", $isSubdirectory = true){
		$this->printHeader($isSubdirectory);
		$this->printNavbar($active, $isSubdirectory);
		$this->printBoxBody();
		echo $content;
		$this->printBoxFooter();
		$this->printFooter();
	}
	public function printSong($content, $active = "", $isSubdirectory = true){
		$this->printHeader($isSubdirectory);
		$this->printNavbar($active, $isSubdirectory);
		echo '<span id="htmlpage" style="display: contents;"><main class="gd-content">'.$content.'</main></span>';
	}
	public function printBoxFooter(){
		echo '</div></div></div></main></span>';
	}
	public function printFooter($sub = ''){
		global $vk;
		global $discord;
		global $twitter;
		global $youtube;
		global $twitch;
		/* The footer is static across SPA navigations, so its links must be
		   depth-independent: resolve the dashboard root as an absolute path. */
		$dir = str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"]));
		$root = basename($dir) === "dashboard" ? $dir : dirname($dir);
		$root = rtrim($root, "/");
		if ($root === "") $root = "/dashboard";
		$socials = '';
		if($youtube != '') $socials .= '<a href="'.$youtube.'" target="_blank" rel="noopener" aria-label="YouTube"><img class="socials" style="width: 18px" src="'.$root.'/incl/socials/youtube.png" alt=""></a>';
		if($discord != '') $socials .= '<a href="'.$discord.'" target="_blank" rel="noopener" aria-label="Discord"><img class="socials" style="width: 18px" src="'.$root.'/incl/socials/discord.png" alt=""></a>';
		if($twitter != '') $socials .= '<a href="'.$twitter.'" target="_blank" rel="noopener" aria-label="Twitter/X"><img class="socials" style="width: 18px" src="'.$root.'/incl/socials/twitter.png" alt=""></a>';
		if($vk != '') $socials .= '<a href="'.$vk.'" target="_blank" rel="noopener" aria-label="VK"><img class="socials" style="width: 18px" src="'.$root.'/incl/socials/vk.png" alt=""></a>';
		if($twitch != '') $socials .= '<a href="'.$twitch.'" target="_blank" rel="noopener" aria-label="Twitch"><img class="socials" style="width: 18px" src="'.$root.'/incl/socials/twitch.png" alt=""></a>';
		echo '<footer class="gd-footer"><div class="gd-footer-inner">
			<div class="gd-footer-brand"><b>GDIPS</b><span class="gd-footer-tag">'.$this->getLocalizedString("footerBuilt").'</span></div>
			<div class="gd-footer-links">
				<a href="/dashboard/project/"><i class="fa-solid fa-circle-info"></i>'.$this->getLocalizedString("aboutProject").'</a>
				<a href="'.htmlspecialchars($this->gdProjectRepo()).'" target="_blank" rel="noopener"><i class="fa-brands fa-github"></i>'.$this->getLocalizedString("sourceCode").'</a>
				<a href="'.htmlspecialchars($this->gdProjectRepo()).'/issues" target="_blank" rel="noopener"><i class="fa-solid fa-bug"></i>'.$this->getLocalizedString("contribute").'</a>
				'.$socials.'
			</div>
			<div class="gd-footer-meta">
				<span>© '.date('Y', time()).' GDIPS · '.$this->getLocalizedString("footer").'</span>
				<span style="margin-left:auto;display:inline-flex;align-items:center;gap:6px"><i class="fa-solid fa-language"></i>
					<a class="dontblock" href="'.$root.'/lang/switchLang.php?lang=ID">Bahasa Indonesia</a> ·
					<a class="dontblock" href="'.$root.'/lang/switchLang.php?lang=EN">English</a>
				</span>
			</div>
		</div></footer>';
	}
	public function printLoginBox($content){
		$this->printBox("<h1 id='center' style='text-align:center'>".$this->getLocalizedString("loginBox")."</h1>".$content);
	}
	public function printLoginBoxInvalid(){
		$this->printLoginBox("<p>".$this->getLocalizedString("wrongNickOrPass")."</p>");
	}
	public function printLoginBoxError($content){
		$this->printLoginBox("<p>An error has occured: $content. <a href=''>Click here to try again.</a></p>");
	}

	/* ------------------------------------------------------------------ *
	 *  Project links - configurable, with fallbacks for older configs    *
	 * ------------------------------------------------------------------ */
	public function gdProjectRepo() {
		global $projectRepo;
		return !empty($projectRepo) ? $projectRepo : "https://github.com/flessan/GDIPS";
	}

	/* Depth-independent URL of the project page (footer & home use it). */
	public function gdProjectUrl() {
		$dir = str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"]));
		return rtrim(dirname($dir), "/")."dashboard/project";
	}

	/* ------------------------------------------------------------------ *
	 *  Shell: sidebar + topbar + drawer + audio player                   *
	 * ------------------------------------------------------------------ */
	private function gdNavItem($href, $icon, $label, $isActive, $extra = '') {
		return '<li><a class="gd-nav-link'.($isActive ? ' is-active' : '').'" href="'.$href.'" onclick="a(\''.$href.'\');return false;">'
			.'<i class="fa-solid '.$icon.'" aria-hidden="true"></i><span>'.$label.'</span>'.$extra.'</a></li>';
	}
	private function gdNavSection($title, $items) {
		if(empty($items)) return '';
		return '<div class="gd-nav-section"><span class="gd-nav-title">'.$title.'</span><ul class="gd-nav-list">'.$items.'</ul></div>';
	}

	public function printNavbar($active, $isSubdirectory = true) {
		global $gdps;
		global $lrEnabled;
		global $msgEnabled;
		global $songEnabled;
		global $sfxEnabled;
		global $clansEnabled;
		global $pc;
		global $mac;
		global $android;
		global $ios;
		global $pcLauncher;
		global $macLauncher;
		global $androidLauncher;
		global $iosLauncher;
		global $thirdParty;
		global $dbPath;
		global $dashboardIcon;
		require_once __DIR__."/../".$dbPath."incl/lib/Captcha.php";
		require __DIR__."/../".$dbPath."config/security.php";
		require __DIR__."/../".$dbPath."config/mail.php";
		require_once __DIR__."/../".$dbPath."incl/lib/mainLib.php";
		require __DIR__."/../".$dbPath."incl/lib/connection.php";
		if(!isset($enableCaptcha)) global $enableCaptcha;
		if(!isset($preactivateAccounts)) global $preactivateAccounts;
		$gs = new mainLib();
		$logged = isset($_SESSION["accountID"]) AND $_SESSION["accountID"] != 0;

		/* ---- messenger badge ---- */
$msgBadge = '';
if($msgEnabled == 1 AND $logged) {
	$newMessagesCount = $db->prepare("
		SELECT COUNT(DISTINCT accID)
		FROM messages
		WHERE toAccountID = :acc
		  AND isNew = 0
	");
	$newMessagesCount->execute([':acc' => $_SESSION["accountID"]]);
	$newMessagesCount = (int)$newMessagesCount->fetchColumn();

	if($newMessagesCount > 0) {
		$msgBadge = '<span class="gd-nav-count">'.$newMessagesCount.'</span>';
	}
}

		/* ---- current user ---- */
		$userName = $logged ? $gs->getAccountName($_SESSION["accountID"]) : "";
		$userAvatar = '';
		if($logged) {
			$q = $db->prepare("SELECT iconType, color1, color2, color3, accGlow, accIcon, accShip, accBall, accBird, accDart, accRobot, accSpider, accSwing, accJetpack FROM users WHERE extID = :extID");
			$q->execute([':extID' => $_SESSION["accountID"]]);
			if($u = $q->fetch(PDO::FETCH_ASSOC)) $userAvatar = $this->gdAvatar($u, 32);
		}
		$userClanID = $logged ? $gs->isPlayerInClan($_SESSION["accountID"]) : 0;
		$clanLink = '';
		if($userClanID) {
			$clanInfo = $gs->getClanInfo($userClanID);
			$clanLink = $this->gdNavItem('clan/'.htmlspecialchars($clanInfo["clan"]), 'fa-dungeon', htmlspecialchars($clanInfo["clan"]), $active === "clan", '');
		}

		/* ---- navigation model ---- */
		$main = '';
		$main .= $this->gdNavItem('', 'fa-house', $this->getLocalizedString("homeNavbar"), $active === "home");
		$main .= $this->gdNavItem('stats/levelsList.php', 'fa-gamepad', $this->getLocalizedString("levels"), in_array($active, ["browse", "levels"], true));
		$main .= $this->gdNavItem('stats/songList.php', 'fa-music', $this->getLocalizedString("songs"), $active === "songs");
		$main .= $this->gdNavItem('stats/accountsList.php', 'fa-users', $this->getLocalizedString("playersList"), $active === "players");
		if($clansEnabled) $main .= $this->gdNavItem('clans', 'fa-dungeon', $this->getLocalizedString("clans"), in_array($active, ["clans"], true));
		if($msgEnabled == 1 AND $logged) $main .= $this->gdNavItem('messenger', 'fa-comments', $this->getLocalizedString("messenger"), $active === "msg", $msgBadge);

		$community = '';
		$community .= $this->gdNavItem('stats/top24h.php', 'fa-ranking-star', $this->getLocalizedString("leaderboardsNav"), in_array($active, ["stats"], true));
		$community .= $this->gdNavItem('stats/dailyTable.php', 'fa-calendar-day', $this->getLocalizedString("dailyNav"), false);
		$community .= $this->gdNavItem('stats/modList.php', 'fa-user-shield', $this->getLocalizedString("modActions"), false);
		$community .= $this->gdNavItem('stats/modActionsList.php', 'fa-clipboard-list', $this->getLocalizedString("modActionsList"), false);

		$account = '';
		if($logged) {
			$account .= $this->gdNavItem('profile/'.$userName, 'fa-id-badge', $this->getLocalizedString("profile"), $active === "profile");
			$account .= $clanLink;
			$account .= $this->gdNavItem('account/changePassword.php', 'fa-key', $this->getLocalizedString("changePassword"), $active === "account");
			$account .= $this->gdNavItem('account/changeUsername.php', 'fa-user-pen', $this->getLocalizedString("changeUsername"), false);
			$account .= $this->gdNavItem('stats/unlisted.php', 'fa-eye-slash', $this->getLocalizedString("unlistedLevels"), false);
			$account .= $this->gdNavItem('stats/manageSongs.php', 'fa-compact-disc', $this->getLocalizedString("manageSongs"), false);
			$account .= $this->gdNavItem('stats/manageSFX.php', 'fa-drum', $this->getLocalizedString("manageSFX"), false);
			$account .= $this->gdNavItem('stats/favouriteSongs.php', 'fa-heart', $this->getLocalizedString("favouriteSongs"), false);
			$account .= $this->gdNavItem('stats/listsTableYour.php', 'fa-list', $this->getLocalizedString("listTableYour"), false);
		}

		$upload = '';
		if($logged) {
			if(strpos($songEnabled, '1') !== false) $upload .= $this->gdNavItem('songs', 'fa-file-audio', $this->getLocalizedString("songAdd"), $active === "reupload");
			if(strpos($songEnabled, '2') !== false) $upload .= $this->gdNavItem('reupload/songAdd.php', 'fa-link', $this->getLocalizedString("songLink"), false);
			if(strpos($sfxEnabled, '1') !== false) $upload .= $this->gdNavItem('sfxs', 'fa-drum', $this->getLocalizedString("sfxAdd"), false);
			if($lrEnabled == 1) {
				$upload .= $this->gdNavItem('levels/levelReupload.php', 'fa-cloud-arrow-down', $this->getLocalizedString("levelReupload"), false);
				$upload .= $this->gdNavItem('levels/levelToGD.php', 'fa-cloud-arrow-up', $this->getLocalizedString("levelToGD"), false);
			}
			$upload .= '<li><button type="button" class="gd-nav-link" id="crbtn" onclick="cron()"><i id="iconcron" class="fa-solid fa-bars-progress" aria-hidden="true"></i><span>'.$this->getLocalizedString('tryCron').'</span></button></li>';
		}

		$mod = '';
		if($logged AND $gs->checkPermission($_SESSION["accountID"], "dashboardModTools")) {
			$mod .= $this->gdNavItem('account/banPerson.php', 'fa-gavel', $this->getLocalizedString("leaderboardBan"), $active === "mod");
			$mod .= $this->gdNavItem('stats/banList.php', 'fa-gavel', $this->getLocalizedString("banList"), false);
			$mod .= $this->gdNavItem('stats/unlistedMod.php', 'fa-eye-slash', $this->getLocalizedString("unlistedMod"), false);
			$mod .= $this->gdNavItem('stats/suggestList.php', 'fa-user-check', $this->getLocalizedString("suggestLevels"), false);
			$mod .= $this->gdNavItem('stats/listsTableMod.php', 'fa-list-ul', $this->getLocalizedString("listTableMod"), false);
			$mod .= $this->gdNavItem('stats/reportMod.php', 'fa-triangle-exclamation', $this->getLocalizedString("reportMod"), false);
			if($gs->checkPermission($_SESSION["accountID"], "dashboardLevelPackCreate")) $mod .= $this->gdNavItem('levels/packCreate.php', 'fa-folder-tree', $this->getLocalizedString("packManage"), false);
			if($gs->checkPermission($_SESSION["accountID"], "dashboardGauntletCreate")) $mod .= $this->gdNavItem('levels/gauntletCreate.php', 'fa-globe', $this->getLocalizedString("gauntletManage"), false);
			if($gs->checkPermission($_SESSION["accountID"], "dashboardManageSongs")) {
				$mod .= $this->gdNavItem('stats/disabledSongsList.php', 'fa-music', $this->getLocalizedString("disabledSongs"), false);
				$mod .= $this->gdNavItem('stats/disabledSFXsList.php', 'fa-drum', $this->getLocalizedString("disabledSFXs"), false);
			}
			if($gs->checkPermission($_SESSION["accountID"], "toolQuestsCreate")) $mod .= $this->gdNavItem('stats/addQuests.php', 'fa-list-ol', $this->getLocalizedString("addQuest"), false);
			if($gs->checkPermission($_SESSION["accountID"], "dashboardAddMod")) $mod .= $this->gdNavItem('account/addMod.php', 'fa-id-badge', $this->getLocalizedString("addMod"), false);
			if($gs->checkPermission($_SESSION["accountID"], "commandSharecpAll")) $mod .= $this->gdNavItem('levels/shareCP.php', 'fa-share', $this->getLocalizedString("shareCPTitle"), false);
			if($gs->checkPermission($_SESSION["accountID"], "dashboardForceChangePassNick")) $mod .= $this->gdNavItem('account/forceChange.php', 'fa-pen-to-square', $this->getLocalizedString("changePassOrNick"), false);
			if($gs->checkPermission($_SESSION["accountID"], "dashboardManageAutomod")) $mod .= $this->gdNavItem('automod', 'fa-robot', $this->getLocalizedString("automodTitle"), false);
			if($gs->checkPermission($_SESSION["accountID"], "dashboardVaultCodesManage")) $mod .= $this->gdNavItem('levels/vaultCodes.php', 'fa-award', $this->getLocalizedString("vaultCodesTitle"), false);
		}

		$repo = $this->gdProjectRepo();
		$project = '';
		$project .= $this->gdNavItem('project/', 'fa-circle-info', $this->getLocalizedString("aboutProject"), $active === "project");
		$project .= '<li><a class="gd-nav-link" href="'.htmlspecialchars($repo).'" target="_blank" rel="noopener"><i class="fa-brands fa-github" aria-hidden="true"></i><span>'.$this->getLocalizedString("sourceCode").'</span></a></li>';
		$project .= '<li><a class="gd-nav-link" href="'.htmlspecialchars($repo).'/issues" target="_blank" rel="noopener"><i class="fa-solid fa-bug" aria-hidden="true"></i><span>'.$this->getLocalizedString("contribute").'</span></a></li>';
		$project .= '<li><a class="gd-nav-link" href="'.htmlspecialchars($repo).'/blob/main/README.md" target="_blank" rel="noopener"><i class="fa-solid fa-book" aria-hidden="true"></i><span>'.$this->getLocalizedString("docsNav").'</span></a></li>';

		/* ---- downloads dropdown (topbar) ---- */
		$downloads = '';
		$glob = function_exists('glob') ? (!empty(glob("../download/".$gdps.".*")) OR !empty(glob("download/".$gdps.".*"))) : true;
		$hasDownloads = $glob OR !empty($pc) OR !empty($mac) OR !empty($android) OR !empty($ios);
		if($hasDownloads) {
			if((file_exists("download/".$gdps.".zip") OR file_exists("../download/".$gdps.".zip")) AND empty($pcLauncher)) $downloads .= '<a class="dropdown-item dontblock" href="download/'.$gdps.'.zip"><div class="icon"><i class="fa-brands fa-windows"></i></div>'.$this->getLocalizedString("forwindows").'</a>';
			elseif(!empty($pcLauncher)) $downloads .= '<a class="dropdown-item dontblock" href="download/'.$pcLauncher.'"><div class="icon"><i class="fa-brands fa-windows"></i></div>'.$this->getLocalizedString("forwindows").'</a>';
			elseif(!empty($pc)) $downloads .= '<a class="dropdown-item dontblock" href="'.$pc.'"><div class="icon"><i class="fa-brands fa-windows"></i></div>'.$this->getLocalizedString("forwindows").'</a>';
			if((file_exists("download/".$gdps.".dmg") OR file_exists("../download/".$gdps.".dmg")) AND empty($macLauncher)) $downloads .= '<a class="dropdown-item dontblock" href="download/'.$gdps.'.dmg"><div class="icon"><i class="fa-brands fa-apple"></i></div>'.$this->getLocalizedString("formac").'</a>';
			elseif(!empty($macLauncher)) $downloads .= '<a class="dropdown-item dontblock" href="download/'.$macLauncher.'"><div class="icon"><i class="fa-brands fa-apple"></i></div>'.$this->getLocalizedString("formac").'</a>';
			elseif(!empty($mac)) $downloads .= '<a class="dropdown-item dontblock" href="'.$mac.'"><div class="icon"><i class="fa-brands fa-apple"></i></div>'.$this->getLocalizedString("formac").'</a>';
			if((file_exists("download/".$gdps.".apk") OR file_exists("../download/".$gdps.".apk")) AND empty($androidLauncher)) $downloads .= '<a class="dropdown-item dontblock" href="download/'.$gdps.'.apk"><div class="icon"><i class="fa-brands fa-android"></i></div>'.$this->getLocalizedString("forandroid").'</a>';
			elseif(!empty($androidLauncher)) $downloads .= '<a class="dropdown-item dontblock" href="download/'.$androidLauncher.'"><div class="icon"><i class="fa-brands fa-android"></i></div>'.$this->getLocalizedString("forandroid").'</a>';
			elseif(!empty($android)) $downloads .= '<a class="dropdown-item dontblock" href="'.$android.'"><div class="icon"><i class="fa-brands fa-android"></i></div>'.$this->getLocalizedString("forandroid").'</a>';
			if((file_exists("download/".$gdps.".ipa") OR file_exists("../download/".$gdps.".ipa")) AND empty($iosLauncher)) $downloads .= '<a class="dropdown-item dontblock" href="download/'.$gdps.'.ipa"><div class="icon"><i class="fa-brands fa-apple"></i></div>'.$this->getLocalizedString("forios").'</a>';
			elseif(!empty($iosLauncher)) $downloads .= '<a class="dropdown-item dontblock" href="download/'.$iosLauncher.'"><div class="icon"><i class="fa-brands fa-apple"></i></div>'.$this->getLocalizedString("forios").'</a>';
			elseif(!empty($ios)) $downloads .= '<a class="dropdown-item dontblock" href="'.$ios.'"><div class="icon"><i class="fa-brands fa-apple"></i></div>'.$this->getLocalizedString("forios").'</a>';
			if(!empty($thirdParty)) {
				$downloads .= '<h6 class="dropdown-header">'.$this->getLocalizedString("third-party").'</h6>';
				$tpcheck = [];
				foreach($thirdParty as $thp) {
					if(isset($tpcheck[$thp[1]])) continue;
					$tpcheck[$thp[1]] = "set";
					$downloads .= '<a title="'.$thp[3].'" class="dropdown-item dontblock" target="_blank" rel="noopener" href="'.$thp[2].'"><div class="icon flag"><img style="border-radius:500px" class="imgflag" src="'.$thp[0].'" alt=""></div> '.$thp[1].'</a>';
				}
			}
		}

		/* ---- language dropdown (topbar) ---- */
		$langs = [
			['ID', 'id.png', 'Bahasa Indonesia', ''],
			['EN', 'us.png', 'English', ''],
			['RU', 'ru.png', 'Русский', ''],
			['TR', 'tr.png', 'Türkçe', 'Translated by EMREOYUN'],
			['UA', 'ua.png', 'Українська', 'Translated by Jamichi'],
			['FR', 'fr.png', 'Français', 'Translated by masckmaster2007 and M336'],
			['ES', 'es.png', 'Español', 'Translated by Nejik and Maxi'],
			['PT', 'pt.png', 'Português', 'Translated by OmgRod'],
			['CZ', 'cz.png', 'Čeština', 'Translated by Matto58'],
			['IT', 'it.png', 'Italiano', 'Translated by Fenix668'],
			['PL', 'pl.png', 'Polski', 'Translated by ExtremeSpe98'],
			['VI', 'vi.png', 'Tiếng Việt', 'Translated by TacoEnjoyer'],
		];
		$langMenu = '';
		foreach($langs as $l) $langMenu .= '<a class="dropdown-item dontblock" href="lang/switchLang.php?lang='.$l[0].'"'.($l[3] != '' ? ' title="'.$l[3].'"' : '').'><div class="icon flag"><img class="imgflag" src="/dashboard/incl/flags/'.$l[1].'?2" alt=""></div>'.$l[2].'</a>';

		/* ---- topbar title ---- */
		$titleMap = [
			"home" => "homeNavbar", "levels" => "levels", "browse" => "browse", "songs" => "songs",
			"players" => "playersList", "clans" => "clans", "clan" => "clan", "msg" => "messenger",
			"profile" => "profile", "account" => "accountManagement", "mod" => "modTools",
			"reupload" => "reuploadSection", "stats" => "statsSection", "project" => "aboutProject",
		];
		$pageTitle = isset($titleMap[$active]) ? $this->getLocalizedString($titleMap[$active]) : $gdps;

		/* ---- topbar right side ---- */
		$topbarRight = '';
		if($msgEnabled == 1 AND $logged) $topbarRight .= '<a class="gd-tbtn'.($active === "msg" ? ' is-active' : '').'" href="messenger" onclick="a(\'messenger\');return false;" title="'.$this->getLocalizedString("messenger").'" aria-label="'.$this->getLocalizedString("messenger").'"><i class="fa-solid fa-comments"></i>'.($msgBadge != '' ? '<span class="new-messages-notify">'.strip_tags($msgBadge).'</span>' : '').'</a>';
		if($hasDownloads) $topbarRight .= '<div class="dropdown"><button class="gd-tbtn dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="'.$this->getLocalizedString("download").'"><i class="fa-solid fa-download"></i></button><div class="dropdown-menu dropdown-menu-right">'.$downloads.'</div></div>';
		$topbarRight .= '<button type="button" class="gd-tbtn" onclick="gdToggleTheme()" title="Toggle theme" aria-label="Toggle theme">
	<i class="fa-solid fa-circle-half-stroke"></i>
</button>';
		$topbarRight .= '<div class="dropdown"><button class="gd-tbtn dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="'.$this->getLocalizedString("language").'"><i class="fa-solid fa-language"></i></button><div class="dropdown-menu dropdown-menu-right">'.$langMenu.'</div></div>';
		if($logged) {
			$topbarRight .= '<div class="dropdown"><button class="gd-tbtn dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span class="gd-userchip-name">'.$userName.'</span></button>
				<div class="dropdown-menu dropdown-menu-right">
					<a type="button" class="dropdown-item" href="profile/'.$userName.'" onclick="a(\'profile/'.$userName.'\', true, true);return false;"><div class="icon"><i class="fa-regular fa-id-badge"></i></div>'.$this->getLocalizedString("profile").'</a>'
					.($userClanID ? '<a class="dropdown-item" href="clan/'.htmlspecialchars($gs->getClanInfo($userClanID, "clan")).'" onclick="a(\'clan/'.htmlspecialchars($gs->getClanInfo($userClanID, "clan")).'\', false, true);return false;"><div class="icon"><i class="fa-solid fa-dungeon"></i></div>'.$this->getLocalizedString("yourClan").'</a>' : '').'
					<div class="dropdown-divider"></div>
					<a class="dropdown-item dontblock" href="login/logout.php"><div class="icon"><i class="fa-solid fa-sign-out"></i></div>'.$this->getLocalizedString("logout").'</a>
				</div></div>';
		} else {
			$topbarRight .= '<button type="button" class="gd-btn gd-btn--primary" onclick="a(\'login/login.php\')"><i class="fa-solid fa-sign-in"></i>'.$this->getLocalizedString("login").'</button>';
		}

		echo '<div id="navbarepta">
			<input type="hidden" id="isSubdirectory" value="'.($isSubdirectory ? 'true' : 'false').'">
			<a class="gd-skip" href="#htmlpage">'.$this->getLocalizedString("skipToContent").'</a>
			<aside class="gd-sidebar" id="gd-sidebar">
				<a class="gd-brand" href="." onclick="a(\'\');return false;">
					<img src="'.$dashboardIcon.'" alt="">
					<span><span class="gd-brand-name">GD<b>IPS</b></span><span class="gd-brand-desc">Geometry Dash Indonesia</span></span>
				</a>
				<nav class="gd-nav" aria-label="Main">
					'.$this->gdNavSection($this->getLocalizedString("navMain"), $main).'
					'.$this->gdNavSection($this->getLocalizedString("navCommunity"), $community).'
					'.$this->gdNavSection($this->getLocalizedString("navAccount"), $account).'
					'.$this->gdNavSection($this->getLocalizedString("navUpload"), $upload).'
					'.$this->gdNavSection($this->getLocalizedString("navModeration"), $mod).'
					'.$this->gdNavSection($this->getLocalizedString("navProject"), $project).'
				</nav>
				<div class="gd-sidebar-foot">';
		if($logged) {
			echo '<a class="gd-userchip" href="profile/'.$userName.'" onclick="a(\'profile/'.$userName.'\', true, true);return false;">
						'.$userAvatar.'
						<span><span class="gd-userchip-name">'.htmlspecialchars($userName).'</span><span class="gd-userchip-sub">'.($_SESSION["accountID"] == 0 ? '' : $this->getLocalizedString("accountID").' '.$_SESSION["accountID"]).'</span></span>
					</a>
					<a class="gd-nav-link" href="login/logout.php" style="justify-content:center;color:var(--tx-3)"><i class="fa-solid fa-sign-out" aria-hidden="true"></i><span>'.$this->getLocalizedString("logout").'</span></a>';
		} else {
			echo '<div class="gd-authbtns">
						<button type="button" class="gd-btn gd-btn--primary" onclick="a(\'login/login.php\')">'.$this->getLocalizedString("login").'</button>
						<button type="button" class="gd-btn gd-btn--secondary" onclick="a(\'login/register.php\')">'.$this->getLocalizedString("register").'</button>
					</div>';
		}
		echo '<span class="gd-side-note"><i class="fa-solid fa-code-branch" aria-hidden="true"></i>'.$this->getLocalizedString("ossNote").'</span>
				</div>
			</aside>
			<div class="gd-scrim" onclick="gdCloseDrawer()" aria-hidden="true"></div>
			<header class="gd-topbar">
				<button type="button" class="gd-burger" onclick="gdToggleDrawer()" aria-label="Menu" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
				<span class="gd-pagetitle">'.htmlspecialchars($pageTitle).'</span>
				<div class="gd-topbar-actions">'.$topbarRight.'</div>
			</header>';
	}

	/* ------------------------------------------------------------------ *
 *  Page wrapper                                                      *
 * ------------------------------------------------------------------ */
public function printPage($content, $isSubdirectory = true, $navbar = "home"){
    $this->printHeader($isSubdirectory);
    $this->printNavbar($navbar, $isSubdirectory);

    echo '<span id="htmlpage" style="display: contents;">
        <main class="gd-content" id="gd-main">

            <div class="gd-audiobar">
                <div id="audioPlayer" class="audioDiv">

                    <div class="cover" onclick="player.play()">
                        <i id="audioButton" class="fa-solid fa-circle-play image"></i>
                        <img
                            id="audioImage"
                            class="image"
                            src="/dashboard/incl/miyuki-san-andreas.jpg"
                            alt=""
                        >
                    </div>

                    <div class="track">
                        <p id="audioName" class="name">'
                            . $this->getLocalizedString("songAddNameFieldPlaceholder") .
                        '</p>

                        <p id="audioAuthor" class="author">'
                            . $this->getLocalizedString("songAddAuthorFieldPlaceholder") .
                        '</p>

                        <div class="duration">
                            <button
                                type="button"
                                onclick="player.previous()"
                                id="audioBackward"
                                disabled
                                aria-label="Previous"
                            >
                                <i class="fa-solid fa-backward"></i>
                            </button>

                            <input
                                type="range"
                                value="0"
                                max="322"
                                id="audioProgress"
                                class="length"
                                aria-label="Seek"
                            >

                            <button
                                type="button"
                                onclick="player.skip()"
                                id="audioForward"
                                disabled
                                aria-label="Next"
                            >
                                <i class="fa-solid fa-forward"></i>
                            </button>
                        </div>
                    </div>

                    <div class="buttons">
                        <button
                            type="button"
                            onclick="player.song.download()"
                            aria-label="Download song"
                        >
                            <i class="fa-solid fa-download"></i>
                        </button>

                        <button
                            type="button"
                            id="audioButtonStop"
                            onclick="player.stop()"
                            aria-label="Stop"
                        >
                            <i class="fa-solid fa-square"></i>
                        </button>
                    </div>

                    <audio id="audioSong" src="" style="display: none"></audio>

                    <div class="volumeDiv">
                        <i
                            id="audioVolumeIcon"
                            class="fa-solid fa-volume-high volume show"
                        ></i>

                        <div id="audioAnotherVolume" class="anotherVolume">
                            <input
                                class="length volume"
                                id="audioVolume"
                                type="range"
                                value="0"
                                max="1000"
                                aria-label="Volume"
                            >
                        </div>
                    </div>

                    <div id="audioQueue" class="audioDiv queueDiv"></div>

                </div>
            </div>

            <div class="gd-content-body">
                '.$content.'
            </div>

            <div class="error-divs" id="error-divs"></div>
            <div id="loadingloool" aria-hidden="true"></div>

            <script>
                window.GDIPS = {
                    i18n: '.json_encode([
                        "cronSuccess" => $this->getLocalizedString("cronSuccess"),
                        "cronError" => $this->getLocalizedString("cronError"),
                        "likeSong" => $this->getLocalizedString("likeSong"),
                        "dislikeSong" => $this->getLocalizedString("dislikeSong"),
                        "songIsAvailable" => $this->getLocalizedString("songIsAvailable"),
                        "songIsDisabled" => $this->getLocalizedString("songIsDisabled"),
                        "songPlaceholder" => $this->getLocalizedString("songAddNameFieldPlaceholder"),
                        "authorPlaceholder" => $this->getLocalizedString("songAddAuthorFieldPlaceholder"),
                        "downloadFailed" => $this->getLocalizedString("downloadFailed"),
                    ], JSON_UNESCAPED_UNICODE).'
                };

                if (window.gdBoot) {
                    gdBoot();
                } else {
                    document.addEventListener("DOMContentLoaded", gdBoot);
                }
            </script>

        </main>
    </span>';
}

	public function handleLangStart() {
		if(!isset($_COOKIE["lang"]) OR !ctype_alpha($_COOKIE["lang"])){
			$browser = strtoupper(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2));
			if(ctype_alpha($browser) AND file_exists(__DIR__.'/lang/locale'.$browser.'.php')) setcookie("lang", $browser, 2147483647, "/");
			else setcookie("lang", "EN", 2147483647, "/");
		}
		if(!isset($_SESSION["accountID"])) $_SESSION["accountID"] = 0;
	}
	public function hex2RGB($hexStr, $returnAsString = false, $seperator = ',') {
		$hexStr = preg_replace("/[^0-9A-Fa-f]/", '', $hexStr);
		$rgbArray = [];
		if(strlen($hexStr) == 6) {
			$colorVal = hexdec($hexStr);
			$rgbArray['red'] = 0xFF & ($colorVal >> 0x10);
			$rgbArray['green'] = 0xFF & ($colorVal >> 0x8);
			$rgbArray['blue'] = 0xFF & $colorVal;
		} elseif (strlen($hexStr) == 3) {
			$rgbArray['red'] = hexdec(str_repeat(substr($hexStr, 0, 1), 2));
			$rgbArray['green'] = hexdec(str_repeat(substr($hexStr, 1, 1), 2));
			$rgbArray['blue'] = hexdec(str_repeat(substr($hexStr, 2, 1), 2));
		} else return false;
		return $returnAsString ? implode($seperator, $rgbArray) : $rgbArray;
	}
	public function convertToDate($timestamp, $acc = false){
		if($acc) {
			if($timestamp == 0) return $this->getLocalizedString("never");
			if(date("d.m.Y", $timestamp) == date("d.m.Y", time())) return date("G:i", $timestamp);
			elseif(date("Y", $timestamp) == date("Y", time())) return date("d.m", $timestamp);
			else return date("d.m.Y", $timestamp);
		} else return date("d.m.Y G:i:s", $timestamp);
	}

	/* ------------------------------------------------------------------ *
	 *  Shared render helpers                                             *
	 * ------------------------------------------------------------------ */

	/* GD player icon from a `users` table row (or '' if unknown). */
	public function gdAvatar($userData, $size = 30) {
		global $iconsRendererServer;
		if(empty($userData) OR !isset($userData['iconType'])) return '';
		$iconType = ($userData['iconType'] > 8) ? 0 : $userData['iconType'];
		$iconTypeMap = [0 => ['type' => 'cube', 'value' => $userData['accIcon']], 1 => ['type' => 'ship', 'value' => $userData['accShip']], 2 => ['type' => 'ball', 'value' => $userData['accBall']], 3 => ['type' => 'ufo', 'value' => $userData['accBird']], 4 => ['type' => 'wave', 'value' => $userData['accDart']], 5 => ['type' => 'robot', 'value' => $userData['accRobot']], 6 => ['type' => 'spider', 'value' => $userData['accSpider']], 7 => ['type' => 'swing', 'value' => $userData['accSwing']], 8 => ['type' => 'jetpack', 'value' => $userData['accJetpack']]];
		if(!isset($iconTypeMap[$iconType])) return '';
		$iconValue = ($iconTypeMap[$iconType]['value'] > 0) ? $iconTypeMap[$iconType]['value'] : 1;
		return '<img src="'.$iconsRendererServer.'/icon.png?type='.$iconTypeMap[$iconType]['type'].'&value='.$iconValue.'&color1='.$userData['color1'].'&color2='.$userData['color2'].(!empty($userData['accGlow']) ? '&glow='.$userData['accGlow'].'&color3='.$userData['color3'] : '').'" alt="" style="width:'.$size.'px;height:'.$size.'px;object-fit:contain" loading="lazy">';
	}

	/* Difficulty chip - keys off the fixed English strings mainLib returns. */
	public function gdDiffChip($diff, $auto, $demonDiff, $levelLength = 0, $starStars = 0) {
		global $dbPath;
		require_once __DIR__."/../".$dbPath."incl/lib/mainLib.php";
		$gs = new mainLib();
		$d = strtolower($gs->getDifficulty($diff, $auto, $demonDiff));
		if(strpos($d, 'auto') !== false) $cls = 'auto';
		elseif(strpos($d, 'demon') !== false) $cls = 'demon';
		elseif(strpos($d, 'insane') !== false) $cls = 'insane';
		elseif(strpos($d, 'harder') !== false) $cls = 'harder';
		elseif(strpos($d, 'hard') !== false) $cls = 'hard';
		elseif(strpos($d, 'normal') !== false) $cls = 'normal';
		else $cls = 'easy';
		$icon = $levelLength == 5 ? 'fa-moon' : ($cls === 'demon' ? 'fa-skull' : 'fa-face-smiling');
		return '<span class="gd-chip gd-diff gd-diff--'.$cls.'" title="'.htmlspecialchars(ucfirst($d)).'"><i class="fa-solid '.$icon.'" aria-hidden="true"></i>'.htmlspecialchars(ucfirst($d)).($starStars > 0 ? ' Â· '.$starStars : '').'</span>';
	}

	/* Fire rating chip: featured / epic / legendary / mythic. */
	public function gdRateChip($starFeatured, $starEpic) {
		if($starEpic == 3) return '<span class="gd-chip gd-chip--red"><i class="fa-solid fa-fire" aria-hidden="true"></i>Mythic</span>';
		if($starEpic == 2) return '<span class="gd-chip gd-chip--red gd-chip--ghost"><i class="fa-solid fa-fire" aria-hidden="true"></i>Legendary</span>';
		if($starEpic == 1) return '<span class="gd-chip gd-chip--gold"><i class="fa-solid fa-fire" aria-hidden="true"></i>Epic</span>';
		if($starFeatured > 0) return '<span class="gd-chip gd-chip--gold gd-chip--ghost"><i class="fa-solid fa-star" aria-hidden="true"></i>Featured</span>';
		return '';
	}

	public function createProfileStats($stars = 0, $moons = 0, $diamonds = 0, $goldCoins = 0, $userCoins = 0, $demons = 0, $creatorPoints = 0, $isCreatorBanned = 0, $returnText = true) {
		if($stars == 0) $st = ''; else $st = '<p class="profilepic">'.$stars.' <i class="fa-solid fa-star" style="color:var(--kuning)"></i></p>';
		if($moons == 0) $ms = ''; else $ms = '<p class="profilepic">'.$moons.' <i class="fa-solid fa-moon" style="color:#80abff"></i></p>';
		if($diamonds == 0) $dm = ''; else $dm = ' <p class="profilepic">'.$diamonds.' <i class="fa-solid fa-gem" style="color:#a6fffb"></i></p>';
		if($goldCoins == 0) $gc = ''; else $gc = '<p class="profilepic">'.$goldCoins.' <i class="fa-solid fa-coins" style="color:#fffd6b"></i></p>';
		if($userCoins == 0) $uc = ''; else $uc = '<p class="profilepic">'.$userCoins.' <i class="fa-solid fa-coins"></i></p>';
		if($demons == 0) $dn = ''; else $dn = '<p class="profilepic">'.$demons.' <i class="fa-solid fa-dragon" style="color:#ffbbbb"></i></p>';
		if($creatorPoints == 0) $cp = ''; else $cp = '<p class="profilepic">'.$creatorPoints.' <i class="fa-solid fa-screwdriver-wrench"></i></p>';
		$all = $st.$ms.$dm.$gc.$uc.$dn.$cp;
		if(empty($all) && $returnText) $all = '<p class="profilepic" style="color:var(--tx-3)">'.$this->getLocalizedString("empty").'</p>';
		return $all;
	}

	/* ------------------------------------------------------------------ *
	 *  Level card                                                        *
	 * ------------------------------------------------------------------ */
	public function generateLevelsCard($action, $modcheck = false, $extraDetails = '') {
		global $dbPath;
		require __DIR__."/../".$dbPath."incl/lib/connection.php";
		require_once __DIR__."/../".$dbPath."incl/lib/mainLib.php";
		require_once __DIR__."/../".$dbPath."incl/lib/exploitPatch.php";
		$gs = new mainLib();
		$levelid = $action["levelID"];
		$levelname = $action["levelName"];
		$levelDesc = $this->parseMessage(htmlspecialchars(ExploitPatch::url_base64_decode($action["levelDesc"])));
		if(empty($levelDesc)) $levelDesc = '<span style="color:var(--tx-3)">'.$this->getLocalizedString("noDesc").'</span>';
		$levelpass = $action["password"];

		/* moderation-only extras: level password + requested stars */
		$lp = $rs = '';
		if($modcheck) {
			$levelpass = substr($levelpass, 1);
			$levelpass = preg_replace('/(0)\1+/', '', $levelpass);
			if($levelpass == 0 OR empty($levelpass)) $lp = '<span class="profilepic"><i class="fa-solid fa-unlock"></i> '.$this->getLocalizedString("nopass").'</span>';
			else {
				if(strlen($levelpass) < 4) while(strlen($levelpass) < 4) $levelpass = '0'.$levelpass;
				$lp = '<span class="profilepic"><i class="fa-solid fa-lock"></i> '.$levelpass.'</span>';
			}
			$reqStars = ($action["requestedStars"] <= 0 || $action["requestedStars"] > 10) ? 0 : $action["requestedStars"];
			$rs = '<span class="profilepic"><i class="fa-solid fa-star-half-stroke"></i> '.$reqStars.'</span>';
		}

		/* song line */
		if($action["songID"] > 0) {
			$songlol = $gs->getSongInfo($action["songID"]);
			$librarySong = $gs->getLibrarySongInfo($action["songID"]);
			$songArtists = is_array($librarySong) ? ($librarySong["artists"] ?? '') : '';
			$artistIDs = array_filter(preg_split('/\./', (string)$songArtists));
			$authorNames = [$songlol["authorName"]];
			foreach($artistIDs as $id) {
				$authorInfo = $gs->getLibrarySongAuthorInfo($id);
				if(!empty($authorInfo["name"])) $authorNames[] = $authorInfo["name"];
			}
			$artistNames = implode(', ', $authorNames);
			$songAuthor = $artistNames ? $artistNames : $songlol["authorName"];
			$btn = '<button type="button" name="btnsng" id="btn'.$action["songID"].'" title="'.htmlspecialchars($songAuthor).' - '.htmlspecialchars($songlol["name"]).'" download="'.str_replace('http://', 'https://', $songlol["download"]).'" onclick="btnsong(\''.$action["songID"].'\');" aria-label="Play song"><span class="icon songbtnpic"><i id="icon'.$action["songID"].'" class="fa-solid fa-play"></i></span></button>';
			$songid = '<div class="gd-level-song">'.$btn.'<div class="songfullname"><span class="songauthor">'.htmlspecialchars($songAuthor).'</span><span class="songname">'.htmlspecialchars($songlol["name"]).'</span></div></div>';
		} else {
			$songid = '<div class="gd-level-song"><span class="icon"><i class="fa-solid fa-music"></i></span><div class="songfullname"><span class="songauthor"></span><span class="songname">'.strstr($gs->getAudioTrack($action["audioTrack"]), ' by ', true).'</span></div></div>';
		}

		/* creator */
		$username = '<button type="button" class="accbtn" onclick="a(\'profile/'.$action["userName"].'\', true, true)">'.htmlspecialchars($action["userName"]).'</button>';
		$time = $this->convertToDate($action["uploadDate"], true);

		/* avatar */
		$query = $db->prepare('SELECT iconType, color1, color2, color3, accGlow, accIcon, accShip, accBall, accBird, accDart, accRobot, accSpider, accSwing, accJetpack FROM users WHERE extID = :extID');
		$query->execute(['extID' => $action['extID']]);
		$userData = $query->fetch(PDO::FETCH_ASSOC);
		$avatarImg = $this->gdAvatar($userData, 20);

		/* mod rating dropdown (attached to the difficulty chip) */
		$stars = '';
		if($modcheck) {
			$stars = '<div class="dropdown-menu" style="padding:17px 17px 0px 17px;">
				<form class="form__inner" method="post" action="levels/rateLevel.php" style="grid-gap: 10px;">
					<p>'.$this->getLocalizedString('featureLevel').'</p>
					<div class="field"><input type="number" id="p1" name="rateStars" placeholder="'.($action['levelLength'] == 5 ? $this->getLocalizedString("moons") : $this->getLocalizedString("stars")).'" value="'.($action["starStars"] > 0 ? $action["starStars"] : "").'"></div>
					<select style="margin: 0px;" name="featured" onclick="event.stopPropagation();">
						<option value="0">'.$this->getLocalizedString('isAdminNo').'</option>
						<option value="1" '.(($action["starFeatured"] > 0 && $action["starEpic"] == 0) ? 'selected' : '').'>Featured</option>
						<option value="2" '.($action["starEpic"] == 1 ? 'selected' : '').'>Epic</option>
						<option value="3" '.($action["starEpic"] == 2 ? 'selected' : '').'>Legendary</option>
						<option value="4" '.($action["starEpic"] == 3 ? 'selected' : '').'>Mythic</option>
					</select>
					<button type="submit" class="btn-song" name="level" value="'.$levelid.'">'.$this->getLocalizedString("rate").'</button>
				</form>
			</div>';
		}
		if(!empty($stars)) {
			$st = '<span style="position:relative"><a class="dropdown" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="text-decoration:none">'.$this->gdDiffChip($action["starDifficulty"], $action["auto"], $action["starDemonDiff"], $action['levelLength'], $action["starStars"]).'</a>'.$stars.'</span>';
		} else {
			$st = $this->gdDiffChip($action["starDifficulty"], $action["auto"], $action["starDemonDiff"], $action['levelLength'], $action["starStars"]);
		}

		/* manage menu (mods) */
		$manage = '';
		if($modcheck) {
			$manage = '<a class="btn-rendel btn-manage" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Level actions">
					<i class="fa-solid fa-ellipsis-vertical"></i>
				</a>
				<div onclick="event.stopPropagation()" class="dropdown-menu dropdown-menu-right">
					<button type="button" class="dropdown-item" onclick="downloadLevel('.$action['levelID'].')">
						<div class="icon"><i id="levelDownloadIcon'.$action['levelID'].'" class="fa-solid fa-download"></i></div>
						'.$this->getLocalizedString("downloadLevelAsGMD").'
					</button>
					<button type="button" class="dropdown-item" onclick="a(\'stats/levelComments.php?levelID='.$action['levelID'].'\', true, true, \'GET\')">
						<div class="icon"><i class="fa-solid fa-comments"></i></div>
						'.$this->getLocalizedString("levelComments").'
					</button>
					<button type="button" class="dropdown-item" onclick="a(\'stats/'.($action['levelLength'] != 5 ? 'level' : 'platformer').'Leaderboards.php?levelID='.$action['levelID'].'\', true, true, \'GET\')">
						<div class="icon"><i class="fa-solid fa-chart-simple"></i></div>
						'.$this->getLocalizedString("levelLeaderboards").'
					</button>'.($gs->checkPermission($_SESSION['accountID'], 'dashboardManageLevels') ? '
					<button type="button" class="dropdown-item" onclick="a(\'levels/manageLevel.php?levelID='.$action['levelID'].'\', true, true, \'GET\')">
						<div class="icon"><i class="fa-solid fa-gamepad"></i></div>
						'.$this->getLocalizedString("manageLevel").'
					</button>' : '').'
				</div>';
		}

		return '<div style="width:100%;display:flex;flex-wrap:wrap;justify-content:center;">
			<article class="gd-level">
				<div class="gd-level-head">
					<div class="gd-level-head-info">
						<h2 class="gd-level-title">'.$this->gdRateChip($action["starFeatured"], $action["starEpic"]).htmlspecialchars($levelname).'</h2>
						<p class="gd-level-byline">'.$avatarImg.' '.$username.' <span>Â·</span> <span><i class="fa-solid fa-clock"></i> '.$gs->getLength($action['levelLength']).'</span></p>
					</div>
					<div class="gd-level-actions">'.$manage.'</div>
				</div>
				<p class="gd-level-desc">'.$levelDesc.'</p>
				<div class="gd-level-meta">
					<span class="profilepic"><i class="fa-solid fa-reply fa-rotate-270"></i> '.$action['downloads'].'</span>
					<span class="profilepic"><i class="fa-regular fa-thumbs-up"></i> '.($action["likes"] - ($action["dislikes"] ?? 0)).'</span>
					'.$st.$lp.$rs.$extraDetails.'
					'.$songid.'
				</div>
				<div class="gd-level-foot">
					<span>'.$this->getLocalizedString("levelid").': <button id="copy'.$action["levelID"].'" class="accbtn songidyeah" onclick="copysong('.$action["levelID"].')">'.$action["levelID"].'</button></span>
					<span>'.$this->getLocalizedString("date").': <b>'.$time.'</b></span>
				</div>
			</article>
		</div>';
	}

	/* ------------------------------------------------------------------ *
	 *  Comment card                                                      *
	 * ------------------------------------------------------------------ */
	public function generateCommentsCard($comment, $commentDeleteCheck = false) {
		global $dbPath;
		require __DIR__."/../".$dbPath."incl/lib/connection.php";
		require_once __DIR__."/../".$dbPath."incl/lib/mainLib.php";
		$gs = new mainLib();
		$commentAccountName = $gs->getUserName($comment['userID']);
		$commentMessage = $this->parseMessage(htmlspecialchars(ExploitPatch::url_base64_decode($comment["comment"])));
		$extIDvalue = $gs->getExtID($comment['userID']);
		$commentIDDiv = '<button id="copy'.$comment["commentID"].'" class="accbtn" onclick="copysong('.$comment["commentID"].')">'.$comment["commentID"].'</button>';
		$deleteComment = '';
		if($commentDeleteCheck || $extIDvalue == $_SESSION['accountID']) $deleteComment = '<button type="button" onclick="a(\'stats/levelComments.php?levelID='.$comment['levelID'].'\', true, true, \'POST\', false, \'deleteComment\')" class="btn-circle" title="Delete" aria-label="Delete comment" style="color:#ff8d90">
				<i class="fa-solid fa-xmark"></i>
				<form name="deleteComment" style="display: none;">
					<input type="hidden" name="deleteCommentID" value="'.$comment["commentID"].'">
				</form>
			</button>';
		$percentText = $comment['percent'] > 0 ? '<span class="profilepercent">'.$comment['percent'].'%</span>' : '';

		$query = $db->prepare('SELECT iconType, color1, color2, color3, accGlow, accIcon, accShip, accBall, accBird, accDart, accRobot, accSpider, accSwing, accJetpack FROM users WHERE extID = :extID');
		$query->execute([':extID' => $extIDvalue]);
		$userData = $query->fetch(PDO::FETCH_ASSOC);
		$avatarImg = $this->gdAvatar($userData, 24);

		$badgeImg = '';
		$queryRoleID = $db->prepare("SELECT roleID FROM roleassign WHERE accountID = :accountID");
		$queryRoleID->execute([':accountID' => $extIDvalue]);
		if($roleAssignData = $queryRoleID->fetch(PDO::FETCH_ASSOC)) {
			$queryBadgeLevel = $db->prepare("SELECT modBadgeLevel FROM roles WHERE roleID = :roleID");
			$queryBadgeLevel->execute([':roleID' => $roleAssignData['roleID']]);
			if(($modBadgeLevel = $queryBadgeLevel->fetchColumn() ?? 0) >= 1 && $modBadgeLevel <= 3) {
				$badgeImg = '<img src="https://raw.githubusercontent.com/Fenix668/GMDprivateServer/master/dashboard/modBadge_0'.$modBadgeLevel.'_001.png" alt="moderator" style="width: 22px; height: 22px; object-fit: contain;">';
			}
		}

		$commentColor = $gs->getAccountCommentColor($extIDvalue);
		return '<div style="width:100%;display:flex;flex-wrap:wrap;justify-content:center;">
			<div class="gd-comment" style="width:100%">
				<div class="gd-comment-head">
					<button type="button" class="gd-comment-author" onclick="a(\'profile/'.htmlspecialchars($commentAccountName).'\', true, true)">'.$avatarImg.htmlspecialchars($commentAccountName).$badgeImg.'</button>
					'.$percentText.'
					<span class="profilepic" style="margin-left:auto;font-size:var(--fs-xs);padding:2px 8px"><i class="fa-regular fa-thumbs-up"></i> '.$comment["likes"].'</span>
					'.$deleteComment.'
				</div>
				<p class="gd-comment-body"'.($commentColor != '255,255,255' ? ' style="color:rgb('.$commentColor.');"' : '').'>'.$commentMessage.'</p>
				<div class="gd-comment-foot">
					<span>ID: '.$commentIDDiv.'</span>
					<span class="spacer"></span>
					<span>'.$this->convertToDate($comment['timestamp'], true).'</span>
				</div>
			</div>
		</div>';
	}

	/* ------------------------------------------------------------------ *
	 *  Leaderboard row card                                              *
	 * ------------------------------------------------------------------ */
	public function generateLeaderboardsCard($x, $leaderboard, $action, $leaderboardDeleteCheck = false, $stats = '') {
		global $dbPath;
		require __DIR__."/../".$dbPath."incl/lib/connection.php";
		require_once __DIR__."/../".$dbPath."incl/lib/mainLib.php";
		$gs = new mainLib();
		switch($x) {
			case 1: $place = '<i class="fa-solid fa-trophy" style="color:#ffd700"></i>'; break;
			case 2: $place = '<i class="fa-solid fa-trophy" style="color:#c0c0c0"></i>'; break;
			case 3: $place = '<i class="fa-solid fa-trophy" style="color:#cd7f32"></i>'; break;
			default: $place = '<span># '.$x.'</span>'; break;
		}
		$deleteLeaderboard = '';
		if($leaderboardDeleteCheck) $deleteLeaderboard = '<button type="button" onclick="a(\'stats/'.($action['levelLength'] != 5 ? 'level' : 'platformer').'Leaderboards.php?levelID='.$leaderboard['levelID'].'\', true, true, \'POST\', false, \'deleteLeaderboard\')" class="btn-circle" style="color:#ff8d90" aria-label="Remove">
				<i class="fa-solid fa-xmark"></i>
				<form name="deleteLeaderboard" style="display: none;">
					<input type="hidden" name="deleteLeaderboardID" value="'.($action['levelLength'] != 5 ? $leaderboard['scoreID'] : $leaderboard['ID']).'">
				</form>
			</button>';

		$query = $db->prepare('SELECT iconType, color1, color2, color3, accGlow, accIcon, accShip, accBall, accBird, accDart, accRobot, accSpider, accSwing, accJetpack FROM users WHERE extID = :extID');
		$query->execute(['extID' => $action['extID']]);
		$userData = $query->fetch(PDO::FETCH_ASSOC);
		$avatarImg = $this->gdAvatar($userData, 30);

		$userid = $action['extID'];
		return '<div style="width:100%;display:flex;flex-wrap:wrap;justify-content:center;">
			<div class="gd-account" style="width:100%">
				<span class="gd-playerrow-rank" style="width:auto">'.$place.'</span>
				'.$avatarImg.'
				<button type="button" style="min-width:0" onclick="a(\'profile/'.$action["userName"].'\', true, true, \'GET\')" class="gd-account-name" title="'.$action["userName"].'">'.htmlspecialchars($action["userName"]).'</button>
				<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-left:auto">'.$stats.$deleteLeaderboard.'</div>
				<div class="gd-account-foot" style="width:100%;border-top:1px solid var(--ink-line);padding-top:8px">
					<span>'.$this->getLocalizedString("accountID").': <b>'.$extIDvalue.'</b></span>
					<span>'.$this->getLocalizedString("date").': <b>'.$this->convertToDate($leaderboard['uploadDate'], true).'</b></span>
				</div>
			</div>
		</div>';
	}

	/* ------------------------------------------------------------------ *
	 *  Song card                                                         *
	 * ------------------------------------------------------------------ */
	public function generateSongCard($song, $extraLabels = '', $includeAuthor = true) {
		global $dbPath;
		require __DIR__."/../".$dbPath."incl/lib/connection.php";
		require_once __DIR__."/../".$dbPath."incl/lib/mainLib.php";
		require_once __DIR__."/../".$dbPath."incl/lib/exploitPatch.php";
		$gs = new mainLib();
		$modCheck = $gs->checkPermission($_SESSION["accountID"], "dashboardManageSongs");
		$check = $modCheck ?: ($_SESSION['accountID'] != 0 && $_SESSION['accountID'] == $song['reuploadID']);
		$songsid = $song["ID"];
		$time = $this->convertToDate($song["reuploadTime"], true);
		$author = htmlspecialchars(ExploitPatch::rucharclean($song["authorName"]));
		$name = htmlspecialchars(ExploitPatch::rucharclean($song["name"]));
		$download = str_replace('http://', 'https://', $song["download"]);
		$size = $song["size"];

		/* favourite */
		$favs = '';
		if($_SESSION["accountID"] != 0) {
			$favourites = $db->prepare("SELECT * FROM favsongs WHERE songID = :id AND accountID = :aid");
			$favourites->execute([':id' => $songsid, ':aid' => $_SESSION["accountID"]]);
			$favRow = $favourites->fetch();
			$isFav = !empty($favRow);
			$favs = '<button type="button" class="gd-song-fav'.($isFav ? ' is-fav' : '').'" title="'.$this->getLocalizedString($isFav ? "dislikeSong" : "likeSong").'" id="like'.$songsid.'" value="'.($isFav ? '1' : '0').'" onclick="likeSong('.$songsid.')"><i id="likeicon'.$songsid.'" class="fa-'.($isFav ? 'solid' : 'regular').' fa-heart"></i></button>';
		}

		/* who reuploaded / disabled state */
		if($song["reuploadID"] == 0) {
			$time = "<span style='color:var(--tx-3)'>Newgrounds</span>";
			$who = '<a style="color:var(--c-link);font-weight:600" target="_blank" rel="noopener" href="https://'.$author.'.newgrounds.com/audio">'.$author.'</a>';
			$btn = '<span class="icon" style="background:var(--bg-3);color:var(--tx-3);width:34px;height:34px;border-radius:var(--r-full)"><i class="fa-solid fa-xmark"></i></span>';
		} else {
			$who = '<button type="button" class="accbtn" onclick="a(\'profile/'.$gs->getAccountName($song['reuploadID']).'\', true, true)">'.$gs->getAccountName($song['reuploadID']).'</button>';
			$btn = '<button type="button" name="btnsng" id="btn'.$songsid.'" title="'.$author.' - '.$name.'" download="'.$download.'" onclick="btnsong(\''.$songsid.'\');" aria-label="Play"><span class="icon songbtnpic" style="width:34px;height:34px;border-radius:var(--r-full)"><i id="icon'.$songsid.'" class="fa-solid fa-play"></i></span></button>';
		}

		/* manage (rename / disable / delete) */
		$isDisabled = $song['isDisabled'] != 0;
		$manage = '';
		if($check) $manage = '<a style="width:max-content" class="btn-rendel btn-manage" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Song actions"><i class="fa-solid fa-pencil"></i></a><div onclick="event.stopPropagation()" class="dropdown-menu dropdown-menu-right" style="padding: 17px 17px 10px;">
				<form class="form__inner" method="post" name="songrename'.$songsid.'" style="grid-gap: 10px;">
					<div class="field" style="display:none"><input type="hidden" name="ID" value="'.$songsid.'"></div>
					<div class="field"><input type="text" name="author" value="'.$author.'" placeholder="'.$author.'"></div>
					<div class="field"><input type="text" name="name" value="'.$name.'" placeholder="'.$name.'"></div>
					<button type="button" class="btn-song" onclick="renameSong('.$songsid.')">'.$this->getLocalizedString("change").'</button>
					'.($modCheck ? '<button id="songDisableButton'.$song['ID'].'" type="button" class="btn-song" onclick="disableSong('.$songsid.', false)">'.$this->getLocalizedString($isDisabled ? "enable" : "disable").'</button>' : '').'
					<button style="width:100%" type="button" class="btn-song" onclick="deleteSong('.$songsid.')">'.$this->getLocalizedString("delete").'</button>
				</form>
			</div>';

		$disabledState = '';
		if(!$includeAuthor) {
			$isDisabledText = !$isDisabled ? $this->getLocalizedString('songIsAvailable') : $this->getLocalizedString('songIsDisabled');
			$isDisabledIcon = !$isDisabled ? 'check' : 'xmark';
			$disabledState = '<span class="profilepic"><i id="songDisableIcon'.$song['ID'].'" class="fa-solid fa-'.$isDisabledIcon.'"></i> <span id="songDisableText'.$song['ID'].'">'.$isDisabledText.'</span></span>';
		}

		return '<div id="songCard'.$song['ID'].'" style="width:100%;display:flex;flex-wrap:wrap;justify-content:center;">
			<div class="gd-song">
				<div class="gd-song-main">
					'.$btn.'
					<div class="gd-song-title"><b><span id="songname'.$songsid.'">'.$author.' - '.$name.'</span></b><span>'.($includeAuthor ? '<i class="fa-solid fa-user-plus"></i> '.$who : '').'</span></div>
				</div>
				<div class="gd-song-meta">
					<span class="profilepic"><i class="fa-solid fa-weight-hanging"></i> '.$size.' MB</span>
					'.$disabledState.$extraLabels.$favs.$manage.'
				</div>
			</div>
		</div>';
	}

	public function generateSFXCard($sfx, $extraLabels = '', $includeAuthor = true) {
		global $dbPath;
		require __DIR__."/../".$dbPath."incl/lib/connection.php";
		require_once __DIR__."/../".$dbPath."incl/lib/mainLib.php";
		$gs = new mainLib();
		$modCheck = $gs->checkPermission($_SESSION["accountID"], "dashboardManageSongs");
		$check = $modCheck ?: ($_SESSION['accountID'] != 0 && $_SESSION['accountID'] == $sfx['reuploadID']);
		$sfxsid = $sfx["ID"];
		$time = $this->convertToDate($sfx["reuploadTime"], true);
		$author = htmlspecialchars($sfx["authorName"]);
		$name = htmlspecialchars($sfx["name"]);
		$size = round($sfx["size"] / 1024 / 1024, 2);
		$download = str_replace('http://', 'https://', $sfx["download"]);
		$btn = '<button type="button" name="btnsng" id="btn'.$sfxsid.'" title="'.$author.' - '.$name.'" download="'.$download.'" onclick="btnsong(\''.$sfxsid.'\');" aria-label="Play"><span class="icon songbtnpic" style="width:34px;height:34px;border-radius:var(--r-full)"><i id="icon'.$sfxsid.'" class="fa-solid fa-play"></i></span></button>';

		$isDisabled = $sfx['isDisabled'] != 0;
		$manage = '';
		if($check) $manage = '<a style="width:max-content" class="btn-rendel btn-manage" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="SFX actions"><i class="fa-solid fa-pencil"></i></a><div onclick="event.stopPropagation()" class="dropdown-menu dropdown-menu-right" style="padding: 17px 17px 10px;">
				<form class="form__inner" method="post" name="songrename'.$sfxsid.'" style="grid-gap: 10px;">
					<div class="field" style="display:none"><input type="hidden" name="ID" value="'.$sfxsid.'"></div>
					<div class="field"><input type="text" name="name" value="'.$name.'" placeholder="'.$name.'"></div>
					<input type="hidden" name="sfx" value="1">
					<button type="button" class="btn-song" onclick="renameSong('.$sfxsid.', true)">'.$this->getLocalizedString("change").'</button>
					'.($modCheck ? '<button id="songDisableButton'.$sfx['ID'].'" type="button" class="btn-song" onclick="disableSong('.$sfxsid.', true)">'.$this->getLocalizedString($isDisabled ? "enable" : "disable").'</button>' : '').'
					<button style="width:100%" type="button" class="btn-song" onclick="deleteSong('.$sfxsid.', true)">'.$this->getLocalizedString("delete").'</button>
				</form>
			</div>';

		$favs = '';
		if($_SESSION["accountID"] != 0) {
			$favourites = $db->prepare("SELECT * FROM favsongs WHERE songID = :id AND accountID = :aid");
			$favourites->execute([':id' => $sfxsid, ':aid' => $_SESSION["accountID"]]);
			$favRow = $favourites->fetch();
			$isFav = !empty($favRow);
			$favs = '<button type="button" class="gd-song-fav'.($isFav ? ' is-fav' : '').'" title="'.$this->getLocalizedString($isFav ? "dislikeSong" : "likeSong").'" id="like'.$sfxsid.'" value="'.($isFav ? '1' : '0').'" onclick="likeSong('.$sfxsid.')"><i id="likeicon'.$sfxsid.'" class="fa-'.($isFav ? 'solid' : 'regular').' fa-heart"></i></button>';
		}

		$disabledState = '';
		if(!$includeAuthor) {
			$isDisabledText = !$isDisabled ? $this->getLocalizedString('songIsAvailable') : $this->getLocalizedString('songIsDisabled');
			$isDisabledIcon = !$isDisabled ? 'check' : 'xmark';
			$disabledState = '<span class="profilepic"><i id="songDisableIcon'.$sfx['ID'].'" class="fa-solid fa-'.$isDisabledIcon.'"></i> <span id="songDisableText'.$sfx['ID'].'">'.$isDisabledText.'</span></span>';
		}

		return '<div id="songCard'.$sfx['ID'].'" style="width:100%;display:flex;flex-wrap:wrap;justify-content:center;">
			<div class="gd-song">
				<div class="gd-song-main">
					'.$btn.'
					<div class="gd-song-title"><b><span id="songname'.$sfxsid.'">'.$name.'</span></b><span>'.($includeAuthor ? '<i class="fa-solid fa-user-plus"></i> '.$author : '').'</span></div>
				</div>
				<div class="gd-song-meta">
					<span class="profilepic"><i class="fa-solid fa-weight-hanging"></i> '.$size.' MB</span>
					'.$disabledState.$extraLabels.$favs.$manage.'
				</div>
			</div>
		</div>';
	}

	/* ------------------------------------------------------------------ *
	 *  Pagination                                                        *
	 *  Form ORDER matters: gdips.js indexes forms from the end of the    *
	 *  document (getdata 1..6), so don't reorder without updating both.  *
	 * ------------------------------------------------------------------ */
	public function generateBottomRow($pagecount, $actualpage) {
		$pageminus = $actualpage - 1;
		$pageplus = $actualpage + 1;
		if($pagecount < 2) return '';
		$pagelol = explode("/", $_SERVER["REQUEST_URI"]);
		$pagelol = $pagelol[count($pagelol)-2]."/".$pagelol[count($pagelol)-1];
		$pagelol = explode("?", $pagelol)[0];
		$ng = '';
		$inputSearch = !empty($_GET["search"]) ? '<input type="hidden" name="search" value="'.htmlspecialchars($_GET["search"]).'">' : '';
		if(!empty($_GET["type"] OR !empty($_GET["who"]))) $inputSearch .= '<input type="hidden" name="type" value="'.$_GET["type"].'"><input type="hidden" name="who" value="'.$_GET["who"].'">';
		if(!empty($_GET["ng"])) $inputSearch .= '<input type="hidden" name="ng" value="'.$_GET["ng"].'">';
		if(!empty($_GET["levelID"])) $inputSearch .= '<input type="hidden" name="levelID" value="'.$_GET["levelID"].'">';
		return '<div class="page-buttons"><span>'.sprintf($this->getLocalizedString("pageInfo"), $actualpage, $pagecount).'</span><div class="btn-group">'
			.$ng
			.'<form method="get" style="margin:0">'.$inputSearch.'<input type="hidden" name="page" value="1"><button type="button" id="first" class="btn" onclick="a(\''.$pagelol.'\', true, true, \'GET\', 5)" value="1" aria-label="First page"><i class="fa-solid fa-backward"></i></button></form>'
			.'<form method="get" style="margin:0">'.$inputSearch.'<input type="hidden" name="page" value="'.$pageminus.'"><button type="button" id="prev" class="btn" onclick="a(\''.$pagelol.'\', true, true, \'GET\', 4)" value="'.$pageminus.'" aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></button></form>'
			.'<button type="button" class="btn" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Go to page">Â·Â·Â·</button>
			<div class="dropdown-menu dropdown-menu-right" style="padding:17px;">
				<form action="" method="get">
					<div class="form-group">'.$inputSearch.'<input type="text" class="form-control" name="page" placeholder="'.$this->getLocalizedString("page").'"></div>
					<button type="button" class="btn-primary" style="width:100%" onclick="a(\''.$pagelol.'\', true, true, \'GET\', 3)">'.$this->getLocalizedString("go").'</button>
				</form>
			</div>'
			.'<form method="get" style="margin:0">'.$inputSearch.'<input type="hidden" name="page" value="'.$pageplus.'"><button type="button" id="next" class="btn" onclick="a(\''.$pagelol.'\', true, true, \'GET\', 2)" value="'.$pageplus.'" aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></button></form>'
			.'<form method="get" style="margin:0">'.$inputSearch.'<input type="hidden" name="page" value="'.$pagecount.'"><button type="button" id="last" class="btn" onclick="a(\''.$pagelol.'\', true, true, \'GET\', 1)" value="'.$pagecount.'" aria-label="Last page"><i class="fa-solid fa-forward"></i></button></form>
		</div></div><script id="bottomrowscript">
			var pagecount = '.$pagecount.';
			var actualpage = '.$actualpage.';
			if(actualpage == 1) {
				document.getElementById("first").disabled = true;
				document.getElementById("prev").disabled = true;
			}
			if(pagecount == actualpage) {
				document.getElementById("last").disabled = true;
				document.getElementById("next").disabled = true;
			}
			</script>';
	}

	public function parseMessage($body) {
		global $dbPath;
		require __DIR__."/../".$dbPath."incl/lib/connection.php";
		require_once __DIR__."/../".$dbPath."incl/lib/exploitPatch.php";
		$parseBody = explode(' ', $body);
		$playersFound = $levelsFound = [];
		foreach($parseBody AS &$element) {
			$firstChar = mb_substr($element, 0, 1);
			if(!in_array($firstChar, ['@', '#'])) continue;
			$element = mb_substr($element, 1);
			switch($firstChar) {
				case '@':
					if($playersFound[$element]) break;
					$element = ExploitPatch::charclean($element);
					$check = $db->prepare('SELECT count(*) FROM accounts WHERE userName = :userName AND isActive != 0');
					$check->execute([':userName' => $element]);
					$check = $check->fetchColumn();
					if($check) {
						$body = str_replace('@'.$element, '<span class="messenger-link" onclick="a(\'profile/'.$element.'\', true, true, \'GET\')">@'.$element.'</span>', $body);
						$playersFound[$element] = true;
					}
					break;
				case '#':
					if(!is_numeric($element) || $levelsFound[$element]) break;
					$check = $db->prepare('SELECT levelName FROM levels WHERE levelID = :levelID AND unlisted = 0 AND unlisted2 = 0');
					$check->execute([':levelID' => $element]);
					$check = $check->fetchColumn();
					if($check) {
						$body = str_replace('#'.$element, '<span class="messenger-link" onclick="a(\'stats/levelsList.php?search='.$check.'\', true, true, \'GET\')">#'.htmlspecialchars($check).'</span>', $body);
						$levelsFound[$element] = true;
					}
					break;
			}
		}
		return $body;
	}

	/* ------------------------------------------------------------------ *
	 *  Compact cards (home page previews & directories)                  *
	 * ------------------------------------------------------------------ */
	public function generateMiniLevelCard($action) {
		global $dbPath;
		require_once __DIR__."/../".$dbPath."incl/lib/mainLib.php";
		require_once __DIR__."/../".$dbPath."incl/lib/exploitPatch.php";
		$gs = new mainLib();
		$desc = trim(htmlspecialchars(ExploitPatch::url_base64_decode($action["levelDesc"])));
		if(empty($desc)) $desc = $this->getLocalizedString("noDesc");
		$rate = $this->gdRateChip($action["starFeatured"], $action["starEpic"]);
		return '<a class="gd-levelcard" href="stats/levelsList.php?search='.$action["levelID"].'" onclick="a(\'stats/levelsList.php?search='.$action["levelID"].'\', true, true);return false;">
			<div class="gd-levelcard-top">
				'.$this->gdDiffChip($action["starDifficulty"], $action["auto"], $action["starDemonDiff"], $action['levelLength'], $action["starStars"]).'
				<span class="gd-levelcard-name">'.htmlspecialchars($action["levelName"]).'</span>
				'.$rate.'
			</div>
			<p class="gd-levelcard-desc">'.$desc.'</p>
			<div class="gd-levelcard-meta">
				<span><i class="fa-solid fa-user"></i>'.htmlspecialchars($action["userName"]).'</span>
				<span><i class="fa-solid fa-reply fa-rotate-270"></i>'.$action["downloads"].'</span>
				<span><i class="fa-regular fa-thumbs-up"></i>'.($action["likes"] - ($action["dislikes"] ?? 0)).'</span>
			</div>
		</a>';
	}

	public function generateMiniPlayerRow($rank, $user) {
		global $dbPath;
		require __DIR__."/../".$dbPath."incl/lib/connection.php";
		$name = ($user["userName"] == "Undefined" OR empty($user["userName"])) ? '#' : $user["userName"];
		$medal = $rank <= 3 ? '<i class="fa-solid fa-trophy" style="color:'.($rank == 1 ? '#ffd700' : ($rank == 2 ? '#c0c0c0' : '#cd7f32')).'"></i>' : '# '.$rank;
		return '<a class="gd-playerrow" href="profile/'.htmlspecialchars($name).'" onclick="a(\'profile/'.htmlspecialchars($name).'\', true, true);return false;">
			<span class="gd-playerrow-rank">'.$medal.'</span>
			'.$this->gdAvatar($user, 30).'
			<span class="gd-playerrow-name">'.htmlspecialchars($name).'</span>
			<span class="gd-playerrow-value"><i class="fa-solid fa-star"></i>'.number_format($user["stars"]).'</span>
		</a>';
	}

	public function generateMiniSongRow($song) {
		$songsid = $song["ID"];
		$author = htmlspecialchars($song["authorName"]);
		$name = htmlspecialchars($song["name"]);
		$download = str_replace('http://', 'https://', $song["download"]);
		return '<div class="gd-songrow">
			<button type="button" class="gd-songrow-play" name="btnsng" id="btn'.$songsid.'" title="'.$author.' - '.$name.'" download="'.$download.'" onclick="btnsong(\''.$songsid.'\');" aria-label="Play"><i id="icon'.$songsid.'" class="fa-solid fa-play"></i></button>
			<div class="gd-songrow-name"><b>'.$name.'</b><span>'.$author.'</span></div>
			<button type="button" class="gd-songrow-id" onclick="copysong('.$songsid.')" title="Copy song ID">ID '.$songsid.'</button>
		</div>';
	}

	public function generateMiniClanCard($clan, $members) {
		global $dbPath;
		require_once __DIR__."/../".$dbPath."incl/lib/exploitPatch.php";
		$name = htmlspecialchars(base64_decode($clan["clan"]));
		$tag = htmlspecialchars(base64_decode($clan["tag"]));
		$desc = $this->parseMessage(htmlspecialchars(base64_decode($clan["desc"])));
		if(empty($desc)) $desc = $this->getLocalizedString("noClanDesc");
		$locked = $clan["isClosed"] == 1 ? ' <i class="fa-solid fa-lock" aria-hidden="true"></i>' : '';
		return '<a class="gd-clan" style="--clan-color:#'.htmlspecialchars($clan["color"]).'" href="clan/'.htmlspecialchars($name).'" onclick="a(\'clan/'.htmlspecialchars($name).'\', true, true);return false;">
			<div class="gd-clan-head">
				<span class="gd-clan-tag">'.htmlspecialchars($tag).'</span>
				<div style="min-width:0;flex:1">
					<h3 class="gd-clan-name" style="color:#'.htmlspecialchars($clan["color"]).'">'.$name.$locked.'</h3>
					<p class="gd-clan-desc" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">'.$desc.'</p>
				</div>
			</div>
			<div class="gd-clan-foot">
				<span class="gd-chip"><i class="fa-solid fa-user-group"></i>'.max(0, $members - 1).'</span>
			</div>
		</a>';
	}

	public function title($title) {
		global $gdps;
		echo '<title>'.htmlspecialchars($title).' | '.$gdps.'</title>';
	}
}
?>

