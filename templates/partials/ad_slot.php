<?php
/** Advertising slot. Paid placements are always labelled. */
$placement = $placement ?? 'top_banner';
if (!db_enabled()) {
    return; // campaigns live in the database
}
try {
    $ad = ad_pick($placement);
} catch (Throwable $e) {
    $ad = null;
}
if ($ad) {
    ad_record_impression($ad);
} else {
    return; // nothing is shown when no paid campaign is running
}
?>
<aside class="ad-slot <?= $placement === 'footer_banner' ? 'footer' : 'top' ?>" aria-label="Advertisement">
  <div class="ad-inner">
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
  </div>
</aside>
