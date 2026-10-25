<h1>Settings</h1>
<form method="post" class="card pad form-stack" style="max-width:760px">
  <?= csrf_field() ?>
  <?php foreach ($schema as $key => $def): $v = (string)($values[$key] ?? ''); ?>
    <?php if ($def[1] === 'bool'): ?>
      <label class="check"><input type="checkbox" name="<?= e($key) ?>" value="1"<?= $v === '1' ? ' checked' : '' ?>><span><?= e($def[0]) ?></span></label>
    <?php elseif ($def[1] === 'text'): ?>
      <label class="field"><span><?= e($def[0]) ?> <span class="hint">Matched against titles, categories, descriptions and names. Add terms in the languages your audience uses.</span></span><textarea name="<?= e($key) ?>" rows="10"><?= e($v) ?></textarea></label>
    <?php else: ?>
      <label class="field<?= !empty($errors[$key]) ? ' has-error' : '' ?>"><span><?= e($def[0]) ?></span><input name="<?= e($key) ?>" type="<?= $def[1] === 'int' ? 'number' : 'email' ?>" value="<?= e($v) ?>"<?= $def[1] === 'int' ? ' min="' . $def[2] . '" max="' . $def[3] . '"' : '' ?>>
      <?php if (!empty($errors[$key])): ?><span class="field-error"><?= e($errors[$key]) ?></span><?php endif; ?></label>
    <?php endif; ?>
  <?php endforeach; ?>
  <button class="btn btn-primary" type="submit">Save settings</button>
</form>
