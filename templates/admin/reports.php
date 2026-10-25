<div class="admin-top"><h1>Reports</h1>
  <form method="get" class="inline-form"><select name="status"><?php foreach (['active' => 'Open & investigating', 'open' => 'Open', 'investigating' => 'Investigating', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed', 'all' => 'All'] as $k => $l): ?><option value="<?= $k ?>"<?= $status === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select><button class="btn btn-ghost btn-sm">Show</button></form>
</div>
<?php if (!$rows): ?><div class="empty"><h2>No reports here</h2><p>Nothing in this view.</p></div><?php else: ?>
<div class="card table-scroll"><table class="tbl">
  <thead><tr><th>Received</th><th>Reason</th><th>About</th><th>Details</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= time_tag($r['created_at']) ?></td>
      <td><strong<?= $r['reason'] === 'minor' ? ' style="color:var(--x-bad)"' : '' ?>><?= e($r['reason']) ?></strong></td>
      <td><?php if ($r['cid']): ?><a href="<?= e(admin_url('competition', ['id' => $r['cid']])) ?>"><?= e($r['title']) ?></a><?php else: ?><span class="muted">General</span><?php endif; ?>
        <?php if ($r['display_name']): ?><br><?php if (!$r['is_removed'] && $r['public_id']): ?><img class="thumb" src="<?= e(admin_url('media', ['p' => $r['public_id']])) ?>" alt=""><?php endif; ?> <?= e($r['display_name']) ?> <span class="muted small">(<?= e($r['is_removed'] ? 'removed' : $r['image_status']) ?>)</span><?php endif; ?></td>
      <td><?= nl2br(e($r['details'])) ?><?php if ($r['reporter_email']): ?><br><span class="muted small">Reply to: <?= e($r['reporter_email']) ?></span><?php endif; ?></td>
      <td><?= e($r['status']) ?><?php if ($r['handler']): ?><br><span class="muted small"><?= e($r['handler']) ?>, <?= time_tag($r['handled_at'], 'date') ?></span><?php endif; ?><?php if ($r['resolution_note']): ?><br><span class="muted small"><?= e($r['resolution_note']) ?></span><?php endif; ?></td>
      <td><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>"><input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>"><input name="note" placeholder="Note" maxlength="500">
        <?php if ($r['status'] === 'open'): ?><button class="btn btn-ghost btn-sm" name="action" value="investigate">Investigate</button><?php endif; ?>
        <?php if (in_array($r['status'], ['open', 'investigating'], true)): ?><button class="btn btn-primary btn-sm" name="action" value="resolve">Resolve</button><button class="btn btn-ghost btn-sm" name="action" value="dismiss">Dismiss</button><?php else: ?><button class="btn btn-ghost btn-sm" name="action" value="reopen">Reopen</button><?php endif; ?>
      </form>
      <?php if ($r['cid'] && admin_can('moderation')): ?><a class="small" href="<?= e(admin_url('moderation')) ?>">Photo actions</a><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php partial('admin_pager', compact('page', 'pages')); ?>
<p class="muted small">Resolving a report doesn’t change content by itself — use the competition or moderation tools for that. Reporter emails are visible only here.</p>
<?php endif; ?>
