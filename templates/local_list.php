<?php /** No database: public matches stored on the server plus matches in this browser. Filled in by assets/js/app.js. */ ?>
<section class="page-head">
  <div class="container">
    <h1><?= e($fixedCategory ? category_label(['category' => $fixedCategory]) : ($serverStore ? 'Explore matches' : 'Your matches')) ?></h1>
    <p><?= $fixedCategory ? e(categories()[$fixedCategory]['prompt']) . ' ' : '' ?><?= $serverStore ? 'Vote on public matches from everyone, or open the ones on this device.' : 'Matches you created or opened from a share link are saved in this browser.' ?></p>
  </div>
</section>
<div class="container">
  <nav class="chips" aria-label="Categories">
    <a class="chip" href="<?= e(url('/explore')) ?>"<?= $fixedCategory === null ? ' aria-current="true"' : '' ?>>All</a>
    <?php foreach (categories() as $key => $cat): if ($key === 'custom') { continue; } ?>
      <a class="chip" href="<?= e(url('/category/' . $key)) ?>"<?= $fixedCategory === $key ? ' aria-current="true"' : '' ?>><?= icon(category_icon($key)) ?> <?= e($cat['label']) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php if ($serverStore): ?>
  <section data-public-list data-category="<?= e((string)$fixedCategory) ?>">
    <h2 class="list-head">Public matches</h2>
    <div class="match-grid" data-public-grid></div>
    <p class="muted" data-public-empty hidden>No public matches here yet. Create one and it will appear here for everyone.</p>
    <div class="btn-row" style="margin-top:16px"><button class="btn btn-ghost btn-sm" type="button" data-public-more hidden>Show more</button></div>
  </section>
  <h2 class="list-head">On this device</h2>
  <?php endif; ?>
  <div class="match-grid" data-local-list data-blocked="<?= e(json_encode($blocked)) ?>" data-category="<?= e((string)$fixedCategory) ?>"
       data-labels="<?= e(json_encode($categoryLabels, JSON_UNESCAPED_UNICODE)) ?>"></div>
  <div class="empty" data-local-empty hidden>
    <h2>No matches here yet</h2>
    <p>Create a match and it will appear here. Matches opened from a friend’s share link show up here too.</p>
    <a class="btn btn-primary" href="<?= e(url('/create' . ($fixedCategory ? '?category=' . $fixedCategory : ''))) ?>"><?= icon('plus') ?> Start a match</a>
  </div>
</div>
