<?php /** Matches saved in this browser (no database). Filled in by assets/js/app.js. */ ?>
<section class="page-head">
  <div class="container">
    <h1><?= e($fixedCategory ? category_label(['category' => $fixedCategory]) : 'Your matches') ?></h1>
    <p><?= $fixedCategory ? e(categories()[$fixedCategory]['prompt']) . ' ' : '' ?>Matches you created or opened from a share link are saved in this browser.</p>
  </div>
</section>
<div class="container">
  <nav class="chips" aria-label="Categories">
    <a class="chip" href="<?= e(url('/explore')) ?>"<?= $fixedCategory === null ? ' aria-current="true"' : '' ?>>All</a>
    <?php foreach (categories() as $key => $cat): if ($key === 'custom') { continue; } ?>
      <a class="chip" href="<?= e(url('/category/' . $key)) ?>"<?= $fixedCategory === $key ? ' aria-current="true"' : '' ?>><?= icon(category_icon($key)) ?> <?= e($cat['label']) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="match-grid" data-local-list data-category="<?= e((string)$fixedCategory) ?>"
       data-labels="<?= e(json_encode($categoryLabels, JSON_UNESCAPED_UNICODE)) ?>"></div>
  <div class="empty" data-local-empty hidden>
    <h2>No matches here yet</h2>
    <p>Create a match and it will appear here. Matches opened from a friend’s share link show up here too.</p>
    <a class="btn btn-primary" href="<?= e(url('/create' . ($fixedCategory ? '?category=' . $fixedCategory : ''))) ?>"><?= icon('plus') ?> Start a match</a>
  </div>
</div>
