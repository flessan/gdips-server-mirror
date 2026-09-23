<?php
session_start();

require __DIR__."/../incl/dashboardLib.php";
require __DIR__."/../../incl/lib/connection.php";
require __DIR__."/../../incl/lib/mainLib.php";
require __DIR__."/../../incl/lib/adminLib.php";

$dl = new dashboardLib();
$gs = new mainLib();
gdAdminLib::requireAdmin($db);

$h = static function($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};

$count = static function($sql) use ($db) {
    try {
        $q = $db->query($sql);
        return (int)$q->fetchColumn();
    } catch(Throwable $e) {
        return null;
    }
};

$accounts = $count("SELECT COUNT(*) FROM accounts");
$activeAccounts = $count("SELECT COUNT(*) FROM accounts WHERE isActive = 1");
$levels = $count("SELECT COUNT(*) FROM levels");
$songs = $count("SELECT COUNT(*) FROM songs");
$clans = gdAdminLib::tableExists('clans', $db) ? $count("SELECT COUNT(*) FROM clans") : null;
$rolesReady = gdAdminLib::tableExists('roles', $db) && gdAdminLib::tableExists('roleassign', $db);

$permissionCards = [
    ['dashboardModTools', 'Moderation tools', 'fa-gavel', 'account/banPerson.php'],
    ['dashboardManageLevels', 'Level management', 'fa-gamepad', 'stats/levelsList.php'],
    ['dashboardManageSongs', 'Song management', 'fa-music', 'stats/disabledSongsList.php'],
    ['dashboardManageAutomod', 'Automod', 'fa-robot', 'automod'],
    ['dashboardAddMod', 'Moderator management', 'fa-user-shield', 'account/members.php'],
    ['dashboardVaultCodesManage', 'Vault codes', 'fa-key', 'levels/vaultCodes.php'],
];

$quick = '';
foreach($permissionCards as $item) {
    [$permission, $label, $icon, $href] = $item;
    if(gdAdminLib::can($permission, $gs)) {
        $quick .= '<a class="gd-shortcut" href="'.$h($href).'" onclick="a(''.$h($href).'',true,true,'GET');return false;">
            <i class="fa-solid '.$icon.' gd-sc-ico"></i><span>'.$h($label).'</span>
        </a>';
    }
}

$cards = [
    ['Accounts', $accounts, 'fa-users'],
    ['Active accounts', $activeAccounts, 'fa-user-check'],
    ['Levels', $levels, 'fa-gamepad'],
    ['Songs', $songs, 'fa-music'],
    ['Clans', $clans, 'fa-dungeon'],
];

$stats = '';
foreach($cards as [$label,$value,$icon]) {
    $display = $value === null ? '—' : number_format($value);
    $stats .= '<div class="gd-stat" role="listitem"><span class="gd-stat-value">'.$h($display).'</span><span class="gd-stat-label"><i class="fa-solid '.$icon.'"></i> '.$h($label).'</span></div>';
}

$setupNotice = '';
if(!$rolesReady) {
    $setupNotice = '<div class="gd-card" style="margin-bottom:var(--sp-5);">
        <strong>Role system needs its database tables.</strong>
        <p style="margin:var(--sp-2) 0 0;color:var(--tx-2);">
            The existing database schema expects <code>roles</code> and <code>roleassign</code>.
            The dashboard will keep working with administrator access even before those tables are installed.
        </p>
    </div>';
}

$tools = '
<div class="gd-grid gd-grid--3">
    <a class="gd-card" href="/dashboard/settings.php" onclick="a('settings.php');return false;">
        <i class="fa-solid fa-sliders fa-2x"></i><h2>Server settings</h2>
        <p>Edit supported GDIPS configuration values.</p>
    </a>
    <a class="gd-card" href="/dashboard/account/members.php" onclick="a('account/members.php');return false;">
        <i class="fa-solid fa-users-gear fa-2x"></i><h2>Accounts & roles</h2>
        <p>Manage administrator flags and role assignments.</p>
    </a>
    <a class="gd-card" href="/dashboard/account/roles.php" onclick="a('account/roles.php');return false;">
        <i class="fa-solid fa-user-shield fa-2x"></i><h2>Role definitions</h2>
        <p>Define permission sets, priorities, colors and badges.</p>
    </a>
    <a class="gd-card" href="/dashboard/account/badges.php" onclick="a('account/badges.php');return false;">
        <i class="fa-solid fa-id-badge fa-2x"></i><h2>Badges</h2>
        <p>Manage moderator and administrator badge assets.</p>
    </a>
</div>';

$content = '
<div class="gd-pagehead">
    <p class="gd-eyebrow">ADMIN CENTER</p>
    <div class="gd-pagehead-row">
        <div>
            <h1 class="gd-display">Server administration</h1>
            <p class="gd-pagehead-sub">A single control surface for GDIPS configuration, accounts, roles and moderation.</p>
        </div>
    </div>
</div>

'.$setupNotice.'

<div class="gd-statband" role="list">'.$stats.'</div>

<section class="gd-section">
    <div class="gd-section-head"><h2 class="gd-display">Administration</h2></div>
    '.$tools.'
</section>

'.($quick !== '' ? '<section class="gd-section">
    <div class="gd-section-head"><h2 class="gd-display">Permission tools</h2></div>
    <div class="gd-shortcuts">'.$quick.'</div>
</section>' : '').'

<section class="gd-section">
    <div class="gd-section-head"><h2 class="gd-display">Access model</h2></div>
    <div class="gd-grid gd-grid--3">
        <div class="gd-card"><strong>Member</strong><p>Normal account features only. No administration or moderation navigation is exposed.</p></div>
        <div class="gd-card"><strong>Moderator</strong><p>Receives only the tools allowed by assigned role permissions.</p></div>
        <div class="gd-card"><strong>Administrator</strong><p>Full dashboard control plus all permission-gated tools.</p></div>
    </div>
</section>';

$dl->printPage($content, true, "admin");
$dl->printFooter("../");
?>
