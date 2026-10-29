<?php
/** Standalone error page: no database, no session dependency. */
$errorCode = $errorCode ?? 500;
$copy = [
    403 => ['Access denied', 'You don’t have permission to view this page.'],
    404 => ['Page not found', 'This page doesn’t exist, or the match is private or no longer available.'],
    429 => ['Slow down a little', 'Too many requests came from your connection in a short time. Wait a few minutes and try again.'],
    500 => ['Something went wrong', 'An unexpected error occurred on our side. Please try again in a moment.'],
];
[$heading, $text] = $copy[$errorCode] ?? $copy[500];
$text = $errorMessage ?? $text;
$base = function_exists('base_path') ? base_path() : '';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= htmlspecialchars($heading) ?> | XEVION FaceMatch</title>
<link rel="icon" href="<?= htmlspecialchars($base) ?>/media/brand/favicon-32.png">
<link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/app.css">
</head>
<body>
<main class="error-page">
  <div>
    <a href="<?= htmlspecialchars($base) ?>/"><img src="<?= htmlspecialchars($base) ?>/media/brand/mark-dark-sm.png" alt="XEVION FaceMatch home" width="54" height="65" style="margin:0 auto 18px"></a>
    <div class="error-code"><?= (int)$errorCode ?></div>
    <h1 style="font-size:1.8rem;margin-top:8px"><?= htmlspecialchars($heading) ?></h1>
    <p class="muted" style="max-width:44ch;margin:0 auto 24px"><?= htmlspecialchars($text) ?></p>
    <div class="btn-row" style="justify-content:center">
      <a class="btn btn-primary" href="<?= htmlspecialchars($base) ?>/">Go to FaceMatch</a>
      <a class="btn btn-ghost" href="<?= htmlspecialchars($base) ?>/explore">Explore matches</a>
    </div>
  </div>
</main>
</body>
</html>
