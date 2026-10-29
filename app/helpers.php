<?php
/**
 * Small, dependency-free helpers shared by the public site, admin and CLI.
 */
declare(strict_types=1);

function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['APP_CONFIG'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function storage_path(string $sub = ''): string
{
    $base = rtrim((string)config('storage_path', APP_ROOT . '/storage'), '/');
    return $sub === '' ? $base : $base . '/' . ltrim($sub, '/');
}

function app_log(string $level, string $message): void
{
    if (config('log', 'file') === 'stderr') {
        // Serverless: the platform collects stderr (Vercel → Logs).
        @file_put_contents('php://stderr', sprintf("%s: %s\n", strtoupper($level), str_replace(["\r", "\n"], ' ', $message)));
        return;
    }
    $dir = storage_path('logs');
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    // Never log raw IPs, tokens or credentials.
    $line = sprintf("[%s] %s: %s\n", gmdate('Y-m-d H:i:s'), strtoupper($level), str_replace(["\r", "\n"], ' ', $message));
    @file_put_contents($dir . '/app.log', $line, FILE_APPEND | LOCK_EX);
}

/** HTML-escape for text and attribute contexts. */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_path(): string
{
    static $path = null;
    if ($path === null) {
        $path = rtrim((string)(parse_url((string)config('base_url', ''), PHP_URL_PATH) ?? ''), '/');
    }
    return $path;
}

/** Root-relative URL for an application path. */
function url(string $path = '/', array $query = []): string
{
    $u = base_path() . '/' . ltrim($path, '/');
    if ($u === '') {
        $u = '/';
    }
    $query = array_filter($query, static fn($v) => $v !== null && $v !== '');
    return $query ? $u . '?' . http_build_query($query) : $u;
}

/** Absolute URL (for share links). */
function abs_url(string $path = '/', array $query = []): string
{
    $origin = rtrim((string)config('base_url', ''), '/');
    $origin = preg_replace('#^(https?://[^/]+).*$#', '$1', $origin) ?: '';
    return $origin . url($path, $query);
}

/** URL of a static brand image in media/brand/ (cache-busted like asset()). */
function brand_img(string $file): string
{
    $path = APP_ROOT . '/media/brand/' . $file;
    $v = is_file($path) ? substr((string)filemtime($path), -6) : APP_VERSION;
    return url('/media/brand/' . $file) . '?v=' . $v;
}

/** Folder for photos uploaded on ordinary hosting (git-ignored, never web-accessible). */
function uploads_path(string $sub = ''): string
{
    $base = APP_ROOT . '/media/uploads';
    return $sub === '' ? $base : $base . '/' . ltrim($sub, '/');
}

function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? substr((string)filemtime($file), -6) : APP_VERSION;
    return url('/assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function redirect(string $to, int $code = 303): never
{
    if (!preg_match('#^(https?:)?//#', $to) && !str_starts_with($to, base_path() . '/') && $to !== base_path()) {
        $to = url($to);
    }
    header('Location: ' . $to, true, $code);
    exit;
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function is_post(): bool
{
    return request_method() === 'POST';
}

function input(string $key, mixed $default = ''): mixed
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function str_input(string $key, int $max = 1000): string
{
    $v = $_POST[$key] ?? $_GET[$key] ?? '';
    if (!is_string($v)) {
        return '';
    }
    $v = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '');
    return mb_substr($v, 0, $max);
}

function is_api_request(): bool
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return str_contains($uri, '/api/') || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (config('trust_proxy') && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
        return true;
    }
    return false;
}

function json_out(array $data, int $code = 200): never
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/** Cryptographically random URL-safe token. */
function random_token(int $bytes = 32): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

/** Random base62 identifier (used for public slugs / ids — never sequential). */
function random_id(int $length = 12): string
{
    $alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max = strlen($alphabet) - 1;
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

function uuid4(): string
{
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

/**
 * True when config/config.php names a database. Without one the site runs as a
 * showcase: public pages work, and features that must store data say so politely.
 */
function db_enabled(): bool
{
    return trim((string)config('db.host', '')) !== '' && trim((string)config('db.name', '')) !== '';
}

function app_secret(): string
{
    $s = (string)config('app_secret', '');
    if (strlen($s) >= 32) {
        return $s;
    }
    if (!db_enabled()) {
        // Nothing is stored without a database, so a per-install derived key is enough.
        return hash('sha256', 'xevion-showcase|' . APP_ROOT);
    }
    throw new RuntimeException('app_secret is missing or too short in config/config.php');
}

/** Keyed hash (HMAC-SHA256) — used for tokens at rest, IP-derived keys, voter ids. */
function keyed_hash(string $value, string $purpose = 'generic'): string
{
    return hash_hmac('sha256', $purpose . '|' . $value, app_secret());
}

/** Reversible encryption (libsodium secretbox) for values the creator must be able to see again. */
function encrypt_value(string $plain): string
{
    $key = sodium_crypto_generichash(app_secret() . '|enc', '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
}

function decrypt_value(?string $cipher): ?string
{
    if (!$cipher) {
        return null;
    }
    $raw = base64_decode($cipher, true);
    if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        return null;
    }
    $key = sodium_crypto_generichash(app_secret() . '|enc', '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $key);
    return $plain === false ? null : $plain;
}

function now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}

/** Render a UTC datetime as <time>; app.js localises it in the browser. */
function time_tag(?string $utc, string $style = 'datetime'): string
{
    if (!$utc) {
        return '<span class="muted">—</span>';
    }
    $ts = strtotime($utc . ' UTC');
    $iso = gmdate('Y-m-d\TH:i:s\Z', $ts);
    $fallback = gmdate($style === 'date' ? 'M j, Y' : 'M j, Y H:i', $ts) . ' UTC';
    return '<time datetime="' . e($iso) . '" data-local="' . e($style) . '">' . e($fallback) . '</time>';
}

function time_left(string $closesAt): string
{
    $diff = strtotime($closesAt . ' UTC') - time();
    if ($diff <= 0) {
        return 'Closed';
    }
    if ($diff < 3600) {
        return max(1, intdiv($diff, 60)) . ' min left';
    }
    if ($diff < 86400) {
        return intdiv($diff, 3600) . ' h left';
    }
    $d = intdiv($diff, 86400);
    return $d . ' day' . ($d === 1 ? '' : 's') . ' left';
}

function plural(int $n, string $one, ?string $many = null): string
{
    return number_format($n) . ' ' . ($n === 1 ? $one : ($many ?? $one . 's'));
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/** Render a template inside a layout. $layout = null renders the bare template. */
function render(string $template, array $vars = [], ?string $layout = 'layout'): void
{
    $vars['_template'] = $template;
    extract($vars, EXTR_SKIP);
    ob_start();
    require APP_ROOT . '/templates/' . $template . '.php';
    $content = ob_get_clean();
    if ($layout === null) {
        echo $content;
        return;
    }
    require APP_ROOT . '/templates/' . $layout . '.php';
}

function partial(string $name, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    require APP_ROOT . '/templates/partials/' . $name . '.php';
}

/** Standalone error page — no database access so it works during outages. */
function render_error(int $code, ?string $message = null): never
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        if ($code === 429) {
            header('Retry-After: 900');
        }
    }
    $errorCode = $code;
    $errorMessage = $message;
    require APP_ROOT . '/templates/errors/error.php';
    exit;
}

function not_found(): never
{
    render_error(404);
}

/** Inline icon from templates/partials/icons.php (decorative: hidden from screen readers). */
function icon(string $name, string $class = ''): string
{
    return '<svg class="icon' . ($class !== '' ? ' ' . e($class) : '') . '" aria-hidden="true" focusable="false"><use href="#i-' . e($name) . '"/></svg>';
}

/** Icon shown for each category. */
function category_icon(string $key): string
{
    return [
        'handsome' => 'crown', 'smile' => 'smile', 'smart' => 'bulb', 'funny' => 'laugh', 'stylish' => 'glasses',
        'fashion' => 'shirt', 'photogenic' => 'camera', 'creative' => 'palette', 'team' => 'trophy', 'custom' => 'sparkle',
    ][$key] ?? 'sparkle';
}

function categories(): array
{
    return [
        'handsome'   => ['label' => 'Handsome',         'prompt' => 'Who is the most handsome?'],
        'smile'      => ['label' => 'Smile',            'prompt' => 'Who has the best smile?'],
        'smart'      => ['label' => 'Smart',            'prompt' => 'Who looks the smartest?'],
        'funny'      => ['label' => 'Funny',            'prompt' => 'Who is the funniest?'],
        'stylish'    => ['label' => 'Stylish',          'prompt' => 'Who is the most stylish?'],
        'fashion'    => ['label' => 'Fashion',          'prompt' => 'Who has the best fashion sense?'],
        'photogenic' => ['label' => 'Photogenic',       'prompt' => 'Who is the most photogenic?'],
        'creative'   => ['label' => 'Creative',         'prompt' => 'Who is the most creative?'],
        'team'       => ['label' => 'Team Competition', 'prompt' => 'Which team has the best style?'],
        'custom'     => ['label' => 'Custom Category',  'prompt' => 'Pick a winner'],
    ];
}

function category_label(array $c): string
{
    if (($c['category'] ?? '') === 'custom' && !empty($c['custom_category'])) {
        return (string)$c['custom_category'];
    }
    return categories()[$c['category'] ?? '']['label'] ?? 'Other';
}

function valid_email(string $email): bool
{
    return strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Accept only absolute http(s) URLs without credentials or control characters.
 * Rejects javascript:, data:, vbscript:, file: and protocol-relative URLs.
 */
function safe_external_url(string $u): ?string
{
    $u = trim($u);
    if ($u === '' || strlen($u) > 500 || preg_match('/[\s\x00-\x1F\x7F<>"\'`]/', $u)) {
        return null;
    }
    $parts = parse_url($u);
    if (!$parts || !isset($parts['scheme'], $parts['host'])) {
        return null;
    }
    if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
        return null;
    }
    if (isset($parts['user']) || isset($parts['pass'])) {
        return null;
    }
    if (!preg_match('/^[a-z0-9.-]+$/i', $parts['host']) || !str_contains($parts['host'], '.')) {
        return null;
    }
    return filter_var($u, FILTER_VALIDATE_URL) !== false ? $u : null;
}
