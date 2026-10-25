<?php /** @var array $c */ $eff = comp_effective_status($c); $deleted = $c['status'] === 'deleted'; ?>
<div class="admin-top">
  <div><h1><?= e($c['title']) ?></h1>
  <div class="match-meta"><span class="pill pill-cat"><?= e(category_label($c)) ?></span><span class="pill"><?= e(status_label($eff)) ?></span><span class="pill"><?= e(ucfirst($c['visibility'])) ?></span><span class="pill">Review: <?= e($c['moderation_status']) ?></span><?php if ($c['is_featured']): ?><span class="pill pill-cat">Featured</span><?php endif; ?></div></div>
  <?php if (!$deleted && $c['status'] !== 'suspended' && ($c['visibility'] === 'public' && $c['moderation_status'] !== 'rejected')): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('/m/' . $c['slug'])) ?>" target="_blank">Open public page</a><?php endif; ?>
</div>
<div class="stats">
  <div class="stat"><b><?= number_format((int)$voteStats['counted']) ?></b><span>Counted votes</span></div>
  <div class="stat"><b><?= number_format((int)$voteStats['voided']) ?></b><span>Voided votes</span></div>
  <div class="stat"><b><?= number_format((int)$voteStats['voters']) ?></b><span>Distinct browsers</span></div>
  <div class="stat"><b><?= time_tag($c['closes_at']) ?></b><span>Closes (<?= e(results_visibility_label($c['results_visibility'])) ?>)</span></div>
</div>
<?php if ((int)$voteStats['counted'] !== (int)$c['votes_total']): ?><div class="notice notice-warn">Cached vote counter (<?= (int)$c['votes_total'] ?>) differs from records. Use “Recalculate”.</div><?php endif; ?>

<?php if (!$deleted): ?>
<section class="card pad stack" style="margin-bottom:20px">
  <h2>Actions</h2>
  <div class="btn-row">
    <?php if ($c['visibility'] === 'public' && admin_can('moderation')): ?>
      <?php if ($c['moderation_status'] !== 'approved'): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><button class="btn btn-primary btn-sm">Approve listing</button></form><?php endif; ?>
      <?php if ($c['moderation_status'] !== 'rejected'): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><input name="note" placeholder="Reason (internal)"><button class="btn btn-danger btn-sm">Reject listing</button></form><?php endif; ?>
    <?php endif; ?>
    <?php if (admin_can('competitions.manage') && comp_is_listed($c)): ?>
      <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $c['is_featured'] ? 'unfeature' : 'feature' ?>"><button class="btn btn-ghost btn-sm"><?= $c['is_featured'] ? 'Unfeature' : 'Feature on homepage' ?></button></form>
    <?php endif; ?>
    <?php if (admin_can('competitions.suspend')): ?>
      <?php if ($c['status'] === 'suspended'): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="restore"><button class="btn btn-ghost btn-sm">Restore</button></form>
      <?php else: ?><form method="post" class="inline-form" data-confirm="Suspend this competition? It will disappear for everyone."><?= csrf_field() ?><input type="hidden" name="action" value="suspend"><input name="reason" placeholder="Reason" required><button class="btn btn-danger btn-sm">Suspend</button></form><?php endif; ?>
    <?php endif; ?>
  </div>
  <?php if (admin_can('competitions.manage')): ?>
  <div class="btn-row">
    <?php if ($eff === 'active'): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="close"><button class="btn btn-ghost btn-sm">Close voting</button></form><?php endif; ?>
    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="reopen"><select name="hours"><?php foreach ($durations as $h => $l): ?><option value="<?= $h ?>"><?= e($l) ?></option><?php endforeach; ?></select><button class="btn btn-ghost btn-sm"><?= $eff === 'active' ? 'Set closing time' : 'Reopen' ?></button></form>
    <form method="post" class="inline-form" data-confirm="Change visibility?"><?= csrf_field() ?><input type="hidden" name="action" value="visibility"><input type="hidden" name="visibility" value="<?= $c['visibility'] === 'public' ? 'private' : 'public' ?>"><button class="btn btn-ghost btn-sm">Make <?= $c['visibility'] === 'public' ? 'private' : 'public' ?></button></form>
    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="recount"><button class="btn btn-ghost btn-sm">Recalculate vote counter</button></form>
  </div>
  <?php endif; ?>
