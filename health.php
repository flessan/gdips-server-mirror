<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

echo json_encode([
    'ok' => true,
    'service' => 'GDIPS',
    'message' => 'Geometry Dash Indonesia Private Server is online.',
    'host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
    'time' => gmdate('c'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
