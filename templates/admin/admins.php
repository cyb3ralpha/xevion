<h1>Administrators</h1>
<?php if ($shown): ?><div class="link-box secret" style="margin-bottom:18px"><strong>Temporary password for <?= e($shown['email']) ?></strong><span class="muted small">Shown once. They must change it at first sign-in.</span><div class="row"><input readonly value="<?= e($shown['pw']) ?>" data-copy-source><button class="btn btn-ghost btn-sm" type="button" data-copy>Copy</button></div></div><?php endif; ?>
<div class="card table-scroll" style="margin-bottom:20px"><table class="tbl">
  <thead><tr><th>Name</th><th>Role</th><th>Status</th><th>Last sign-in</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($rows as $a): $self = (int)$a['id'] === (int)$me['id']; ?>
    <tr><td><?= e($a['name']) ?><?= $self ? ' (you)' : '' ?><br><span class="muted small"><?= e($a['email']) ?></span></td>
    <td><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="admin_id" value="<?= (int)$a['id'] ?>"><select name="role"><?php foreach (admin_roles() as $k => $l): ?><option value="<?= $k ?>"<?= $a['role'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><button class="btn btn-ghost btn-sm">Save</button></form></td>
    <td><?= (int)$a['is_active'] ? 'Active' : 'Deactivated' ?><?= (int)$a['must_change_password'] ? '<br><span class="muted small">Temporary password</span>' : '' ?></td>
    <td><?= time_tag($a['last_login_at']) ?></td>
    <td><?php if (!$self): ?>
      <form method="post" class="inline-form" data-confirm="<?= (int)$a['is_active'] ? 'Deactivate' : 'Activate' ?> this account?"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="admin_id" value="<?= (int)$a['id'] ?>"><button class="btn btn-ghost btn-sm"><?= (int)$a['is_active'] ? 'Deactivate' : 'Activate' ?></button></form>
      <form method="post" class="inline-form" data-confirm="Issue a temporary password? Their current password stops working."><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="admin_id" value="<?= (int)$a['id'] ?>"><button class="btn btn-ghost btn-sm">Reset password</button></form>
    <?php else: ?><a href="<?= e(admin_url('account')) ?>">Change my password</a><?php endif; ?></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
<section class="card pad" style="max-width:640px">
  <h2>Add administrator</h2>
  <form method="post" class="form-stack"><?= csrf_field() ?><input type="hidden" name="action" value="create">
    <div class="grid-2"><label class="field"><span>Name</span><input name="name" required maxlength="80"></label><label class="field"><span>Email</span><input type="email" name="email" required maxlength="190"></label></div>
    <label class="field"><span>Role</span><select name="role"><?php foreach (admin_roles() as $k => $l): ?><option value="<?= $k ?>"<?= $k === 'moderator' ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    <button class="btn btn-primary btn-sm">Create with temporary password</button>
  </form>
  <p class="muted small" style="margin-top:12px">Super Administrator: everything. Administrator: competitions, moderation, reports, votes, messages, audit. Moderator: review, reports, suspensions. Advertising Manager: campaigns and their statistics.</p>
</section>
