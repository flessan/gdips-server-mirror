<?php
/**
 * Bootstrap diagnostic. This intentionally catches fatal startup errors
 * while loading the GDPS application connection stack.
 */

ob_start();
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

$stage = "start";
$payload = [
    "ok" => false,
    "stage" => $stage,
    "phpVersion" => PHP_VERSION,
    "timestamp" => gmdate("c"),
];

register_shutdown_function(static function() use (&$stage, &$payload): void {
    $error = error_get_last();

    if($error && in_array($error["type"], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        while(ob_get_level() > 0) ob_end_clean();
        http_response_code(500);
        $payload["ok"] = false;
        $payload["stage"] = $stage . ":fatal";
        $payload["fatalError"] = [
            "type" => $error["type"],
            "message" => $error["message"],
            "file" => basename($error["file"]),
            "line" => $error["line"],
        ];
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
});

$stage = "before_connection";
require __DIR__ . "/../incl/lib/connection.php";
$stage = "after_connection";

$payload["connection"] = [
    "dbSet" => isset($db),
    "dbIsPdo" => isset($db) && $db instanceof PDO,
];

if(isset($db) && $db instanceof PDO) {
    $stage = "before_mysql_ping";
    $payload["connection"]["mysqlPing"] = ((int)$db->query("SELECT 1")->fetchColumn() === 1);
    $stage = "after_mysql_ping";
}

$stage = "before_telegraph";
require_once __DIR__ . "/../incl/lib/telegraphCloud.php";
$stage = "after_telegraph";

$payload["telegraphCloud"] = [
    "enabled" => gdTelegraphCloud::enabled(),
];

$payload["ok"] = true;
$payload["stage"] = "complete";

while(ob_get_level() > 0) ob_end_clean();
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>