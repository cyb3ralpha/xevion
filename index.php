<?php
/**
 * XEVION FaceMatch — public front controller.
 * Apache rewrites every non-file request here (see .htaccess).
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require APP_ROOT . '/app/controllers/public.php';

start_public_session();
send_security_headers();
maybe_housekeeping();

$path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
if (base_path() !== '' && str_starts_with($path, base_path())) {
    $path = substr($path, strlen(base_path()));
}
$path = '/' . trim($path, '/');
if ($path === '/index.php') {
    $path = '/';
}

$routes = [
    '#^/$#'                                   => 'page_home',
    '#^/explore$#'                            => 'page_explore',
    '#^/category/([a-z]{2,20})$#'             => 'page_category',
    '#^/create$#'                             => 'page_create',
    '#^/created$#'                            => 'page_created',
    '#^/m/([A-Za-z0-9]{12})$#'                => 'page_match',
    '#^/m/([A-Za-z0-9]{12})/play$#'           => 'page_play',
    '#^/m/([A-Za-z0-9]{12})/results$#'        => 'page_results',
    '#^/p/([A-Za-z0-9_-]{20,64})$#'           => 'page_invite',
    '#^/manage/([A-Za-z0-9_-]{30,64})$#'      => 'page_manage',
    '#^/api/next$#'                           => 'api_next',
    '#^/api/vote$#'                           => 'api_vote',
    '#^/api/cron$#'                           => 'api_cron',
    '#^/media/([A-Za-z0-9]{16})/(thumb|full)$#' => 'media_participant',
    '#^/media/ad/([A-Za-z0-9]{12})/(d|m)$#'   => 'media_ad',
    '#^/go/([A-Za-z0-9]{12})$#'               => 'page_ad_click',
    '#^/report$#'                             => 'page_report',
    '#^/contact$#'                            => 'page_contact',
    '#^/privacy$#'                            => 'page_privacy',
    '#^/terms$#'                              => 'page_terms',
];

foreach ($routes as $pattern => $handler) {
    if (preg_match($pattern, $path, $m)) {
        array_shift($m);
        $handler(...$m);
        exit;
    }
}
not_found();
