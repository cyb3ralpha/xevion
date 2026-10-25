<section class="page-head"><div class="container narrow"><h1>Report content</h1>
<p>Tell us about photos, titles or behaviour that break our rules. Reports go to the moderation team; your details are never shown publicly.</p></div></section>
<div class="container narrow">
<?php if ($sent): ?>
  <div class="notice notice-success" role="status"><strong>Report received.</strong> Moderators will review it. Photos with several reports, or reports of someone under 18, are hidden while they are checked.</div>
  <p><a href="<?= e($c ? url('/m/' . $c['slug']) : url('/')) ?>">Back</a></p>
<?php else: ?>
  <form method="post" class="card pad form-stack" action="<?= e(url('/report', ['m' => $c['slug'] ?? null, 'p' => $participant['public_id'] ?? null])) ?>">
    <?= csrf_field() ?>
    <?php if (!empty($errors['form'])): ?><div class="notice notice-error" role="alert"><?= e($errors['form']) ?></div><?php endif; ?>
    <?php if ($c): ?>
      <p class="notice notice-info" style="margin:0">Reporting <?= $participant ? 'the photo of <strong>' . e($participant['display_name']) . '</strong> in ' : '' ?>“<?= e($c['title']) ?>”.</p>
    <?php endif; ?>
    <fieldset class="field<?= !empty($errors['reason']) ? ' has-error' : '' ?>" style="border:0;padding:0;margin:0">
      <legend class="field-label">What’s wrong?</legend>
      <?php foreach ($reasons as $k => $l): ?>
        <label class="check"><input type="radio" name="reason" value="<?= e($k) ?>"<?= (($_POST['reason'] ?? '') === $k) ? ' checked' : '' ?> required><span><?= e($l) ?></span></label>
      <?php endforeach; ?>
      <?php partial('field_error', ['errors' => $errors, 'name' => 'reason']); ?>
    </fieldset>
    <label class="field<?= !empty($errors['details']) ? ' has-error' : '' ?>"><span>Details <span class="hint"><?= $c ? 'Optional' : 'Include the link to the match' ?></span></span>
      <textarea name="details" maxlength="1000"><?= e($_POST['details'] ?? '') ?></textarea>
      <?php partial('field_error', ['errors' => $errors, 'name' => 'details']); ?></label>
    <label class="field<?= !empty($errors['email']) ? ' has-error' : '' ?>"><span>Your email <span class="hint">Optional — only if you’d like a reply. If it’s your photo, this helps us confirm removal.</span></span>
      <input type="email" name="email" maxlength="190" value="<?= e($_POST['email'] ?? '') ?>">
      <?php partial('field_error', ['errors' => $errors, 'name' => 'email']); ?></label>
    <button class="btn btn-primary" type="submit">Send report</button>
  </form>
<?php endif; ?>
</div>
