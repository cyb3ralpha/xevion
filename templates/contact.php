<section class="page-head"><div class="container narrow"><h1>Contact</h1>
<p>Questions, advertising and sponsorship enquiries, privacy requests or photo removal requests.<?php if ($contactEmail): ?> You can also email <a href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a>.<?php endif; ?></p></div></section>
<div class="container narrow">
<?php if ($sent): ?>
  <div class="notice notice-success" role="status"><strong>Message sent.</strong> We’ll reply to <?= e($old['email']) ?>.</div>
<?php else: ?>
  <form method="post" action="<?= e(url('/contact')) ?>" class="card pad form-stack">
    <?= csrf_field() ?>
    <?php if (!empty($errors['form'])): ?><div class="notice notice-error" role="alert"><?= e($errors['form']) ?></div><?php endif; ?>
    <div class="grid-2">
      <label class="field<?= !empty($errors['name']) ? ' has-error' : '' ?>"><span>Name</span><input name="name" maxlength="80" required value="<?= e($old['name']) ?>"><?php partial('field_error', ['errors' => $errors, 'name' => 'name']); ?></label>
      <label class="field<?= !empty($errors['email']) ? ' has-error' : '' ?>"><span>Email</span><input type="email" name="email" maxlength="190" required value="<?= e($old['email']) ?>"><?php partial('field_error', ['errors' => $errors, 'name' => 'email']); ?></label>
    </div>
    <label class="field"><span>Topic</span><select name="topic"><?php foreach ($topics as $k => $l): ?><option value="<?= e($k) ?>"<?= $old['topic'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    <label class="field<?= !empty($errors['message']) ? ' has-error' : '' ?>"><span>Message</span><textarea name="message" maxlength="3000" required><?= e($old['message']) ?></textarea><?php partial('field_error', ['errors' => $errors, 'name' => 'message']); ?></label>
    <label class="sr-only" aria-hidden="true">Leave this empty <input name="website" tabindex="-1" autocomplete="off"></label>
    <button class="btn btn-primary" type="submit">Send message</button>
  </form>
<?php endif; ?>
</div>
