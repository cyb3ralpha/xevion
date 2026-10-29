<?php /** @var string $feature */ ?>
<section class="section">
  <div class="container narrow">
    <div class="unavailable" data-reveal>
      <span class="unavailable-icon"><?= icon('shield') ?></span>
      <h1><?= e($feature) ?> is not available here</h1>
      <p>Matches on this site are saved in each visitor&rsquo;s own browser, not on our servers, so there is nothing for this page to show. You can still create a match, vote and share it.</p>
      <div class="btn-row">
        <a class="btn btn-primary" href="<?= e(url('/')) ?>"><?= icon('home') ?> Back to home</a>
        <a class="btn btn-ghost" href="<?= e(url('/create')) ?>"><?= icon('plus') ?> Start a match</a>
      </div>
    </div>
  </div>
</section>
