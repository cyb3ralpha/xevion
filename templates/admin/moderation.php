<div class="admin-top"><h1>Moderation queue</h1>
  <form method="get" class="inline-form"><select name="status"><?php foreach (['' => 'Needs review', 'escalated' => 'Escalated (hidden)', 'flagged' => 'Flagged', 'pending' => 'New uploads', 'rejected' => 'Rejected'] as $k => $l): ?><option value="<?= $k ?>"<?= $status === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select><button class="btn btn-ghost btn-sm">Show</button></form>
</div>
<?php if ($pendingComps && $status === ''): ?>
<section class="card pad" style="margin-bottom:22px">
  <h2>Public matches awaiting approval</h2>
  <table class="tbl"><tbody>
  <?php foreach ($pendingComps as $c): ?>
    <tr><td><a href="<?= e(admin_url('competition', ['id' => $c['id']])) ?>"><?= e($c['title']) ?></a><br><span class="muted small"><?= e(category_label($c)) ?><?= $c['category'] === 'custom' ? ' (custom)' : '' ?> · <?= (int)$c['participant_count'] ?> contenders</span></td>
    <td><?= time_tag($c['created_at']) ?></td>
    <td><form method="post" action="<?= e(admin_url('competition', ['id' => $c['id']])) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><button class="btn btn-primary btn-sm">Approve</button></form>
        <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('competition', ['id' => $c['id']])) ?>">Review</a></td></tr>
  <?php endforeach; ?>
  </tbody></table>
  <p class="muted small">Approving a match also approves its new photos. Check the photos below or on the review page first.</p>
</section>
<?php endif; ?>
<?php if (!$rows): ?>
  <div class="empty"><h2>Queue is clear</h2><p>No photos need review right now.</p></div>
<?php else: ?>
<div class="mod-grid">
  <?php foreach ($rows as $p): ?>
  <article class="mod-card <?= e($p['image_status']) ?>">
    <a href="<?= e(admin_url('media', ['p' => $p['public_id'], 's' => 'full'])) ?>" target="_blank"><img src="<?= e(admin_url('media', ['p' => $p['public_id']])) ?>" alt="Photo of <?= e($p['display_name']) ?>" loading="lazy"></a>
    <div class="body">
      <strong><?= e($p['display_name']) ?></strong>
      <a href="<?= e(admin_url('competition', ['id' => $p['cid']])) ?>"><?= e($p['title']) ?></a>
      <span class="muted"><?= e(ucfirst($p['visibility'])) ?> · uploaded <?= time_tag($p['uploaded_at']) ?></span>
      <span>Status: <strong><?= e($p['image_status']) ?></strong><?= $p['open_reports'] ? ' · ' . (int)$p['open_reports'] . ' open reports (' . e($p['reasons']) . ')' : '' ?></span>
    </div>
    <div class="actions">
      <?php $back = $_SERVER['REQUEST_URI'] ?? ''; ?>
      <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>"><input type="hidden" name="participant_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="resolve_reports" value="1"><button class="btn btn-primary btn-sm" name="action" value="approve">Approve</button><button class="btn btn-ghost btn-sm" name="action" value="reject">Reject</button><?php if ($p['image_status'] !== 'escalated'): ?><button class="btn btn-ghost btn-sm" name="action" value="escalate">Escalate</button><?php endif; ?></form>
      <form method="post" class="inline-form" data-confirm="Permanently delete this photo and remove the contender?"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>"><input type="hidden" name="participant_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="action" value="remove"><button class="btn btn-danger btn-sm">Remove now</button></form>
    </div>
  </article>
  <?php endforeach; ?>
</div>
<p class="muted small" style="margin-top:14px">Approve: visible. Reject: hidden, file kept for appeal. Escalate: hidden until a senior reviewer decides. Remove: file deleted immediately and contender removed from rankings.</p>
<?php endif; ?>
