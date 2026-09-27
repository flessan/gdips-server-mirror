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

$tableReady = false;
try {
    $db->exec("CREATE TABLE IF NOT EXISTS debug_download_trace (
        id TINYINT UNSIGNED NOT NULL,
        updated_at DATETIME NOT NULL,
        payload LONGTEXT NOT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $tableReady = true;
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode([
    "ok" => true,
    "snapshot" => $data
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
