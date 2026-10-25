<section class="hero">
  <div class="container hero-grid">
    <div>
      <div class="hero-by"><img src="<?= e(asset('img/brand/mark-dark-sm.png')) ?>" alt="" width="18" height="22"> A XEVION product</div>
      <h1>Put the photos side by side. Let the crowd pick.</h1>
      <p class="hero-lede">FaceMatch turns a group of photos into head-to-head matchups. Choose a category like best smile or most stylish, share the link, and watch the standings build one vote at a time.</p>
      <div class="btn-row">
        <a class="btn btn-primary btn-lg" href="<?= e(url('/create')) ?>">Start a match</a>
        <a class="btn btn-ghost btn-lg" href="<?= e(url('/explore')) ?>">Explore matches</a>
      </div>
      <p class="muted small" style="margin-top:16px">No account needed. Make it public or keep it to an invite-only link.</p>
    </div>
    <div><?php partial('faceoff_preview'); ?></div>
  </div>
</section>

<?php if ($isEmpty): ?>
<section class="section">
  <div class="container">
    <div class="empty">
      <h2>No public matches yet</h2>
      <p>Be the first. Add two or more photos, pick a category and share the link with your friends.</p>
      <a class="btn btn-primary" href="<?= e(url('/create')) ?>">Create the first match</a>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($featured): ?>
<section class="section">
  <div class="container">
    <div class="section-head"><div><h2>Featured</h2><p>Picked by the FaceMatch team.</p></div></div>
    <div class="match-grid"><?php foreach ($featured as $c) { partial('match_card', ['c' => $c]); } ?></div>
  </div>
</section>
<?php endif; ?>

<?php if ($trending): ?>
<section class="section">
  <div class="container">
    <div class="section-head"><div><h2>Trending now</h2><p>Most votes in the last 24 hours.</p></div><a href="<?= e(url('/explore', ['sort' => 'trending'])) ?>">See all trending</a></div>
    <div class="match-grid"><?php foreach ($trending as $c) { partial('match_card', ['c' => $c]); } ?></div>
  </div>
</section>
<?php endif; ?>

<section class="section" id="categories">
  <div class="container">
    <div class="section-head"><div><h2>Categories</h2><p>Every match picks one question for voters to answer.</p></div></div>
    <div class="cat-grid">
      <?php foreach (categories() as $key => $cat): if ($key === 'custom') { continue; } $n = $categoryCounts[$key] ?? 0; ?>
        <a class="cat-tile" href="<?= e(url('/category/' . $key)) ?>"><?= e($cat['label']) ?><small><?= $n ? e(plural($n, 'match', 'matches')) : 'None yet' ?></small></a>
      <?php endforeach; ?>
      <?php if (!empty($categoryCounts['custom'])): ?>
        <a class="cat-tile" href="<?= e(url('/category/custom')) ?>">Custom<small><?= e(plural($categoryCounts['custom'], 'match', 'matches')) ?></small></a>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php if ($recent): ?>
<section class="section">
  <div class="container">
    <div class="section-head"><div><h2>Just created</h2><p>The newest public matches.</p></div><a href="<?= e(url('/explore')) ?>">Browse everything</a></div>
    <div class="match-grid"><?php foreach ($recent as $c) { partial('match_card', ['c' => $c]); } ?></div>
  </div>
</section>
<?php endif; ?>

<section class="section" id="how">
  <div class="container">
    <div class="section-head"><div><h2>How FaceMatch works</h2></div></div>
    <ol class="how">
      <li><h3>Add the contenders</h3><p>Upload a photo and a nickname for each person or team, from 2 up to <?= e(setting_int('max_participants', 20)) ?>. Only add people who agreed to take part.</p></li>
      <li><h3>Share the link</h3><p>Public matches appear in Explore after a quick review. Private matches only open through your invite link.</p></li>
      <li><h3>Vote head-to-head</h3><p>Voters see two photos at a time and tap their pick. Rankings come from each contender’s win rate across all matchups.</p></li>
    </ol>
  </div>
</section>
