<?php
$route = admin_route();
$counts = [];
try {
    if (admin_can('moderation')) {
        $counts['moderation'] = (int)q_val("SELECT COUNT(*) FROM participants p JOIN competitions c ON c.id = p.competition_id WHERE p.is_removed = 0 AND c.status <> 'deleted' AND p.image_status IN ('pending','flagged','escalated')")
            + (int)q_val("SELECT COUNT(*) FROM competitions WHERE visibility = 'public' AND moderation_status = 'pending' AND status IN ('active','closed')");
    }
    if (admin_can('reports')) {
        $counts['reports'] = (int)q_val("SELECT COUNT(*) FROM reports WHERE status IN ('open','investigating')");
    }
    if (admin_can('messages')) {
        $counts['messages'] = (int)q_val("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'");
    }
} catch (Throwable $e) {
}
$nav = [
    ['', 'Dashboard', 'dashboard'],
    ['competitions', 'Competitions', 'competitions.view'],
    ['moderation', 'Moderation', 'moderation'],
    ['reports', 'Reports', 'reports'],
    ['ads', 'Advertising', 'ads'],
    ['messages', 'Messages', 'messages'],
    ['blocks', 'Abuse blocks', 'blocks'],
    ['audit', 'Audit log', 'audit.view'],
    ['admins', 'Administrators', 'admins'],
    ['settings', 'Settings', 'settings'],
    ['account', 'My account', null],
];
$cur = ['competition' => 'competitions', 'ad' => 'ads'][$route] ?? $route;
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title ?? 'Admin') ?> | FaceMatch admin</title>
<link rel="icon" href="<?= e(asset('img/brand/favicon-32.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="admin">
<aside class="admin-side">
  <a class="brand" href="<?= e(admin_url()) ?>"><img src="<?= e(asset('img/brand/mark-dark-sm.png')) ?>" alt="" width="28" height="34"><span class="brand-product">FaceMatch<small>Admin</small></span></a>
  <nav aria-label="Admin">
    <?php foreach ($nav as [$r, $label, $perm]): if ($perm && !admin_can($perm)) { continue; } ?>
      <a href="<?= e(admin_url($r)) ?>"<?= $cur === $r ? ' aria-current="page"' : '' ?>><?= e($label) ?><?php if (!empty($counts[$r])): ?><span class="count<?= $r === 'reports' ? ' warn' : '' ?>"><?= $counts[$r] ?></span><?php endif; ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="who">
    Signed in as <strong><?= e($admin['name']) ?></strong><br><?= e(admin_roles()[$admin['role']] ?? $admin['role']) ?>
    <form method="post" action="<?= e(admin_url('logout')) ?>" style="margin-top:10px"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Sign out</button></form>
  </div>
</aside>
<main class="admin-main" id="main">
  <?php foreach (flashes() as $f): ?><div class="notice notice-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div><?php endforeach; ?>
  <?= $content ?>
</main>
</body>
</html>
