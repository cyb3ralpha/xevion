<?php /** @var array $c */ ?>
<div class="pills">
  <span class="pill pill-cat"><?= e(category_label($c)) ?></span>
  <?php if ($effective === 'active'): ?><span class="pill pill-live">Voting open, <?= e(time_left($c['closes_at'])) ?></span><?php else: ?><span class="pill pill-closed">Voting closed</span><?php endif; ?>
  <?php if ($c['visibility'] === 'private'): ?><span class="pill pill-private">Private</span><?php endif; ?>
  <?php if (!empty($c['sponsor_name'])): ?><span class="pill pill-sponsor">Sponsored by <?= e($c['sponsor_name']) ?></span><?php endif; ?>
</div>
