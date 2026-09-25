<?php
session_start();

require "../incl/dashboardLib.php";
require "../".$dbPath."incl/lib/connection.php";
require_once "../".$dbPath."incl/lib/mainLib.php";
require_once "../".$dbPath."incl/lib/exploitPatch.php";

$gs = new mainLib();
$dl = new dashboardLib();
$dl->title($dl->getLocalizedString("leaderboardTime"));

$actualpage = (isset($_GET["page"]) && ctype_digit((string)$_GET["page"]) && (int)$_GET["page"] > 0)
    ? (int)$_GET["page"] : 1;
$page = ($actualpage - 1) * 10;

$time = time() - 86400;
$members = '';
$result = [];

$conditions = [
    "actions.type = 9",
    "actions.timestamp > :time",
    "actions.value > 0"
];
$params = [":time" => $time];

/*
 * Exclude active bans without interpolating user data into the SQL.
 * personType:
 *   0 = account/extID
 *   1 = player/userID
 *   2 = IP
 */
$bans = $gs->getAllBansOfBanType(0);
$banExtIDs = [];
$banUserIDs = [];
$bannedIPs = [];

foreach($bans as $ban) {
    switch((int)$ban["personType"]) {
        case 0:
            $banExtIDs[] = (string)$ban["person"];
            break;
        case 1:
            $banUserIDs[] = (string)$ban["person"];
            break;
        case 2:
            $ip = $gs->IPForBan($ban["person"], true);
            if($ip !== false && $ip !== '') $bannedIPs[] = $ip;
            break;
    }
}

if(!empty($banExtIDs)) {
    $placeholders = [];
    foreach($banExtIDs as $i => $value) {
        $key = ":banExt".$i;
        $placeholders[] = $key;
        $params[$key] = $value;
    }
    $conditions[] = "users.extID NOT IN (".implode(",", $placeholders).")";
}

if(!empty($banUserIDs)) {
    $placeholders = [];
    foreach($banUserIDs as $i => $value) {
        $key = ":banUser".$i;
        $placeholders[] = $key;
        $params[$key] = $value;
    }
    $conditions[] = "users.userID NOT IN (".implode(",", $placeholders).")";
}

if(!empty($bannedIPs)) {
    $regex = implode("|", array_map(
        static function($ip) { return preg_quote($ip, "/"); },
        $bannedIPs
    ));
    $conditions[] = "users.IP NOT REGEXP :banRegex";
    $params[":banRegex"] = $regex;
}

$where = implode(" AND ", $conditions);

/*
 * Strict and deterministic leaderboard query.
 * Group by the actual selected user identity instead of a SUM alias.
 */
$sql = "
    SELECT users.extID, users.userName, SUM(actions.value) AS stars
    FROM actions
    INNER JOIN users ON users.extID = actions.account
    WHERE ".$where."
    GROUP BY users.extID, users.userName
    ORDER BY stars DESC, users.extID ASC
    LIMIT 10 OFFSET ".$page;
$query = $db->prepare($sql);
$query->execute($params);
$result = $query->fetchAll(PDO::FETCH_ASSOC);

if(empty($result)) {
    $dl->printPage(
        '<div class="gd-card">
            <div class="gd-empty">
                <i class="fa-solid fa-ranking-star"></i>
                <h2>'.$dl->getLocalizedString("emptyPage").'</h2>
                <p style="color:var(--tx-2)">There are no leaderboard results for this page yet.</p>
            </div>
        </div>',
        true,
        "stats"
    );
    $dl->printFooter("../");
    exit;
}

$rank = $page + 1;

