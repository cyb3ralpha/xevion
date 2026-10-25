<?php /** @var array $c */ ?>
<div class="container">
  <header class="match-head">
    <?php partial('match_header', compact('c', 'effective')); ?>
    <h1><?= e($c['title']) ?></h1>
    <?php if ($description): ?><p class="desc"><?= nl2br(e($description)) ?></p><?php endif; ?>
    <div class="facts">
      <span><strong><?= count($participants) ?></strong> contenders</span>
      <span><strong><?= number_format((int)$c['votes_total']) ?></strong> votes</span>
      <span><?= $effective === 'active' ? 'Closes' : 'Closed' ?> <?= time_tag($effective === 'active' ? $c['closes_at'] : ($c['closed_at'] ?: $c['closes_at'])) ?></span>
      <span>Results: <?= e(results_visibility_label($c['results_visibility'])) ?></span>
    </div>
  </header>

  <?php if ($c['visibility'] === 'public' && $c['moderation_status'] === 'pending'): ?>
    <div class="notice notice-info">This match is awaiting review. It works through its link but isn’t listed in Explore yet.</div>
  <?php elseif ($c['moderation_status'] === 'rejected'): ?>
    <div class="notice notice-warn">Moderators did not approve this match for public listing. Only you can see it.</div>
  <?php endif; ?>
  <?php if ($creator): ?><div class="notice notice-info">You created this match. Use your private management link to change settings.</div><?php endif; ?>

  <div class="side-layout" style="margin-top:20px">
    <section aria-label="Contenders">
      <div class="people">
        <?php foreach ($participants as $p): $img = participant_img($p); ?>
          <figure class="person" style="margin:0">
            <?php if ($img): ?><img src="<?= e($img) ?>" alt="Photo of <?= e($p['display_name']) ?>" loading="lazy" width="480" height="600"><?php else: ?><div class="ph">Photo under review</div><?php endif; ?>
            <figcaption><?= e($p['display_name']) ?> <a href="<?= e(url('/report', ['m' => $c['slug'], 'p' => $p['public_id']])) ?>" aria-label="Report photo of <?= e($p['display_name']) ?>">Report</a></figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
    </section>
    <aside class="stack">
      <div class="card pad stack">
        <?php if ($effective === 'active'): ?>
          <a class="btn btn-primary btn-lg" href="<?= e(url('/m/' . $c['slug'] . '/play')) ?>">Start voting</a>
          <p class="muted small" style="margin:0">You’ll see two contenders at a time. Each pair can get one vote from your browser.</p>
        <?php else: ?>
          <p style="margin:0"><strong>Voting has closed.</strong></p>
        <?php endif; ?>
        <?php if ($resultsVisible): ?><a class="btn btn-ghost" href="<?= e(url('/m/' . $c['slug'] . '/results')) ?>">See results</a>
        <?php else: ?><p class="muted small" style="margin:0"><?= $c['results_visibility'] === 'after_close' ? 'Results are revealed when voting closes.' : 'Only the organiser can see the results.' ?></p><?php endif; ?>
      </div>
      <?php if ($shareUrl): ?>
      <div class="card pad stack">
        <strong>Share</strong>
        <div class="share"><input readonly value="<?= e($shareUrl) ?>" aria-label="Share link" data-copy-source><button class="btn btn-ghost btn-sm" type="button" data-copy>Copy</button></div>
        <button class="btn btn-ghost btn-sm" type="button" data-share data-share-title="<?= e($c['title']) ?>" data-share-url="<?= e($shareUrl) ?>" hidden>Share…</button>
      </div>
      <?php elseif ($c['visibility'] === 'private'): ?>
      <div class="card pad"><p class="muted small" style="margin:0">This is a private match. Share it only by passing on the invite link you received.</p></div>
      <?php endif; ?>
      <?php if (!empty($c['sponsor_name']) && !empty($c['sponsor_url'])): ?>
      <div class="card pad"><span class="muted small">Sponsored by</span><br><a href="<?= e($c['sponsor_url']) ?>" rel="sponsored noopener" target="_blank"><?= e($c['sponsor_name']) ?></a></div>
      <?php endif; ?>
      <p class="small"><a href="<?= e(url('/report', ['m' => $c['slug']])) ?>">Report this match</a></p>
    </aside>
  </div>
</div>
