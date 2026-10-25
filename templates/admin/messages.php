<div class="admin-top"><h1>Messages</h1><nav class="chips" style="margin:0"><a class="chip" href="<?= e(admin_url('messages')) ?>"<?= $status !== 'archived' ? ' aria-current="true"' : '' ?>>Inbox</a><a class="chip" href="<?= e(admin_url('messages', ['status' => 'archived'])) ?>"<?= $status === 'archived' ? ' aria-current="true"' : '' ?>>Archived</a></nav></div>
<?php if (!$rows): ?><div class="empty"><h2>No messages</h2></div><?php else: ?>
<div class="stack">
<?php foreach ($rows as $m): ?>
  <article class="card pad"<?= $m['status'] === 'new' ? ' style="border-color:var(--x-blue)"' : '' ?>>
    <div class="admin-top" style="margin-bottom:8px"><strong><?= e($m['name']) ?> &lt;<a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a>&gt;</strong><span class="muted small"><?= e($m['topic']) ?> · <?= time_tag($m['created_at']) ?></span></div>
    <p style="white-space:pre-wrap"><?= e($m['message']) ?></p>
    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="message_id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="back" value="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>">
      <?php if ($m['status'] === 'new'): ?><button class="btn btn-ghost btn-sm" name="status" value="read">Mark read</button><?php endif; ?>
      <?php if ($m['status'] !== 'archived'): ?><button class="btn btn-ghost btn-sm" name="status" value="archived">Archive</button><?php else: ?><button class="btn btn-ghost btn-sm" name="status" value="read">Move to inbox</button><?php endif; ?>
    </form>
  </article>
<?php endforeach; ?>
</div><?php endif; ?>
