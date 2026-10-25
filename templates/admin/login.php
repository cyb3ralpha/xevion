<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title>Sign in | FaceMatch admin</title>
<link rel="icon" href="<?= e(asset('img/brand/favicon-32.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body>
<main class="admin-login">
  <form method="post" action="<?= e(admin_url('login')) ?>" class="card pad form-stack">
    <img src="<?= e(asset('img/brand/wordmark-dark-md.png')) ?>" alt="XEVION" width="220" height="77">
    <h1 style="font-size:1.4rem;margin:0">FaceMatch admin</h1>
    <?php foreach (flashes() as $f): ?><div class="notice notice-<?= e($f['type']) ?>"><?= e($f['message']) ?></div><?php endforeach; ?>
    <?php if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label class="field"><span>Email</span><input type="email" name="email" required autocomplete="username" value="<?= e($email) ?>" autofocus></label>
    <label class="field"><span>Password</span><input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn-primary" type="submit">Sign in</button>
  </form>
</main>
</body>
</html>
