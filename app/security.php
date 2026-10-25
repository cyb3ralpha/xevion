<?php
/**
 * Sessions, CSRF, visitor identifiers, rate limiting, abuse blocks, security headers.
 *
 * Privacy model: raw IP addresses are never written to the database. Where an
 * IP-derived key is needed it is an HMAC keyed with the server secret
 * (see ip_hash()), and those keys are cleared after `ip_retention_days`.
 */
declare(strict_types=1);

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (config('trust_proxy') && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $first = trim(explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            $ip = $first;
        }
    }
    return $ip;
}

/** Keyed, non-reversible IP-derived key. IPv6 addresses are reduced to their /64. */
function ip_hash(): string
{
    static $h = null;
    if ($h !== null) {
        return $h;
    }
    $ip = client_ip();
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $packed = inet_pton($ip);
        $ip = $packed !== false ? bin2hex(substr($packed, 0, 8)) . '::/64' : $ip;
    }
    return $h = keyed_hash($ip, 'ip');
}

function cookie_options(int $expires = 0, ?string $path = null): array
{
    return [
        'expires'  => $expires,
        'path'     => $path ?? (base_path() === '' ? '/' : base_path() . '/'),
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

/** session_set_cookie_params() takes 'lifetime' instead of 'expires'. */
function session_cookie_options(array $o): array
{
    $o['lifetime'] = $o['expires'] ?? 0;
    unset($o['expires']);
    return $o;
}

function start_public_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '86400');
    $sessDir = storage_path('sessions');
    if (is_dir($sessDir) && is_writable($sessDir)) {
        session_save_path($sessDir);
    }
    session_name('fm_s');
    session_set_cookie_params(session_cookie_options(cookie_options(0)));
    session_start();
}

/**
 * Long-lived random browser identifier used only for "this browser already voted
 * on this pair" checks. It is not tied to any personal data. Stored hashed.
 */
function voter_hash(): string
{
    $raw = $_COOKIE['fm_v'] ?? '';
    if (!is_string($raw) || !preg_match('/^[A-Za-z0-9_-]{32,64}$/', $raw)) {
        $raw = random_token(24);
        if (!headers_sent()) {
            setcookie('fm_v', $raw, cookie_options(time() + 86400 * 365));
        }
        $_COOKIE['fm_v'] = $raw;
    }
    return keyed_hash($raw, 'voter');
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = random_token(32);
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return is_string($sent) && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $sent);
}

function csrf_require(): void
{
    if (!csrf_valid()) {
        if (is_api_request()) {
            json_out(['ok' => false, 'error' => 'csrf', 'message' => 'Your session expired. Reload the page and try again.'], 403);
        }
        render_error(403, 'Your session expired or the form was submitted from another site. Go back, reload the page and try again.');
    }
}

/**
 * Fixed-window rate limiter backed by MySQL. Returns true when the action is allowed.
 * Exceeding a limit by 5x places a temporary abuse block on the IP-derived key.
 */
function rate_limit(string $action, int $max, int $windowSeconds, ?string $subject = null, int $cost = 1): bool
{
    $subject ??= ip_hash();
    $bucket = intdiv(time(), $windowSeconds);
    $key = hash('sha256', $action . '|' . $subject . '|' . $windowSeconds . '|' . $bucket);
    $cost = max(1, $cost);
    q('INSERT INTO rate_limits (rkey, hits, window_start) VALUES (?, ?, UTC_TIMESTAMP())
       ON DUPLICATE KEY UPDATE hits = hits + ?', [$key, $cost, $cost]);
    $hits = (int)q_val('SELECT hits FROM rate_limits WHERE rkey = ?', [$key]);
    if ($hits > $max * 5 && $subject === ip_hash()) {
        abuse_block(ip_hash(), 'Automatic: excessive "' . $action . '" requests', 3600);
    }
    return $hits <= $max;
}

function rate_limit_or_fail(string $action, int $max, int $windowSeconds, ?string $subject = null): void
{
    if (!rate_limit($action, $max, $windowSeconds, $subject)) {
        if (is_api_request()) {
            json_out(['ok' => false, 'error' => 'rate_limited', 'message' => 'Too many requests. Please slow down and try again shortly.'], 429);
        }
        render_error(429);
    }
}

function abuse_block(string $ipHash, string $reason, int $seconds, ?int $adminId = null): void
{
    $exists = q_val('SELECT 1 FROM abuse_blocks WHERE ip_hash = ? AND expires_at > UTC_TIMESTAMP()', [$ipHash]);
    if ($exists) {
        return;
    }
    q('INSERT INTO abuse_blocks (ip_hash, reason, expires_at, created_by) VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND), ?)',
        [$ipHash, mb_substr($reason, 0, 255), $seconds, $adminId]);
}

function is_blocked(): bool
{
    return (bool)q_val('SELECT 1 FROM abuse_blocks WHERE ip_hash = ? AND expires_at > UTC_TIMESTAMP() LIMIT 1', [ip_hash()]);
}

function is_probable_bot(): bool
{
    $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua === '' || strlen($ua) < 12) {
        return true;
    }
    return (bool)preg_match('/bot|crawl|spider|slurp|preview|fetch|curl|wget|python|httpclient|headless|phantom|monitor|scan|lighthouse/', $ua);
}

function send_security_headers(bool $noindex = false): void
{
    if (headers_sent()) {
        return;
    }
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
    if ($noindex) {
        header('X-Robots-Tag: noindex, nofollow, noarchive');
    }
}

/**
 * Periodic maintenance: closes expired competitions, purges stale rate-limit rows
 * and clears IP-derived keys after the retention period. Called opportunistically
 * (≈2% of requests) and by cli/maintenance.php for cron.
 */
function run_housekeeping(): array
{
    $days = max(1, (int)setting('ip_retention_days', 30));
    $out = [];
    $out['closed'] = q("UPDATE competitions SET status = 'closed', closed_at = closes_at
                        WHERE status = 'active' AND closes_at <= UTC_TIMESTAMP()")->rowCount();
    $out['rate_limits'] = q('DELETE FROM rate_limits WHERE window_start < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY)')->rowCount();
    $out['blocks'] = q('DELETE FROM abuse_blocks WHERE expires_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)')->rowCount();
    $out['vote_ip'] = q('UPDATE votes SET ip_hash = NULL WHERE ip_hash IS NOT NULL AND created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)', [$days])->rowCount();
    q('UPDATE competitions SET creator_ip_hash = NULL WHERE creator_ip_hash IS NOT NULL AND created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)', [$days]);
    q('UPDATE reports SET reporter_ip_hash = NULL WHERE reporter_ip_hash IS NOT NULL AND created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)', [$days]);
    q('UPDATE contact_messages SET ip_hash = NULL WHERE ip_hash IS NOT NULL AND created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)', [$days]);
    q('UPDATE audit_log SET ip_hash = NULL WHERE ip_hash IS NOT NULL AND created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)', [max($days, 90)]);
    $out['ads_archived'] = q("UPDATE ad_campaigns SET status = 'archived' WHERE status IN ('active','paused') AND ends_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)")->rowCount();
    return $out;
}

function maybe_housekeeping(): void
{
    if (random_int(1, 50) === 1) {
        try {
            run_housekeeping();
        } catch (Throwable $e) {
            app_log('warning', 'Housekeeping failed: ' . $e->getMessage());
        }
    }
}
