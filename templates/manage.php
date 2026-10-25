<?php /** @var array $c */ $locked = $c['status'] === 'suspended'; ?>
<section class="page-head">
  <div class="container">
    <?php partial('match_header', compact('c', 'effective')); ?>
    <h1 style="margin-top:10px">Manage: <?= e($c['title']) ?></h1>
    <p>This page is private to you. Anyone with this page’s address can change the match, so don’t share it.</p>
  </div>
</section>
<div class="container stack">
  <?php if ($locked): ?><div class="notice notice-error">Moderators suspended this match. It can’t be viewed or voted on. Contact us if you think this is a mistake.</div><?php endif; ?>
  <?php if ($c['visibility'] === 'public' && $c['moderation_status'] === 'pending'): ?><div class="notice notice-info">Awaiting review — reachable by link, not yet listed in Explore.</div><?php endif; ?>
  <?php if ($c['moderation_status'] === 'rejected'): ?><div class="notice notice-warn">Not approved for public listing. The match is only visible to you.</div><?php endif; ?>
  <?php if ($openReports): ?><div class="notice notice-warn"><?= e(plural($openReports, 'report')) ?> about this match are being reviewed by moderators.</div><?php endif; ?>
  <?php if (!empty($newLinks['manage'])): ?>
    <div class="link-box secret"><strong>Your new management link</strong><span class="muted small">Save it now. The previous link no longer works.</span>
      <div class="row"><input readonly value="<?= e($newLinks['manage']) ?>" data-copy-source aria-label="New management link"><button class="btn btn-ghost btn-sm" type="button" data-copy>Copy</button></div></div>
  <?php endif; ?>
  <?php if (!empty($newLinks['code'])): ?>
    <div class="link-box"><strong>New access code</strong><span class="muted small">Shown once. The old code no longer works.</span>
      <div class="row"><span class="code-big" data-copy-source><?= e($newLinks['code']) ?></span><button class="btn btn-ghost btn-sm" type="button" data-copy>Copy</button></div></div>
  <?php endif; ?>

  <div class="manage-grid">
    <section class="card pad stack">
      <h2>Overview</h2>
      <div class="facts" style="display:grid;gap:6px">
        <span>Status: <strong><?= e(status_label($effective)) ?></strong></span>
        <span><?= $effective === 'active' ? 'Closes' : 'Closed' ?>: <?= time_tag($effective === 'active' ? $c['closes_at'] : ($c['closed_at'] ?: $c['closes_at'])) ?></span>
        <span>Votes: <strong><?= number_format((int)$c['votes_total']) ?></strong></span>
        <span>Contenders: <strong><?= count($participants) ?></strong></span>
        <span>Visibility: <strong><?= $c['visibility'] === 'public' ? 'Public' : 'Private' ?></strong></span>
      </div>
      <div class="btn-row">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/m/' . $c['slug'])) ?>">View match</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/m/' . $c['slug'] . '/results')) ?>">Results</a>
      </div>
    </section>

    <section class="card pad stack">
      <h2>Share</h2>
      <?php if ($shareUrl): ?>
        <div class="share"><input readonly value="<?= e($shareUrl) ?>" data-copy-source aria-label="Share link"><button class="btn btn-ghost btn-sm" type="button" data-copy>Copy</button></div>
        <?php if ($c['visibility'] === 'private' && $c['invite_expires_at']): ?><p class="muted small">Invite expires <?= time_tag($c['invite_expires_at']) ?>.</p><?php endif; ?>
      <?php else: ?>
        <p class="muted">There is no active invite link. Create one below to let people in.</p>
      <?php endif; ?>
      <?php if ($c['visibility'] === 'private' && !$locked): ?>
        <form method="post" action="<?= e($actionUrl) ?>" class="form-stack">
          <?= csrf_field() ?><input type="hidden" name="action" value="rotate_invite">
          <label class="field"><span>New invite link expires</span>
            <select name="invite_expiry"><?php foreach ($inviteExpiry as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></label>
          <button class="btn btn-ghost btn-sm" type="submit"><?= $shareUrl ? 'Replace invite link' : 'Create invite link' ?></button>
        </form>
        <?php if ($shareUrl): ?>
        <form method="post" action="<?= e($actionUrl) ?>" data-confirm="Revoke the invite link? Nobody new will be able to open the match, and people already in will lose access.">
          <?= csrf_field() ?><input type="hidden" name="action" value="revoke_invite">
          <button class="btn btn-danger btn-sm" type="submit">Revoke invite link</button>
        </form>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <?php if ($c['visibility'] === 'private'): ?>
    <section class="card pad stack">
      <h2>Access code</h2>
      <p class="muted small"><?= $c['access_code_hash'] ? 'Voters must enter an access code after opening the invite link.' : 'No access code is required right now.' ?></p>
      <form method="post" action="<?= e($actionUrl) ?>" class="btn-row">
        <?= csrf_field() ?><input type="hidden" name="action" value="access_code">
        <button class="btn btn-ghost btn-sm" type="submit" name="mode" value="new"><?= $c['access_code_hash'] ? 'Generate new code' : 'Require an access code' ?></button>
        <?php if ($c['access_code_hash']): ?><button class="btn btn-ghost btn-sm" type="submit" name="mode" value="remove">Remove code</button><?php endif; ?>
      </form>
    </section>
    <?php endif; ?>

    <section class="card pad stack">
      <h2>Voting</h2>
      <?php if ($locked): ?><p class="muted">Unavailable while suspended.</p>
      <?php elseif ($effective === 'active'): ?>
        <form method="post" action="<?= e($actionUrl) ?>" data-confirm="Close voting now?">
          <?= csrf_field() ?><input type="hidden" name="action" value="close">
          <button class="btn btn-ghost btn-sm" type="submit">Close voting now</button>
        </form>
      <?php endif; ?>
      <?php if (!$locked): ?>
      <form method="post" action="<?= e($actionUrl) ?>" class="form-stack">
        <?= csrf_field() ?><input type="hidden" name="action" value="reopen">
        <label class="field"><span><?= $effective === 'active' ? 'Change closing time to' : 'Reopen voting for' ?></span>
          <select name="duration"><?php foreach ($durations as $h => $l): ?><option value="<?= $h ?>"><?= e($l) ?> from now</option><?php endforeach; ?></select></label>
        <button class="btn btn-ghost btn-sm" type="submit"><?= $effective === 'active' ? 'Update closing time' : 'Reopen voting' ?></button>
      </form>
      <?php endif; ?>
      <form method="post" action="<?= e($actionUrl) ?>" class="form-stack">
        <?= csrf_field() ?><input type="hidden" name="action" value="results">
        <label class="field"><span>Who can see results</span>
          <select name="results_visibility"><?php foreach (['live', 'after_close', 'restricted'] as $rv): ?><option value="<?= $rv ?>"<?= $c['results_visibility'] === $rv ? ' selected' : '' ?>><?= e(results_visibility_label($rv)) ?></option><?php endforeach; ?></select></label>
        <button class="btn btn-ghost btn-sm" type="submit">Save results setting</button>
      </form>
    </section>

    <section class="card pad stack">
      <h2>Management link</h2>
      <p class="muted small">If you think someone else has this page’s address, replace it. The current link stops working immediately.</p>
      <form method="post" action="<?= e($actionUrl) ?>" data-confirm="Replace your management link? Make sure you save the new one.">
        <?= csrf_field() ?><input type="hidden" name="action" value="rotate_manage">
        <button class="btn btn-ghost btn-sm" type="submit">Replace management link</button>
      </form>
    </section>

    <section class="card pad stack danger-zone">
      <h2>Delete match</h2>
      <p class="muted small">Removes the match and deletes every photo from our servers. This can’t be undone.</p>
      <form method="post" action="<?= e($actionUrl) ?>" class="form-stack">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete">
        <label class="field"><span>Type DELETE to confirm</span><input name="confirm" autocomplete="off" pattern="DELETE" required></label>
        <button class="btn btn-danger btn-sm" type="submit">Delete match and photos</button>
      </form>
    </section>
  </div>
</div>
