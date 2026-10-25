<?php $o = $old; $err = static fn($k) => !empty($errors[$k]) ? '<span class="field-error">' . e($errors[$k]) . '</span>' : ''; $dt = static fn($v) => $v ? substr(str_replace(' ', 'T', (string)$v), 0, 16) : ''; ?>
<div class="admin-top"><h1><?= $ad ? e($ad['name']) : 'New campaign' ?></h1><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('ads')) ?>">All campaigns</a></div>
<?php if ($errors): ?><div class="notice notice-error" role="alert">Fix the highlighted fields.</div><?php endif; ?>
<?php if ($ad): ?>
<div class="stats">
  <div class="stat"><b><?= e(ad_state_label($ad)) ?></b><span>State</span></div>
  <div class="stat"><b><?= number_format($totals['impressions']) ?></b><span>Impressions</span></div>
  <div class="stat"><b><?= number_format($totals['clicks']) ?></b><span>Clicks</span></div>
  <div class="stat"><b><?= number_format($totals['ctr'], 2) ?>%</b><span>Click-through rate</span></div>
</div>
<section class="card pad stack" style="margin-bottom:20px">
  <h2>Preview</h2>
  <div class="ad-preview">
    <div class="ad-slot"><div class="ad-inner"><span class="ad-label">Advertisement</span>
      <span class="ad-creative"><?php if ($ad['creative_type'] === 'image' && $ad['image_file']): ?><img src="<?= e(admin_url('media', ['ad' => $ad['public_id'], 'v' => 'd'])) ?>" alt="" style="max-height:120px;width:100%;object-fit:contain"><?php elseif ($ad['creative_type'] === 'text'): ?><span class="ad-text"><strong><?= e($ad['headline']) ?></strong><span><?= e($ad['body_text']) ?></span><span class="ad-cta"><?= e($ad['cta_label'] ?: 'Learn more') ?></span></span><?php else: ?><span class="muted">No banner uploaded yet.</span><?php endif; ?></span>
      <span class="ad-advertiser">by <?= e($ad['advertiser']) ?></span></div></div>
  </div>
  <?php if ($ad['mobile_image_file']): ?><p class="muted small">Mobile variant:</p><img src="<?= e(admin_url('media', ['ad' => $ad['public_id'], 'v' => 'm'])) ?>" alt="" style="max-width:360px;border-radius:8px"><?php endif; ?>
  <p class="muted small">Destination: <a href="<?= e($ad['destination_url']) ?>" rel="noopener" target="_blank"><?= e($ad['destination_url']) ?></a></p>
  <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="status">
    <?php foreach (['active' => 'Activate', 'paused' => 'Pause', 'archived' => 'Archive', 'draft' => 'Back to draft'] as $s => $l): if ($s === $ad['status']) { continue; } ?><button class="btn btn-ghost btn-sm" name="status" value="<?= $s ?>"><?= $l ?></button><?php endforeach; ?>
  </form>
