<?php
/**
 * Vercel entry point. vercel.json routes every request that is not a static asset here,
 * and this file hands it to the matching front controller (the same two that Apache
 * uses through .htaccess). Source folders are never served as files on Vercel.
 */
declare(strict_types=1);

$path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

if (preg_match('#^/admin(/|$)#', $path)) {
    require dirname(__DIR__) . '/admin/index.php';
} else {
    require dirname(__DIR__) . '/index.php';
}
