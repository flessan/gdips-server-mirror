<?php
/**
 * Show the last successful Geometry Dash level download request.
 * Administrator-only; contains no auth cookies, passwords, API keys, or raw level data.
 *
 * Usage:
 *   /debug/telegraph/download-last.php
 */

session_start();

require __DIR__ . "/../../incl/lib/connection.php";
require_once __DIR__ . "/../../incl/lib/adminLib.php";

gdAdminLib::requireAdmin($db);

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");
header("X-Content-Type-Options: nosniff");

$path = sys_get_temp_dir() . "/gdips-last-download.json";

if (!is_file($path)) {
    http_response_code(404);
    echo json_encode([
        "ok" => false,
        "error" => "no_download_snapshot",
        "message" => "Trigger one level download in Geometry Dash first, then refresh this endpoint."
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$raw = @file_get_contents($path);
$data = is_string($raw) ? json_decode($raw, true) : null;

if (!is_array($data)) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "error" => "invalid_snapshot"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    "ok" => true,
    "snapshot" => $data
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
