<?php
/**
 * Router for PHP's built-in development server (mirrors .htaccess):
 *   php -S localhost:8000 router.php
 * Not used by Apache in production.
 */
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (preg_match('#^/(app|config|storage|database|templates|cli|tests|docs)(/|$)#', $path) || preg_match('#(^|/)\.#', $path)
    || preg_match('#\.(sql|md|log|lock|ini|sh)$#i', $path)) {
    http_response_code(403);
    exit('Forbidden');
}
if (preg_match('#^/install/?$#', $path)) {
    require __DIR__ . '/install/index.php';
    return true;
}
if (preg_match('#^/admin(/|$)#', $path)) {
    require __DIR__ . '/admin/index.php';
    return true;
}
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file) && !preg_match('#\.php$#', $file)) {
    return false; // let the built-in server send the static file
}
require __DIR__ . '/index.php';
return true;
