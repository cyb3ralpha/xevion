<?php
$o = $old + ['title' => '', 'category' => '', 'custom_category' => '', 'description' => '', 'instructions' => '', 'visibility' => 'public',
             'results_visibility' => 'live', 'duration' => '168', 'custom_close' => '', 'invite_expiry' => '', 'use_access_code' => false, 'names' => []];
$rows = max($minP, count($o['names']), min($maxP, (int)($_GET['rows'] ?? 0)));
$pe = $errors['people'] ?? [];
$err = static fn(string $k) => !empty($errors[$k]) && is_string($errors[$k]) ? ' has-error' : '';
$formClass = ($o['category'] === 'custom' ? 'show-custom ' : '') . ($o['visibility'] === 'private' ? 'show-private' : '');
$local = !empty($local); // no database: the match is saved in this browser
?>
<section class="page-head">
  <div class="container narrow">
    <h1>Start a match</h1>
    <?php if ($local): ?>
    <p><?= !empty($serverStore) ? 'Give it a title, add ' . $minP . '–' . $maxP . ' photos, and get a link to share. Public matches appear on the site for everyone to vote on.' : 'Add ' . $minP . '–' . $maxP . ' contenders and choose what voters should judge. Your match is saved in this browser, and you get a link to share it with friends.' ?></p>
    <?php else: ?>
    <p>Add <?= $minP ?>–<?= $maxP ?> contenders, choose what voters should judge, and decide who can see it. You will get a share link and a private link to manage the match.</p>
    <?php endif; ?>
  </div>
