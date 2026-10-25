<div class="admin-top"><h1>Competitions</h1><span class="muted"><?= e(plural($total, 'result')) ?></span></div>
<form class="admin-filters" method="get">
  <label class="field"><span>Search</span><input name="q" value="<?= e($f['q']) ?>" placeholder="Title or ID"></label>
  <label class="field"><span>Status</span><select name="status"><?php foreach (['' => 'All (not deleted)', 'open' => 'Open', 'closed' => 'Closed', 'suspended' => 'Suspended', 'deleted' => 'Deleted'] as $k => $l): ?><option value="<?= $k ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
  <label class="field"><span>Visibility</span><select name="visibility"><?php foreach (['' => 'Any', 'public' => 'Public', 'private' => 'Private'] as $k => $l): ?><option value="<?= $k ?>"<?= $f['visibility'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
  <label class="field"><span>Review</span><select name="moderation"><?php foreach (['' => 'Any', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $k => $l): ?><option value="<?= $k ?>"<?= $f['moderation'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
  <button class="btn btn-ghost btn-sm" type="submit">Filter</button>
</form>
<div class="card table-scroll">
<table class="tbl">
  <thead><tr><th>Title</th><th>Status</th><th>Visibility</th><th>Review</th><th class="num">Contenders</th><th class="num">Votes</th><th class="num">Reports</th><th>Created</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $c): $eff = comp_effective_status($c); ?>
    <tr>
      <td><a href="<?= e(admin_url('competition', ['id' => $c['id']])) ?>"><?= e($c['title']) ?></a><?= $c['is_featured'] ? ' <span class="pill pill-cat">Featured</span>' : '' ?><br><span class="muted small"><?= e(category_label($c)) ?> · <?= e($c['slug']) ?></span></td>
      <td><?= e(status_label($eff)) ?></td>
      <td><?= e(ucfirst($c['visibility'])) ?></td>
      <td><?= e(ucfirst($c['moderation_status'])) ?></td>
      <td class="num"><?= (int)$c['participant_count'] ?></td>
      <td class="num"><?= number_format((int)$c['votes_total']) ?></td>
      <td class="num"><?= (int)$c['open_reports'] ?: '' ?></td>
      <td><?= time_tag($c['created_at'], 'date') ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="8" class="muted">No competitions match these filters.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php partial('admin_pager', compact('page', 'pages')); ?>
