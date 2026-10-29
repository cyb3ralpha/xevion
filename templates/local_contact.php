<?php /** Contact and report pages when the site runs without a database. @var string $kind @var string $email */ ?>
<section class="page-head">
  <div class="container narrow">
    <h1><?= $kind === 'report' ? 'Report a match' : 'Contact' ?></h1>
    <p><?= $kind === 'report'
        ? 'Seen a match that breaks the rules, or a photo of you shared without permission? Tell us and we can block it on this site.'
        : 'Questions, feedback or a photo removal request.' ?></p>
  </div>
</section>
<div class="container narrow">
  <div class="card pad form-stack">
    <?php if ($kind === 'report'): ?>
      <ol class="steps-list" style="margin:0;padding-left:20px">
        <li>Open the match and copy its <strong>match ID</strong> (shown at the bottom of the match page) or its share link.</li>
        <li>Send it to us with a short reason.</li>
        <li>We block the match so it no longer opens on this site. Copies already saved on someone’s phone can only be deleted by them.</li>
      </ol>
    <?php endif; ?>
    <?php if ($email !== ''): ?>
      <p style="margin:0">Email us at <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>.</p>
      <div class="btn-row">
        <a class="btn btn-primary" href="mailto:<?= e($email) ?>?subject=<?= rawurlencode($kind === 'report' ? 'FaceMatch report' : 'FaceMatch question') ?>"><?= icon('link') ?> <?= $kind === 'report' ? 'Email a report' : 'Send an email' ?></a>
      </div>
    <?php else: ?>
      <p class="muted" style="margin:0">A contact address hasn’t been published yet. The site owner can add one with the <code>CONTACT_EMAIL</code> setting.</p>
    <?php endif; ?>
    <p class="muted small" style="margin:0">Matches on this site are saved in visitors’ browsers, not on our servers, so we can’t delete someone else’s saved copy. Blocking stops a match from opening here for everyone.</p>
  </div>
</div>
