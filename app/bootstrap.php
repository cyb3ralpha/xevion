<?php
/**
 * XEVION FaceMatch — application bootstrap.
 * Loaded by every entry point (index.php, admin/index.php, cli/*, tests/*).
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '1.0.0');

date_default_timezone_set('UTC'); // all stored timestamps are UTC

require_once APP_ROOT . '/app/env_config.php';

$configFile = APP_ROOT . '/config/config.php';
$useEnv = env_config_available() && (!is_file($configFile) || env_bool('APP_CONFIG_FROM_ENV', false));
if (!$useEnv && !is_file($configFile)) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "XEVION FaceMatch is not installed. Run: php cli/install.php\n");
        exit(1);
    }
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    if (env_platform()) {
        // Vercel: nothing to install by hand, but the database connection must be configured.
        echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Database not connected</title>'
           . '<body style="font-family:system-ui;background:#0C0727;color:#fff;display:grid;place-items:center;min-height:100vh;margin:0;padding:16px">'
           . '<div style="max-width:520px"><h1>Database not connected</h1>'
           . '<p>XEVION FaceMatch sets itself up automatically, but it needs a MySQL database. In Vercel, open '
           . '<b>Project → Settings → Environment Variables</b>, add the variables below, then redeploy.</p>'
           . '<ul style="line-height:1.8"><li><code>DATABASE_URL</code> = <code>mysql://user:password@host:3306/dbname</code><br>'
           . 'or <code>DB_HOST</code>, <code>DB_PORT</code>, <code>DB_NAME</code>, <code>DB_USER</code>, <code>DB_PASSWORD</code></li>'
           . '<li><code>ADMIN_EMAIL</code> and <code>ADMIN_PASSWORD</code> (12+ characters) for the admin account</li></ul>'
           . '<p style="opacity:.75">Tables are created on the first visit after that.</p></div>';
        exit;
    }
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    if (str_ends_with($base, '/admin')) { $base = substr($base, 0, -6); }
    echo '<!doctype html><meta charset="utf-8"><title>Setup required</title>'
       . '<body style="font-family:system-ui;background:#0C0727;color:#fff;display:grid;place-items:center;min-height:100vh;margin:0">'
       . '<div style="max-width:420px;text-align:center"><h1>Setup required</h1>'
       . '<p>XEVION FaceMatch has not been installed yet.</p>'
       . '<p><a style="color:#188CF1" href="' . htmlspecialchars($base, ENT_QUOTES) . '/install/">Open the installer</a></p></div>';
    exit;
}

$GLOBALS['APP_CONFIG'] = $useEnv ? env_config() : require $configFile;
unset($useEnv, $configFile);

require APP_ROOT . '/app/helpers.php';
require_once APP_ROOT . '/app/db.php';
require APP_ROOT . '/app/security.php';
require APP_ROOT . '/app/settings.php';
require APP_ROOT . '/app/content_filter.php';
require APP_ROOT . '/app/uploads.php';
require APP_ROOT . '/app/competitions.php';
require APP_ROOT . '/app/voting.php';
require APP_ROOT . '/app/ads.php';
require APP_ROOT . '/app/audit.php';
require APP_ROOT . '/app/auto_setup.php';

$isDebug = (config('env') === 'development');
ini_set('display_errors', $isDebug ? '1' : '0');
ini_set('log_errors', '1');
if (config('log', 'file') !== 'stderr') {
    ini_set('error_log', storage_path('logs/php-errors.log'));
}
error_reporting(E_ALL);

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED, E_NOTICE, E_USER_NOTICE], true)) {
        return false; // logged by PHP, never fatal (keeps future PHP versions working)
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $e): void {
    app_log('error', get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e . "\n");
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    // Environment-configured deployments (Vercel): say clearly when the database is unreachable.
    if (config('from_env') && $e instanceof RuntimeException && $e->getMessage() === 'Database unavailable' && !is_api_request()) {
        http_response_code(503);
        header('Cache-Control: no-store');
        echo '<!doctype html><meta charset="utf-8"><title>Database unavailable</title>'
           . '<body style="font-family:system-ui;background:#0C0727;color:#fff;display:grid;place-items:center;min-height:100vh;margin:0;padding:16px">'
           . '<div style="max-width:480px"><h1>Cannot reach the database</h1>'
           . '<p>Check the database environment variables in Vercel (host, port, name, user, password) and that the database '
           . 'accepts connections from the internet. Set <code>DB_SSL=false</code> only if your database has no TLS. '
           . 'Details are in the Vercel function logs.</p></div>';
        return;
    }
    if (config('env') === 'development') {
        echo '<pre style="white-space:pre-wrap">' . e((string)$e) . '</pre>';
        return;
    }
    if (is_api_request()) {
        json_out(['ok' => false, 'error' => 'server_error', 'message' => 'Something went wrong. Please try again.'], 500);
    }
    render_error(500);
});

// Vercel and other environment-configured hosts set themselves up on first use.
if (config('from_env')) {
    auto_setup_boot();
}