</section>
<?php endif; ?>
<form method="post" enctype="multipart/form-data" class="card pad form-stack" style="max-width:860px">
  <?= csrf_field() ?>
  <div class="grid-2">
    <label class="field"><span>Campaign name</span><input name="name" maxlength="120" required value="<?= e($o['name']) ?>"><?= $err('name') ?></label>
    <label class="field"><span>Advertiser</span><input name="advertiser" maxlength="120" required value="<?= e($o['advertiser']) ?>"><?= $err('advertiser') ?></label>
  </div>
  <div class="grid-2">
    <label class="field"><span>Creative type</span><select name="creative_type"><option value="image"<?= $o['creative_type'] === 'image' ? ' selected' : '' ?>>Image banner</option><option value="text"<?= $o['creative_type'] === 'text' ? ' selected' : '' ?>>Text</option></select></label>
    <label class="field"><span>Placement</span><select name="placement"><option value="top_banner"<?= $o['placement'] === 'top_banner' ? ' selected' : '' ?>>Top banner (all pages)</option><option value="footer_banner"<?= $o['placement'] === 'footer_banner' ? ' selected' : '' ?>>Footer banner (all pages)</option></select></label>
  </div>
  <div class="grid-2">
    <label class="field"><span>Desktop banner <span class="hint">JPG/PNG/WebP, ≤2 MB, ≥728 px wide, ratio 3:1–12:1 (e.g. 1456×180)</span></span><input type="file" name="image" accept="image/jpeg,image/png,image/webp"><?= $err('image') ?></label>
    <label class="field"><span>Mobile banner (optional) <span class="hint">≥320 px wide, ratio 2:1–6:1 (e.g. 640×200)</span></span><input type="file" name="mobile_image" accept="image/jpeg,image/png,image/webp"><?= $err('mobile_image') ?>
      <?php if (!empty($ad['mobile_image_file'])): ?><label class="check"><input type="checkbox" name="remove_mobile" value="1"><span>Remove current mobile banner</span></label><?php endif; ?></label>
  </div>
  <label class="field"><span>Headline <span class="hint">Required for text ads; used as image alt text</span></span><input name="headline" maxlength="90" value="<?= e($o['headline']) ?>"><?= $err('headline') ?></label>
  <div class="grid-2">
    <label class="field"><span>Body text (text ads)</span><input name="body_text" maxlength="160" value="<?= e($o['body_text']) ?>"></label>
    <label class="field"><span>Button label (text ads)</span><input name="cta_label" maxlength="30" value="<?= e($o['cta_label']) ?>" placeholder="Learn more"></label>
  </div>
  <label class="field"><span>Destination URL</span><input name="destination_url" maxlength="500" required value="<?= e($o['destination_url']) ?>"><?= $err('destination_url') ?></label>
  <div class="grid-2">
    <label class="field"><span>Starts (UTC)</span><input type="datetime-local" name="starts_at" required value="<?= e($dt($o['starts_at'])) ?>"></label>
    <label class="field"><span>Ends (UTC)</span><input type="datetime-local" name="ends_at" required value="<?= e($dt($o['ends_at'])) ?>"><?= $err('ends_at') ?></label>
  </div>
  <div class="grid-2">
    <label class="field"><span>Priority <span class="hint">1–10. Highest running priority wins; ties rotate.</span></span><input type="number" name="priority" min="1" max="10" value="<?= (int)$o['priority'] ?>"><?= $err('priority') ?></label>
    <label class="field"><span>Status</span><select name="status"><?php foreach (['draft', 'active', 'paused', 'archived'] as $s): ?><option value="<?= $s ?>"<?= $o['status'] === $s ? ' selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select><?= $err('status') ?></label>
  </div>
  <div class="grid-2">
    <label class="field"><span>Budget (optional, for your records only)</span><input name="budget_amount" value="<?= e($o['budget_amount']) ?>" placeholder="1500.00"><?= $err('budget_amount') ?></label>
    <label class="field"><span>Currency</span><input name="budget_currency" maxlength="3" value="<?= e($o['budget_currency'] ?: 'USD') ?>"></label>
  </div>
  <label class="field"><span>Notes</span><textarea name="notes" maxlength="2000"><?= e($o['notes']) ?></textarea></label>
  <button class="btn btn-primary" type="submit">Save campaign</button>
</form>
<?php if ($ad): ?>
<section class="card pad" style="margin-top:20px;max-width:860px">
  <h2>Daily performance (last 30 days, UTC)</h2>
  <?php if (!$daily): ?><p class="muted">No impressions recorded yet.</p><?php else: ?>
  <table class="tbl"><thead><tr><th>Day</th><th class="num">Impressions</th><th class="num">Clicks</th><th class="num">CTR</th></tr></thead><tbody>
  <?php foreach ($daily as $d): ?><tr><td><?= e($d['day']) ?></td><td class="num"><?= number_format((int)$d['impressions']) ?></td><td class="num"><?= number_format((int)$d['clicks']) ?></td><td class="num"><?= $d['impressions'] ? number_format($d['clicks'] * 100 / $d['impressions'], 2) . '%' : '—' ?></td></tr><?php endforeach; ?>
  </tbody></table><?php endif; ?>
</section>
<?php endif; ?>
