<h1>Abuse blocks</h1>
<p class="muted">Temporary blocks are added automatically when a network sends far more requests than the rate limits allow. Blocked networks can still browse but cannot create, vote, report or send messages. Only hashed network keys are stored.</p>
<?php if (!$rows): ?><div class="empty"><h2>No recent blocks</h2></div><?php else: ?>
<div class="card table-scroll"><table class="tbl"><thead><tr><th>Network key</th><th>Reason</th><th>Created</th><th>Expires</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $b): $live = strtotime($b['expires_at'] . ' UTC') > time(); ?>
  <tr><td><code><?= e(substr($b['ip_hash'], 0, 12)) ?>…</code></td><td><?= e($b['reason']) ?></td><td><?= time_tag($b['created_at']) ?></td><td><?= time_tag($b['expires_at']) ?><?= $live ? '' : ' (expired)' ?></td>
  <td><?php if ($live): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="block_id" value="<?= (int)$b['id'] ?>"><button class="btn btn-ghost btn-sm">Lift block</button></form><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?>
