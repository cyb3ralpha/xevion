<?php
/** Shared CLI helpers. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function cli_ask(string $q, string $default = ''): string
{
    $suffix = $default !== '' ? " [$default]" : '';
    fwrite(STDOUT, $q . $suffix . ': ');
    $a = trim((string)fgets(STDIN));
    return $a === '' ? $default : $a;
}

function cli_secret(string $q): string
{
    fwrite(STDOUT, $q . ': ');
    $tty = function_exists('posix_isatty') && posix_isatty(STDIN);
    if ($tty) {
        shell_exec('stty -echo');
    }
    $a = rtrim((string)fgets(STDIN), "\r\n");
    if ($tty) {
        shell_exec('stty echo');
        fwrite(STDOUT, "\n");
    }
    return $a;
}

/** Password from env var (for automation) or hidden prompt, entered twice. */
function cli_new_password(string $envVar = 'FM_ADMIN_PASSWORD'): string
{
    $env = getenv($envVar);
    if (is_string($env) && $env !== '') {
        if (strlen($env) < 12) {
            fwrite(STDERR, "$envVar must be at least 12 characters.\n");
            exit(1);
        }
        return $env;
    }
    for ($i = 0; $i < 3; $i++) {
        $a = cli_secret('New password (12+ characters)');
        $b = cli_secret('Repeat password');
        if ($a !== $b) {
            fwrite(STDOUT, "Passwords do not match.\n");
        } elseif (strlen($a) < 12) {
            fwrite(STDOUT, "Too short.\n");
        } else {
            return $a;
        }
    }
    fwrite(STDERR, "Giving up.\n");
    exit(1);
}
