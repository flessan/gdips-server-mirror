<?php
// Minimal runtime probe for Wasmer/PHP. Intentionally has no database or
// application dependencies, so it can distinguish a runtime/host failure
// from a failure inside the GDPS application stack.
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");
header("X-Content-Type-Options: nosniff");

echo json_encode([
    "ok" => true,
    "phpVersion" => PHP_VERSION,
    "sapi" => PHP_SAPI,
    "zlibLoaded" => extension_loaded("zlib"),
    "pdoLoaded" => extension_loaded("PDO"),
    "pdoMysqlLoaded" => extension_loaded("pdo_mysql"),
    "timestamp" => gmdate("c"),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>