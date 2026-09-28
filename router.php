<?php
declare(strict_types=1);

/*
 * GDIPS router for PHP's built-in web server / Wasmer.
 *
 * Apache .htaccess is not evaluated by "php -S", so pretty dashboard URLs
 * need to be dispatched here:
 *
 *   /dashboard/profile/Flessan
 *   /dashboard/profile/Flessan/settings
 *   /dashboard/messenger/SomeUser
 *   /dashboard/clan/MyClan
 *
 * The original PHP files use relative requires such as "../incl/...", so the
 * working directory is switched to the target script directory before loading
 * them. This matches the directory context those files expect under Apache.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? rawurldecode($path) : '/';

$documentRoot = realpath(__DIR__) ?: __DIR__;

/*
 * Real files/directories must keep normal PHP built-in server behavior.
 * Do not route assets, ordinary PHP endpoints, or existing directories.
 */
$realTarget = realpath($documentRoot . $path);
if (
    $path !== '/' &&
    $realTarget !== false &&
    str_starts_with($realTarget, $documentRoot . DIRECTORY_SEPARATOR) &&
    (is_file($realTarget) || is_dir($realTarget))
) {
    return false;
}

$routes = [
    '#^/dashboard/profile(?:/.*)?$#'   => '/dashboard/profile/index.php',
    '#^/dashboard/messenger(?:/.*)?$#' => '/dashboard/messenger/index.php',
    '#^/dashboard/clan(?:/.*)?$#'      => '/dashboard/clan/index.php',
];

foreach ($routes as $pattern => $script) {
    if (!preg_match($pattern, $path)) {
        continue;
    }

    $scriptPath = $documentRoot . $script;

    if (!is_file($scriptPath)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Not found";
        exit;
    }

    // Preserve the legacy Apache rewrite contract used by the PHP pages.
    $_GET['id'] = $path;

    $_SERVER['SCRIPT_NAME'] = $script;
    $_SERVER['SCRIPT_FILENAME'] = $scriptPath;
    $_SERVER['PATH_TRANSLATED'] = $scriptPath;

    /*
     * These legacy dashboard pages contain relative require() statements.
     * Run them from their own directory so "../incl/..." resolves correctly.
     */
    $previousCwd = getcwd();
    chdir(dirname($scriptPath));

    try {
        require $scriptPath;
    } finally {
        if ($previousCwd !== false) {
            chdir($previousCwd);
        }
    }

    exit;
}

// Let the built-in server handle everything else.
return false;
