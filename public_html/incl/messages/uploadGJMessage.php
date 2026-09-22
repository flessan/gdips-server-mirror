<?php
chdir(dirname(__FILE__));
require "../lib/connection.php";
require_once "../lib/GJPCheck.php";
require_once "../lib/exploitPatch.php";
require_once "../lib/mainLib.php";
require_once "../lib/automod.php";
if(Automod::isAccountsDisabled(3)) exit('-1');
$gs = new mainLib();
$gameVersion =  ExploitPatch::remove($_POST["gameVersion"]);
$binaryVersion =  ExploitPatch::remove($_POST["binaryVersion"]);
$secret =  ExploitPatch::remove($_POST["secret"]);
$subject =  ExploitPatch::remove($_POST["subject"]);
$toAccountID =  ExploitPatch::number($_POST["toAccountID"]);
$body =  ExploitPatch::remove($_POST["body"]);
$accID =  GJPCheck::getAccountIDOrDie();
if($accID == $toAccountID) exit("-1");
$query3 = "SELECT userName FROM users WHERE extID = :accID ORDER BY userName DESC";
$query3 = $db->prepare($query3);
$query3->execute([':accID' => $accID]);
$userName = $query3->fetchColumn();
//continuing the accounts system
$register = 1;
$userID = $gs->getUserID($accID);
$uploadDate = time();

$checkBan = $gs->getPersonBan($accID, $userID, 3);
if($checkBan) exit('-1');

$checkExistence = $db->prepare("SELECT count(*) FROM accounts WHERE accountID = :toAccountID");
$checkExistence->execute([':toAccountID' => $toAccountID]);
if(!$checkExistence->fetchColumn()) exit('-1');

$blocked = $db->prepare("SELECT ID FROM `blocks` WHERE person1 = :toAccountID AND person2 = :accountID");
$blocked->execute([':toAccountID' => $toAccountID, ':accountID' => $accID]);
$blocked = $blocked->fetchAll(PDO::FETCH_COLUMN);
$mSOnly = $db->prepare("SELECT mS FROM `accounts` WHERE accountID = :toAccountID AND mS > 0");
$mSOnly->execute([':toAccountID' => $toAccountID]);
$mSOnly = $mSOnly->fetchAll(PDO::FETCH_COLUMN);
$friend = $db->prepare("SELECT ID FROM `friendships` WHERE (person1 = ? AND person2 = ?) || (person2 = ? AND person1 = ?)");
$friend->execute([$accID, $toAccountID, $accID, $toAccountID]);
$friend = $friend->fetchAll(PDO::FETCH_COLUMN);

$query = $db->prepare("INSERT INTO messages (subject, body, accID, userID, userName, toAccountID, secret, timestamp)
VALUES (:subject, :body, :accID, :userID, :userName, :toAccountID, :secret, :uploadDate)");

if (!empty($mSOnly[0]) and $mSOnly[0] == 2) {
    echo -1;
} else {
    if (empty($blocked[0]) and (empty($mSOnly[0]) || !empty($friend[0]))) {
        $query->execute([':subject' => $subject, ':body' => $body, ':accID' => $accID, ':userID' => $userID, ':userName' => $userName, ':toAccountID' => $toAccountID, ':secret' => $secret, ':uploadDate' => $uploadDate]);
        echo 1;
    } else {
        echo -1;
    }
}
?>