foreach($result as $action) {
    $userid = $action["extID"];
    $stars = (int)$action["stars"];
    $username = $action["userName"];

    $stats = $dl->createProfileStats($stars, 0, 0, 0, 0, 0, 0, 0);

    switch($rank) {
        case 1:
            $place = '<i class="fa-solid fa-trophy" style="color:#ffd700"> 1</i>';
            break;
        case 2:
            $place = '<i class="fa-solid fa-trophy" style="color:#c0c0c0"> 2</i>';
            break;
        case 3:
            $place = '<i class="fa-solid fa-trophy" style="color:#cd7f32"> 3</i>';
            break;
        default:
            $place = '<span style="color:var(--tx-2)"># '.$rank.'</span>';
            break;
    }

    $avatarImg = '';
    $queryAvatar = $db->prepare(
        'SELECT iconType, color1, color2, color3, accGlow, accIcon, accShip, accBall,
                accBird, accDart, accRobot, accSpider, accSwing, accJetpack
         FROM users WHERE extID = :extID LIMIT 1'
    );
    $queryAvatar->execute([":extID" => $userid]);
    $userData = $queryAvatar->fetch(PDO::FETCH_ASSOC);

    if($userData) {
        $iconType = (int)$userData["iconType"];
        if($iconType < 0 || $iconType > 8) $iconType = 0;

        $iconTypeMap = [
            0 => ["type" => "cube", "value" => $userData["accIcon"]],
            1 => ["type" => "ship", "value" => $userData["accShip"]],
            2 => ["type" => "ball", "value" => $userData["accBall"]],
            3 => ["type" => "ufo", "value" => $userData["accBird"]],
            4 => ["type" => "wave", "value" => $userData["accDart"]],
            5 => ["type" => "robot", "value" => $userData["accRobot"]],
            6 => ["type" => "spider", "value" => $userData["accSpider"]],
            7 => ["type" => "swing", "value" => $userData["accSwing"]],
            8 => ["type" => "jetpack", "value" => $userData["accJetpack"]]
        ];

        $iconValue = (int)$iconTypeMap[$iconType]["value"];
        if($iconValue <= 0) $iconValue = 1;

        $avatarImg =
            '<img src="'.$iconsRendererServer.'/icon.png?type='.$iconTypeMap[$iconType]["type"].
            '&value='.$iconValue.
            '&color1='.rawurlencode($userData["color1"]).
            '&color2='.rawurlencode($userData["color2"]).
            ((int)$userData["accGlow"] !== 0
                ? '&glow='.(int)$userData["accGlow"].'&color3='.rawurlencode($userData["color3"])
                : '').
            '" alt="" style="width:30px;height:30px;object-fit:contain" loading="lazy">';
    }

    $commentColor = $gs->getAccountCommentColor($userid);
    if($commentColor === false || !preg_match('/^\d{1,3},\d{1,3},\d{1,3}$/', (string)$commentColor)) {
        $commentColor = "255,255,255";
    }

    $members .= '
        <div class="gd-account">
            '.$avatarImg.'
            <div style="min-width:0;flex:1">
                <div class="gd-account-head">
                    <button type="button"
                        onclick="a(\'profile/'.htmlspecialchars($username, ENT_QUOTES, "UTF-8").'\', true, true, \'GET\')"
                        class="gd-account-name"
                        style="color:rgb('.htmlspecialchars($commentColor, ENT_QUOTES, "UTF-8").')">'
                        .htmlspecialchars($username, ENT_QUOTES, "UTF-8").
                    '</button>
                </div>
                <div class="gd-account-stats">'.$stats.'</div>
            </div>
            <div class="gd-account-foot">
                <span>ID <b style="color:var(--tx-2)">'.htmlspecialchars((string)$userid, ENT_QUOTES, "UTF-8").'</b></span>
            </div>
        </div>';
    $rank++;
}

/* Count groups for pagination. */
$countSql = "
    SELECT COUNT(*)
    FROM (
        SELECT users.extID
        FROM actions
        INNER JOIN users ON users.extID = actions.account
        WHERE ".$where."
        GROUP BY users.extID
    ) ranked";
$countQuery = $db->prepare($countSql);
$countQuery->execute($params);
$total = (int)$countQuery->fetchColumn();
$pagecount = max(1, (int)ceil($total / 10));

$pagel = '<div class="gd-pagehead">
    <p class="gd-eyebrow">GDIPS</p>
    <div class="gd-pagehead-row">
        <div>
            <h1 class="gd-display">'.$dl->getLocalizedString("leaderboardTime").'</h1>
            <p class="gd-pagehead-sub">Top players by star activity during the last 24 hours.</p>
        </div>
    </div>
</div>
<div class="gd-list">'.$members.'</div>';

$bottomrow = $dl->generateBottomRow($pagecount, $actualpage);
$dl->printPage($pagel.$bottomrow, true, "stats");
$dl->printFooter("../");
?>