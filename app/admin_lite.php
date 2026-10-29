<?php
/**
 * Admin dashboard when the site runs without a database.
 *
 * There is no stored data to manage on the server, so this dashboard is honest about
 * scope: deployment status, inspecting and blocking shared matches, and managing the
 * matches saved in the admin's own browser.
 *
 * Sign-in: ADMIN_EMAIL plus ADMIN_PASSWORD_HASH (bcrypt, preferred) or ADMIN_PASSWORD,
 * set as Vercel environment variables. Sessions are a signed, expiring cookie because
 * serverless instances share no session storage. Forms use a double-submit CSRF token
 * and an Origin check.
 */
declare(strict_types=1);

const LITE_COOKIE = 'fm_adm';
const LITE_CSRF = 'fm_adm_csrf';
const LITE_TTL = 8 * 3600;

function lite_admin_configured(): bool
{
    return filter_var(env_value('ADMIN_EMAIL'), FILTER_VALIDATE_EMAIL) !== false
        && (env_value('ADMIN_PASSWORD_HASH') !== '' || strlen(env_value('ADMIN_PASSWORD')) >= 12);
}

/** Signing key: APP_SECRET if set, otherwise derived from the admin credentials (rotates with them). */
function lite_key(): string
{
    $s = env_value('APP_SECRET');
    if (strlen($s) >= 32) {
        return hash('sha256', 'lite-admin|' . $s, true);
    }
    return hash('sha256', 'lite-admin|' . env_value('ADMIN_EMAIL') . '|' . env_value('ADMIN_PASSWORD_HASH') . '|' . env_value('ADMIN_PASSWORD'), true);
}

function lite_b64(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

function lite_unb64(string $s): string
{
    return (string)base64_decode(strtr($s, '-_', '+/'), true);
}

function lite_cookie_opts(int $expires): array
{
    return ['expires' => $expires, 'path' => base_path() . '/admin', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Strict'];
}

/** The signed-in admin's email, or null. */
function lite_current(): ?string
{
    $raw = (string)($_COOKIE[LITE_COOKIE] ?? '');
    if (!lite_admin_configured() || !preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $raw)) {
        return null;
    }
    [$body, $sig] = explode('.', $raw, 2);
    if (!hash_equals(lite_b64(hash_hmac('sha256', $body, lite_key(), true)), $sig)) {
        return null;
    }
    $data = json_decode(lite_unb64($body), true);
    if (!is_array($data) || ($data['x'] ?? 0) < time() || !hash_equals(strtolower(env_value('ADMIN_EMAIL')), (string)($data['e'] ?? ''))) {
        return null;
    }
    return (string)$data['e'];
}

function lite_sign_in(string $email): void
{
    $body = lite_b64((string)json_encode(['e' => strtolower($email), 'x' => time() + LITE_TTL, 'n' => bin2hex(random_bytes(8))]));
    setcookie(LITE_COOKIE, $body . '.' . lite_b64(hash_hmac('sha256', $body, lite_key(), true)), lite_cookie_opts(time() + LITE_TTL));
}

function lite_csrf_token(): string
{
    static $t = null;
    if ($t === null) {
        $t = (string)($_COOKIE[LITE_CSRF] ?? '');
        if (!preg_match('/^[A-Za-z0-9_-]{32,64}$/', $t)) {
            $t = random_token(24);
            setcookie(LITE_CSRF, $t, lite_cookie_opts(0));
        }
    }
    return $t;
}

/** Double-submit token plus same-origin check for every POST. */
function lite_post_ok(): bool
{
    $cookie = (string)($_COOKIE[LITE_CSRF] ?? '');
    $sent = (string)($_POST['_csrf'] ?? '');
    if ($cookie === '' || !hash_equals($cookie, $sent)) {
        return false;
    }
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== '') {
        // Compare host and port: "Origin: http://localhost:8181" must match "Host: localhost:8181".
        $o = parse_url($origin);
        $authority = strtolower(($o['host'] ?? '') . (isset($o['port']) ? ':' . $o['port'] : ''));
        if ($authority === '' || $authority !== strtolower((string)($_SERVER['HTTP_HOST'] ?? ''))) {
            return false;
        }
    }
    return true;
}

