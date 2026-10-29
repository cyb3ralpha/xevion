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
      <a href="#server"><?= icon('image') ?> Matches</a>
      <a href="#status"><?= icon('chart') ?> Status</a>
      <a href="#moderation"><?= icon('shield') ?> Moderation</a>
      <a href="#matches"><?= icon('lock') ?> This browser</a>
    </nav>
    <form method="post" action="<?= e(url('/admin/logout')) ?>"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><button class="btn btn-ghost btn-sm" type="submit">Sign out</button></form>
  </header>

  <main class="container lite-main">
    <?php if ($notice !== ''): [$kind, $msg] = array_pad(explode(':', $notice, 2), 2, ''); ?>
      <div class="notice notice-<?= $kind === 'ok' ? 'success' : 'error' ?>" role="status"><?= e($msg) ?></div>
    <?php endif; ?>

    <section class="card pad" id="server">
      <h2><?= icon('image') ?> Matches on the server</h2>
      <?php if ($server === null): ?>
        <p class="muted">Storage isn’t connected, so matches only live in visitors’ browsers. In Vercel open <strong>Storage → Create Database → Blob</strong> (Private), connect it to this project and redeploy.</p>
      <?php elseif (empty($server['ok'])): ?>
        <div class="notice notice-error">The match list could not be loaded (<?= e((string)($server['error'] ?? 'error')) ?>). If you just changed the admin variables, redeploy so the site and the storage function use the same values.</div>
      <?php else: ?>
        <p class="muted small"><?= (int)$server['total'] ?> stored · <?= (int)$server['publicCount'] ?> public · <?= (int)$server['total'] - (int)$server['publicCount'] ?> private. Private matches are encrypted with their access code, so only their ID and votes are visible here. Deleting removes the match, its photos and its votes for everyone.</p>
        <?php if (!$server['items']): ?><p class="muted">No matches yet.</p><?php else: ?>
        <div class="table-scroll"><table class="tbl lite-matches">
          <thead><tr><th>Match</th><th>Type</th><th class="num">Votes</th><th>Created</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($server['items'] as $it): $id = (string)($it['id'] ?? ''); if (!preg_match('/^[A-Za-z0-9]{10}$/', $id)) { continue; } ?>
            <tr>
              <td><div class="lite-match">
                <span class="lite-thumbs"><?php foreach (array_slice((array)($it['g'] ?? []), 0, 2) as $g): if (is_string($g) && preg_match('#^data:image/(jpeg|png|webp);base64,[A-Za-z0-9+/=]+$#', $g)): ?><img src="<?= e($g) ?>" alt="" width="36" height="45"><?php endif; endforeach; ?><?php if (empty($it['public'])): ?><span class="lite-lock"><?= icon('lock') ?></span><?php endif; ?></span>
                <span><strong><?= e(!empty($it['public']) ? ((string)$it['t'] ?: 'Untitled') : 'Private match') ?></strong><br><a href="<?= e(url('/s/' . $id)) ?>" target="_blank" rel="noopener"><code><?= e($id) ?></code></a><?= !empty($it['blocked']) ? ' <span class="pill">Blocked</span>' : '' ?></span>
              </div></td>
              <td><?= !empty($it['public']) ? 'Public' : 'Private' ?><?= isset($it['n']) && $it['n'] ? '<br><span class="muted small">' . (int)$it['n'] . ' photos</span>' : '' ?></td>
              <td class="num"><?= (int)$it['v'] ?><br><span class="muted small"><?= (int)$it['voters'] ?> voters</span></td>
              <td><time datetime="<?= e(gmdate('c', (int)(($it['at'] ?? 0) / 1000))) ?>" data-local><?= e(gmdate('j M Y, H:i', (int)(($it['at'] ?? 0) / 1000))) ?> UTC</time></td>
              <td><form method="post" action="<?= e(url('/admin/')) ?>" data-confirm="Delete this match, its photos and votes for everyone? This cannot be undone.">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="op" value="delete"><input type="hidden" name="id" value="<?= e($id) ?>"><input type="hidden" name="page" value="<?= (int)$page ?>">
                <button class="btn btn-ghost btn-sm danger" type="submit">Delete</button></form></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <?php endif; ?>
        <?php if ((int)$server['pages'] > 1): ?>
          <nav class="btn-row" style="margin-top:14px" aria-label="Pages">
            <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/', ['page' => $page - 1])) ?>#server">Newer</a><?php endif; ?>
            <span class="muted small">Page <?= (int)$page ?> of <?= (int)$server['pages'] ?></span>
            <?php if ($page < (int)$server['pages']): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/', ['page' => $page + 1])) ?>#server">Older</a><?php endif; ?>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </section>

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
