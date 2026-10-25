<?php
/**
 * Installation routines shared by install/index.php (web) and cli/install.php.
 * Works before config/config.php exists, so it does not use the normal bootstrap.
 */
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

function installer_is_locked(): bool
{
    return is_file(APP_ROOT . '/config/config.php') && is_file(APP_ROOT . '/storage/installed.lock');
}

function installer_requirements(): array
{
    $checks = [
        'PHP 8.1 or newer'             => version_compare(PHP_VERSION, '8.1.0', '>='),
        'PDO MySQL extension'          => extension_loaded('pdo_mysql'),
        'GD extension (image processing)' => extension_loaded('gd'),
        'Fileinfo extension'           => extension_loaded('fileinfo'),
        'Sodium extension'             => extension_loaded('sodium'),
        'Mbstring extension'           => extension_loaded('mbstring'),
        'config/ folder is writable'   => is_writable(APP_ROOT . '/config'),
        'storage/ folder is writable'  => is_writable(APP_ROOT . '/storage'),
    ];
    $checks['WebP output (optional, falls back to JPEG)'] = function_exists('imagewebp') ? true : null;
    return $checks;
}

function installer_setup_key(): string
{
    $file = APP_ROOT . '/storage/setup-key.txt';
    if (!is_file($file)) {
        file_put_contents($file, rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=') . "\n", LOCK_EX);
        @chmod($file, 0640);
    }
    return trim((string)file_get_contents($file));
}

function installer_connect(array $db): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], (int)$db['port'], $db['name']);
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}

function installer_apply_schema(PDO $pdo): void
{
    $sql = (string)file_get_contents(APP_ROOT . '/database/schema.sql');
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? '';
    foreach (preg_split('/;\s*(\r?\n|$)/', $sql) ?: [] as $stmt) {
        if (trim($stmt) !== '') {
            $pdo->exec($stmt);
        }
    }
}

/**
 * @param array{base_url:string, db:array, admin_name:string, admin_email:string, admin_password:string, contact_email?:string} $in
 * @return list<string> errors (empty on success)
 */
function installer_run(array $in): array
{
    $errors = [];
    $base = rtrim(trim($in['base_url']), '/');
    if (!preg_match('#^https?://[^\s/]+(/[^\s]*)?$#i', $base)) {
        $errors[] = 'Site URL must look like https://example.com or http://localhost/facematch';
    }
    if (!filter_var($in['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Administrator email is not valid.';
    }
    if (mb_strlen(trim($in['admin_name'])) < 2) {
        $errors[] = 'Administrator name is required.';
    }
    if (strlen($in['admin_password']) < 12) {
        $errors[] = 'Administrator password must be at least 12 characters.';
    }
    if ($errors) {
        return $errors;
    }
    try {
        $pdo = installer_connect($in['db']);
    } catch (PDOException $e) {
        return ['Could not connect to the database. Check host, port, database name, user and password. (MySQL error ' . ($e->errorInfo[1] ?? $e->getCode()) . ')'];
    }
    try {
        installer_apply_schema($pdo);
        $existing = (int)$pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'super_admin'")->fetchColumn();
        if ($existing === 0) {
            $st = $pdo->prepare("INSERT INTO admins (email, name, password_hash, role, is_active, password_changed_at)
                                 VALUES (?, ?, ?, 'super_admin', 1, UTC_TIMESTAMP())");
            $st->execute([strtolower(trim($in['admin_email'])), trim($in['admin_name']), password_hash($in['admin_password'], PASSWORD_DEFAULT)]);
        }
        if (!empty($in['contact_email']) && filter_var($in['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)')
                ->execute(['contact_email', $in['contact_email']]);
        }
    } catch (PDOException $e) {
        return ['Database setup failed: ' . $e->getMessage()];
    }

    $config = [
        'env'          => 'production',
        'base_url'     => $base,
        'app_secret'   => bin2hex(random_bytes(32)),
        'trust_proxy'  => false,
        'storage_path' => APP_ROOT . '/storage',
        'db'           => [
            'host' => $in['db']['host'], 'port' => (int)$in['db']['port'], 'name' => $in['db']['name'],
            'user' => $in['db']['user'], 'pass' => $in['db']['pass'],
        ],
    ];
    if (is_file(APP_ROOT . '/config/config.php')) {
        // Keep the existing secret so already-issued links and hashes stay valid.
        $old = require APP_ROOT . '/config/config.php';
        if (!empty($old['app_secret'])) {
            $config['app_secret'] = $old['app_secret'];
        }
    }
    $php = "<?php\n// Generated by the XEVION FaceMatch installer on " . gmdate('Y-m-d H:i') . " UTC.\n"
         . "// Keep this file private. Never commit it to version control.\nreturn " . var_export($config, true) . ";\n";
    if (file_put_contents(APP_ROOT . '/config/config.php', $php, LOCK_EX) === false) {
        return ['Could not write config/config.php — check folder permissions.'];
    }
    @chmod(APP_ROOT . '/config/config.php', 0640);
    foreach (['media', 'logs', 'sessions'] as $d) {
        if (!is_dir(APP_ROOT . '/storage/' . $d)) {
            @mkdir(APP_ROOT . '/storage/' . $d, 0750, true);
        }
    }
    file_put_contents(APP_ROOT . '/storage/installed.lock', gmdate('c') . "\n");
    @unlink(APP_ROOT . '/storage/setup-key.txt');
    return [];
}
