<?php
/** Play a match saved in this browser (no database). Filled in by assets/js/app.js. */
$cats = [];
foreach ($categoryLabels as $k => $label) {
    $cats[$k] = ['l' => $label, 'p' => $categoryPrompts[$k] ?? ''];
}
?>
<div class="container arena-wrap" data-local-play data-cats="<?= e(json_encode($cats, JSON_UNESCAPED_UNICODE)) ?>" data-blocked="<?= e(json_encode($blocked)) ?>" data-short-id="<?= e((string)($shortId ?? '')) ?>" data-short-enabled="<?= !empty($shortEnabled) ? '1' : '' ?>">
  <div data-lp-loading class="arena-state card"><p class="muted">Loading the match…</p></div>

  <div data-lp-blocked class="arena-state card" hidden>
    <h1 class="state-title">This match was removed</h1>
    <p class="muted">The site administrator has blocked this match, so it can’t be opened here.</p>
    <div class="btn-row" style="justify-content:center"><a class="btn btn-primary" href="<?= e(url('/create')) ?>"><?= icon('plus') ?> Start a match</a></div>
  </div>

  <div data-lp-missing class="arena-state card" hidden>
    <h1 class="state-title">Match not found</h1>
    <p class="muted">This match isn’t saved in this browser, or the link is incomplete. Ask for the share link again, or start your own.</p>
    <div class="btn-row" style="justify-content:center"><a class="btn btn-primary" href="<?= e(url('/create')) ?>"><?= icon('plus') ?> Start a match</a></div>
  </div>

  <form data-lp-locked class="arena-state card" hidden>
    <span class="unavailable-icon"><?= icon('lock') ?></span>
    <h1 class="state-title" style="margin-top:14px">This match is private</h1>
    <p class="muted">Enter the access code the organiser sent you.</p>
    <div class="share" style="max-width:360px;margin:0 auto">
      <input type="text" name="code" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="24" aria-label="Access code" required>
      <button class="btn btn-primary btn-sm" type="submit">Open</button>
    </div>
    <p class="field-error" data-lp-locked-error role="alert"></p>
  </form>

  <div data-lp-main hidden>
    <p class="notice notice-error" data-lp-upload-failed hidden>Your match is saved in this browser, but it couldn’t be uploaded to the website just now, so it isn’t listed yet. Press Share to try again.</p>
    <div class="arena-top">
      <div>
        <div class="pills"><span class="pill pill-cat" data-lp-cat></span><span class="pill pill-live" data-lp-round></span></div>
        <h1 style="margin-top:8px" data-lp-title></h1>
        <p class="arena-prompt" data-lp-prompt></p>
      </div>
      <div class="btn-row">
        <button class="btn btn-ghost btn-sm" type="button" data-lp-tab="results"><?= icon('chart') ?> Results</button>
        <button class="btn btn-primary btn-sm" type="button" data-lp-share><?= icon('link') ?> Share</button>
      </div>
    </div>

    <div class="share-panel card pad" data-lp-share-panel hidden>
      <p class="muted small" style="margin-top:0" data-lp-share-intro>Anyone who opens this link can vote on their own device. Photos travel inside the link in a smaller size.</p>
      <p class="small" data-lp-code-row hidden>Access code: <strong class="code-big" style="font-size:1.1rem" data-lp-code></strong> — send it separately from the link.</p>
      <div class="share"><input type="text" readonly data-lp-share-url aria-label="Share link"><button class="btn btn-ghost btn-sm" type="button" data-lp-copy>Copy</button></div>
      <p class="small" data-lp-share-note aria-live="polite"></p>
    </div>

    <div class="progress" role="progressbar" aria-label="Matchups voted this round" aria-valuemin="0" aria-valuemax="100"><i data-lp-progress></i></div>

    <section data-lp-arena>
      <div class="arena" data-lp-grid>
        <button class="contender left" type="button" data-side="0" aria-label="Vote for the left contender">
          <img alt="" data-lp-img>
          <span class="name"><span data-lp-name></span><span class="hint kbd-hint">Press 1 or ←</span></span>
        </button>
        <div class="vs-core" aria-hidden="true"><span class="wing l"></span><span class="wing r"></span><b>VS</b></div>
        <button class="contender right" type="button" data-side="1" aria-label="Vote for the right contender">
          <img alt="" data-lp-img>
          <span class="name"><span data-lp-name></span><span class="hint kbd-hint">Press 2 or →</span></span>
        </button>
      </div>
      <p class="arena-status" data-lp-status aria-live="polite"></p>
    </section>

    <section data-lp-done class="arena-state card" hidden>
      <h2>Round complete</h2>
      <p class="muted" data-lp-done-text>You voted on every matchup. See the standings, or hand the device to the next person.</p>
      <div class="btn-row" style="justify-content:center">
        <button class="btn btn-primary" type="button" data-lp-tab="results"><?= icon('chart') ?> See results</button>
        <button class="btn btn-ghost" type="button" data-lp-next-round><?= icon('bolt') ?> Next voter</button>
        <button class="btn btn-ghost" type="button" data-lp-done-share hidden><?= icon('link') ?> Share</button>
      </div>
    </section>

    <section data-lp-results class="card pad" hidden style="margin-top:20px">
      <div class="section-head" style="margin-bottom:8px"><div><h2 style="margin:0">Standings</h2><p data-lp-total aria-live="polite"></p></div>
        <div class="btn-row">
          <div class="seg" role="group" aria-label="Whose votes" data-lp-views hidden>
            <button type="button" data-lp-view="all" aria-pressed="true">Everyone</button>
            <button type="button" data-lp-view="me" aria-pressed="false">This device</button>
          </div>
          <button class="btn btn-ghost btn-sm" type="button" data-lp-tab="arena"><?= icon('bolt') ?> Keep voting</button>
        </div></div>
      <div class="table-scroll"><table class="standings">
        <thead><tr><th>#</th><th>Contender</th><th class="num">Win rate</th><th class="num">Won–lost</th></tr></thead>
        <tbody data-lp-rows></tbody>
      </table></div>
      <p class="muted small" style="margin-bottom:0" data-lp-results-note>Ranked by win rate across head-to-head votes on this device. Equal win rates are split by total wins; if still level, contenders share the place.</p>
    </section>

    <div class="arena-foot">
      <span>Tap a photo to vote.<span class="kbd-hint"> Keyboard: <span class="kbd">1</span> / <span class="kbd">←</span> left, <span class="kbd">2</span> / <span class="kbd">→</span> right.</span></span>
      <span><span class="muted">Match ID <code data-lp-id></code></span> · <a href="<?= e(url('/report')) ?>">Report</a> · <button class="link-btn" type="button" data-lp-reset>Reset votes</button> · <button class="link-btn danger" type="button" data-lp-delete>Delete match</button></span>
    </div>
  </div>
</div>
