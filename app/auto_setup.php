<?php
/**
 * Zero-step setup for environment-configured hosts (Vercel).
 *
 * With only database variables set, the first request creates the tables, an
 * application secret (unless APP_SECRET is set) and, when ADMIN_EMAIL and
 * ADMIN_PASSWORD are set, the first super administrator. Later deploys that change
 * database/schema.sql apply the new tables automatically (bump SCHEMA_VERSION).
 */
declare(strict_types=1);

const SCHEMA_VERSION = '3';

function auto_setup_boot(): void
{
    $meta = auto_meta_cached();
    if ($meta === null || ($meta['schema_version'] ?? '') !== SCHEMA_VERSION || empty($meta['app_secret'])) {
        auto_setup_run();
        $meta = auto_meta_load() ?? [];
        auto_meta_cache($meta);
    }
    if (strlen((string)config('app_secret', '')) < 32 && !empty($meta['app_secret'])) {
        $GLOBALS['APP_CONFIG']['app_secret'] = (string)$meta['app_secret'];
    }
}

/** @return array<string,string>|null null when the table does not exist yet */
function auto_meta_load(): ?array
{
    try {
        $rows = q_all('SELECT mkey, mvalue FROM app_meta');
    } catch (PDOException $e) {
        if (($e->errorInfo[0] ?? '') === '42S02') {
            return null;
        }
        throw $e;
    }
    return array_column($rows, 'mvalue', 'mkey');
}

/** APCu (available on Vercel's PHP) saves one query per request on a warm instance. */
function auto_meta_cached(): ?array
{
    if (function_exists('apcu_enabled') && apcu_enabled()) {
        $v = apcu_fetch('xfm_meta_' . SCHEMA_VERSION, $hit);
        if ($hit && is_array($v)) {
            return $v;
        }
    }
    $meta = auto_meta_load();
    if ($meta !== null) {
        auto_meta_cache($meta);
    }
    return $meta;
}

function auto_meta_cache(array $meta): void
{
    if (function_exists('apcu_enabled') && apcu_enabled() && ($meta['schema_version'] ?? '') === SCHEMA_VERSION) {
        apcu_store('xfm_meta_' . SCHEMA_VERSION, $meta, 300);
    }
}

function auto_setup_run(): void
{
    require_once APP_ROOT . '/app/installer.php';
    $pdo = db();
    $locked = false;
    try {
        $locked = (int)$pdo->query("SELECT GET_LOCK('xevion_facematch_setup', 15)")->fetchColumn() === 1;
    } catch (Throwable) {
        // Some MySQL-compatible services lack GET_LOCK; every step below is idempotent anyway.
    }
    try {
        installer_apply_schema($pdo);
        // INSERT IGNORE keeps the first secret if two instances race; never overwrite it.
        q('INSERT IGNORE INTO app_meta (mkey, mvalue) VALUES (?, ?)', ['app_secret', bin2hex(random_bytes(32))]);
        q('INSERT INTO app_meta (mkey, mvalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE mvalue = VALUES(mvalue)', ['schema_version', SCHEMA_VERSION]);
        ensure_env_admin();
        app_log('info', 'Automatic setup applied (schema ' . SCHEMA_VERSION . ')');
    } finally {
        if ($locked) {
            $pdo->query("SELECT RELEASE_LOCK('xevion_facematch_setup')");
        }
    }
}

/**
 * Create the first super administrator from ADMIN_EMAIL / ADMIN_PASSWORD when none exists.
 * Never changes an existing account, so later password changes in the dashboard stick.
 */
function ensure_env_admin(): void
{
    $email = strtolower(trim(env_str('ADMIN_EMAIL')));
    $password = env_str('ADMIN_PASSWORD');
    if ($email === '' || $password === '') {
        return;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
        app_log('warning', 'ADMIN_EMAIL must be valid and ADMIN_PASSWORD at least 12 characters; no administrator created');
        return;
    }
    if ((int)q_val("SELECT COUNT(*) FROM admins WHERE role = 'super_admin'") > 0) {
        return;
    }
    try {
        q("INSERT INTO admins (email, name, password_hash, role, is_active, password_changed_at)
           VALUES (?, ?, ?, 'super_admin', 1, UTC_TIMESTAMP())",
            [$email, env_str('ADMIN_NAME', 'Administrator'), password_hash($password, PASSWORD_DEFAULT)]);
        app_log('info', 'Super administrator created from ADMIN_EMAIL');
    } catch (PDOException $e) {
        if (!is_duplicate_key($e)) {
            throw $e;
        }
    }
}