</section>

<?php if (admin_can('competitions.manage')): ?>
<details class="card pad" style="margin-bottom:20px">
  <summary><strong>Edit details &amp; sponsorship</strong></summary>
  <form method="post" class="form-stack" style="margin-top:14px">
    <?= csrf_field() ?><input type="hidden" name="action" value="edit">
    <label class="field"><span>Title</span><input name="title" maxlength="120" value="<?= e($c['title']) ?>" required></label>
    <?php if ($c['category'] === 'custom'): ?><label class="field"><span>Custom category</span><input name="custom_category" maxlength="40" value="<?= e($c['custom_category']) ?>"></label><?php endif; ?>
    <label class="field"><span>Description</span><textarea name="description" maxlength="600"><?= e($c['description']) ?></textarea></label>
    <label class="field"><span>Instructions</span><input name="instructions" maxlength="300" value="<?= e($c['instructions']) ?>"></label>
    <div class="grid-2">
      <label class="field"><span>Sponsor name <span class="hint">Shows a “Sponsored” label</span></span><input name="sponsor_name" maxlength="80" value="<?= e($c['sponsor_name']) ?>"></label>
      <label class="field"><span>Sponsor URL</span><input name="sponsor_url" maxlength="500" value="<?= e($c['sponsor_url']) ?>" placeholder="https://"></label>
    </div>
    <button class="btn btn-primary btn-sm" type="submit">Save details</button>
  </form>
</details>
<?php endif; ?>
<?php endif; ?>

<section class="card pad" style="margin-bottom:20px">
  <h2>Contenders &amp; standings</h2>
  <div class="table-scroll"><table class="tbl">
    <thead><tr><th>Photo</th><th>Name</th><th>Image status</th><th class="num">Rank</th><th class="num">W–L</th><th class="num">Open reports</th><th></th></tr></thead>
    <tbody>
    <?php $rankBy = []; foreach ($ranking['rows'] as $r) { $rankBy[$r['id']] = $r; } ?>
    <?php foreach ($parts as $p): $r = $rankBy[$p['id']] ?? null; ?>
      <tr>
        <td><?php if ($p['image_file']): ?><a href="<?= e(admin_url('media', ['p' => $p['public_id'], 's' => 'full'])) ?>" target="_blank"><img class="thumb" src="<?= e(admin_url('media', ['p' => $p['public_id']])) ?>" alt=""></a><?php else: ?><span class="muted small">Deleted</span><?php endif; ?></td>
        <td><?= e($p['display_name']) ?><?= $p['is_removed'] ? '<br><span class="muted small">Removed: ' . e($p['removed_reason']) . '</span>' : '' ?></td>
        <td><?= e($p['image_status']) ?></td>
        <td class="num"><?= $r ? $r['rank'] . ($r['tied'] ? ' (tie)' : '') : '—' ?></td>
        <td class="num"><?= $r ? $r['wins'] . '–' . $r['losses'] : '—' ?></td>
        <td class="num"><?= (int)$p['open_reports'] ?: '' ?></td>
        <td><?php if (!$p['is_removed'] && !$deleted && admin_can('moderation')): ?>
          <form method="post" class="inline-form" data-confirm="Remove this contender and permanently delete the photo?"><?= csrf_field() ?><input type="hidden" name="action" value="remove_participant"><input type="hidden" name="participant_id" value="<?= (int)$p['id'] ?>"><input name="reason" placeholder="Reason" required><button class="btn btn-danger btn-sm">Remove</button></form>
        <?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>

