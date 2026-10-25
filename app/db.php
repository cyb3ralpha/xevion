<?php
/**
 * PDO access. Every query in the application goes through prepared statements.
 */
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = config('db');
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $c['host'] ?? 'localhost',
        (int)($c['port'] ?? 3306),
        $c['name'] ?? ''
    );
    try {
        $pdo = new PDO($dsn, (string)($c['user'] ?? ''), (string)($c['pass'] ?? ''), db_options($c));
    } catch (PDOException $e) {
        // Do not leak host/user details to the visitor; log a generic line.
        app_log('error', 'Database connection failed (code ' . $e->getCode() . ')');
        throw new RuntimeException('Database unavailable');
    }
    $pdo->exec("SET time_zone = '+00:00'");
    $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
    return $pdo;
}

/** PDO options, including TLS for hosted MySQL (PlanetScale, TiDB Cloud, Aiven, …). */
function db_options(array $c): array
{
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ];
    if (!empty($c['ssl'])) {
        $ca = (string)($c['ssl_ca'] ?? '');
        if ($ca === '') {
            foreach (['/etc/pki/tls/certs/ca-bundle.crt', '/etc/ssl/certs/ca-certificates.crt', '/etc/ssl/cert.pem'] as $f) {
                if (is_readable($f)) {
                    $ca = $f;
                    break;
                }
            }
        }
        // PHP 8.4+ moved the MySQL constants to Pdo\Mysql.
        $mysql = class_exists('Pdo\Mysql') ? 'Pdo\Mysql::ATTR_SSL_' : 'PDO::MYSQL_ATTR_SSL_';
        if ($ca !== '') {
            $opts[constant($mysql . 'CA')] = $ca;
        }
        $opts[constant($mysql . 'VERIFY_SERVER_CERT')] = $ca !== '';
    }
    return $opts;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function q_one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function q_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function q_val(string $sql, array $params = []): mixed
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

function db_tx(callable $fn): mixed
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $fn($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function is_duplicate_key(PDOException $e): bool
{
    return ($e->errorInfo[1] ?? null) === 1062;
}

/** Build "?,?,?" for IN clauses. */
function placeholders(array $items): string
{
    return implode(',', array_fill(0, max(1, count($items)), '?'));
}
