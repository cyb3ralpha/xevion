<?php /** @var string $feature */ ?>
<section class="section">
  <div class="container narrow">
    <div class="unavailable" data-reveal>
      <span class="unavailable-icon"><?= icon('clock') ?></span>
      <h1><?= e($feature) ?> is coming soon</h1>
      <p>This site is running in preview mode, so nothing can be saved yet. You can look around, browse the categories and see how FaceMatch works.</p>
      <div class="btn-row">
        <a class="btn btn-primary" href="<?= e(url('/')) ?>"><?= icon('home') ?> Back to home</a>
        <a class="btn btn-ghost" href="<?= e(url('/') . '#how') ?>">How it works</a>
      </div>
    </div>
  </div>
</section>
