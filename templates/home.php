<?php
$prompts = array_values(array_map(static fn($c) => $c['prompt'], array_diff_key(categories(), ['custom' => 1])));
$lists = [
    ['featured', 'star', 'Featured', 'Picked by the FaceMatch team.', null],
    ['trending', 'fire', 'Trending now', 'Most votes in the last 24 hours.', ['See all trending', url('/explore', ['sort' => 'trending'])]],
    ['recent', 'clock', 'Just created', 'The newest public matches.', ['Browse everything', url('/explore')]],
];
?>
<section class="hero">
  <div class="aurora" aria-hidden="true"><span></span><span></span><span></span></div>
  <div class="container hero-grid">
    <div class="hero-copy">
      <h1>Put them side by side. <span class="grad-text">Let the crowd pick.</span></h1>
      <p class="ticker" aria-hidden="true"><?= icon('bolt') ?><span class="ticker-q" data-ticker="<?= e(implode('|', $prompts)) ?>"><?= e($prompts[0]) ?></span></p>
      <p class="hero-lede"><?= db_enabled() ? 'Add photos of your friends, pick a question, share the link and watch head-to-head votes roll in.' : 'Add a few photos, share the link, and everyone votes two at a time. No sign-up, live results.' ?></p>
      <div class="btn-row">
        <a class="btn btn-primary btn-lg btn-shine" href="<?= e(url('/create')) ?>">Start a match <?= icon('arrow') ?></a>
        <a class="btn btn-ghost btn-lg" href="<?= e(url('/explore')) ?>"><?= icon('search') ?> <?= db_enabled() ? 'Explore' : 'See matches' ?></a>
      </div>
      <ul class="trust">
        <li><?= icon('shield') ?> No sign-up</li>
        <li><?= icon('lock') ?> Public or invite-only</li>
        <li><?= icon('chart') ?> Live results</li>
      </ul>
    </div>
    <div class="hero-stage"><?php partial('faceoff_preview'); ?></div>
  </div>
</section>

<?php if (!empty($serverStore)): ?>
<section class="section" data-public-list data-limit="9" data-labels="<?= e(json_encode(array_map(static fn($c) => $c['label'], categories()), JSON_UNESCAPED_UNICODE)) ?>">
  <div class="container">
    <div class="section-head">
      <div><h2><span class="h-icon"><?= icon('fire') ?></span>Latest matches</h2><p>Open one and vote. Everyone’s votes add up to one live leaderboard.</p></div>
      <a class="more-link" href="<?= e(url('/explore')) ?>">See all matches <?= icon('arrow') ?></a>
    </div>
    <div class="match-grid" data-public-grid></div>
    <div class="empty" data-public-empty hidden>
      <h2>No public matches yet</h2>
      <p>Create the first one. It takes about a minute.</p>
      <a class="btn btn-primary" href="<?= e(url('/create')) ?>"><?= icon('plus') ?> Create a match</a>
    </div>
  </div>
</section>
<?php endif; ?>

<?php foreach ($lists as [$var, $ic, $heading, $sub, $link]): ?>
  <?php if (!empty($$var)): ?>
  <section class="section">
    <div class="container">
      <div class="section-head" data-reveal>
        <div><h2><span class="h-icon"><?= icon($ic) ?></span><?= e($heading) ?></h2><p><?= e($sub) ?></p></div>
        <?php if ($link): ?><a class="more-link" href="<?= e($link[1]) ?>"><?= e($link[0]) ?> <?= icon('arrow') ?></a><?php endif; ?>
      </div>
      <div class="match-grid"><?php foreach ($$var as $i => $c): ?><div data-reveal style="--d:<?= $i % 4 ?>"><?php partial('match_card', ['c' => $c]); ?></div><?php endforeach; ?></div>
    </div>
  </section>
  <?php endif; ?>
<?php endforeach; ?>

<?php if (db_enabled()): ?>
<section class="section" id="categories">
  <div class="container">
    <div class="section-head" data-reveal><div><h2>Pick a question</h2><p>Every match asks voters one thing. Tap a category to see its matches or start one.</p></div></div>
    <div class="cat-grid">
      <?php $i = 0; foreach (categories() as $key => $cat): $n = $categoryCounts[$key] ?? 0; if ($key === 'custom' && !$n) { continue; } ?>
        <a class="cat-tile" data-reveal style="--d:<?= $i++ % 5 ?>" href="<?= e($n ? url('/category/' . $key) : url('/create', ['category' => $key])) ?>">
          <span class="cat-icon"><?= icon(category_icon($key)) ?></span>
          <span class="cat-name"><?= e($key === 'custom' ? 'Custom' : $cat['label']) ?></span>
          <small><?= e($cat['prompt']) ?></small>
          <span class="cat-count"><?= $n ? e(plural($n, 'match', 'matches')) : 'Start the first one' ?> <?= icon('arrow') ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section" id="how">
  <div class="container">
    <div class="section-head" data-reveal><div><h2>How it works</h2><p>Three steps, about a minute.</p></div></div>
    <ol class="how">
      <li data-reveal style="--d:0"><span class="how-icon"><?= icon('image') ?></span><h3>Add the contenders</h3><p>A photo and a nickname for each person or team, from 2 up to <?= e(setting_int('max_participants', 20)) ?>. Only add people who agreed to take part.</p></li>
      <li data-reveal style="--d:1"><span class="how-icon"><?= icon('link') ?></span><h3>Share the link</h3><p><?= db_enabled() ? 'Public matches appear in Explore after a quick review. Private matches open only through your invite link.' : (!empty($serverStore) ? 'Public matches appear on the site for everyone. Private ones open only with their link and the access code you send.' : 'Send the link to friends. Choose a private match and it only opens with the access code you give them.') ?></p></li>
      <li data-reveal style="--d:2"><span class="how-icon"><?= icon('bolt') ?></span><h3>Vote head-to-head</h3><p>Voters see two photos at a time and tap a pick<?= !empty($serverStore) ? ', once per pair' : '' ?>. Rankings come from each contender’s win rate across all matchups.</p></li>
    </ol>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="cta-band" data-reveal>
      <div>
        <h2><?= $isEmpty ? 'Be the first face-off' : 'Ready for a face-off?' ?></h2>
        <p><?= !db_enabled() ? 'Add a few photos, pick a question and share the link with your friends. It takes about a minute.' : ($isEmpty ? 'No public matches yet. Start one now and share it with your friends.' : 'Set one up in a minute and share the link with your group.') ?></p>
      </div>
      <a class="btn btn-primary btn-lg btn-shine" href="<?= e(url('/create')) ?>">Start a match <?= icon('arrow') ?></a>
    </div>
  </div>
</section>
