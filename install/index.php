<?php
/**
 * One-time web installer. Disables itself permanently once installation succeeds
 * (config/config.php + storage/installed.lock present). Before that, it requires the
 * setup key written to storage/setup-key.txt, which only someone with file access
 * to the server can read — so a stranger cannot hijack a fresh deployment.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/installer.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

if (installer_is_locked()) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Not found</title><p>Not found.</p>';
    exit;
}

// In environment mode (Vercel) each request may hit a different instance, so the installer
// keeps no session: the secret SETUP_KEY in every POST already proves who is submitting.
$envMode = installer_env_mode();
if (!$envMode) {
    session_name('fm_install');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
    session_start();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/install/index.php')), '/\\');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$guessUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $basePath;

$reqs = installer_requirements();
$reqsOk = !in_array(false, $reqs, true);
$keyReady = $envMode ? installer_setup_key() !== '' : (is_writable(dirname(__DIR__) . '/storage') ? installer_setup_key() !== '' : false);
$errors = [];
$done = false;
$old = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reqsOk) {
    $attempts = $envMode ? 0 : ($_SESSION['attempts'] = ($_SESSION['attempts'] ?? 0) + 1);
    if ($attempts > 10) {
        $errors[] = 'Too many attempts. Close the browser and try again later.';
    } elseif (!$envMode && !hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        $errors[] = 'Session expired — reload the page.';
    } elseif (installer_setup_key() === '' || !hash_equals(installer_setup_key(), trim((string)($_POST['setup_key'] ?? '')))) {
        $errors[] = $envMode
            ? 'The setup key is incorrect. Enter the value of the SETUP_KEY environment variable.'
            : 'The setup key is incorrect. Open storage/setup-key.txt on your server and copy its contents.';
    } elseif (($_POST['admin_password'] ?? '') !== ($_POST['admin_password2'] ?? '')) {
        $errors[] = 'The two administrator passwords do not match.';
    } else {
        $errors = installer_run([
            'base_url' => $envMode ? (env_config()['base_url'] ?: $guessUrl) : (string)($_POST['base_url'] ?? ''),
            'db' => [
                'host' => trim((string)($_POST['db_host'] ?? 'localhost')),
                'port' => (int)($_POST['db_port'] ?? 3306),
                'name' => trim((string)($_POST['db_name'] ?? '')),
                'user' => trim((string)($_POST['db_user'] ?? '')),
                'pass' => (string)($_POST['db_pass'] ?? ''),
            ],
            'admin_name' => (string)($_POST['admin_name'] ?? ''),
            'admin_email' => (string)($_POST['admin_email'] ?? ''),
            'admin_password' => (string)($_POST['admin_password'] ?? ''),
            'contact_email' => (string)($_POST['contact_email'] ?? ''),
        ]);
        $done = !$errors;
        if ($done && !$envMode) {
            session_destroy();
        }
    }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Install · XEVION FaceMatch</title>
<link rel="icon" href="<?= $h($basePath) ?>/assets/img/brand/favicon-32.png">
<link rel="stylesheet" href="<?= $h($basePath) ?>/assets/css/app.css">
</head>
<body class="page-install">
<main class="container narrow install">
  <img class="install-logo" src="<?= $h($basePath) ?>/assets/img/brand/wordmark-dark-md.png" alt="XEVION — Your vision, our technology" width="360" height="126">
  <h1>Install FaceMatch</h1>
<?php if ($done): ?>
  <div class="notice notice-success"><strong>Installation complete.</strong> The installer is now disabled.</div>
  <p>Next steps:</p>
  <ol class="steps-list">
    <li>Sign in to the <a href="<?= $h($basePath) ?>/admin/">admin dashboard</a> with the account you just created.</li>
<?php if ($envMode): ?>
    <li>The daily maintenance job runs through Vercel Cron (see <code>vercel.json</code>).</li>
<?php else: ?>
    <li>Optionally delete the <code>install/</code> folder from the server.</li>
    <li>Add the maintenance cron job described in <code>docs/DEPLOYMENT.md</code>.</li>
<?php endif; ?>
  </ol>
  <p><a class="btn btn-primary" href="<?= $h($basePath) ?>/">Open the site</a></p>
<?php else: ?>
  <section class="card pad">
    <h2>Server check</h2>
    <ul class="checklist">
      <?php foreach ($reqs as $label => $ok): ?>
        <li class="<?= $ok === true ? 'ok' : ($ok === null ? 'warn' : 'fail') ?>"><?= $ok === true ? '✓' : ($ok === null ? '!' : '✗') ?> <?= $h($label) ?></li>
      <?php endforeach; ?>
    </ul>
    <?php if (!$reqsOk): ?><p class="notice notice-error">Fix the items marked ✗, then reload this page.</p><?php endif; ?>
  </section>

  <?php if ($errors): ?>
    <div class="notice notice-error" role="alert"><ul><?php foreach ($errors as $e): ?><li><?= $h($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <?php if ($reqsOk): ?>
  <form method="post" class="card pad form-stack" autocomplete="off">
<?php if ($envMode): ?>
    <h2>1 · Setup key</h2>
    <p class="muted">Enter the value of the <code>SETUP_KEY</code> environment variable you set for this deployment. Database settings are read from the environment, so only the administrator account is needed here.</p>
    <label class="field"><span>Setup key</span><input name="setup_key" type="password" required <?= $keyReady ? '' : 'disabled' ?>></label>
    <label class="field"><span>Public contact email (optional)</span><input type="email" name="contact_email" value="<?= $h($old['contact_email'] ?? '') ?>"></label>
<?php else: ?>
    <input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>">
    <h2>1 · Setup key</h2>
    <p class="muted">A one-time key was written to <code>storage/setup-key.txt</code> on your server. Open it with your hosting file manager or FTP and paste it here. This proves you control the server.</p>
    <label class="field"><span>Setup key</span><input name="setup_key" required <?= $keyReady ? '' : 'disabled' ?>></label>

    <h2>2 · Site</h2>
    <label class="field"><span>Site URL (no trailing slash)</span><input name="base_url" required value="<?= $h($old['base_url'] ?? $guessUrl) ?>"></label>
    <label class="field"><span>Public contact email (optional)</span><input type="email" name="contact_email" value="<?= $h($old['contact_email'] ?? '') ?>"></label>

    <h2>3 · Database</h2>
    <div class="grid-2">
      <label class="field"><span>Host</span><input name="db_host" required value="<?= $h($old['db_host'] ?? 'localhost') ?>"></label>
      <label class="field"><span>Port</span><input name="db_port" type="number" required value="<?= $h($old['db_port'] ?? '3306') ?>"></label>
    </div>
    <label class="field"><span>Database name (must already exist)</span><input name="db_name" required value="<?= $h($old['db_name'] ?? '') ?>"></label>
    <div class="grid-2">
      <label class="field"><span>User</span><input name="db_user" required value="<?= $h($old['db_user'] ?? '') ?>"></label>
      <label class="field"><span>Password</span><input name="db_pass" type="password"></label>
    </div>
<?php endif; ?>

    <h2><?= $envMode ? '2' : '4' ?> · Super administrator</h2>
    <label class="field"><span>Name</span><input name="admin_name" required value="<?= $h($old['admin_name'] ?? '') ?>"></label>
    <label class="field"><span>Email (used to sign in)</span><input name="admin_email" type="email" required value="<?= $h($old['admin_email'] ?? '') ?>"></label>
    <div class="grid-2">
      <label class="field"><span>Password (12+ characters)</span><input name="admin_password" type="password" minlength="12" required autocomplete="new-password"></label>
      <label class="field"><span>Repeat password</span><input name="admin_password2" type="password" minlength="12" required autocomplete="new-password"></label>
    </div>
    <button class="btn btn-primary btn-lg" type="submit">Install</button>
  </form>
  <?php endif; ?>
<?php endif; ?>
</main>
</body>
</html>
