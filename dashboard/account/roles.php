<?php
session_start();

require __DIR__."/../incl/dashboardLib.php";
require __DIR__."/../../incl/lib/connection.php";
require __DIR__."/../../incl/lib/badgeLib.php";

$dl = new dashboardLib();
$dl->title("Roles");

$q = $db->prepare("SELECT isAdmin FROM accounts WHERE accountID = :id");
$q->execute([":id" => $_SESSION["accountID"] ?? 0]);
if((int)$q->fetchColumn() !== 1){
    $dl->printPage('<div class="gd-card"><h1>Roles</h1><p>Administrator access required.</p></div>', true, "roles");
    $dl->printFooter("../");
    exit;
}

if(!isset($_SESSION["gdips_roles_csrf"])) $_SESSION["gdips_roles_csrf"] = bin2hex(random_bytes(32));
$csrf = $_SESSION["gdips_roles_csrf"];

$columns = $db->query("SHOW COLUMNS FROM roles")->fetchAll(PDO::FETCH_ASSOC);
$permissions = [];
foreach($columns as $column){
    $name = $column["Field"];
    if(in_array($name, ["roleID","priority","roleName","isDefault","commentColor","modBadgeLevel"], true)) continue;
    if(
        strpos($name, "command") === 0 ||
        strpos($name, "action") === 0 ||
        strpos($name, "tool") === 0 ||
        strpos($name, "dashboard") === 0 ||
        strpos($name, "demonlist") === 0 ||
        strpos($name, "modip") === 0
    ) $permissions[] = $name;
}
sort($permissions, SORT_NATURAL | SORT_FLAG_CASE);

