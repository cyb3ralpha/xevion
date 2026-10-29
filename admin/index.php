<?php
/**
 * XEVION FaceMatch — administrator front controller (/admin/...).
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require APP_ROOT . '/app/admin_auth.php';
require APP_ROOT . '/app/controllers/admin.php';

if (!db_enabled()) {
    // No database: a smaller dashboard signed in through environment variables.
    require APP_ROOT . '/app/admin_lite.php';
    $p = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    $p = trim(str_starts_with($p, base_path() . '/admin') ? substr($p, strlen(base_path() . '/admin')) : $p, '/');
    admin_lite($p === 'index.php' ? '' : $p);
    exit;
}
ensure_first_admin(); // picks up an admin email/password added after the first visit
admin_session_start();
send_security_headers(true);
header('Cache-Control: no-store');
header('Referrer-Policy: same-origin');

$path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$prefix = base_path() . '/admin';
$route = trim(str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path, '/');
if ($route === 'index.php') {
    $route = '';
}
$GLOBALS['ADMIN_ROUTE'] = $route;

$routes = [
    ''            => 'admin_dashboard',
    'login'       => 'admin_login',
    'logout'      => 'admin_logout_page',
    'competitions' => 'admin_competitions',
    'competition' => 'admin_competition',
    'moderation'  => 'admin_moderation',
    'reports'     => 'admin_reports',
    'ads'         => 'admin_ads',
    'ad'          => 'admin_ad',
    'admins'      => 'admin_admins',
    'settings'    => 'admin_settings',
    'blocks'      => 'admin_blocks',
    'audit'       => 'admin_audit',
    'messages'    => 'admin_messages',
    'account'     => 'admin_account',
    'media'       => 'admin_media',
];
if (!isset($routes[$route])) {
    render_error(404);
}
$routes[$route]();