</section>
<div class="container narrow">
  <?php if ($errors): ?>
    <div class="notice notice-error" role="alert" tabindex="-1" data-focus-on-load>
      <strong><?= e($errors['form'] ?? 'Some details need fixing before the match can be created.') ?></strong>
      <?php if ($pe || !empty($errors['participants'])): ?><br>Photos can’t be kept between attempts, so choose them again for each contender.<?php endif; ?>
    </div>
  <?php endif; ?>
  <form method="post" action="<?= e(url('/create')) ?>" enctype="multipart/form-data" class="create-steps <?= e($formClass) ?>" data-create-form<?= $local ? ' data-local' : '' ?><?= !empty($serverStore) ? ' data-server="1"' : '' ?>
        data-min="<?= (int)$minP ?>" data-max="<?= (int)$maxP ?>" data-max-mb="<?= (int)$maxMb ?>" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="tz_offset" value="0" data-tz>

    <section class="card pad">
      <div class="step-title"><span class="n">1</span><h2>What’s the match?</h2></div>
      <div class="form-stack">
        <label class="field<?= $err('title') ?>"><span>Title</span>
          <input name="title" maxlength="120" required value="<?= e($o['title']) ?>" placeholder="Who has the best smile in our class of 2016?">
          <?php partial('field_error', ['errors' => $errors, 'name' => 'title']); ?>
        </label>
        <?php if ($local): ?>
        <input type="hidden" name="category" value="open" data-category>
        <input type="hidden" name="custom_category" value="">
        <input type="hidden" name="description" value="">
        <label class="field"><span>Question for voters <span class="hint">Optional, shown above every face-off</span></span>
          <input name="instructions" maxlength="300" value="<?= e($o['instructions']) ?>" placeholder="Who has the best smile?">
        </label>
        <?php else: ?>
        <label class="field<?= $err('category') ?>"><span>Category</span>
          <select name="category" required data-category>
            <option value="">Choose a category</option>
            <?php foreach (categories() as $k => $cat): ?><option value="<?= e($k) ?>"<?= $o['category'] === $k ? ' selected' : '' ?>><?= e($cat['label']) ?></option><?php endforeach; ?>
          </select>
          <?php partial('field_error', ['errors' => $errors, 'name' => 'category']); ?>
        </label>
        <label class="field when-custom<?= $err('custom_category') ?>"><span>Your category <span class="hint">3–40 characters. Custom categories are reviewed before they go public. Keep it kind: no insults, body comparisons or sexual themes.</span></span>
          <input name="custom_category" maxlength="40" value="<?= e($o['custom_category']) ?>" placeholder="Best dance moves">
          <?php partial('field_error', ['errors' => $errors, 'name' => 'custom_category']); ?>
        </label>
        <label class="field<?= $err('description') ?>"><span>Description <span class="hint">Optional, up to 600 characters</span></span>
          <textarea name="description" maxlength="600"><?= e($o['description']) ?></textarea>
          <?php partial('field_error', ['errors' => $errors, 'name' => 'description']); ?>
        </label>
        <label class="field<?= $err('instructions') ?>"><span>Instructions for voters <span class="hint">Optional, shown above each matchup</span></span>
          <input name="instructions" maxlength="300" value="<?= e($o['instructions']) ?>" placeholder="Judge the outfit, not the background">
          <?php partial('field_error', ['errors' => $errors, 'name' => 'instructions']); ?>
        </label>
        <?php endif; ?>
      </div>
    </section>

    <section class="card pad">
      <div class="step-title"><span class="n">2</span><h2>Contenders</h2></div>
      <p class="muted small">Tap a box to add a photo from your phone or computer. Any JPG, PNG or WebP works, and big photos are resized for you. Location and camera details are removed<?= $local ? '' : ' before anything is saved' ?>.</p>
      <?php if (!empty($errors['participants'])): ?><p class="field-error"><?= e($errors['participants']) ?></p><?php endif; ?>
      <div class="people-editor" data-people>
        <?php for ($i = 0; $i < $rows; $i++): ?>
          <div class="p-row<?= isset($pe[$i]) ? ' has-error' : '' ?>" data-row>
            <button type="button" class="p-remove" data-remove aria-label="Remove contender" hidden>×</button>
            <label class="p-drop"><span data-drop-label>+ Add photo</span>
              <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" aria-label="Photo for contender <?= $i + 1 ?>">
            </label>
            <input name="names[]" maxlength="40" placeholder="Name" value="<?= e($o['names'][$i] ?? '') ?>" aria-label="Name of contender <?= $i + 1 ?>">
            <?php foreach ($pe[$i] ?? [] as $m): ?><span class="p-err"><?= e($m) ?></span><?php endforeach; ?>
          </div>
        <?php endfor; ?>
        <button type="button" class="p-add" data-add hidden>+ Add contender</button>
      </div>
      <noscript><p><a href="<?= e(url('/create', ['rows' => min($maxP, $rows + 1)])) ?>">Add another row</a> (reloads the form)</p></noscript>
      <template data-row-template>
        <div class="p-row" data-row>
          <button type="button" class="p-remove" data-remove aria-label="Remove contender">×</button>
          <label class="p-drop"><span data-drop-label>+ Add photo</span><input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" aria-label="Photo"></label>
          <input name="names[]" maxlength="40" placeholder="Name" aria-label="Name">
        </div>
      </template>
    </section>

    <?php if ($local): ?>
    <section class="card pad">
      <div class="step-title"><span class="n">3</span><h2>Who can open it</h2></div>
      <div class="choice-row" role="radiogroup" aria-label="Who can open the match">
        <div class="choice"><input type="radio" id="lv-link" name="local_visibility" value="link" checked><label for="lv-link"><strong><?= !empty($serverStore) ? 'Public' : 'Anyone with the link' ?></strong><span><?= !empty($serverStore) ? 'Listed on the website for everyone to vote on, and shareable by link.' : 'Share the link and friends can vote straight away.' ?></span></label></div>
        <div class="choice"><input type="radio" id="lv-code" name="local_visibility" value="code"><label for="lv-code"><strong>Private, with an access code</strong><span><?= !empty($serverStore) ? 'Not listed anywhere. It opens only through its link plus the code you send separately.' : 'The link is encrypted. People also need the code you send them separately.' ?></span></label></div>
      </div>
      <ul class="trust trust-stack" style="margin-top:16px">
        <?php if (!empty($serverStore)): ?>
        <li><?= icon('lock') ?> The match is saved on our server. Private matches are encrypted in your browser first, so only people with the code can open them.</li>
        <?php else: ?>
        <li><?= icon('lock') ?> The match and its photos are saved only in this browser, never on our servers.</li>
        <?php endif; ?>
        <?php if (!empty($serverStore)): ?>
        <li><?= icon('chart') ?> Everyone’s votes add up to one live leaderboard. On a private match, only people with the code can vote.</li>
        <?php else: ?>
        <li><?= icon('chart') ?> Each device keeps its own results. Pass one phone around to vote together.</li>
        <?php endif; ?>
      </ul>
    </section>
    <?php else: ?>
    <section class="card pad">
      <div class="step-title"><span class="n">3</span><h2>Who can see it</h2></div>
      <div class="form-stack">
        <div class="choice-row" role="radiogroup" aria-label="Visibility">
          <div class="choice"><input type="radio" id="vis-public" name="visibility" value="public" data-visibility<?= $o['visibility'] !== 'private' ? ' checked' : '' ?>><label for="vis-public"><strong>Public</strong><span>Listed in Explore after review. Anyone can vote.</span></label></div>
          <div class="choice"><input type="radio" id="vis-private" name="visibility" value="private" data-visibility<?= $o['visibility'] === 'private' ? ' checked' : '' ?>><label for="vis-private"><strong>Private</strong><span>Never listed. Only people with your invite link can open it.</span></label></div>
        </div>
        <div class="grid-2 when-private">
          <label class="field"><span>Invite link expires</span>
            <select name="invite_expiry"><?php foreach ($inviteExpiry as $k => $l): ?><option value="<?= e($k) ?>"<?= (string)$o['invite_expiry'] === (string)$k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
          </label>
          <label class="check" style="align-self:end"><input type="checkbox" name="use_access_code" value="1"<?= $o['use_access_code'] ? ' checked' : '' ?>><span>Also require an access code (we generate it for you)</span></label>
        </div>
        <label class="field<?= $err('results_visibility') ?>"><span>Results</span>
          <select name="results_visibility">
            <?php foreach (['live', 'after_close', 'restricted'] as $rv): ?><option value="<?= $rv ?>"<?= $o['results_visibility'] === $rv ? ' selected' : '' ?>><?= e(results_visibility_label($rv)) ?></option><?php endforeach; ?>
          </select>
        </label>
        <div class="grid-2">
          <label class="field<?= $err('duration') ?>"><span>Voting stays open for</span>
            <select name="duration" data-duration>
              <?php foreach ($durations as $h => $l): ?><option value="<?= $h ?>"<?= (string)$o['duration'] === (string)$h ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
              <option value="custom"<?= $o['duration'] === 'custom' ? ' selected' : '' ?>>Pick a closing date…</option>
            </select>
            <?php partial('field_error', ['errors' => $errors, 'name' => 'duration']); ?>
          </label>
          <label class="field" data-custom-close<?= $o['duration'] === 'custom' ? '' : ' hidden' ?>><span>Closes at (your local time)</span>
            <input type="datetime-local" name="custom_close" value="<?= e($o['custom_close']) ?>">
          </label>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <section class="card pad">
      <div class="step-title"><span class="n">4</span><h2>Confirm</h2></div>
      <div class="form-stack">
        <label class="check<?= $err('confirm_rights') ?>"><input type="checkbox" name="confirm_rights" value="1" required><span>I took these photos or have permission to upload and share them, and everyone pictured agreed to be in this match.</span></label>
        <?php partial('field_error', ['errors' => $errors, 'name' => 'confirm_rights']); ?>
        <label class="check"><input type="checkbox" name="confirm_adults" value="1" required><span>Everyone pictured is 18 or older.</span></label>
        <?php partial('field_error', ['errors' => $errors, 'name' => 'confirm_adults']); ?>
        <label class="check"><input type="checkbox" name="accept_terms" value="1" required><span>I accept the <a href="<?= e(url('/terms')) ?>" target="_blank">Terms of Service</a> and have read the <a href="<?= e(url('/privacy')) ?>" target="_blank">Privacy Policy</a>.</span></label>
        <?php partial('field_error', ['errors' => $errors, 'name' => 'accept_terms']); ?>
        <p class="field-error" data-client-error role="alert" hidden></p>
        <div class="btn-row"><button class="btn btn-primary btn-lg" type="submit" data-submit>Create match</button><span class="muted small" data-submit-note></span></div>
      </div>
    </section>
  </form>
</div>
