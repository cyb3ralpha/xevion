<div class="admin-top"><h1>Audit log</h1><span class="muted"><?= e(plural($total, 'entry', 'entries')) ?></span></div>
<form class="admin-filters" method="get">
  <label class="field"><span>Action starts with</span><input name="action" value="<?= e($f['action']) ?>" placeholder="competition."></label>
  <label class="field"><span>Target type</span><input name="target_type" value="<?= e($f['target_type']) ?>" placeholder="competition"></label>
  <label class="field"><span>Target ID</span><input name="target_id" type="number" value="<?= $f['target_id'] ?: '' ?>"></label>
  <button class="btn btn-ghost btn-sm">Filter</button>
</form>
<div class="card table-scroll"><table class="tbl"><thead><tr><th>When</th><th>Who</th><th>Action</th><th>Target</th><th>Details</th></tr></thead><tbody>
<?php foreach ($rows as $l): ?>
  <tr><td><?= time_tag($l['created_at']) ?></td><td><?= e($l['admin_name'] ?? ucfirst($l['actor'])) ?></td><td><code><?= e($l['action']) ?></code></td>
  <td><?php if ($l['target_type'] === 'competition' && $l['target_id']): ?><a href="<?= e(admin_url('competition', ['id' => $l['target_id']])) ?>">competition #<?= (int)$l['target_id'] ?></a><?php else: ?><?= e($l['target_type']) ?> <?= $l['target_id'] ? '#' . (int)$l['target_id'] : '' ?><?php endif; ?></td>
  <td><div class="details-json"><?= e($l['details']) ?></div></td></tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="5" class="muted">No entries.</td></tr><?php endif; ?>
</tbody></table></div>
<?php partial('admin_pager', compact('page', 'pages')); ?>
<p class="muted small">The audit log is append-only; there are no controls to edit or delete entries.</p>
