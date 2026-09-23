<?php
session_start();
require "../incl/dashboardLib.php";
$dl = new dashboardLib();
global $clansEnabled;
require "../".$dbPath."incl/lib/connection.php";
require "../".$dbPath."config/dashboard.php";
$dl->title($dl->getLocalizedString("aboutProject"));

$repo = $dl->gdProjectRepo();
$gdpsName = htmlspecialchars($gdps);

/* third-party credits from config (array: avatar, name, link, contribution) */
$credits = '';
if(!empty($thirdParty)) {
	$seen = [];
	foreach($thirdParty as $tp) {
		if(isset($seen[$tp[1]])) continue;
		$seen[$tp[1]] = true;
		$credits .= '<div class="gd-account" style="padding:12px 16px">
			<img src="'.$tp[0].'" alt="" style="width:36px;height:36px;border-radius:20%;object-fit:cover">
			<div style="min-width:0;flex:1">
				<a href="'.$tp[2].'" target="_blank" rel="noopener" style="font-weight:700;font-family:var(--font-display)">'.htmlspecialchars($tp[1]).'</a>
				<div style="font-size:var(--fs-xs);color:var(--tx-3)">'.htmlspecialchars($tp[3]).'</div>
			</div>
		</div>';
	}
}

$res = function($href, $icon, $title, $desc, $external = true) {
	return '<a class="gd-res" href="'.$href.'"'.($external ? ' target="_blank" rel="noopener"' : '').'>
		<span class="gd-res-ico"><i class="'.$icon.'" aria-hidden="true"></i></span>
		<span class="gd-res-info"><b>'.$title.'</b><span>'.$desc.'</span></span>
		<i class="fa-solid '.($external ? 'fa-arrow-up-right-from-square' : 'fa-arrow-right').'" aria-hidden="true"></i>
	</a>';
};

$content = '
<section class="gd-project-hero gd-kawung-band">
	<p class="gd-eyebrow" style="letter-spacing:0.2em">GDIPS</p>
	<h1 class="gd-display">'.$dl->getLocalizedString("aboutProject").'</h1>
	<p style="color:var(--tx-1)">'.$dl->getLocalizedString("projectIntro").'</p>
	<p style="color:var(--tx-1)" class="gd-chip gd-chip--gold" style="font-size:var(--fs-sm);padding:6px 14px"><i class="fa-solid fa-map-pin"></i>'.$dl->getLocalizedString("projectTagline").'</p>
</section>

<section class="gd-section">
	<div class="gd-values">
		<div class="gd-value">
			<i class="fa-brands fa-github" aria-hidden="true"></i>
			<h3>'.$dl->getLocalizedString("valueOpenTitle").'</h3>
			<p>'.$dl->getLocalizedString("valueOpenBody").'</p>
		</div>
		<div class="gd-value">
			<i class="fa-solid fa-people-group" aria-hidden="true"></i>
			<h3>'.$dl->getLocalizedString("valueCommunityTitle").'</h3>
			<p>'.$dl->getLocalizedString("valueCommunityBody").'</p>
		</div>
		<div class="gd-value">
			<i class="fa-solid fa-server" aria-hidden="true"></i>
			<h3>'.$dl->getLocalizedString("valueSelfhostTitle").'</h3>
			<p>'.$dl->getLocalizedString("valueSelfhostBody").'</p>
		</div>
		<div class="gd-value">
			<i class="fa-solid fa-magnifying-glass-chart" aria-hidden="true"></i>
			<h3>'.$dl->getLocalizedString("valueTransparentTitle").'</h3>
			<p>'.$dl->getLocalizedString("valueTransparentBody").'</p>
		</div>
	</div>
</section>

<section class="gd-section">
	<div class="gd-section-head"><h2 class="gd-display">'.$dl->getLocalizedString("projectResources").'</h2></div>
	<div class="gd-reslist">
		'.$res(htmlspecialchars($repo), 'fa-brands fa-github', $dl->getLocalizedString("resSource"), $dl->getLocalizedString("resSourceDesc")).'
		'.$res(htmlspecialchars($repo).'/issues', 'fa-solid fa-bug', $dl->getLocalizedString("resContribute"), $dl->getLocalizedString("resContributeDesc")).'
		'.$res(htmlspecialchars($repo).'/blob/main/README.md', 'fa-solid fa-book', $dl->getLocalizedString("resDocs"), $dl->getLocalizedString("resDocsDesc")).'
		'.$res('stats/levelsList.php', 'fa-solid fa-plug', $dl->getLocalizedString("resAPI"), $dl->getLocalizedString("resAPIDesc"), false).'
	</div>
</section>

<section class="gd-section">
	<div class="gd-card gd-kawung-band">
		<h2 class="gd-display" style="display:flex;align-items:center;gap:12px"><i class="fa-solid fa-code" style="color:var(--merah-strong)" aria-hidden="true"></i>'.$dl->getLocalizedString("projectTechTitle").'</h2>
		<p style="color:var(--tx-2);margin:0">'.$dl->getLocalizedString("projectTechBody").'</p>
	</div>
</section>

'.($credits != '' ? '<section class="gd-section">
	<div class="gd-section-head"><h2 class="gd-display">'.$dl->getLocalizedString("projectCredits").'</h2></div>
	<div class="gd-grid gd-grid--2">'.$credits.'</div>
</section>' : '').'
<div class="gd-motif" aria-hidden="true"><i class="fa-solid fa-heart"></i></div>
<p style="text-align:center;color:var(--tx-3);font-size:var(--fs-sm)">'.$gdpsName.' · '.$dl->getLocalizedString("footerBuilt").'</p>';

$dl->printSong($content, 'project');
$dl->printFooter('../');
?>
