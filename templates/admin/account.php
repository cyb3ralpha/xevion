<h1>My account</h1>
<div class="card pad" style="max-width:520px">
  <p class="muted"><?= e($me['name']) ?> · <?= e($me['email']) ?> · <?= e(admin_roles()[$me['role']]) ?></p>
  <?php if ((int)$me['must_change_password']): ?><div class="notice notice-warn">You’re using a temporary password. Choose a new one to continue.</div><?php endif; ?>
  <?php if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="form-stack">
    <?= csrf_field() ?>
    <label class="field"><span>Current password</span><input type="password" name="current_password" required autocomplete="current-password"></label>
    <label class="field"><span>New password <span class="hint">At least 12 characters</span></span><input type="password" name="new_password" required minlength="12" autocomplete="new-password"></label>
    <label class="field"><span>Repeat new password</span><input type="password" name="new_password2" required minlength="12" autocomplete="new-password"></label>
    <button class="btn btn-primary" type="submit">Change password</button>
  </form>
</div>