<?php if (admin_can('votes.void') && $heavy): ?>
<section class="card pad" style="margin-bottom:20px">
  <h2>Vote concentration</h2>
  <p class="muted small">Browsers with the most votes here. A single browser can vote once per pair, so the maximum is the number of pairs. Voiding keeps the records but stops them counting; it is logged.</p>
  <table class="tbl"><thead><tr><th>Browser (hashed)</th><th class="num">Votes</th><th>First</th><th>Last</th><th></th></tr></thead><tbody>
  <?php foreach ($heavy as $h): ?>
    <tr><td><code><?= e(substr($h['voter_hash'], 0, 12)) ?>…</code></td><td class="num"><?= (int)$h['n'] ?></td><td><?= time_tag($h['first_at']) ?></td><td><?= time_tag($h['last_at']) ?></td>
    <td><form method="post" class="inline-form" data-confirm="Void every counted vote from this browser in this competition?"><?= csrf_field() ?><input type="hidden" name="action" value="void_voter"><input type="hidden" name="voter_hash" value="<?= e($h['voter_hash']) ?>"><input name="reason" placeholder="Reason (required)" required minlength="5"><button class="btn btn-danger btn-sm">Void</button></form></td></tr>
  <?php endforeach; ?>
  </tbody></table>
  <?php if ($heavyIp): ?><p class="muted small" style="margin-top:12px">Networks with several browsers voting: <?php foreach ($heavyIp as $h): ?><code><?= e(substr($h['ip_hash'], 0, 10)) ?></code> <?= (int)$h['n'] ?> votes / <?= (int)$h['browsers'] ?> browsers; <?php endforeach; ?></p><?php endif; ?>
</section>
<?php endif; ?>

<div class="two-col">
  <section class="card pad">
    <h2>Reports</h2>
    <?php if (!$reports): ?><p class="muted">No reports.</p><?php else: ?>
    <table class="tbl"><tbody><?php foreach ($reports as $r): ?>
      <tr><td><?= time_tag($r['created_at'], 'date') ?></td><td><?= e($r['reason']) ?><?= $r['display_name'] ? ' · ' . e($r['display_name']) : '' ?><br><span class="muted small"><?= e(mb_substr((string)$r['details'], 0, 140)) ?></span></td><td><?= e($r['status']) ?></td></tr>
    <?php endforeach; ?></tbody></table>
    <p><a href="<?= e(admin_url('reports', ['status' => 'all'])) ?>">Manage reports</a></p>
    <?php endif; ?>
  </section>
  <section class="card pad">
    <h2>Audit history</h2>
    <?php if (!$history): ?><p class="muted">No recorded actions.</p><?php else: ?>
    <table class="tbl"><tbody><?php foreach ($history as $h): ?>
      <tr><td><?= time_tag($h['created_at']) ?></td><td><?= e($h['admin_name'] ?? ucfirst($h['actor'])) ?></td><td><code><?= e($h['action']) ?></code><?php if ($h['details']): ?><div class="details-json"><?= e($h['details']) ?></div><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table>
    <?php endif; ?>
  </section>
</div>

<?php if (!$deleted && admin_can('competitions.delete')): ?>
<section class="card pad danger-zone" style="margin-top:20px;max-width:560px">
  <h2>Delete competition</h2>
  <p class="muted small">Deletes all photo files and hides the competition permanently. Vote and audit records are kept.</p>
  <form method="post" class="form-stack"><?= csrf_field() ?><input type="hidden" name="action" value="delete">
    <label class="field"><span>Type the ID <code><?= e($c['slug']) ?></code> to confirm</span><input name="confirm" required autocomplete="off"></label>
    <label class="field"><span>Reason</span><input name="reason" required></label>
    <button class="btn btn-danger btn-sm">Delete permanently</button></form>
</section>
<?php endif; ?>
