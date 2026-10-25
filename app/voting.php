<?php
/**
 * Pairwise voting engine and ranking.
 *
 * Matchups: every unordered pair of playable participants is a possible matchup.
 * A browser is never shown a pair it has already voted on; among the remaining
 * pairs, those with the fewest recorded comparisons are preferred so every pair
 * gathers data evenly. Odd participant counts need no special handling because
 * pairs are drawn from all combinations, not from a bracket.
 *
 * Ranking (documented in docs/VOTING.md):
 *   1. Win rate  = wins / (wins + losses) over counted votes between active participants
 *   2. Tie-break = total wins
 *   3. Still equal → shared rank, shown as a tie
 * A single winner is only declared after voting closes, when exactly one
 * participant holds rank 1 and has at least one comparison.
 */
declare(strict_types=1);

const PAIR_TOKEN_TTL = 7200;

/** @return array{status: string, pair?: array, progress?: array} */
function vote_next_pair(array $c, string $voterHash): array
{
    if (!comp_is_open($c)) {
        return ['status' => 'closed'];
    }
    $players = playable_participants((int)$c['id']);
    if (count($players) < 2) {
        return ['status' => 'not_enough'];
    }
    $byId = [];
    foreach ($players as $p) {
        $byId[(int)$p['id']] = $p;
    }
    $ids = array_keys($byId);
    sort($ids);

    $voted = [];
    foreach (q_all('SELECT pair_low, pair_high FROM votes WHERE competition_id = ? AND voter_hash = ?', [$c['id'], $voterHash]) as $r) {
        $voted[$r['pair_low'] . '-' . $r['pair_high']] = true;
    }
    $counts = [];
    foreach (q_all("SELECT pair_low, pair_high, COUNT(*) AS n FROM votes WHERE competition_id = ? AND status = 'counted'
                    GROUP BY pair_low, pair_high", [$c['id']]) as $r) {
        $counts[$r['pair_low'] . '-' . $r['pair_high']] = (int)$r['n'];
    }

    $total = 0;
    $candidates = [];
    for ($i = 0; $i < count($ids); $i++) {
        for ($j = $i + 1; $j < count($ids); $j++) {
            $total++;
            $k = $ids[$i] . '-' . $ids[$j];
            if (!isset($voted[$k])) {
                $candidates[] = [$ids[$i], $ids[$j], $counts[$k] ?? 0];
            }
        }
    }
    $done = $total - count($candidates);
    if (!$candidates) {
        return ['status' => 'complete', 'progress' => ['done' => $done, 'total' => $total]];
    }

    // Avoid showing someone from the previous matchup again when there is an alternative.
    $last = $_SESSION['last_pair'][(int)$c['id']] ?? [];
    $fresh = array_values(array_filter($candidates, static fn($p) => !in_array($p[0], $last, true) && !in_array($p[1], $last, true)));
    $pool = $fresh ?: $candidates;

    // Prefer the least-compared pairs: keep those within +1 of the minimum, then pick at random.
    $min = min(array_column($pool, 2));
    $pool = array_values(array_filter($pool, static fn($p) => $p[2] <= $min + 1));
    [$a, $b] = $pool[random_int(0, count($pool) - 1)];
    if (random_int(0, 1) === 1) {
        [$a, $b] = [$b, $a];
    }
    $_SESSION['last_pair'][(int)$c['id']] = [$a, $b];

    $token = random_token(16);
    $tokens = $_SESSION['pairs'][(int)$c['id']] ?? [];
    $tokens = array_filter($tokens, static fn($t) => $t['t'] > time() - PAIR_TOKEN_TTL);
    $tokens = array_slice($tokens, -9, null, true);
    $tokens[$token] = ['a' => $a, 'b' => $b, 't' => time()];
    $_SESSION['pairs'][(int)$c['id']] = $tokens;

    $side = static fn(array $p) => [
        'id'    => $p['public_id'],
        'name'  => $p['display_name'],
        'image' => participant_img($p, 'full'),
    ];
    return [
        'status'   => 'ok',
        'token'    => $token,
        'pair'     => [$side($byId[$a]), $side($byId[$b])],
        'progress' => ['done' => $done, 'total' => $total],
    ];
}

/**
 * Record one vote. Safe to retry: the same idempotency key always yields the same
 * outcome and never inserts a second row.
 * @return array{ok: bool, code: string, message?: string, split?: array}
 */
function vote_cast(array $c, string $pairToken, string $choicePublicId, string $idemKey, string $voterHash, ?string $ipHash): array
{
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $idemKey)) {
        return ['ok' => false, 'code' => 'bad_request', 'message' => 'Invalid request.'];
    }

    // 1. Replayed request? Return the original outcome.
    $prior = q_one('SELECT competition_id, voter_hash, winner_id, loser_id FROM votes WHERE idempotency_key = ?', [$idemKey]);
    if ($prior) {
        if ((int)$prior['competition_id'] !== (int)$c['id'] || !hash_equals($prior['voter_hash'], $voterHash)) {
            return ['ok' => false, 'code' => 'bad_request', 'message' => 'Invalid request.'];
        }
        return ['ok' => true, 'code' => 'duplicate', 'split' => vote_split($c, (int)$prior['winner_id'], (int)$prior['loser_id'])];
    }

    if (!comp_is_open($c)) {
        return ['ok' => false, 'code' => 'closed', 'message' => 'Voting has closed for this match.'];
    }

    // 2. The pair must be one this browser was actually served.
    $slot = $_SESSION['pairs'][(int)$c['id']][$pairToken] ?? null;
    if (!$slot || $slot['t'] < time() - PAIR_TOKEN_TTL) {
        return ['ok' => false, 'code' => 'stale', 'message' => 'This matchup expired. Loading a fresh one.'];
    }

    // 3. The chosen participant must belong to this competition and to the served pair.
    $chosen = q_one('SELECT * FROM participants WHERE public_id = ? AND competition_id = ?', [$choicePublicId, $c['id']]);
    if (!$chosen || !in_array((int)$chosen['id'], [$slot['a'], $slot['b']], true)) {
        return ['ok' => false, 'code' => 'bad_request', 'message' => 'That choice is not part of this matchup.'];
    }
    $winnerId = (int)$chosen['id'];
    $loserId = $winnerId === $slot['a'] ? $slot['b'] : $slot['a'];
    $loser = q_one('SELECT * FROM participants WHERE id = ? AND competition_id = ?', [$loserId, $c['id']]);
    if (!$loser || !participant_image_servable($chosen) || !participant_image_servable($loser)) {
        unset($_SESSION['pairs'][(int)$c['id']][$pairToken]);
        return ['ok' => false, 'code' => 'stale', 'message' => 'A participant in this matchup is no longer available.'];
    }
    $low = min($winnerId, $loserId);
    $high = max($winnerId, $loserId);

    // 4. Heuristic: many votes on the same pair from one network in 24h.
    if ($ipHash) {
        $fromIp = (int)q_val("SELECT COUNT(*) FROM votes WHERE competition_id = ? AND ip_hash = ? AND pair_low = ? AND pair_high = ?
                              AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)", [$c['id'], $ipHash, $low, $high]);
        if ($fromIp >= setting_int('ip_pair_limit_per_day', 8)) {
            return ['ok' => false, 'code' => 'limit', 'message' => 'This matchup has received many votes from your network today. Try another match.'];
        }
    }

    // 5. Insert atomically. Locking the competition row serialises the open/closed check with the insert.
    try {
        $res = db_tx(function () use ($c, $winnerId, $loserId, $low, $high, $voterHash, $ipHash, $idemKey) {
            $row = q_one('SELECT status, closes_at FROM competitions WHERE id = ? FOR UPDATE', [$c['id']]);
            if (!$row || $row['status'] !== 'active' || strtotime($row['closes_at'] . ' UTC') <= time()) {
                return ['ok' => false, 'code' => 'closed', 'message' => 'Voting closed while you were deciding.'];
            }
            q('INSERT INTO votes (competition_id, winner_id, loser_id, pair_low, pair_high, voter_hash, ip_hash, idempotency_key)
               VALUES (?,?,?,?,?,?,?,?)', [$c['id'], $winnerId, $loserId, $low, $high, $voterHash, $ipHash, $idemKey]);
            q('UPDATE competitions SET votes_total = votes_total + 1, last_vote_at = UTC_TIMESTAMP() WHERE id = ?', [$c['id']]);
            return ['ok' => true, 'code' => 'counted'];
        });
    } catch (PDOException $e) {
        if (!is_duplicate_key($e)) {
            throw $e;
        }
        // Either the same key raced in (idempotent success) or this browser already voted on the pair.
        $again = q_one('SELECT winner_id, loser_id FROM votes WHERE idempotency_key = ?', [$idemKey]);
        if ($again) {
            $res = ['ok' => true, 'code' => 'duplicate'];
        } else {
            unset($_SESSION['pairs'][(int)$c['id']][$pairToken]);
            return ['ok' => false, 'code' => 'already_voted', 'message' => 'You already voted on this matchup.'];
        }
    }
    if ($res['ok']) {
        unset($_SESSION['pairs'][(int)$c['id']][$pairToken]);
        $res['split'] = vote_split($c, $winnerId, $loserId);
    }
    return $res;
}

