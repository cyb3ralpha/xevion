<?php
/** Advertising slot. Paid placements are always labelled. */
$placement = $placement ?? 'top_banner';
try {
    $ad = ad_pick($placement);
} catch (Throwable $e) {
    $ad = null;
}
if ($ad) {
    ad_record_impression($ad);
} elseif ($placement !== 'top_banner' || setting('show_house_ad', '1') !== '1') {
    return;
}
?>
<aside class="ad-slot <?= $placement === 'footer_banner' ? 'footer' : 'top' ?>" aria-label="<?= $ad ? 'Advertisement' : 'Advertising information' ?>">
  <div class="ad-inner">
  <?php if ($ad): ?>
    <span class="ad-label">Advertisement</span>
    <a class="ad-creative" href="<?= e(ad_click_url($ad)) ?>" rel="sponsored noopener" target="_blank">
      <?php if ($ad['creative_type'] === 'image' && $ad['image_file']): ?>
        <picture>
          <?php if ($ad['mobile_image_file']): ?><source media="(max-width: 640px)" srcset="<?= e(ad_media_url($ad, true)) ?>"><?php endif; ?>
          <img src="<?= e(ad_media_url($ad)) ?>" alt="<?= e($ad['headline'] ?: ('Advertisement from ' . $ad['advertiser'])) ?>" loading="lazy">
        </picture>
      <?php else: ?>
        <span class="ad-text">
          <strong><?= e($ad['headline']) ?></strong>
          <?php if ($ad['body_text']): ?><span><?= e($ad['body_text']) ?></span><?php endif; ?>
          <span class="ad-cta"><?= e($ad['cta_label'] ?: 'Learn more') ?></span>
        </span>
      <?php endif; ?>
    </a>
    <span class="ad-advertiser">by <?= e($ad['advertiser']) ?></span>
  <?php else: ?>
    <span class="ad-house">This space is available for your brand. <a href="<?= e(url('/contact', ['topic' => 'advertising'])) ?>">Advertise on FaceMatch</a></span>
  <?php endif; ?>
  </div>
</aside>
