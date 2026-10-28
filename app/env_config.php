<?php
/**
 * Configuration from environment variables, for hosts without a writable
 * config/config.php (Vercel and other serverless platforms).
 *
 * Used when config/config.php does not exist, or when APP_CONFIG_FROM_ENV=1.
 * See docs/VERCEL.md for the full list of variables.
 */
declare(strict_types=1);

function env_str(string $key, string $default = ''): string
{
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : (string)$v;
}

function env_bool(string $key, bool $default): bool
{
    $v = getenv($key);
    if ($v === false || $v === '') {
        return $default;
    }
    return in_array(strtolower((string)$v), ['1', 'true', 'yes', 'on'], true);
}

/** First non-empty variable among $keys (accepts the names used by common MySQL providers). */
function env_first(array $keys, string $default = ''): string
{
    foreach ($keys as $k) {
        if (($v = env_str($k)) !== '') {
            return $v;
        }
    }
    return $default;
}

const ENV_DB_URL  = ['DATABASE_URL', 'MYSQL_URL', 'MYSQL_PUBLIC_URL', 'TIDB_DATABASE_URL'];
const ENV_DB_HOST = ['DB_HOST', 'MYSQL_HOST', 'MYSQLHOST', 'TIDB_HOST'];

function env_config_available(): bool
{
    return env_first(ENV_DB_URL) !== '' || env_first(ENV_DB_HOST) !== '';
}

/** True on Vercel (or when forced), even before any database variable is set. */
function env_platform(): bool
{
    return env_str('VERCEL') !== '' || env_bool('APP_CONFIG_FROM_ENV', false);
}

function env_config(): array
{
    $onVercel = env_str('VERCEL') !== '';
    $db = [
        'host' => env_first(ENV_DB_HOST, 'localhost'),
        'port' => (int)env_first(['DB_PORT', 'MYSQL_PORT', 'MYSQLPORT', 'TIDB_PORT'], '3306'),
        'name' => env_first(['DB_NAME', 'DB_DATABASE', 'MYSQL_DATABASE', 'MYSQLDATABASE', 'TIDB_DATABASE']),
        'user' => env_first(['DB_USER', 'DB_USERNAME', 'MYSQL_USER', 'MYSQLUSER', 'TIDB_USER']),
        'pass' => env_first(['DB_PASSWORD', 'DB_PASS', 'MYSQL_PASSWORD', 'MYSQLPASSWORD', 'TIDB_PASSWORD']),
    ];
    // A connection URL (mysql://user:pass@host:port/dbname) overrides the separate variables.
    if (($u = env_first(ENV_DB_URL)) !== '' && ($p = parse_url($u)) && isset($p['host'])) {
        $db['host'] = $p['host'];
        $db['port'] = (int)($p['port'] ?? 3306);
        $db['name'] = ltrim(rawurldecode($p['path'] ?? ''), '/');
        $db['user'] = rawurldecode($p['user'] ?? '');
        $db['pass'] = rawurldecode($p['pass'] ?? '');
        parse_str($p['query'] ?? '', $qs);
        $sslMode = strtolower((string)($qs['ssl-mode'] ?? $qs['sslmode'] ?? $qs['ssl'] ?? ''));
        if ($sslMode !== '') {
            $db['ssl'] = !in_array($sslMode, ['disabled', 'disable', 'false', '0'], true);
        }
    }
    $db['ssl'] = env_bool('DB_SSL', $db['ssl'] ?? $onVercel);
    $db['ssl_ca'] = env_str('DB_SSL_CA');

    $base = env_str('BASE_URL');
    if ($base === '' && ($host = env_str('VERCEL_PROJECT_PRODUCTION_URL')) !== '') {
        $base = 'https://' . $host;
    }
    return [
        'env'            => env_str('APP_ENV', 'production'),
        'base_url'       => rtrim($base, '/'),
        'app_secret'     => env_str('APP_SECRET'),
        'trust_proxy'    => env_bool('TRUST_PROXY', $onVercel),
        'storage_path'   => APP_ROOT . '/storage',
        'from_env'       => true,
        // Serverless disks are temporary and not shared, so keep state in MySQL.
        'session_driver' => env_str('SESSION_DRIVER', $onVercel ? 'db' : 'files'),
        'media_driver'   => env_str('MEDIA_DRIVER', $onVercel ? 'db' : 'files'),
        'log'            => env_str('LOG_TARGET', $onVercel ? 'stderr' : 'file'),
        'admin_email'    => env_str('ADMIN_EMAIL'),
        'admin_password' => env_str('ADMIN_PASSWORD'),
        'admin_name'     => env_str('ADMIN_NAME', 'Administrator'),
        'cron_secret'    => env_str('CRON_SECRET'),
        'db'             => $db,
    ];
}