$h = static function($v){ return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8"); };
$roleID = max(0, (int)($_GET["role"] ?? 0));
$notice = "";
$error = "";

if($_SERVER["REQUEST_METHOD"] === "POST"){
    if(!hash_equals($csrf, (string)($_POST["csrf"] ?? ""))){
        $error = "Security token expired. Reload the page.";
    } else {
        try{
            $action = (string)($_POST["roleAction"] ?? "save");
            $id = (int)($_POST["roleID"] ?? 0);

            if($action === "delete"){
                $q = $db->prepare("SELECT isDefault FROM roles WHERE roleID = :id");
                $q->execute([":id"=>$id]);
                $role = $q->fetch(PDO::FETCH_ASSOC);
                if(!$role) throw new RuntimeException("Role not found.");
                if((int)$role["isDefault"] === 1) throw new RuntimeException("The default role cannot be deleted.");

                $q = $db->prepare("SELECT COUNT(*) FROM roleassign WHERE roleID = :id");
                $q->execute([":id"=>$id]);
                if((int)$q->fetchColumn() > 0) throw new RuntimeException("This role is still assigned to players.");

                $q = $db->prepare("DELETE FROM roles WHERE roleID = :id");
                $q->execute([":id"=>$id]);
                $roleID = 0;
                $notice = "Role deleted.";
            } else {
                $name = trim((string)($_POST["roleName"] ?? ""));
                $priority = (int)($_POST["priority"] ?? 0);
                $default = isset($_POST["isDefault"]) ? 1 : 0;
                $color = trim((string)($_POST["commentColor"] ?? "000,000,000"));
                $badge = (int)($_POST["modBadgeLevel"] ?? 0);

                if($name === "" || strlen($name) > 255) throw new RuntimeException("Role name is required.");
                if($priority < 0 || $priority > 1000000000) throw new RuntimeException("Invalid priority.");
                if($badge < 0 || $badge > 3) throw new RuntimeException("Badge must be 0-3.");

                $parts = explode(",", $color);
                if(count($parts) !== 3) throw new RuntimeException("Comment color must be R,G,B.");
                foreach($parts as $part){
                    if(!ctype_digit($part) || (int)$part > 255) throw new RuntimeException("Comment color must be R,G,B with channels 0-255.");
                }

                $db->beginTransaction();
                if($default) $db->exec("UPDATE roles SET isDefault = 0");

                if($id > 0){
                    $q = $db->prepare("UPDATE roles SET roleName=:name, priority=:priority, isDefault=:def, commentColor=:color, modBadgeLevel=:badge WHERE roleID=:id");
                    $q->execute([
                        ":name"=>$name, ":priority"=>$priority, ":def"=>$default,
                        ":color"=>$color, ":badge"=>$badge, ":id"=>$id
                    ]);

                    foreach($permissions as $permission){
                        $value = max(0, min(2, (int)($_POST["perm"][$permission] ?? 0)));
                        $q = $db->prepare("UPDATE roles SET `".$permission."`=:value WHERE roleID=:id");
                        $q->execute([":value"=>$value, ":id"=>$id]);
                    }
                    $roleID = $id;
                    $notice = "Role updated.";
                } else {
                    $q = $db->prepare("INSERT INTO roles (roleName,priority,isDefault,commentColor,modBadgeLevel) VALUES (:name,:priority,:def,:color,:badge)");
                    $q->execute([
                        ":name"=>$name, ":priority"=>$priority, ":def"=>$default,
                        ":color"=>$color, ":badge"=>$badge
                    ]);
                    $roleID = (int)$db->lastInsertId();

                    foreach($permissions as $permission){
                        $value = max(0, min(2, (int)($_POST["perm"][$permission] ?? 0)));
                        $q = $db->prepare("UPDATE roles SET `".$permission."`=:value WHERE roleID=:id");
                        $q->execute([":value"=>$value, ":id"=>$roleID]);
                    }

                    $notice = "Role created.";
                }
                $db->commit();
            }
        }catch(Throwable $e){
            if($db->inTransaction()) $db->rollBack();
            $error = $e->getMessage();
        }
    }
}

$roles = $db->query(
    "SELECT r.roleID,r.roleName,r.priority,r.isDefault,r.commentColor,r.modBadgeLevel,
            COUNT(ra.accountID) AS assignedCount
     FROM roles r
     LEFT JOIN roleassign ra ON ra.roleID=r.roleID
     GROUP BY r.roleID,r.roleName,r.priority,r.isDefault,r.commentColor,r.modBadgeLevel
     ORDER BY r.priority DESC,r.roleID ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$current = [
    "roleID"=>0, "roleName"=>"", "priority"=>0, "isDefault"=>0,
    "commentColor"=>"000,000,000", "modBadgeLevel"=>0
];
foreach($permissions as $permission) $current[$permission]=0;

if($roleID > 0){
    $q=$db->prepare("SELECT * FROM roles WHERE roleID=:id");
    $q->execute([":id"=>$roleID]);
    $found=$q->fetch(PDO::FETCH_ASSOC);
    if($found) $current=array_merge($current,$found);
    else { $roleID=0; $error=$error ?: "Role not found."; }
}

$list = '<div class="gd-card">
    <div style="display:flex;justify-content:space-between;gap:var(--sp-3);align-items:center;flex-wrap:wrap;">
        <div><strong>Roles</strong><p style="margin:var(--sp-1) 0 0;color:var(--tx-2);font-size:var(--fs-sm);">Roles, priorities, badges and permissions.</p></div>
        <a class="gd-btn gd-btn--secondary" href="roles.php" onclick="a(\'account/roles.php\');return false;"><i class="fa-solid fa-plus"></i> New</a>
    </div>
    <div class="gd-list" style="margin-top:var(--sp-4);">';

foreach($roles as $role){
    $badge=gdBadgeLib::render((int)$role["modBadgeLevel"],"",28);
    $default=(int)$role["isDefault"]===1 ? '<span class="gd-chip">default</span>' : "";
    $list.='<div class="profile" style="display:flex;justify-content:space-between;align-items:center;gap:var(--sp-3);flex-wrap:wrap;">
        <div><div style="display:flex;gap:var(--sp-2);align-items:center;flex-wrap:wrap;"><strong>'.$h($role["roleName"]).'</strong>'.$badge.$default.'</div>
        <div style="color:var(--tx-2);font-size:var(--fs-sm);">ID '.$h($role["roleID"]).' · priority '.$h($role["priority"]).' · assigned '.$h($role["assignedCount"]).' · RGB '.$h($role["commentColor"]).'</div></div>
        <a class="gd-btn gd-btn--ghost" href="roles.php?role='.$h($role["roleID"]).'" onclick="a(\'account/roles.php?role='.$h($role["roleID"]).'\');return false;"><i class="fa-solid fa-pen"></i> Edit</a>
    </div>';
}
$list.='</div></div>';

$form='<form method="post" class="gd-card">
    <input type="hidden" name="csrf" value="'.$h($csrf).'">
    <input type="hidden" name="roleID" value="'.$h($current["roleID"]).'">
    <input type="hidden" name="roleAction" value="save">
    <strong>'.($roleID>0?"Edit role":"Create role").'</strong>

    <div class="gd-grid gd-grid--2" style="margin-top:var(--sp-4);">
        <div class="form-group"><label><strong>Role name</strong></label><input class="form-control gd-input" name="roleName" value="'.$h($current["roleName"]).'" required></div>
        <div class="form-group"><label><strong>Priority</strong></label><input class="form-control gd-input" type="number" min="0" max="1000000000" name="priority" value="'.$h($current["priority"]).'" required></div>
        <div class="form-group"><label><strong>Comment color</strong></label><input class="form-control gd-input" name="commentColor" value="'.$h($current["commentColor"]).'" required></div>
        <div class="form-group"><label><strong>Badge</strong></label><select class="form-control gd-input" name="modBadgeLevel">
            <option value="0"'.((int)$current["modBadgeLevel"]===0?" selected":"").'>None</option>
            <option value="1"'.((int)$current["modBadgeLevel"]===1?" selected":"").'>Badge 1</option>
            <option value="2"'.((int)$current["modBadgeLevel"]===2?" selected":"").'>Badge 2</option>
            <option value="3"'.((int)$current["modBadgeLevel"]===3?" selected":"").'>Badge 3</option>
        </select></div>
    </div>
    <label class="checkbox" style="margin-top:var(--sp-3);"><input type="checkbox" name="isDefault" value="1"'.(!empty($current["isDefault"])?" checked":"").'> Default role</label>
    <details style="margin-top:var(--sp-5);"><summary><strong>Permissions ('.$h(count($permissions)).')</strong></summary>
        <div class="gd-grid gd-grid--2" style="margin-top:var(--sp-4);">';

foreach($permissions as $permission){
    $value=(int)($current[$permission]??0);
    $form.='<div class="form-group"><label><strong>'.$h($permission).'</strong></label><select class="form-control gd-input" name="perm['.$h($permission).']">
        <option value="0"'.($value===0?" selected":"").'>Default</option>
        <option value="1"'.($value===1?" selected":"").'>Allow</option>
        <option value="2"'.($value===2?" selected":"").'>Deny</option>
    </select></div>';
}
$form.='</div></details>
    <div style="display:flex;justify-content:space-between;margin-top:var(--sp-5);">
        <div>'.($roleID>0?'<button class="gd-btn gd-btn--danger" type="submit" name="roleAction" value="delete" onclick="return confirm(\'Delete this role?\');"><i class="fa-solid fa-trash"></i> Delete</button>':"").'</div>
        <button class="gd-btn gd-btn--primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> '.($roleID>0?"Save role":"Create role").'</button>
    </div>
</form>';

$content='<div class="gd-pagehead"><p class="gd-eyebrow">ADMIN</p><div class="gd-pagehead-row"><div><h1 class="gd-display">Role manager</h1><p class="gd-pagehead-sub">Manage priorities, badges, colors and permission overrides.</p></div></div></div>';
if($notice!=="") $content.='<div class="gd-card" style="margin-bottom:var(--sp-4);"><strong>'.$h($notice).'</strong></div>';
if($error!=="") $content.='<div class="gd-card" style="margin-bottom:var(--sp-4);"><strong>Save failed:</strong> '.$h($error).'</div>';
$content.='<div class="gd-grid gd-grid--2">'.$list.$form.'</div>';

$dl->printPage($content,true,"roles");
$dl->printFooter("../");
?>