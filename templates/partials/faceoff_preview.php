<?php
/* Illustrative hero animation. Abstract silhouettes, not real people or data. */
$sil = static fn(string $a, string $b) => '<svg viewBox="0 0 200 250" role="presentation" focusable="false"><defs><linearGradient id="g' . $a . '" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' . $a . '"/><stop offset="1" stop-color="' . $b . '"/></linearGradient></defs><rect width="200" height="250" fill="url(#g' . $a . ')"/><circle cx="100" cy="98" r="42" fill="rgba(255,255,255,.18)"/><path d="M28 250c6-58 36-86 72-86s66 28 72 86z" fill="rgba(255,255,255,.14)"/></svg>';
?>
<div class="faceoff-wrap" aria-hidden="true">
  <span class="float-tag t1"><?= icon('smile') ?> Best smile?</span>
  <span class="float-tag t2"><?= icon('glasses') ?> Most stylish?</span>
  <span class="float-tag t3"><?= icon('laugh') ?> Funniest?</span>
  <div class="faceoff">
    <div class="faceoff-side left"><?= $sil('#188CF1', '#0C0727') ?><span class="faceoff-tag">Contender A</span><span class="preview-pick"><?= icon('check') ?></span></div>
    <div class="vs-core"><span class="wing l"></span><span class="wing r"></span><b>VS</b></div>
    <div class="faceoff-side right"><?= $sil('#0659A5', '#17113F') ?><span class="faceoff-tag">Contender B</span><span class="preview-pick"><?= icon('check') ?></span></div>
  </div>
  <div class="vote-meter"><i></i></div>
</div>
