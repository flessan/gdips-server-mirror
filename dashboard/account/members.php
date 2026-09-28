<?php
session_start();

require __DIR__."/../incl/dashboardLib.php";
require __DIR__."/../../incl/lib/connection.php";
require_once __DIR__."/../../incl/lib/mainLib.php";
require_once __DIR__."/../../incl/lib/adminLib.php";

$dl = new dashboardLib();
$gs = new mainLib();
gdAdminLib::requireAdmin($db);

if(!gdAdminLib::tableExists('roles', $db) || !gdAdminLib::tableExists('roleassign', $db)) {
    $dl->title("Accounts & Roles");
    $dl->printPage(
        '<div class="gd-card"><h1>Accounts & roles</h1><p>The role tables are not installed. Import the current <code>database.sql</code> schema, then reload this page.</p></div>',
        true,
        "members"
    );
    $dl->printFooter("../");
    exit;
}

if(!isset($_SESSION['gdips_members_csrf'])) {
    $_SESSION['gdips_members_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['gdips_members_csrf'];

$h = static function($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};

$notice = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if(!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Security token expired. Reload the page.');
        }

        $accountID = (int)($_POST['accountID'] ?? 0);
        if($accountID <= 0) throw new RuntimeException('Invalid account.');

        $action = (string)($_POST['action'] ?? '');

        $q = $db->prepare("SELECT accountID,userName,isAdmin FROM accounts WHERE accountID=:id");
        $q->execute([':id'=>$accountID]);
        $account = $q->fetch(PDO::FETCH_ASSOC);
        if(!$account) throw new RuntimeException('Account not found.');

        if($action === 'admin') {
            $newState = (int)($_POST['isAdmin'] ?? 0) === 1 ? 1 : 0;

            if($accountID === gdAdminLib::accountId() && $newState === 0) {
                throw new RuntimeException('You cannot remove administrator access from your own account here.');
            }

            $q = $db->prepare("UPDATE accounts SET isAdmin=:isAdmin WHERE accountID=:id");
            $q->execute([':isAdmin'=>$newState, ':id'=>$accountID]);
            $notice = $newState ? 'Administrator access granted.' : 'Administrator access removed.';
        } elseif($action === 'roles') {
            $selected = isset($_POST['roles']) && is_array($_POST['roles']) ? array_map('intval', $_POST['roles']) : [];
            $selected = array_values(array_unique(array_filter($selected, static function($id){ return $id > 0; })));

            $db->beginTransaction();

            $q = $db->prepare("DELETE FROM roleassign WHERE accountID=:id");
            $q->execute([':id'=>$accountID]);

            if(!empty($selected)) {
                $q = $db->prepare("INSERT INTO roleassign (roleID,accountID) VALUES (:roleID,:accountID)");
                foreach($selected as $roleID) {
                    $check = $db->prepare("SELECT roleID FROM roles WHERE roleID=:roleID");
                    $check->execute([':roleID'=>$roleID]);
                    if(!$check->fetchColumn()) continue;
                    $q->execute([':roleID'=>$roleID, ':accountID'=>$accountID]);
                }
            }

            $db->commit();
            $notice = 'Role assignments saved.';
        }
    } catch(Throwable $e) {
        if($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    }
}

$search = trim((string)($_GET['q'] ?? ''));
$selectedAccountID = max(0, (int)($_GET['account'] ?? 0));

$roles = $db->query(
    "SELECT roleID,roleName,priority,isDefault,commentColor,modBadgeLevel
     FROM roles ORDER BY priority DESC, roleID ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$sql = "SELECT accountID,userName,email,isAdmin,isActive,registerDate
        FROM accounts";
$params = [];
if($search !== '') {
    $sql .= " WHERE userName LIKE :q OR email LIKE :q OR CAST(accountID AS CHAR) LIKE :q";
    $params[':q'] = '%'.$search.'%';
}
$sql .= " ORDER BY isAdmin DESC,userName ASC LIMIT 100";

$q = $db->prepare($sql);
$q->execute($params);
$accounts = $q->fetchAll(PDO::FETCH_ASSOC);

if($selectedAccountID > 0) {
    $exists = false;
    foreach($accounts as $a) if((int)$a['accountID'] === $selectedAccountID) { $exists = true; break; }
    if(!$exists && $search === '') {
        $q = $db->prepare("SELECT accountID,userName,email,isAdmin,isActive,registerDate FROM accounts WHERE accountID=:id");
        $q->execute([':id'=>$selectedAccountID]);
        if($a=$q->fetch(PDO::FETCH_ASSOC)) array_unshift($accounts,$a);
    }
}

$selected = null;
foreach($accounts as $a) {
    if((int)$a['accountID'] === $selectedAccountID) {
        $selected = $a;
        break;
    }
}
if(!$selected && !empty($accounts)) {
    $selected = $accounts[0];
    $selectedAccountID = (int)$selected['accountID'];
}

$assigned = [];
if($selected) {
    $q = $db->prepare("SELECT roleID FROM roleassign WHERE accountID=:id");
    $q->execute([':id'=>$selectedAccountID]);
    $assigned = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
}

$list = '';
foreach($accounts as $account) {
    $isSelected = (int)$account['accountID'] === $selectedAccountID;
    $label = (int)$account['isAdmin'] === 1 ? 'Administrator' : 'Member';
    $chips = $label;
    $roleSummary = gdAdminLib::roleAssignments((int)$account['accountID'], $db);
    if(!empty($roleSummary)) {
        $chips .= ' · '.implode(', ', array_map(static function($r){ return $r['roleName']; }, $roleSummary));
    }

    $list .= '<a class="gd-card" href="members.php?account='.$h($account['accountID']).($search!==''?'&q='.rawurlencode($search):'').'" style="display:block;border:1px solid '.($isSelected?'var(--accent)':'var(--line)').';">
        <div style="display:flex;justify-content:space-between;gap:var(--sp-3);align-items:flex-start;">
            <div>
                <strong>'.$h($account['userName']).'</strong>
                <p style="margin:var(--sp-1) 0 0;color:var(--tx-2);font-size:var(--fs-sm);">'.$h($chips).'</p>
            </div>
            <span class="gd-chip">#'.$h($account['accountID']).'</span>
        </div>
    </a>';
}

if($selected) {
    $roleOptions = '';
    foreach($roles as $role) {
        $checked = in_array((int)$role['roleID'], $assigned, true) ? ' checked' : '';
        $roleOptions .= '<label class="checkbox"><input type="checkbox" name="roles[]" value="'.$h($role['roleID']).'"'.$checked.'> '.$h($role['roleName']).' <span style="color:var(--tx-2)">('.$h($role['priority']).')</span></label>';
    }

    $adminChecked = (int)$selected['isAdmin'] === 1 ? ' checked' : '';
    $adminControl = '
        <form method="post" class="gd-card" style="margin-top:var(--sp-4);">
            <input type="hidden" name="csrf" value="'.$h($csrf).'">
            <input type="hidden" name="accountID" value="'.$h($selected['accountID']).'">
            <input type="hidden" name="action" value="admin">
            <label class="checkbox">
                <input type="checkbox" name="isAdmin" value="1"'.$adminChecked.'>
                Administrator access
            </label>
            <button class="gd-btn gd-btn--primary" style="margin-top:var(--sp-4)" type="submit">
                <i class="fa-solid fa-shield-halved"></i> Save administrator access
            </button>
        </form>';

    $roleControl = '
        <form method="post" class="gd-card" style="margin-top:var(--sp-4);">
            <input type="hidden" name="csrf" value="'.$h($csrf).'">
            <input type="hidden" name="accountID" value="'.$h($selected['accountID']).'">
            <input type="hidden" name="action" value="roles">
            <strong>Role assignments</strong>
            <p style="margin:var(--sp-1) 0 var(--sp-4);color:var(--tx-2);font-size:var(--fs-sm);">Roles control moderator permissions. Administrators already bypass permission checks.</p>
            <div style="display:grid;gap:var(--sp-2);">'.$roleOptions.'</div>
            <button class="gd-btn gd-btn--primary" style="margin-top:var(--sp-4)" type="submit">
                <i class="fa-solid fa-floppy-disk"></i> Save roles
            </button>
        </form>';

    $details = '<div>
        <div class="gd-card">
            <p class="gd-eyebrow">ACCOUNT</p>
            <h2 class="gd-display" style="margin-top:var(--sp-2)">'.$h($selected['userName']).'</h2>
            <p style="color:var(--tx-2)">Account #'.$h($selected['accountID']).' · '.$h($selected['email']).'</p>
            <p style="color:var(--tx-2)">Status: '.((int)$selected['isActive']===1?'active':'inactive').'</p>
        </div>
        '.$adminControl.$roleControl.'
    </div>';
} else {
    $details = '<div class="gd-card"><h2>No accounts found</h2><p>Try another search.</p></div>';
}

$content = '
<div class="gd-pagehead">
    <p class="gd-eyebrow">ADMIN CENTER</p>
    <div class="gd-pagehead-row"><div>
        <h1 class="gd-display">Accounts & roles</h1>
        <p class="gd-pagehead-sub">Give administrator access and assign granular moderator roles without touching account passwords or authentication tokens.</p>
    </div></div>
</div>';

if($notice!=='') $content.='<div class="gd-card" style="margin-bottom:var(--sp-4)"><strong>'.$h($notice).'</strong></div>';
if($error!=='') $content.='<div class="gd-card" style="margin-bottom:var(--sp-4)"><strong>Save failed:</strong> '.$h($error).'</div>';

$content .= '
<form class="gd-toolbar" method="get">
    <input class="form-control gd-input" name="q" value="'.$h($search).'" placeholder="Search username, email or account ID">
    <button class="gd-btn gd-btn--secondary" type="submit"><i class="fa-solid fa-search"></i> Search</button>
</form>
<div class="gd-grid gd-grid--2">
    <div style="display:grid;gap:var(--sp-3);align-content:start;">'.($list!==''?$list:'<div class="gd-card">No accounts found.</div>').'</div>
    '.$details.'
</div>';

$dl->title("Accounts & roles");
$dl->printPage($content, true, "members");
$dl->printFooter("../");
?>
