<?php
/**
 * Create an administrator account.
 *   php cli/create-admin.php --email=a@b.c --name="Name" --role=moderator
 * Roles: super_admin, admin, moderator, ad_manager. Password via prompt or FM_ADMIN_PASSWORD.
 */
declare(strict_types=1);

require __DIR__ . '/_cli.php';
require dirname(__DIR__) . '/app/bootstrap.php';

$o = getopt('', ['email:', 'name:', 'role:']);
$email = strtolower(trim($o['email'] ?? cli_ask('Email')));
$name = trim($o['name'] ?? cli_ask('Name'));
$role = $o['role'] ?? cli_ask('Role (super_admin|admin|moderator|ad_manager)', 'admin');
if (!valid_email($email) || $name === '' || !in_array($role, ['super_admin', 'admin', 'moderator', 'ad_manager'], true)) {
    fwrite(STDERR, "Invalid email, name or role.\n");
    exit(1);
}
if (q_val('SELECT 1 FROM admins WHERE email = ?', [$email])) {
    fwrite(STDERR, "An administrator with that email already exists.\n");
    exit(1);
}
$pw = cli_new_password();
q('INSERT INTO admins (email, name, password_hash, role, password_changed_at) VALUES (?,?,?,?,UTC_TIMESTAMP())',
    [$email, $name, password_hash($pw, PASSWORD_DEFAULT), $role]);
audit('admin.create_cli', 'admin', (int)db()->lastInsertId(), ['email' => $email, 'role' => $role], null, 'cli');
echo "Created $role $email\n";
