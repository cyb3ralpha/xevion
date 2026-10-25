<?php
/**
 * Command-line installer (alternative to the web installer).
 *   php cli/install.php
 * Non-interactive: pass options and FM_ADMIN_PASSWORD / FM_DB_PASS environment variables:
 *   FM_DB_PASS=... FM_ADMIN_PASSWORD=... php cli/install.php --base-url=https://example.com \
 *     --db-host=localhost --db-name=facematch --db-user=fm --admin-email=you@example.com --admin-name="Your Name"
 */
declare(strict_types=1);

require __DIR__ . '/_cli.php';
require dirname(__DIR__) . '/app/installer.php';

if (installer_is_locked()) {
    fwrite(STDERR, "Already installed (storage/installed.lock exists). Delete it only if you really intend to re-run setup.\n");
    exit(1);
}
foreach (installer_requirements() as $label => $ok) {
    if ($ok === false) {
        fwrite(STDERR, "Requirement not met: $label\n");
        exit(1);
    }
}
$o = getopt('', ['base-url:', 'db-host:', 'db-port:', 'db-name:', 'db-user:', 'admin-email:', 'admin-name:', 'contact-email:']);
$in = [
    'base_url' => $o['base-url'] ?? cli_ask('Site URL', 'http://localhost'),
    'db' => [
        'host' => $o['db-host'] ?? cli_ask('Database host', 'localhost'),
        'port' => (int)($o['db-port'] ?? cli_ask('Database port', '3306')),
        'name' => $o['db-name'] ?? cli_ask('Database name', 'facematch'),
        'user' => $o['db-user'] ?? cli_ask('Database user'),
        'pass' => getenv('FM_DB_PASS') !== false ? (string)getenv('FM_DB_PASS') : cli_secret('Database password'),
    ],
    'admin_name' => $o['admin-name'] ?? cli_ask('Super admin name'),
    'admin_email' => $o['admin-email'] ?? cli_ask('Super admin email'),
    'contact_email' => $o['contact-email'] ?? '',
];
$in['admin_password'] = cli_new_password();
$errors = installer_run($in);
if ($errors) {
    fwrite(STDERR, "Installation failed:\n - " . implode("\n - ", $errors) . "\n");
    exit(1);
}
echo "Installed. Sign in at {$in['base_url']}/admin/\n";
