<section class="page-head">
  <div class="container narrow">
    <h1>Your match is ready</h1>
    <p><?= e($c['title']) ?></p>
  </div>
</section>
<div class="container narrow stack">
  <?php if ($c['visibility'] === 'public' && $c['moderation_status'] === 'pending'): ?>
    <div class="notice notice-info">Your match works right away through the link below. It will be listed in Explore once a moderator approves it.</div>
  <?php endif; ?>
  <div class="link-box">
    <strong><?= $c['visibility'] === 'public' ? 'Share link' : 'Private invite link' ?></strong>
    <span class="muted small"><?= $c['visibility'] === 'public' ? 'Send this to voters.' : 'Only people with this link can open the match. You can revoke it any time.' ?></span>
    <div class="row"><input readonly value="<?= e($shareUrl) ?>" aria-label="Share link" data-copy-source><button class="btn btn-primary btn-sm" type="button" data-copy>Copy</button></div>
  </div>
  <?php if ($accessCode): ?>
  <div class="link-box">
    <strong>Access code</strong>
    <span class="muted small">Voters must enter this after opening the invite link. It is shown only now; you can create a new one from the management page.</span>
    <div class="row"><span class="code-big" data-copy-source><?= e($accessCode) ?></span><button class="btn btn-ghost btn-sm" type="button" data-copy>Copy</button></div>
  </div>
  <?php endif; ?>
  <div class="link-box secret">
    <strong>Your management link — keep it secret</strong>
    <span class="muted small">Anyone with this link can close, reopen or delete the match. We can’t show it again after you leave, so bookmark it or save it somewhere safe.</span>
    <div class="row"><input readonly value="<?= e($manageUrl) ?>" aria-label="Management link" data-copy-source><button class="btn btn-ghost btn-sm" type="button" data-copy>Copy</button></div>
  </div>
  <div class="btn-row">
    <a class="btn btn-primary" href="<?= e(url('/m/' . $c['slug'] . '/play')) ?>">Cast the first vote</a>
    <a class="btn btn-ghost" href="<?= e($manageUrl) ?>">Open management page</a>
  </div>
</div>
