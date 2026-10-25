<section class="page-head"><div class="container"><h1>Privacy Policy</h1><p>How XEVION FaceMatch handles information. Plain-language summary of what the service actually does.</p></div></section>
<div class="container prose">
  <h2>What we collect</h2>
  <ul>
    <li><strong>Photos and names you upload</strong> for a match. Photos are decoded and saved as new image files, which removes embedded metadata such as GPS location and camera details. Originals are not kept.</li>
    <li><strong>Votes.</strong> Each vote records which contender won a matchup, a random browser identifier (stored hashed) and the time.</li>
    <li><strong>Network-derived keys.</strong> We never store your IP address. For rate limiting and abuse prevention we store a one-way keyed hash of it. These hashes are cleared from votes, matches, reports and messages after <?= (int)$retention ?> days.</li>
    <li><strong>Reports and contact messages</strong>, including an email address only if you choose to give one.</li>
  </ul>
  <h2>Cookies</h2>
  <ul>
    <li><code>fm_s</code> — session cookie that keeps forms secure, remembers which private matches you unlocked and prevents duplicate votes. Deleted when you close the browser.</li>
    <li><code>fm_v</code> — random identifier (kept up to one year) so the same browser isn’t shown or counted twice for the same matchup. It contains no personal information.</li>
  </ul>
  <p>We don’t use advertising trackers, third-party analytics or browser fingerprinting. Advertisements are served from our own server; clicking one takes you to the advertiser’s site, where their policy applies.</p>
  <h2>Who can see what</h2>
  <p>Public matches can be seen by anyone once approved. Private matches are only available to people holding the invite link (and the access code if one is set). Results are shown according to the organiser’s settings. Our moderators can review any content to enforce the Terms.</p>
  <h2>How long we keep it</h2>
  <p>Matches stay available until the organiser or a moderator deletes them. Deleting a match removes its photo files from our servers immediately. Rate-limit records are kept for no more than two days.</p>
  <h2>Your choices</h2>
  <ul>
    <li>Organisers can delete their match at any time from their management link.</li>
    <li>If a photo of you was uploaded without permission, <a href="<?= e(url('/report')) ?>">report it</a> or <a href="<?= e(url('/contact', ['topic' => 'takedown'])) ?>">contact us</a>; we prioritise these requests.</li>
    <li>For access or deletion requests about other data, <a href="<?= e(url('/contact', ['topic' => 'privacy'])) ?>">contact us</a><?= $contactEmail ? ' or email <a href="mailto:' . e($contactEmail) . '">' . e($contactEmail) . '</a>' : '' ?>.</li>
  </ul>
  <h2>Limits of anonymous voting</h2>
  <p>Because FaceMatch doesn’t require accounts, we can’t guarantee that each vote comes from a different person. We use browser identifiers and rate limits to discourage repeated voting, but results are entertainment, not verified polls.</p>
</div>
