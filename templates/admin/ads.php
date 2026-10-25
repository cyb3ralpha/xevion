<div class="admin-top"><h1>Advertising</h1><a class="btn btn-primary btn-sm" href="<?= e(admin_url('ad')) ?>">New campaign</a></div>
<p class="muted small">Impressions count once per browser session per campaign every 30 minutes (bots excluded). Clicks count through the tracked redirect with the same de-duplication. Budgets are notes you enter manually — they are not payments.</p>
<?php if (!$rows): ?><div class="empty"><h2>No campaigns yet</h2><p>Create a campaign to fill the top banner. Until then visitors see a short “advertise with us” message.</p><a class="btn btn-primary" href="<?= e(admin_url('ad')) ?>">Create campaign</a></div><?php else: ?>
<div class="card table-scroll"><table class="tbl">
  <thead><tr><th>Campaign</th><th>State</th><th>Placement</th><th class="num">Priority</th><th>Runs</th><th class="num">Impressions</th><th class="num">Clicks</th><th class="num">CTR</th></tr></thead>
  <tbody><?php foreach ($rows as $a): $imp = (int)$a['impressions']; $clk = (int)$a['clicks']; ?>
    <tr><td><a href="<?= e(admin_url('ad', ['id' => $a['id']])) ?>"><?= e($a['name']) ?></a><br><span class="muted small"><?= e($a['advertiser']) ?></span></td>
    <td><?= e(ad_state_label($a)) ?></td><td><?= e($a['placement'] === 'top_banner' ? 'Top banner' : 'Footer banner') ?></td><td class="num"><?= (int)$a['priority'] ?></td>
    <td><?= time_tag($a['starts_at'], 'date') ?> – <?= time_tag($a['ends_at'], 'date') ?></td>
    <td class="num"><?= number_format($imp) ?></td><td class="num"><?= number_format($clk) ?></td><td class="num"><?= $imp ? number_format($clk * 100 / $imp, 2) . '%' : '—' ?></td></tr>
  <?php endforeach; ?></tbody>
</table></div>
<?php endif; ?>
