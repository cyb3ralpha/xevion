<?php
/**
 * Account recovery for administrators (requires shell access to the server).
 *   php cli/reset-password.php --email=admin@example.com [--activate]
 * Sets a new password, signs out existing sessions on next request, and optionally
 * re-activates a deactivated account. The action is written to the audit log.
 */
declare(strict_types=1);

require __DIR__ . '/_cli.php';
require dirname(__DIR__) . '/app/bootstrap.php';

$o = getopt('', ['email:', 'activate']);
$email = strtolower(trim($o['email'] ?? cli_ask('Administrator email')));
$admin = q_one('SELECT id, is_active FROM admins WHERE email = ?', [$email]);
if (!$admin) {
    fwrite(STDERR, "No administrator with that email.\n");
    exit(1);
}
$pw = cli_new_password();
q('UPDATE admins SET password_hash = ?, password_changed_at = UTC_TIMESTAMP(), must_change_password = 0' . (isset($o['activate']) ? ', is_active = 1' : '') . ' WHERE id = ?',
    [password_hash($pw, PASSWORD_DEFAULT), $admin['id']]);
audit('admin.password_reset_cli', 'admin', (int)$admin['id'], ['activated' => isset($o['activate'])], null, 'cli');
echo "Password updated for $email" . (isset($o['activate']) ? ' (account active)' : '') . "\n";
