<?php
/**
 * Direct-sold advertising: campaign selection, impression and click tracking.
 *
 * Counting rules (to keep numbers honest):
 *   - Impressions are counted server-side when a banner is rendered, at most once
 *     per browser session per campaign every 30 minutes, never for obvious bots,
 *     and capped per IP-derived key (60/hour per campaign).
 *   - Clicks go through the first-party /go/{id} redirect. A click counts only if it
 *     carries a valid per-session signature, is not from a bot, and is not a repeat
 *     from the same session within 30 minutes.
 */
declare(strict_types=1);

const AD_DEDUPE_SECONDS = 1800;

function ad_pick(string $placement): ?array
{
    $rows = q_all("SELECT * FROM ad_campaigns
                   WHERE placement = ? AND status = 'active' AND starts_at <= UTC_TIMESTAMP() AND ends_at > UTC_TIMESTAMP()
                     AND ((creative_type = 'image' AND image_file IS NOT NULL) OR (creative_type = 'text' AND headline IS NOT NULL))
                   ORDER BY priority DESC", [$placement]);
    if (!$rows) {
        return null;
    }
    $top = (int)$rows[0]['priority'];
    $pool = array_values(array_filter($rows, static fn($r) => (int)$r['priority'] === $top));
    return $pool[random_int(0, count($pool) - 1)];
}

function ad_is_live(array $ad): bool
{
    return $ad['status'] === 'active'
        && strtotime($ad['starts_at'] . ' UTC') <= time()
        && strtotime($ad['ends_at'] . ' UTC') > time();
}

function ad_state_label(array $ad): string
{
    if ($ad['status'] === 'active') {
        if (strtotime($ad['ends_at'] . ' UTC') <= time()) {
            return 'Expired';
        }
        if (strtotime($ad['starts_at'] . ' UTC') > time()) {
            return 'Scheduled';
        }
        return 'Running';
    }
    return ucfirst($ad['status']);
}

function ad_click_signature(string $publicId): string
{
    return substr(keyed_hash($publicId . '|' . session_id(), 'adclick'), 0, 20);
}

function ad_click_url(array $ad): string
{
    return url('/go/' . $ad['public_id'], ['k' => ad_click_signature($ad['public_id'])]);
}

function ad_bump(int $campaignId, string $column): void
{
    $col = $column === 'clicks' ? 'clicks' : 'impressions';
    q("INSERT INTO ad_stats_daily (campaign_id, day, $col) VALUES (?, UTC_DATE(), 1)
       ON DUPLICATE KEY UPDATE $col = $col + 1", [$campaignId]);
}

function ad_record_impression(array $ad): void
{
    if (is_probable_bot()) {
        return;
    }
    $id = (int)$ad['id'];
    $seen = $_SESSION['ad_seen'][$id] ?? 0;
    if ($seen > time() - AD_DEDUPE_SECONDS) {
        return;
    }
    $_SESSION['ad_seen'][$id] = time();
    if (!rate_limit('ad_imp_' . $id, 60, 3600)) {
        return;
    }
    ad_bump($id, 'impressions');
}

/** Handle /go/{publicId}. Always redirects; counts only legitimate clicks. */
function ad_handle_click(string $publicId, string $sig): never
{
    $ad = preg_match('/^[A-Za-z0-9]{12}$/', $publicId) ? q_one('SELECT * FROM ad_campaigns WHERE public_id = ?', [$publicId]) : null;
    if (!$ad || !ad_is_live($ad)) {
        redirect('/');
    }
    $dest = safe_external_url((string)$ad['destination_url']);
    if (!$dest) {
        redirect('/');
    }
    $id = (int)$ad['id'];
    $validSig = $sig !== '' && hash_equals(ad_click_signature($publicId), $sig);
    $recent = ($_SESSION['ad_click'][$id] ?? 0) > time() - AD_DEDUPE_SECONDS;
    if ($validSig && !$recent && !is_probable_bot() && rate_limit('ad_click_' . $id, 20, 3600)) {
        $_SESSION['ad_click'][$id] = time();
        ad_bump($id, 'clicks');
    }
    header('Referrer-Policy: origin');
    header('Cache-Control: no-store');
    header('Location: ' . $dest, true, 302);
    exit;
}

function ad_media_url(array $ad, bool $mobile = false): ?string
{
    $file = $mobile ? $ad['mobile_image_file'] : $ad['image_file'];
    return $file ? url('/media/ad/' . $ad['public_id'] . ($mobile ? '/m' : '/d')) : null;
}

/** Totals per campaign for admin reporting. */
function ad_totals(?int $campaignId = null, int $days = 0): array
{
    $where = [];
    $params = [];
    if ($campaignId) {
        $where[] = 'campaign_id = ?';
        $params[] = $campaignId;
    }
    if ($days > 0) {
        $where[] = 'day >= DATE_SUB(UTC_DATE(), INTERVAL ? DAY)';
        $params[] = $days - 1;
    }
    $sql = 'SELECT COALESCE(SUM(impressions),0) AS impressions, COALESCE(SUM(clicks),0) AS clicks FROM ad_stats_daily'
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
    $r = q_one($sql, $params);
    $imp = (int)$r['impressions'];
    $clk = (int)$r['clicks'];
    return ['impressions' => $imp, 'clicks' => $clk, 'ctr' => $imp > 0 ? $clk * 100 / $imp : 0.0];
}
