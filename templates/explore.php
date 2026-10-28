<?php $f = $filters; ?>
<section class="page-head">
  <div class="container">
    <h1><?= e($fixedCategory ? category_label(['category' => $fixedCategory]) : 'Explore matches') ?></h1>
    <p><?= $fixedCategory ? e(categories()[$fixedCategory]['prompt']) : 'Vote on public face-offs or find one to share.' ?></p>
  </div>
</section>
<div class="container">
  <nav class="chips" aria-label="Categories">
    <a class="chip" href="<?= e(url('/explore', ['sort' => $f['sort'] !== 'recent' ? $f['sort'] : null, 'status' => $f['status'], 'q' => $f['q']])) ?>"<?= $f['category'] === '' ? ' aria-current="true"' : '' ?>>All</a>
    <?php foreach (categories() as $key => $cat): ?>
      <?php if ($key === 'custom' && empty($categoryCounts['custom'])) { continue; } ?>
      <a class="chip" href="<?= e(url('/category/' . $key, ['sort' => $f['sort'] !== 'recent' ? $f['sort'] : null, 'status' => $f['status'], 'q' => $f['q']])) ?>"<?= $f['category'] === $key ? ' aria-current="true"' : '' ?>><?= icon(category_icon($key)) ?> <?= e($cat['label']) ?><?php if (!empty($categoryCounts[$key])): ?> <span class="chip-n"><?= (int)$categoryCounts[$key] ?></span><?php endif; ?></a>
    <?php endforeach; ?>
  </nav>
  <form class="filters" method="get" action="<?= e($fixedCategory ? url('/category/' . $fixedCategory) : url('/explore')) ?>" role="search">
    <label class="field"><span>Search</span><input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="e.g. best smile"></label>
    <?php if (!$fixedCategory && $f['category'] !== ''): ?><input type="hidden" name="category" value="<?= e($f['category']) ?>"><?php endif; ?>
    <label class="field"><span>Status</span>
      <select name="status"><option value="">Any</option><option value="open"<?= $f['status'] === 'open' ? ' selected' : '' ?>>Open for voting</option><option value="closed"<?= $f['status'] === 'closed' ? ' selected' : '' ?>>Closed</option></select>
    </label>
    <label class="field"><span>Sort by</span>
      <select name="sort">
        <?php foreach (['recent' => 'Newest', 'activity' => 'Recent activity', 'trending' => 'Trending (24h)', 'popular' => 'Most votes', 'closing' => 'Closing soon'] as $k => $l): ?>
          <option value="<?= e($k) ?>"<?= $f['sort'] === $k ? ' selected' : '' ?>><?= e($l) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn btn-ghost" type="submit">Apply</button>
  </form>
  <p class="muted small"><?= e(plural($total, 'match', 'matches')) ?></p>
  <?php if ($items): ?>
    <div class="match-grid"><?php foreach ($items as $c) { partial('match_card', ['c' => $c]); } ?></div>
    <?php if ($pages > 1): $qs = ['category' => $fixedCategory ? null : $f['category'], 'status' => $f['status'], 'sort' => $f['sort'], 'q' => $f['q']]; $base = $fixedCategory ? '/category/' . $fixedCategory : '/explore'; ?>
      <nav class="pager" aria-label="Pages">
        <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="<?= e(url($base, $qs + ['page' => $page - 1])) ?>">Previous</a><?php endif; ?>
        <span class="muted">Page <?= $page ?> of <?= $pages ?></span>
        <?php if ($page < $pages): ?><a class="btn btn-ghost btn-sm" href="<?= e(url($base, $qs + ['page' => $page + 1])) ?>">Next</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php else: ?>
    <div class="empty">
      <h2>Nothing matches these filters</h2>
      <p><?= $f['q'] !== '' || $f['status'] !== '' ? 'Try clearing the search or status filter.' : 'There are no public matches in this category yet. Start one and share it.' ?></p>
      <a class="btn btn-primary" href="<?= e(url('/create')) ?>">Start a match</a>
    </div>
  <?php endif; ?>
</div>
