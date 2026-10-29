<?php /** No database: public matches stored on the server plus matches in this browser. Filled in by assets/js/app.js. */ ?>
<section class="page-head">
  <div class="container">
    <h1><?= e($fixedCategory ? category_label(['category' => $fixedCategory]) : ($serverStore ? 'Matches' : 'Your matches')) ?></h1>
    <p><?= $fixedCategory ? e(categories()[$fixedCategory]['prompt']) . ' ' : '' ?><?= $serverStore ? 'Pick a match and vote. Every vote counts towards its live leaderboard.' : 'Matches you created or opened from a share link are saved in this browser.' ?></p>
  </div>
</section>
<div class="container">
  <?php if ($serverStore): ?>
  <section data-public-list data-category="<?= e((string)$fixedCategory) ?>">
    <h2 class="list-head">Public matches</h2>
    <div class="match-grid" data-public-grid></div>
    <div class="empty" data-public-empty hidden>
      <h2>No public matches yet</h2>
      <p>Create one and it appears here for everyone to vote on.</p>
      <a class="btn btn-primary" href="<?= e(url('/create')) ?>"><?= icon('plus') ?> Create a match</a>
    </div>
    <div class="btn-row" style="margin-top:16px"><button class="btn btn-ghost btn-sm" type="button" data-public-more hidden>Show more</button></div>
  </section>
  <div data-local-section>
  <h2 class="list-head">Your private and saved matches</h2>
  <?php endif; ?>
  <div class="match-grid" data-local-list data-blocked="<?= e(json_encode($blocked)) ?>" data-category="<?= e((string)$fixedCategory) ?>"
       data-labels="<?= e(json_encode($categoryLabels, JSON_UNESCAPED_UNICODE)) ?>"></div>
  <div class="empty" data-local-empty hidden>
    <?php if ($serverStore): ?>
    <p class="muted">Private matches you create or unlock show up here.</p>
    <?php else: ?>
    <h2>No matches here yet</h2>
    <p>Create a match and it will appear here. Matches opened from a friend’s share link show up here too.</p>
    <a class="btn btn-primary" href="<?= e(url('/create')) ?>"><?= icon('plus') ?> Start a match</a>
    <?php endif; ?>
  </div>
  <?php if ($serverStore): ?></div><?php endif; ?>
</div>
