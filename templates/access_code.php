<section class="page-head"><div class="container narrow"><h1>Private match</h1><p>The organiser protected this match with an access code. Enter it to continue.</p></div></section>
<div class="container narrow">
  <form method="post" action="<?= e($actionUrl) ?>" class="card pad form-stack" style="max-width:420px">
    <?= csrf_field() ?>
    <?php if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <label class="field"><span>Access code</span><input name="code" required autocomplete="off" autocapitalize="characters" maxlength="40" autofocus></label>
    <button class="btn btn-primary" type="submit">Open match</button>
  </form>
</div>
