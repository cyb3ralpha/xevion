<h1>Dashboard</h1>
<?php if (isset($m['competitions'])): ?>
<div class="stats">
  <a class="stat" href="<?= e(admin_url('competitions')) ?>"><b><?= number_format($m['competitions']) ?></b><span>Competitions</span></a>
  <a class="stat" href="<?= e(admin_url('competitions', ['status' => 'open'])) ?>"><b><?= number_format($m['active']) ?></b><span>Open for voting</span></a>
  <a class="stat" href="<?= e(admin_url('competitions', ['status' => 'closed'])) ?>"><b><?= number_format($m['closed']) ?></b><span>Closed</span></a>
  <a class="stat<?= $m['pending'] ? ' alert' : '' ?>" href="<?= e(admin_url('moderation')) ?>"><b><?= number_format($m['pending']) ?></b><span>Awaiting approval</span></a>
  <a class="stat<?= $m['queue'] ? ' alert' : '' ?>" href="<?= e(admin_url('moderation')) ?>"><b><?= number_format($m['queue']) ?></b><span>Photos in review queue</span></a>
  <a class="stat" href="<?= e(admin_url('competitions', ['status' => 'suspended'])) ?>"><b><?= number_format($m['suspended']) ?></b><span>Suspended</span></a>
  <div class="stat"><b><?= number_format($m['votes']) ?></b><span>Counted votes (<?= number_format($m['votes_24h']) ?> in 24h)</span></div>
  <div class="stat"><b><?= number_format($m['images']) ?></b><span>Stored photos</span></div>
  <?php if (isset($m['reports'])): ?><a class="stat<?= $m['reports'] ? ' alert' : '' ?>" href="<?= e(admin_url('reports')) ?>"><b><?= number_format($m['reports']) ?></b><span>Reports awaiting review</span></a><?php endif; ?>
  <?php if (isset($m['messages'])): ?><a class="stat" href="<?= e(admin_url('messages')) ?>"><b><?= number_format($m['messages']) ?></b><span>New messages</span></a><?php endif; ?>
</div>
<?php endif; ?>
<?php if (isset($m['ads_active'])): ?>
<div class="stats">
  <a class="stat" href="<?= e(admin_url('ads')) ?>"><b><?= number_format($m['ads_active']) ?></b><span>Ads running now</span></a>
  <div class="stat"><b><?= number_format($m['ads_30']['impressions']) ?></b><span>Ad impressions, 30 days</span></div>
  <div class="stat"><b><?= number_format($m['ads_30']['clicks']) ?></b><span>Ad clicks, 30 days</span></div>
  <div class="stat"><b><?= number_format($m['ads_30']['ctr'], 2) ?>%</b><span>Click-through rate, 30 days</span></div>
</div>
<?php endif; ?>
<?php if (isset($m['series'])): $max = max(1, max($m['series'])); ?>
  <section class="card pad">
    <h2>Votes per day, last 14 days (UTC)</h2>
    <div class="bars" role="img" aria-label="Daily votes chart">
      <?php foreach ($m['series'] as $d => $n): ?><div style="height:<?= max(1, round($n * 100 / $max)) ?>%" title="<?= e($d) ?>: <?= $n ?> votes"></div><?php endforeach; ?>
    </div>
    <div class="bars-labels"><?php foreach ($m['series'] as $d => $n): ?><span><?= e(substr($d, 8)) ?></span><?php endforeach; ?></div>
    <p class="muted small">Total in period: <?= number_format(array_sum($m['series'])) ?></p>
  </section>
<?php endif; ?>
