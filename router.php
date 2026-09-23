<?php
declare(strict_types=1);

/**
 * GDIPS front controller for PHP's built-in web server / Wasmer.
 *
 * The original project uses Apache .htaccess rewrites for pretty dashboard
 * routes such as:
 *   /dashboard/profile/Flessan
 *   /dashboard/messenger/SomeUser
 *   /dashboard/clan/MyClan
 *
 * PHP's built-in server does not process .htaccess, so this router reproduces
 * only those required rewrites while leaving real files untouched.
 */

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uriPath = is_string($uriPath) ? $uriPath : '/';

// Let the built-in server serve real files/directories normally.
$documentRoot = realpath(__DIR__) ?: __DIR__;
$decodedPath = rawurldecode($uriPath);
$target = realpath($documentRoot . $decodedPath);

if (
    $decodedPath !== '/' &&
    $target !== false &&
    str_starts_with($target, $documentRoot . DIRECTORY_SEPARATOR) &&
    (is_file($target) || is_dir($target))
) {
    return false;
}

/**
 * Route a pretty dashboard URL to an existing index.php and provide the
 * original URL through ?id=..., matching the old Apache rewrite rules.
 */
$routes = [
    '#^/dashboard/profile(?:/(.*))?$#'   => '/dashboard/profile/index.php',
    '#^/dashboard/messenger(?:/(.*))?$#' => '/dashboard/messenger/index.php',
    '#^/dashboard/clan(?:/(.*))?$#'      => '/dashboard/clan/index.php',
];

foreach ($routes as $pattern => $script) {
    if (!preg_match($pattern, $decodedPath, $matches)) {
        continue;
    }

    $scriptPath = $documentRoot . $script;
    if (!is_file($scriptPath)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Not found";
        return true;
    }

    // The original rewrite passed REQUEST_URI as the id parameter.
    $_GET['id'] = $decodedPath;

    $_SERVER['SCRIPT_NAME'] = $script;
    $_SERVER['SCRIPT_FILENAME'] = $scriptPath;

    require $scriptPath;
    return true;
}

// Root and normal PHP files are handled by the built-in server.
return false;
