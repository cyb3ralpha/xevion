<?php
/** Admin without a database: setup instructions, sign-in, dashboard. See app/admin_lite.php. */
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title><?= $view === 'dashboard' ? 'Dashboard' : ($view === 'login' ? 'Sign in' : 'Set up admin') ?> | FaceMatch admin</title>
<link rel="icon" href="<?= e(brand_img('favicon-32.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body>
<?php partial('icons'); ?>
<?php if ($view !== 'dashboard'): ?>
<main class="admin-login">
  <?php if ($view === 'setup'): ?>
  <div class="card pad form-stack" style="max-width:560px">
    <img src="<?= e(brand_img('wordmark-dark-md.png')) ?>" alt="XEVION" width="220" height="77">
    <h1 style="font-size:1.4rem;margin:0">Set up the admin dashboard</h1>
    <p class="muted" style="margin:0">The dashboard is switched off until a sign-in is configured on the server. In Vercel, open <strong>Project → Settings → Environment Variables</strong>, add these, then redeploy:</p>
    <ul class="trust trust-stack">
      <li><?= icon('shield') ?> <span><code>ADMIN_EMAIL</code> — your email address</span></li>
      <li><?= icon('lock') ?> <span><code>ADMIN_PASSWORD</code> — at least 12 characters (or <code>ADMIN_PASSWORD_HASH</code>, a bcrypt hash)</span></li>
      <li><?= icon('sparkle') ?> <span>Optional: <code>APP_SECRET</code>, <code>CONTACT_EMAIL</code>, <code>BLOCKED_MATCHES</code></span></li>
    </ul>
    <a class="btn btn-ghost" href="<?= e(url('/')) ?>"><?= icon('home') ?> Back to the site</a>
  </div>
  <?php else: ?>
  <form method="post" action="<?= e(url('/admin/login')) ?>" class="card pad form-stack">
    <img src="<?= e(brand_img('wordmark-dark-md.png')) ?>" alt="XEVION" width="220" height="77">
    <h1 style="font-size:1.4rem;margin:0">FaceMatch admin</h1>
    <?php if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label class="field"><span>Email</span><input type="email" name="email" required autocomplete="username" value="<?= e($email) ?>" autofocus></label>
    <label class="field"><span>Password</span><input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn-primary" type="submit">Sign in</button>
  </form>
  <?php endif; ?>
</main>
<?php else: ?>
<div class="lite-admin" data-admin-lite data-blocked="<?= e(json_encode($blocked)) ?>">
  <header class="lite-top">
    <a class="brand" href="<?= e(url('/admin/')) ?>"><img src="<?= e(brand_img('mark-dark-sm.png')) ?>" alt="" width="28" height="34"><span class="brand-product">FaceMatch<small>Admin</small></span></a>
    <nav class="lite-tabs" aria-label="Admin sections">
      <a href="#status"><?= icon('chart') ?> Status</a>
      <a href="#moderation"><?= icon('shield') ?> Moderation</a>
      <a href="#matches"><?= icon('image') ?> Matches in this browser</a>
    </nav>
    <form method="post" action="<?= e(url('/admin/logout')) ?>"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><button class="btn btn-ghost btn-sm" type="submit">Sign out</button></form>
  </header>

  <main class="container lite-main">
    <div class="notice notice-info"><strong>This site runs without a database.</strong> Matches, photos and votes live in each visitor’s browser and inside share links, so there is no central list of matches to manage. What you can do here is below; everything else needs a database (see docs/VERCEL.md).</div>

    <section class="card pad" id="status">
      <h2><?= icon('chart') ?> Status</h2>
      <p class="muted small">Signed in as <?= e($admin) ?>. Values come from this deployment.</p>
      <table class="tbl"><tbody>
        <?php foreach ($status as $k => $v): ?><tr><th scope="row"><?= e($k) ?></th><td><?= e($v) ?></td></tr><?php endforeach; ?>
      </tbody></table>
    </section>

    <section class="card pad" id="moderation">
      <h2><?= icon('shield') ?> Moderation</h2>
      <p class="muted">Paste a share link someone reported. It is opened here in your browser only, so you can see what it contains and get its match ID.</p>
      <form class="form-stack" data-lite-inspect>
        <label class="field"><span>Share link</span><input type="url" name="link" required placeholder="https://www.xevion.pk/s/… or …/play#s=…"></label>
        <label class="field"><span>Access code <span class="hint">Only for private links</span></span><input type="text" name="code" autocomplete="off"></label>
        <div class="btn-row"><button class="btn btn-primary btn-sm" type="submit"><?= icon('search') ?> Inspect</button></div>
      </form>
      <div data-lite-result class="lite-result" hidden></div>
      <h3 style="margin-top:24px">Blocked matches</h3>
      <p class="muted small">To block a match, add its ID (for short links, the code after <code>/s/</code>) to the <code>BLOCKED_MATCHES</code> environment variable in Vercel (IDs separated by commas) and redeploy. Blocked matches no longer open on this site and disappear from visitors’ lists. Copies already saved on someone’s device can’t be deleted remotely.</p>
      <?php if ($blocked): ?>
        <ul class="lite-list"><?php foreach ($blocked as $id): ?><li><code><?= e($id) ?></code></li><?php endforeach; ?></ul>
      <?php else: ?><p class="muted small">No matches are blocked.</p><?php endif; ?>
    </section>

    <section class="card pad" id="matches">
      <h2><?= icon('image') ?> Matches in this browser</h2>
      <p class="muted">These are the matches saved in the browser you are using now. You can delete them, or download a backup file and restore it later or on another computer.</p>
      <div class="btn-row" style="margin-bottom:14px">
        <button class="btn btn-ghost btn-sm" type="button" data-lite-export><?= icon('arrow') ?> Download backup</button>
        <label class="btn btn-ghost btn-sm" style="cursor:pointer"><?= icon('plus') ?> Restore backup<input type="file" accept="application/json,.json" data-lite-import hidden></label>
        <span class="small muted" data-lite-note aria-live="polite"></span>
      </div>
      <div class="table-scroll"><table class="tbl"><thead><tr><th>Match</th><th>Contenders</th><th>Votes</th><th>ID</th><th></th></tr></thead><tbody data-lite-rows></tbody></table></div>
      <p class="muted small" data-lite-empty hidden>No matches are saved in this browser.</p>
    </section>
  </main>
</div>
<?php endif; ?>
</body>
</html>
