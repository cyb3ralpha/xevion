<section class="page-head"><div class="container"><h1>Privacy Policy</h1><p>How XEVION FaceMatch handles information. Plain-language summary of what the service actually does.</p></div></section>
<div class="container prose">
<?php if (!db_enabled() && short_links_enabled()): ?>
  <h2>How this site handles your data</h2>
  <p>This site runs without a database. Matches you create are <strong>stored on our server</strong> (file storage at our hosting provider, Vercel) so their links work on any device.</p>
  <ul>
    <li><strong>Photos and names</strong> are resized and redrawn in your browser before upload, which removes metadata such as GPS location. The server keeps smaller copies of the photos.</li>
    <li><strong>Public matches</strong> are listed on the website, and anyone can open and vote on them.</li>
    <li><strong>Private matches</strong> are encrypted in your browser with the access code before upload. They are never listed, and we can’t read them without the code.</li>
    <li><strong>Votes</strong> are added to the match’s shared results on our server. We store only the vote counts per contender and short one-way hashes that stop the same browser voting twice on the same matchup, made from a random identifier your browser keeps for this site. We don’t store your IP address or any account with your votes. On private matches, a vote is accepted only from someone who has the access code.</li>
    <li><strong>Deleting:</strong> use “Delete match” on the match page in the browser that created it. That removes the match and its results from the server, and its link stops working. Copies other people opened stay on their devices.</li>
    <li><strong>Server logs:</strong> Vercel keeps standard request logs for security and reliability.</li>
  </ul>
  <p>We don’t use advertising trackers, third-party analytics, cookies for tracking or browser fingerprinting.</p>
  <?php if (site_contact_email() !== ''): ?><p>Questions or removal requests: <a href="mailto:<?= e(site_contact_email()) ?>"><?= e(site_contact_email()) ?></a>.</p><?php endif; ?>
<?php elseif (!db_enabled()): ?>
  <h2>How this site handles your data</h2>
  <p>This site runs without a database. Matches you create are kept <strong>in your own browser</strong> and are never uploaded to our servers.</p>
  <ul>
    <li><strong>Photos and names</strong> are resized and redrawn in your browser, which removes metadata such as GPS location, and are saved in your browser’s storage.</li>
    <li><strong>Votes and results</strong> are counted on your device only. We never receive them.</li>
    <li><strong>Share links</strong> carry the match, including smaller photos, after the <code>#</code> in the address. Browsers never send that part to servers, so it doesn’t reach us or appear in our logs. Anyone you give the link to can see the match; private matches also need the access code.</li>
    <li><strong>Deleting:</strong> use “Delete match” on the match page, or clear this site’s data in your browser. Copies that other people opened stay on their devices; ask them to delete them.</li>
    <li><strong>Server logs:</strong> our hosting provider (Vercel) keeps standard request logs, such as the page address without the part after <code>#</code>, for security and reliability.</li>
  </ul>
  <p>We don’t use advertising trackers, third-party analytics, cookies for tracking or browser fingerprinting.</p>
  <?php if (site_contact_email() !== ''): ?><p>Questions or removal requests: <a href="mailto:<?= e(site_contact_email()) ?>"><?= e(site_contact_email()) ?></a>.</p><?php endif; ?>
<?php else: ?>
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
<?php endif; ?>
</div>
