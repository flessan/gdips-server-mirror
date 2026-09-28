<?php
error_reporting(0);
class au {
  function auth($dbPath = '../') {
    if(file_exists($dbPath."incl/lib/connection.php")) require_once $dbPath."incl/lib/connection.php";
    elseif(file_exists("../../$dbPath".''."incl/lib/connection.php")) require_once "../../$dbPath".''."incl/lib/connection.php";
    else require_once "../$dbPath".''."incl/lib/connection.php";
    $check = $db->query("SHOW COLUMNS FROM `accounts` LIKE 'auth'");
    $exist = $check->fetchAll();
    if(empty($exist)) return 'no';
	$cookieAuth = $_COOKIE["auth"] ?? null;
	if(!is_string($cookieAuth) || trim($cookieAuth) === '' || strtolower(trim($cookieAuth)) === 'none') {
		$_SESSION["accountID"] = 0;
		return true;
	}
	if(!empty($_SESSION["accountID"])) {
        $query = $db->prepare("SELECT auth FROM accounts WHERE accountID = :id");
        $query->execute([':id' => $_SESSION["accountID"]]);
        $auth = $query->fetch();
        if(!$auth || !is_string($auth["auth"]) || !hash_equals($auth["auth"], $cookieAuth)) $_SESSION["accountID"] = 0;
    } else {
        $query = $db->prepare("SELECT accountID FROM accounts WHERE BINARY auth = BINARY :id AND auth != ''");
        $query->execute([':id' => $cookieAuth]);
        $auth = $query->fetch();
        if(!empty($auth)) $_SESSION["accountID"] = $auth["accountID"];
    }
	return true;
  }
}
?>
