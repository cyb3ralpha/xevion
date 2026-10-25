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

function env_config_available(): bool
{
    return env_str('DATABASE_URL') !== '' || env_str('DB_HOST') !== '';
}

function env_config(): array
{
    $onVercel = env_str('VERCEL') !== '';
    $db = [
        'host' => env_str('DB_HOST', 'localhost'),
        'port' => (int)env_str('DB_PORT', '3306'),
        'name' => env_str('DB_NAME', env_str('DB_DATABASE')),
        'user' => env_str('DB_USER', env_str('DB_USERNAME')),
        'pass' => env_str('DB_PASSWORD', env_str('DB_PASS')),
    ];
    // DATABASE_URL=mysql://user:pass@host:port/dbname overrides the separate variables.
    if (($u = env_str('DATABASE_URL')) !== '' && ($p = parse_url($u)) && isset($p['host'])) {
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
        'setup_key'      => env_str('SETUP_KEY'),
        'cron_secret'    => env_str('CRON_SECRET'),
        'db'             => $db,
    ];
}