/** Head-to-head split for a pair — only returned when results are visible to this browser. */
function vote_split(array $c, int $winnerId, int $loserId): ?array
{
    $fresh = comp_find((int)$c['id']) ?? $c;
    if (!comp_results_visible($fresh)) {
        return null;
    }
    $rows = q_all("SELECT winner_id, COUNT(*) AS n FROM votes WHERE competition_id = ? AND status = 'counted'
                   AND pair_low = ? AND pair_high = ? GROUP BY winner_id", [$c['id'], min($winnerId, $loserId), max($winnerId, $loserId)]);
    $n = [$winnerId => 0, $loserId => 0];
    foreach ($rows as $r) {
        $n[(int)$r['winner_id']] = (int)$r['n'];
    }
    $sum = max(1, array_sum($n));
    return ['chosen' => round($n[$winnerId] * 100 / $sum), 'other' => round($n[$loserId] * 100 / $sum), 'votes' => array_sum($n)];
}

/**
 * Compute standings from counted votes. Votes involving removed participants are excluded.
 * @return array{rows: array, total_votes: int, leader_tied: bool, provisional: bool, h2h: array}
 */
function ranking_compute(int $compId): array
{
    $parts = comp_participants($compId);
    $active = [];
    foreach ($parts as $p) {
        $active[(int)$p['id']] = $p + ['wins' => 0, 'losses' => 0];
    }
    $h2h = [];
    $total = 0;
    if ($active) {
        $ids = array_keys($active);
        $in = placeholders($ids);
        $rows = q_all("SELECT winner_id, loser_id, COUNT(*) AS n FROM votes
                       WHERE competition_id = ? AND status = 'counted' AND winner_id IN ($in) AND loser_id IN ($in)
                       GROUP BY winner_id, loser_id", array_merge([$compId], $ids, $ids));
        foreach ($rows as $r) {
            $w = (int)$r['winner_id'];
            $l = (int)$r['loser_id'];
            $n = (int)$r['n'];
            $active[$w]['wins'] += $n;
            $active[$l]['losses'] += $n;
            $h2h[$w][$l] = ($h2h[$w][$l] ?? 0) + $n;
            $total += $n;
        }
    }
    $rows = [];
    foreach ($active as $p) {
        $cmp = $p['wins'] + $p['losses'];
        $p['comparisons'] = $cmp;
        $p['win_rate'] = $cmp > 0 ? $p['wins'] / $cmp : 0.0;
        $p['vote_share'] = $total > 0 ? $p['wins'] * 100 / $total : 0.0;
        $rows[] = $p;
    }
    usort($rows, static function ($a, $b) {
        $r = round($b['win_rate'], 6) <=> round($a['win_rate'], 6);
        return $r !== 0 ? $r : ($b['wins'] <=> $a['wins'] ?: $a['position'] <=> $b['position']);
    });
    $rank = 0;
    $prev = null;
    foreach ($rows as $i => &$r) {
        $key = round($r['win_rate'], 6) . '|' . $r['wins'];
        if ($key !== $prev) {
            $rank = $i + 1;
            $prev = $key;
        }
        $r['rank'] = $rank;
    }
    unset($r);
    $rankCounts = array_count_values(array_column($rows, 'rank'));
    foreach ($rows as &$r) {
        $r['tied'] = ($rankCounts[$r['rank']] ?? 0) > 1;
    }
    unset($r);
    $provisional = (bool)array_filter($rows, static fn($r) => $r['comparisons'] < 3);
    return [
        'rows' => $rows,
        'total_votes' => $total,
        'leader_tied' => $rows && $rows[0]['tied'],
        'provisional' => $provisional,
        'h2h' => $h2h,
    ];
}

/** A winner exists only after close, with a unique leader who has been compared at least once. */
function ranking_winner(array $c, array $ranking): ?array
{
    if (comp_effective_status($c) !== 'closed' || !$ranking['rows']) {
        return null;
    }
    $top = $ranking['rows'][0];
    return (!$top['tied'] && $top['comparisons'] > 0) ? $top : null;
}
