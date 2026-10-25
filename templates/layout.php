<?php
/** @var string $content */
$title = $title ?? 'FaceMatch';
$path = '/' . trim(substr((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), strlen(base_path())), '/');
$navCurrent = static fn(string $p) => ($path === $p || ($p !== '/' && str_starts_with($path, $p))) ? ' aria-current="page"' : '';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | XEVION FaceMatch</title>
<meta name="description" content="<?= e($metaDescription ?? 'Create photo face-offs, share them with friends and vote head-to-head. FaceMatch by XEVION.') ?>">
<?php if (!empty($noindex)): ?><meta name="robots" content="noindex, nofollow, noarchive"><?php endif; ?>
<meta name="theme-color" content="#0C0727">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:site_name" content="XEVION FaceMatch">
<meta property="og:image" content="<?= e(abs_url('/assets/img/brand/og-mark-512.png')) ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('img/brand/favicon-32.png')) ?>">
<link rel="apple-touch-icon" href="<?= e(asset('img/brand/favicon-180.png')) ?>">
<link rel="preload" href="<?= e(url('/assets/fonts/outfit.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<a class="skip" href="#main">Skip to content</a>
<?php partial('ad_slot', ['placement' => 'top_banner']); ?>
<header class="site-header">
  <nav class="container nav" aria-label="Main">
    <a class="brand" href="<?= e(url('/')) ?>" aria-label="XEVION FaceMatch home">
      <img src="<?= e(asset('img/brand/mark-dark-sm.png')) ?>" alt="" width="28" height="34">
      <span class="brand-product">FaceMatch<small>by XEVION</small></span>
    </a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="nav-links" data-nav-toggle>Menu</button>
    <div class="nav-links" id="nav-links">
      <a href="<?= e(url('/explore')) ?>"<?= $navCurrent('/explore') ?>>Explore</a>
      <a href="<?= e(url('/') . '#categories') ?>">Categories</a>
      <a href="<?= e(url('/') . '#how') ?>">How it works</a>
      <a class="btn btn-primary btn-sm" href="<?= e(url('/create')) ?>">Start a match</a>
    </div>
  </nav>
</header>
<main id="main">
  <?php $fl = flashes(); if ($fl): ?>
  <div class="container flash-stack" role="status">
    <?php foreach ($fl as $f): ?><div class="notice notice-<?= e($f['type']) ?>"><?= e($f['message']) ?></div><?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?= $content ?>
</main>
<?php partial('ad_slot', ['placement' => 'footer_banner']); ?>
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <img src="<?= e(asset('img/brand/wordmark-dark-md.png')) ?>" alt="XEVION — Your vision, our technology" width="220" height="77" loading="lazy">
        <p class="muted small" style="margin-top:12px">FaceMatch is a XEVION product for friendly photo face-offs.</p>
      </div>
      <div>
        <h4>FaceMatch</h4>
        <ul>
          <li><a href="<?= e(url('/create')) ?>">Start a match</a></li>
          <li><a href="<?= e(url('/explore')) ?>">Explore matches</a></li>
          <li><a href="<?= e(url('/contact', ['topic' => 'advertising'])) ?>">Advertise with us</a></li>
        </ul>
      </div>
      <div>
        <h4>Trust &amp; safety</h4>
        <ul>
          <li><a href="<?= e(url('/report')) ?>">Report content</a></li>
          <li><a href="<?= e(url('/privacy')) ?>">Privacy policy</a></li>
          <li><a href="<?= e(url('/terms')) ?>">Terms of service</a></li>
          <li><a href="<?= e(url('/contact')) ?>">Contact</a></li>
        </ul>
      </div>
    </div>
    <p class="footer-legal">© <?= gmdate('Y') ?> XEVION. Votes on FaceMatch are for fun and reflect the opinions of visitors, not facts about anyone pictured.</p>
  </div>
</footer>
</body>
</html>
