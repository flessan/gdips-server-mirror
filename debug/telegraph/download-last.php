<?php
/**
 * Show the last Geometry Dash level-download diagnostic.
 * Administrator-only. No auth cookies, passwords, API keys, or raw level payloads.
 */

session_start();

require __DIR__ . "/../../incl/lib/connection.php";
require_once __DIR__ . "/../../incl/lib/adminLib.php";

gdAdminLib::requireAdmin($db);

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");
header("X-Content-Type-Options: nosniff");

try {
    $db->exec("CREATE TABLE IF NOT EXISTS debug_download_traces (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        payload LONGTEXT NOT NULL,
        PRIMARY KEY (id),
        KEY idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "error" => "debug_storage_unavailable"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $query = $db->query(
        "SELECT id, created_at, updated_at, payload
         FROM debug_download_traces
         ORDER BY id DESC
         LIMIT 20"
    );
    $rows = $query ? $query->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "error" => "debug_trace_read_failed"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$requests = [];
foreach (array_reverse($rows) as $row) {
    $data = json_decode((string)$row["payload"], true);
    if (!is_array($data)) {
        $data = [
            "ok" => false,
            "stage" => "invalid_snapshot",
        ];
    }
    $data["_id"] = (int)$row["id"];
    $data["_createdAt"] = $row["created_at"];
    $data["_storedAt"] = $row["updated_at"];
    $requests[] = $data;
}

if (empty($requests)) {
    http_response_code(404);
    echo json_encode([
        "ok" => false,
        "error" => "no_download_snapshot",
        "message" => "Trigger one level download in Geometry Dash first, then refresh this endpoint."
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
        "ok" => false,
        "error" => "debug_storage_unavailable"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $query = $db->query(
        "SELECT updated_at, payload
         FROM debug_download_trace
         WHERE id = 1
         LIMIT 1"
    );
    $row = $query ? $query->fetch(PDO::FETCH_ASSOC) : false;
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "error" => "debug_trace_read_failed"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!$row) {
    http_response_code(404);
    echo json_encode([
        "ok" => false,
        "error" => "no_download_snapshot",
        "message" => "Trigger one level download in Geometry Dash first, then refresh this endpoint."
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$data = json_decode((string)$row["payload"], true);

if (!is_array($data)) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "error" => "invalid_snapshot"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$data["_storedAt"] = $row["updated_at"];

$listTrace = null;
try {
    $listQuery = $db->query(
        "SELECT updated_at, payload
         FROM debug_gjlevels_trace
         WHERE id = 1
         LIMIT 1"
    );
    $listRow = $listQuery ? $listQuery->fetch(PDO::FETCH_ASSOC) : false;
    if ($listRow) {
        $listData = json_decode((string)$listRow["payload"], true);
        if (is_array($listData)) {
            $listData["_storedAt"] = $listRow["updated_at"];
            $listTrace = $listData;
        }
    }
} catch (Throwable $ignored) {
    // Level-list tracing is optional.
}

echo json_encode([
    "ok" => true,
    "requests" => $requests,
    "levelListSnapshot" => $listTrace
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
