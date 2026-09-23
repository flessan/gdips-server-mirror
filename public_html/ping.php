<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

echo "pong\n";
echo "GDIPS PHP runtime OK\n";
echo "host=" . ($_SERVER['HTTP_HOST'] ?? 'unknown') . "\n";
echo "time=" . gmdate('c') . "\n";
