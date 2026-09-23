<?php

/**
 * Privilege helper for the GDIPS web dashboard.
 *
 * Privilege model:
 *   member       = authenticated account without moderator permissions
 *   moderator    = account with one or more role permissions
 *   administrator= accounts.isAdmin = 1
 *
 * Administrators inherit every role permission through mainLib::checkPermission().
 */
class gdAdminLib {
    public static function accountId() {
        return (int)($_SESSION['accountID'] ?? 0);
    }

    public static function isLoggedIn() {
        return self::accountId() > 0;
    }

    private static function pdo($db = null) {
        if($db instanceof PDO) return $db;
        global $db;
        return $db instanceof PDO ? $db : null;
    }

    public static function isAdmin($db = null) {
        if(!self::isLoggedIn()) return false;
        $pdo = self::pdo($db);
        if(!$pdo) return false;

        $q = $pdo->prepare("SELECT isAdmin FROM accounts WHERE accountID = :id LIMIT 1");
        $q->execute([':id' => self::accountId()]);
        return (int)$q->fetchColumn() === 1;
    }

    public static function can($permission, $gs = null) {
        if(!self::isLoggedIn()) return false;
        if(self::isAdmin()) return true;

        $allowed = [
            'commandRate','commandFeature','commandEpic','commandUnepic',
            'commandVerifycoins','commandDaily','commandWeekly','commandEvent',
            'commandDelete','commandSetacc','commandRenameOwn','commandRenameAll',
            'commandPassOwn','commandPassAll','commandDescriptionOwn','commandDescriptionAll',
            'commandPublicOwn','commandPublicAll','commandUnlistOwn','commandUnlistAll',
            'commandSharecpOwn','commandSharecpAll','commandSongOwn','commandSongAll',
            'commandLockCommentsOwn','commandLockCommentsAll','commandLockUpdating',
            'actionRateDemon','actionRateStars','actionRateDifficulty','actionRequestMod',
            'actionSuggestRating','actionDeleteComment','toolLeaderboardsban',
            'dashboardGauntletCreate','toolQuestsCreate','toolModactions','toolSuggestlist',
            'dashboardModTools','dashboardLevelPackCreate','dashboardAddMod',
            'dashboardManageSongs','dashboardForceChangePassNick','dashboardDeleteLeaderboards',
            'dashboardManageLevels','dashboardManageAutomod','dashboardVaultCodesManage',
            'demonlistAdd','demonlistApprove','modipCategory'
        ];

        if(!in_array($permission, $allowed, true)) return false;

        $main = $gs ?: new mainLib();
        return (bool)$main->checkPermission(self::accountId(), $permission);
    }

    public static function hasModeratorAccess($gs = null) {
        if(self::isAdmin()) return true;
        return self::can('dashboardModTools', $gs ?: new mainLib());
    }

    public static function requireAdmin($db = null) {
        if(!self::isAdmin($db)) {
            http_response_code(403);
            exit('Administrator access required.');
        }
        return true;
    }

    public static function tableExists($table, $db = null) {
        $pdo = self::pdo($db);
        if(!$pdo || !preg_match('/^[A-Za-z0-9_]+$/', $table)) return false;

        $q = $pdo->prepare("SHOW TABLES LIKE :table");
        $q->execute([':table' => $table]);
        return (bool)$q->fetchColumn();
    }

    public static function roleColumns($db = null) {
        $pdo = self::pdo($db);
        if(!$pdo || !self::tableExists('roles', $pdo)) return [];

        try {
            $columns = $pdo->query("SHOW COLUMNS FROM roles")->fetchAll(PDO::FETCH_ASSOC);
            return array_map(static function($column) {
                return (string)$column['Field'];
            }, $columns);
        } catch(Throwable $e) {
            return [];
        }
    }

    public static function roleAssignments($accountId = null, $db = null) {
        $accountId = $accountId === null ? self::accountId() : (int)$accountId;
        $pdo = self::pdo($db);
        if(
            !$pdo ||
            !self::tableExists('roleassign', $pdo) ||
            !self::tableExists('roles', $pdo)
        ) return [];

        $columns = self::roleColumns($pdo);
        $required = ['roleID','roleName','priority'];
        foreach($required as $column) {
            if(!in_array($column, $columns, true)) return [];
        }

        $select = 'r.roleID,r.roleName,r.priority';
        foreach(['isDefault','commentColor','modBadgeLevel'] as $column) {
            if(in_array($column, $columns, true)) {
                $select .= ',r.'.$column;
            }
        }

        try {
            $q = $pdo->prepare(
                "SELECT ".$select."
                 FROM roleassign a
                 INNER JOIN roles r ON r.roleID = a.roleID
                 WHERE a.accountID = :accountID
                 ORDER BY r.priority DESC, r.roleID ASC"
            );
            $q->execute([':accountID' => $accountId]);
            return $q->fetchAll(PDO::FETCH_ASSOC);
        } catch(Throwable $e) {
            return [];
        }
    }

    public static function roleSummary($accountId = null, $db = null) {
        $roles = self::roleAssignments($accountId, $db);
        if(
            self::isAdmin($db) &&
            ($accountId === null || (int)$accountId === self::accountId())
        ) return 'Administrator';

        if(empty($roles)) return 'Member';

        return implode(', ', array_map(
            static function($role){ return $role['roleName']; },
            $roles
        ));
    }
}