function lite_password_ok(string $password): bool
{
    $ok = false;
    foreach (['ADMIN_PASSWORD_HASH', 'ADMIN_PASSWORD'] as $var) {
        $stored = env_value($var);
        if ($stored === '') {
            continue;
        }
        if (password_get_info($stored)['algo'] !== null) {
            $ok = password_verify($password, $stored) || $ok;          // a real bcrypt/argon hash
        } elseif (strlen($stored) >= 12) {
            // A plain password, also when it was put in ADMIN_PASSWORD_HASH by mistake.
            $ok = hash_equals(hash('sha256', $stored), hash('sha256', $password)) || $ok;
        }
    }
    return $ok;
}

function lite_render(string $view, array $vars = []): void
{
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    header('Referrer-Policy: same-origin');
    extract($vars, EXTR_SKIP);
    require APP_ROOT . '/templates/admin/lite.php';
    exit;
}

/** Entry point from admin/index.php when db_enabled() is false. */
function admin_lite(string $route): void
{
    send_security_headers(true);
    if (!lite_admin_configured()) {
        http_response_code(200);
        lite_render('setup');
    }
    if ($route === 'logout') {
        if (is_post() && lite_post_ok()) {
            setcookie(LITE_COOKIE, '', lite_cookie_opts(time() - 3600));
        }
        redirect('/admin/login');
    }
    if ($route === 'login') {
        $error = '';
        $email = '';
        if (is_post()) {
            $email = strtolower(str_input('email', 190));
            if (!lite_post_ok()) {
                $error = 'Your session expired. Reload the page and try again.';
            } elseif (hash_equals(strtolower(env_value('ADMIN_EMAIL')), $email) & lite_password_ok((string)($_POST['password'] ?? ''))) {
                lite_sign_in($email);
                redirect('/admin/');
            } else {
                usleep(random_int(600000, 1100000)); // slows guessing; there is no shared store for lockouts
                $error = 'That email and password don’t match. They must be exactly ADMIN_EMAIL and ADMIN_PASSWORD from Vercel’s environment variables, and Vercel only uses changed values after a redeploy.';
            }
        } elseif (lite_current()) {
            redirect('/admin/');
        }
        lite_render('login', ['error' => $error, 'email' => $email, 'csrf' => lite_csrf_token()]);
    }
    if ($route !== '') {
        render_error(404);
    }
    $admin = lite_current();
    if (!$admin) {
        redirect('/admin/login');
    }
    $status = [
        'Storage' => 'None on the server — matches live in visitors’ browsers',
        'Environment' => env_value('VERCEL_ENV', PHP_SAPI === 'cli-server' ? 'local (built-in server)' : 'local'),
        'Deployed commit' => ($sha = env_value('VERCEL_GIT_COMMIT_SHA')) !== '' ? substr($sha, 0, 7) . ' — ' . env_value('VERCEL_GIT_COMMIT_MESSAGE', '') : 'not on Vercel',
        'Region' => env_value('VERCEL_REGION', '—'),
        'PHP' => PHP_VERSION,
        'HTTPS' => is_https() ? 'Yes' : 'No',
        'Contact email' => site_contact_email() !== '' ? site_contact_email() : 'Not set (CONTACT_EMAIL)',
        'Short links' => env_value('BLOB_READ_WRITE_TOKEN') !== '' ? 'On (Vercel Blob connected)' : 'Off — connect a Vercel Blob store to get /s/… links',
        'Blocked matches' => (string)count(blocked_matches()),
        'Signing key' => strlen(env_value('APP_SECRET')) >= 32 ? 'APP_SECRET' : 'Derived from the admin password (set APP_SECRET to keep sessions across password changes)',
    ];
    lite_render('dashboard', ['admin' => $admin, 'status' => $status, 'blocked' => blocked_matches(), 'csrf' => lite_csrf_token()]);
}
