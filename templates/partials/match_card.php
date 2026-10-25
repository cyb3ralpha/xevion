<?php /** @var array $c */
$eff = comp_effective_status($c);
$pv = $c['preview'] ?? [];
?>
<a class="match-card" href="<?= e(url('/m/' . $c['slug'])) ?>">
  <div class="duo" aria-hidden="true">
    <?php for ($i = 0; $i < 2; $i++): $p = $pv[$i] ?? null; ?>
      <?php if ($p): ?><img src="<?= e(url('/media/' . $p['public_id'] . '/thumb')) ?>" alt="" loading="lazy"><?php else: ?><span class="duo-empty">Photo hidden</span><?php endif; ?>
    <?php endfor; ?>
    <span class="duo-vs">VS</span>
  </div>
  <div class="match-body">
    <h3><?= e($c['title']) ?></h3>
    <div class="match-meta">
      <span class="pill pill-cat"><?= e(category_label($c)) ?></span>
      <?php if ($eff === 'active'): ?><span class="pill pill-live"><?= e(time_left($c['closes_at'])) ?></span><?php else: ?><span class="pill pill-closed">Closed</span><?php endif; ?>
      <?php if (!empty($c['sponsor_name'])): ?><span class="pill pill-sponsor">Sponsored</span><?php endif; ?>
    </div>
    <div class="match-meta">
      <span><?= e(plural((int)($c['participant_count'] ?? 0), 'contender')) ?></span>
      <span><?= e(plural((int)$c['votes_total'], 'vote')) ?></span>
      <?php if (isset($c['votes_24h']) && (int)$c['votes_24h'] > 0): ?><span><?= e(number_format((int)$c['votes_24h'])) ?> today</span><?php endif; ?>
    </div>
  </div>
</a>
