<?php /** @var array $c */ $n = $next; $pair = $n['pair'] ?? null; ?>
<div class="container arena-wrap"
     data-arena data-slug="<?= e($c['slug']) ?>" data-csrf="<?= e(csrf_token()) ?>"
     data-next-url="<?= e(url('/api/next', ['m' => $c['slug']])) ?>" data-vote-url="<?= e(url('/api/vote')) ?>"
     data-results-url="<?= $resultsVisible ? e(url('/m/' . $c['slug'] . '/results')) : '' ?>">
  <div class="arena-top">
    <div>
      <?php partial('match_header', compact('c', 'effective')); ?>
      <h1 style="margin-top:8px"><?= e($c['title']) ?></h1>
      <p class="arena-prompt"><?= e($c['instructions'] ?: (categories()[$c['category']]['prompt'] ?? 'Pick your favourite')) ?></p>
    </div>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/m/' . $c['slug'])) ?>">Match details</a>
  </div>

  <?php $pct = isset($n['progress']) && $n['progress']['total'] > 0 ? round($n['progress']['done'] * 100 / $n['progress']['total']) : 0; ?>
  <div class="progress" role="progressbar" aria-label="Matchups you have voted on" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $pct ?>" data-progress><i style="width:<?= $pct ?>%"></i></div>

  <div data-arena-body>
  <?php if ($n['status'] === 'ok'): ?>
    <form method="post" action="<?= e(url('/m/' . $c['slug'] . '/play')) ?>" data-vote-form>
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($n['token']) ?>">
      <input type="hidden" name="key" value="<?= e($idem) ?>">
      <fieldset style="border:0;padding:0;margin:0">
        <legend class="sr-only">Choose one contender</legend>
        <div class="arena" data-arena-grid>
          <?php foreach ($pair as $i => $side): ?>
            <?php if ($i === 1): ?><div class="vs-core" aria-hidden="true"><span class="wing l"></span><span class="wing r"></span><b>VS</b></div><?php endif; ?>
            <button class="contender <?= $i === 0 ? 'left' : 'right' ?>" type="submit" name="choice" value="<?= e($side['id']) ?>" data-choice="<?= e($side['id']) ?>" aria-label="Vote for <?= e($side['name']) ?>">
              <img src="<?= e($side['image']) ?>" alt="" width="600" height="750">
              <span class="split" data-split></span>
              <span class="name"><span data-name><?= e($side['name']) ?></span><span class="hint kbd-hint">Press <?= $i === 0 ? '1 or ←' : '2 or →' ?></span></span>
            </button>
          <?php endforeach; ?>
        </div>
      </fieldset>
    </form>
    <p class="arena-status" data-arena-status aria-live="polite"></p>
  <?php elseif ($n['status'] === 'complete'): ?>
    <div class="arena-state card">
      <h2>You’ve voted on every matchup</h2>
      <p class="muted">Thanks! That’s all <?= (int)$n['progress']['total'] ?> pairs in this match.</p>
      <?php if ($resultsVisible): ?><a class="btn btn-primary" href="<?= e(url('/m/' . $c['slug'] . '/results')) ?>">See results</a><?php else: ?><p class="muted"><?= $c['results_visibility'] === 'after_close' ? 'Results are revealed when voting closes.' : 'Only the organiser can see the results.' ?></p><?php endif; ?>
    </div>
  <?php elseif ($n['status'] === 'closed'): ?>
    <div class="arena-state card">
      <h2>Voting has closed</h2>
      <?php if ($resultsVisible): ?><a class="btn btn-primary" href="<?= e(url('/m/' . $c['slug'] . '/results')) ?>">See results</a><?php else: ?><p class="muted">The organiser has kept results private.</p><?php endif; ?>
    </div>
  <?php else: ?>
    <div class="arena-state card">
      <h2>Not enough contenders right now</h2>
      <p class="muted">Some photos are being reviewed by moderators. Check back soon.</p>
    </div>
  <?php endif; ?>
  </div>

  <div class="arena-foot">
    <span>Tap a photo to vote.<span class="kbd-hint"> Keyboard: <span class="kbd">1</span> / <span class="kbd">←</span> left, <span class="kbd">2</span> / <span class="kbd">→</span> right.</span></span>
    <span>
      <?php if ($resultsVisible): ?><a href="<?= e(url('/m/' . $c['slug'] . '/results')) ?>">Results</a> · <?php endif; ?>
      <a href="<?= e(url('/report', ['m' => $c['slug']])) ?>">Report</a>
    </span>
  </div>
</div>
