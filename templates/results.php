<?php /** @var array $c */ ?>
<div class="container">
  <header class="match-head">
    <?php partial('match_header', compact('c', 'effective')); ?>
    <h1><?= e($c['title']) ?></h1>
    <p class="desc"><?= $effective === 'active' ? 'Live standings — they will keep changing until voting closes.' : 'Final results.' ?></p>
  </header>

  <?php if (!$ranking): ?>
    <div class="empty">
      <h2><?= $c['results_visibility'] === 'after_close' ? 'Results are hidden until voting closes' : 'Results are private' ?></h2>
      <p><?= $c['results_visibility'] === 'after_close' ? 'Come back after ' : 'The organiser chose to keep results to themselves. Voting closes ' ?><?= time_tag($c['closes_at']) ?>.</p>
      <?php if ($effective === 'active'): ?><a class="btn btn-primary" href="<?= e(url('/m/' . $c['slug'] . '/play')) ?>">Keep voting</a><?php endif; ?>
    </div>
  <?php else: $rows = $ranking['rows']; ?>
    <?php if ($winner): $wImg = participant_img($winner, 'full'); ?>
      <section class="winner reveal" aria-label="Winner">
        <?php if ($wImg): ?><img src="<?= e($wImg) ?>" alt="Photo of <?= e($winner['display_name']) ?>"><?php else: ?><div class="ph"></div><?php endif; ?>
        <div>
          <span class="crown">Winner, <?= e(category_label($c)) ?></span>
          <h2><?= e($winner['display_name']) ?></h2>
          <p class="muted">Won <?= number_format($winner['wins']) ?> of <?= number_format($winner['comparisons']) ?> matchups (<?= round($winner['win_rate'] * 100) ?>%).</p>
        </div>
      </section>
    <?php elseif ($effective === 'closed' && $ranking['leader_tied']): ?>
      <div class="notice notice-warn"><strong>It’s a tie for first place.</strong> <?= e(implode(' and ', array_map(static fn($r) => $r['display_name'], array_filter($rows, static fn($r) => $r['rank'] === 1)))) ?> finished level on both win rate and total wins.</div>
    <?php elseif ($effective === 'closed' && $ranking['total_votes'] === 0): ?>
      <div class="notice notice-info">Voting closed without any votes, so there is no winner.</div>
    <?php endif; ?>

    <?php if ($ranking['provisional'] && $ranking['total_votes'] > 0): ?>
      <p class="notice notice-info small">Some contenders have appeared in fewer than 3 matchups, so their positions are still provisional.</p>
    <?php endif; ?>

    <div class="card pad">
      <div class="section-head"><h2>Standings</h2><span class="muted small"><?= e(plural($ranking['total_votes'], 'vote')) ?> counted</span></div>
      <div class="table-scroll">
      <table class="standings">
        <thead><tr><th>#</th><th>Contender</th><th class="num">Win rate</th><th class="num">Won–lost</th><th class="num">Share of votes</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $img = participant_img($r); ?>
          <tr>
            <td class="rank"><?= $r['rank'] ?></td>
            <td><div class="who"><?php if ($img): ?><img src="<?= e($img) ?>" alt=""><?php else: ?><span class="ph"></span><?php endif; ?><span><?= e($r['display_name']) ?><?php if ($r['tied']): ?><span class="tie">Tied</span><?php endif; ?></span></div></td>
            <td class="num"><?= $r['comparisons'] ? round($r['win_rate'] * 100) . '%' : '—' ?><div class="bar"><i style="width:<?= round($r['win_rate'] * 100) ?>%"></i></div></td>
            <td class="num"><?= (int)$r['wins'] ?>–<?= (int)$r['losses'] ?></td>
            <td class="num"><?= number_format($r['vote_share'], 1) ?>%</td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <p class="muted small" style="margin-top:14px">How ranking works: every vote is one head-to-head win. Contenders are ordered by win rate (wins ÷ matchups). If two are level, more total wins ranks higher; if still level they share the position. “Share of votes” is the contender’s wins as a share of all votes.</p>
    </div>

    <?php if (count($rows) > 2 && count($rows) <= 10 && $ranking['total_votes'] > 0): $h = $ranking['h2h']; ?>
    <div class="card pad" style="margin-top:20px">
      <h2>Head to head</h2>
      <p class="muted small">Each cell shows how many times the row contender beat the column contender.</p>
      <div class="table-scroll">
        <table class="h2h">
          <tr><th></th><?php foreach ($rows as $col): ?><th scope="col"><?= e($col['display_name']) ?></th><?php endforeach; ?></tr>
          <?php foreach ($rows as $row): ?>
            <tr><th scope="row"><?= e($row['display_name']) ?></th>
            <?php foreach ($rows as $col): ?><?php if ($row['id'] === $col['id']): ?><td class="self">—</td><?php else: ?><td><?= (int)($h[$row['id']][$col['id']] ?? 0) ?></td><?php endif; ?><?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <div class="btn-row" style="margin-top:24px">
      <?php if ($effective === 'active'): ?><a class="btn btn-primary" href="<?= e(url('/m/' . $c['slug'] . '/play')) ?>">Keep voting</a><?php endif; ?>
      <?php if ($shareUrl): ?><div class="share" style="flex:1;min-width:260px"><input readonly value="<?= e($shareUrl) ?>" aria-label="Results link" data-copy-source><button class="btn btn-ghost btn-sm" type="button" data-copy>Copy results link</button></div><?php endif; ?>
    </div>
  <?php endif; ?>
</div>
