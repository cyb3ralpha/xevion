<?php
/**
 * Administrator authentication and role-based permissions.
 * The admin area uses its own session cookie scoped to /admin/.
 */
declare(strict_types=1);

const ADMIN_IDLE_TIMEOUT = 1800;      // 30 minutes
const ADMIN_ABSOLUTE_TIMEOUT = 43200; // 12 hours

function admin_roles(): array
{
    return [
        'super_admin' => 'Super Administrator',
        'admin'       => 'Administrator',
        'moderator'   => 'Moderator',
        'ad_manager'  => 'Advertising Manager',
    ];
}

/** Permission matrix. Checked on every protected action, not just used to hide buttons. */
function admin_permissions(): array
{
    return [
        'super_admin' => ['*'],
        'admin'       => ['dashboard', 'competitions.view', 'competitions.manage', 'competitions.suspend', 'competitions.delete',
                          'moderation', 'reports', 'votes.void', 'messages', 'audit.view', 'blocks'],
        'moderator'   => ['dashboard', 'competitions.view', 'competitions.suspend', 'moderation', 'reports'],
        'ad_manager'  => ['dashboard', 'ads', 'ads.reports'],
    ];
}

function admin_can(string $perm, ?array $admin = null): bool
{
    $admin ??= admin_current();
    if (!$admin) {
        return false;
    }
    $perms = admin_permissions()[$admin['role']] ?? [];
    return in_array('*', $perms, true) || in_array($perm, $perms, true);
}

function admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    use_session_storage();
    session_name('fm_admin');
    $opts = cookie_options(0, base_path() . '/admin/');
    $opts['samesite'] = 'Strict';
    session_set_cookie_params(session_cookie_options($opts));
    session_start();
}

function admin_current(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $id = (int)($_SESSION['admin_id'] ?? 0);
    if ($id <= 0) {
        return $cache = null;
    }
    $now = time();
    if (($now - (int)($_SESSION['admin_seen'] ?? 0)) > ADMIN_IDLE_TIMEOUT || ($now - (int)($_SESSION['admin_login'] ?? 0)) > ADMIN_ABSOLUTE_TIMEOUT) {
        admin_logout('Your session expired. Please sign in again.');
        return $cache = null;
    }
    $a = q_one('SELECT id, email, name, role, is_active, must_change_password, password_changed_at FROM admins WHERE id = ?', [$id]);
    if (!$a || !(int)$a['is_active'] || (string)$a['password_changed_at'] !== (string)($_SESSION['admin_pwv'] ?? '')) {
        admin_logout('Please sign in again.');
        return $cache = null;
    }
    $_SESSION['admin_seen'] = $now;
    return $cache = $a;
}

/**
 * @return string|null error message, null on success
 */
function admin_attempt_login(string $email, string $password): ?string
{
    $email = strtolower(trim($email));
    $generic = 'Incorrect email or password.';
    if (!rate_limit('admin_login_ip', 10, 900) || !rate_limit('admin_login_acct', 6, 900, keyed_hash($email, 'login'))) {
        return 'Too many sign-in attempts. Wait 15 minutes and try again.';
    }
    $a = q_one('SELECT * FROM admins WHERE email = ?', [$email]);
    // Verify against a dummy hash when the account doesn't exist to keep timing similar.
    $hash = $a['password_hash'] ?? password_hash(random_token(16), PASSWORD_DEFAULT);
    $ok = password_verify($password, $hash);
    if (!$a || !$ok) {
        audit('admin.login_failed', 'admin', $a['id'] ?? null, [], null, 'system');
        return $generic;
    }
    if (!(int)$a['is_active']) {
        audit('admin.login_inactive', 'admin', $a['id'], [], (int)$a['id']);
        return 'This account is deactivated. Contact a super administrator.';
    }
    if (password_needs_rehash($a['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $a['id']]);
    }
    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION['admin_id'] = (int)$a['id'];
    $_SESSION['admin_login'] = time();
    $_SESSION['admin_seen'] = time();
    $_SESSION['admin_pwv'] = (string)$a['password_changed_at'];
    q('UPDATE admins SET last_login_at = UTC_TIMESTAMP() WHERE id = ?', [$a['id']]);
    audit('admin.login', 'admin', $a['id'], [], (int)$a['id']);
    return null;
}

function admin_logout(?string $message = null): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    if ($message) {
        flash('info', $message);
    }
}

function admin_url(string $path = '', array $query = []): string
{
    return url('/admin/' . ltrim($path, '/'), $query);
}

/** Gate a page or action. Unauthenticated → login; authenticated without permission → 403. */
function admin_require(?string $perm = null): array
{
    $a = admin_current();
    if (!$a) {
        if (is_post()) {
            render_error(403, 'Your admin session has ended. Sign in again.');
        }
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? admin_url();
        redirect(admin_url('login'));
    }
    if ((int)$a['must_change_password'] && !in_array(admin_route(), ['account', 'logout'], true)) {
        flash('info', 'Please choose a new password before continuing.');
        redirect(admin_url('account'));
    }
    if ($perm !== null && !admin_can($perm, $a)) {
        render_error(403, 'Your role does not have access to this area.');
    }
    return $a;
}

function admin_route(): string
{
    return (string)($GLOBALS['ADMIN_ROUTE'] ?? '');
}

function password_policy_error(string $pw): ?string
{
    if (strlen($pw) < 12) {
        return 'Password must be at least 12 characters.';
    }
    if (strlen($pw) > 200) {
        return 'Password is too long.';
    }
    if (preg_match('/^(.)\1+$/', $pw) || in_array(strtolower($pw), ['password1234', '123456789012', 'qwertyuiopas', 'adminadmin12'], true)) {
        return 'Choose a less predictable password.';
    }
    return null;
}
