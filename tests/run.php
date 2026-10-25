<?php
/**
 * Integration tests for the core engine, run against the configured database.
 *   php tests/run.php
 * Every record created here is tagged and removed at the end, so it is safe to run
 * on a staging copy. Do not run it on a busy production database.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';
require APP_ROOT . '/app/admin_auth.php';

$_SESSION = [];
$_SERVER['REMOTE_ADDR'] = '203.0.113.' . random_int(1, 250);
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64) FaceMatchTest';

$pass = 0;
$fail = 0;
$created = [];
$adIds = [];
$tmpFiles = [];

function check(string $name, bool $ok, string $extra = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  \033[32m✓\033[0m $name\n";
    } else {
        $fail++;
        echo "  \033[31m✗ $name\033[0m" . ($extra ? " — $extra" : '') . "\n";
    }
}

function section(string $s): void
{
    echo "\n$s\n";
}

function fake_upload(int $w = 400, int $h = 500, string $type = 'png'): array
{
    global $tmpFiles;
    $img = imagecreatetruecolor($w, $h);
    imagefill($img, 0, 0, imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
    $path = tempnam(sys_get_temp_dir(), 'fmt');
    $type === 'png' ? imagepng($img, $path) : imagejpeg($img, $path);
    $tmpFiles[] = $path;
    return ['name' => "photo.$type", 'type' => "image/$type", 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path)];
}

function make_comp(array $over = [], int $people = 3): array
{
    global $created;
    $list = [];
    for ($i = 0; $i < $people; $i++) {
        $paths = store_participant_photo(fake_upload());
        $list[] = ['name' => 'Person ' . chr(65 + $i), 'image' => $paths['image'], 'thumb' => $paths['thumb']];
    }
    $res = comp_create($over + [
        'title' => '[test] Best smile', 'category' => 'smile', 'custom_category' => null, 'description' => '', 'instructions' => '',
        'visibility' => 'public', 'results_visibility' => 'live', 'closes_at' => gmdate('Y-m-d H:i:s', time() + 86400),
        'invite_expires_at' => null, 'access_code' => null,
    ], $list);
    $created[] = (int)$res['comp']['id'];
    return $res;
}

function fresh_session(): void
{
    $_SESSION = [];
}

try {
    section('Content filter');
    check('blocks degrading term', find_blocked_term('Who is the ugliest?') !== null);
    check('blocks spaced-out evasion', find_blocked_term('s e x i e s t girl') !== null);
    check('blocks leetspeak', find_blocked_term('n4k3d pics') !== null);
    check('allows normal title', find_blocked_term('Best smile of 2026') === null);
    check('no false positive inside names', find_blocked_term('Diego and Cassidy') === null);

    section('URL validation');
    foreach (['javascript:alert(1)', 'data:text/html,hi', '//evil.com', 'http://user:pw@x.com', 'ftp://x.com', 'https://x.com/"onload'] as $bad) {
        check("rejects $bad", safe_external_url($bad) === null);
    }
    check('accepts https URL', safe_external_url('https://brand.example.com/landing?utm=x') !== null);

    section('Image uploads');
    $p = store_participant_photo(fake_upload(800, 1000, 'jpeg'));
    check('stores full and thumb', media_locate($p['image']) !== null && media_locate($p['thumb']) !== null);
    [$tw, $th] = getimagesize(media_locate($p['thumb']));
    check('thumb is 480x600', $tw === 480 && $th === 600, "$tw x $th");
    delete_media($p['image']);
    delete_media($p['thumb']);
    check('delete_media removes file', media_locate($p['image']) === null);
    $txt = tempnam(sys_get_temp_dir(), 'fmt');
    file_put_contents($txt, "<?php echo 'x'; ?>");
    $tmpFiles[] = $txt;
    try {
        store_participant_photo(['name' => 'x.jpg', 'tmp_name' => $txt, 'error' => 0, 'size' => filesize($txt)]);
        check('rejects non-image disguised as jpg', false);
    } catch (UploadError $e) {
        check('rejects non-image disguised as jpg', true);
    }
    try {
        store_participant_photo(fake_upload(100, 100));
        check('rejects tiny image', false);
    } catch (UploadError $e) {
        check('rejects tiny image', true);
    }
    check('rejects path traversal in media lookup', media_locate('../config/config.php') === null);

    section('Creation and access');
    $r = make_comp();
    $c = $r['comp'];
    check('public match starts pending review', $c['moderation_status'] === 'pending');
    check('slug is 12 random chars, not the id', strlen($c['slug']) === 12 && $c['slug'] !== (string)$c['id']);
    check('manage token stored only as hash', $c['manage_token_hash'] === manage_token_hash($r['manage_token']) && !str_contains(json_encode($c), $r['manage_token']));
    fresh_session();
    check('pending public match viewable by link', comp_viewable($c));
    check('pending public match not listed', !comp_is_listed($c));
    $ids = array_column(comp_listing(['sort' => 'recent'], 200), 'id');
    check('pending match absent from discovery', !in_array($c['id'], $ids));
    q("UPDATE competitions SET moderation_status = 'approved' WHERE id = ?", [$c['id']]);
    $c = comp_find((int)$c['id']);
    $ids = array_column(comp_listing(['sort' => 'recent'], 200), 'id');
    check('approved match appears in discovery', in_array($c['id'], $ids));

    section('Voting engine (3 contenders → 3 pairs)');
    fresh_session();
    $voter = keyed_hash('voter-1', 'voter');
    $seenPairs = [];
    $firstKey = null;
    $firstToken = null;
    for ($i = 0; $i < 3; $i++) {
        $n = vote_next_pair($c, $voter);
        if ($n['status'] !== 'ok') {
            check('next pair available', false, $n['status']);
            break;
        }
        $a = $n['pair'][0]['id'];
        $b = $n['pair'][1]['id'];
        check("pair " . ($i + 1) . " has two different contenders", $a !== $b);
        $key = uuid4();
        $res = vote_cast($c, $n['token'], $a, $key, $voter, ip_hash());
        check("vote " . ($i + 1) . " counted", $res['ok'] && $res['code'] === 'counted', json_encode($res));
        $pk = [$a, $b];
        sort($pk);
        $seenPairs[implode('-', $pk)] = true;
        if ($i === 0) {
            $firstKey = $key;
            $firstToken = $n['token'];
            $retry = vote_cast($c, $n['token'], $a, $key, $voter, ip_hash());
            check('retry with same idempotency key is not double-counted', $retry['ok'] && $retry['code'] === 'duplicate');
        }
    }
    check('every pair served exactly once', count($seenPairs) === 3);
    $done = vote_next_pair($c, $voter);
    check('voter sees "complete" after all pairs', $done['status'] === 'complete' && $done['progress']['total'] === 3);
    check('3 vote rows recorded', (int)q_val("SELECT COUNT(*) FROM votes WHERE competition_id = ? AND status = 'counted'", [$c['id']]) === 3);
    check('cached counter matches records', (int)comp_find((int)$c['id'])['votes_total'] === 3);

    // Forged token for an already-voted pair
    $parts = comp_participants((int)$c['id']);
    $_SESSION['pairs'][(int)$c['id']]['forged'] = ['a' => (int)$parts[0]['id'], 'b' => (int)$parts[1]['id'], 't' => time()];
    $dup = vote_cast($c, 'forged', $parts[0]['public_id'], uuid4(), $voter, ip_hash());
    check('same browser cannot vote twice on a pair', !$dup['ok'] && $dup['code'] === 'already_voted');
    $nope = vote_cast($c, 'not-a-token', $parts[0]['public_id'], uuid4(), keyed_hash('voter-x', 'voter'), ip_hash());
    check('vote without a served pair is rejected', !$nope['ok'] && $nope['code'] === 'stale');

    // Participant from another competition
    $other = make_comp([], 2);
    $op = comp_participants((int)$other['comp']['id']);
    fresh_session();
    $v2 = keyed_hash('voter-2', 'voter');
    $n = vote_next_pair($c, $v2);
    $bad = vote_cast($c, $n['token'], $op[0]['public_id'], uuid4(), $v2, ip_hash());
    check('choice from another competition rejected', !$bad['ok'] && $bad['code'] === 'bad_request');
    $badKey = vote_cast($c, $n['token'], $n['pair'][0]['id'], 'not-a-uuid', $v2, ip_hash());
    check('malformed idempotency key rejected', !$badKey['ok']);

    // Closing while voting
    q('UPDATE competitions SET closes_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE id = ?', [$c['id']]);
    $closed = comp_find((int)$c['id']);
    $late = vote_cast($closed, $n['token'], $n['pair'][0]['id'], uuid4(), $v2, ip_hash());
    check('vote after closing is rejected', !$late['ok'] && $late['code'] === 'closed');
    check('next pair reports closed', vote_next_pair($closed, $v2)['status'] === 'closed');
    $late2 = vote_cast($c, $n['token'], $n['pair'][0]['id'], uuid4(), $v2, ip_hash()); // stale in-memory row says open
    check('database re-check catches stale "open" state', !$late2['ok'] && $late2['code'] === 'closed');

    section('Ranking and ties');
    $t = make_comp([], 2);
    $tc = $t['comp'];
    $tp = comp_participants((int)$tc['id']);
    foreach ([[0, 1, 'v-a'], [1, 0, 'v-b']] as [$w, $l, $vk]) {
        q('INSERT INTO votes (competition_id, winner_id, loser_id, pair_low, pair_high, voter_hash, idempotency_key) VALUES (?,?,?,?,?,?,?)',
            [$tc['id'], $tp[$w]['id'], $tp[$l]['id'], min($tp[0]['id'], $tp[1]['id']), max($tp[0]['id'], $tp[1]['id']), keyed_hash($vk, 'voter'), uuid4()]);
    }
    $rk = ranking_compute((int)$tc['id']);
    check('1–1 split produces a shared first place', $rk['rows'][0]['rank'] === 1 && $rk['rows'][1]['rank'] === 1 && $rk['leader_tied']);
    q('UPDATE competitions SET closes_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE id = ?', [$tc['id']]);
    check('no single winner declared on a tie', ranking_winner(comp_find((int)$tc['id']), $rk) === null);
    q('INSERT INTO votes (competition_id, winner_id, loser_id, pair_low, pair_high, voter_hash, idempotency_key) VALUES (?,?,?,?,?,?,?)',
        [$tc['id'], $tp[0]['id'], $tp[1]['id'], min($tp[0]['id'], $tp[1]['id']), max($tp[0]['id'], $tp[1]['id']), keyed_hash('v-c', 'voter'), uuid4()]);
    $rk = ranking_compute((int)$tc['id']);
    $win = ranking_winner(comp_find((int)$tc['id']), $rk);
    check('clear leader becomes winner after close', $win && (int)$win['id'] === (int)$tp[0]['id']);
    check('vote share sums to 100%', abs(array_sum(array_column($rk['rows'], 'vote_share')) - 100) < 0.01);
    q("UPDATE votes SET status = 'voided' WHERE competition_id = ? AND voter_hash = ?", [$tc['id'], keyed_hash('v-c', 'voter')]);
    check('voided votes are excluded from ranking', ranking_compute((int)$tc['id'])['total_votes'] === 2);

    section('Results visibility');
    $rv = make_comp(['results_visibility' => 'after_close'], 2)['comp'];
    fresh_session();
    check('after_close: hidden while open', !comp_results_visible($rv));
    q('UPDATE competitions SET closes_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE id = ?', [$rv['id']]);
    check('after_close: visible after close', comp_results_visible(comp_find((int)$rv['id'])));
    $rr = make_comp(['results_visibility' => 'restricted'], 2);
    fresh_session();
    check('restricted: hidden from visitors', !comp_results_visible($rr['comp']));
    grant_creator($rr['comp']);
    check('restricted: visible to creator', comp_results_visible($rr['comp']));
    fresh_session();
    $n = vote_next_pair($rr['comp'], keyed_hash('v-r', 'voter'));
    $vr = vote_cast($rr['comp'], $n['token'], $n['pair'][0]['id'], uuid4(), keyed_hash('v-r', 'voter'), null);
    check('vote response leaks no split when results hidden', $vr['ok'] && $vr['split'] === null);

    section('Private matches');
    $pr = make_comp(['visibility' => 'private', 'access_code' => 'ABCD2345'], 2);
    $pc = $pr['comp'];
    fresh_session();
    check('private match is approved but never listed', !comp_is_listed($pc) && !in_array($pc['id'], array_column(comp_listing([], 200), 'id')));
    check('no access without invite', !comp_viewable($pc));
    check('invite token stored hashed', $pc['invite_token_hash'] === invite_token_hash($pr['invite_token']));
    check('creator can read invite link again', comp_share_url($pc) === abs_url('/p/' . $pr['invite_token']));
    check('access code stored as password hash', password_verify('ABCD2345', $pc['access_code_hash']));
    grant_invite($pc);
    check('access after invite grant', comp_viewable($pc));
    comp_rotate_invite((int)$pc['id'], null);
    check('rotating the invite revokes existing grants', !comp_viewable(comp_find((int)$pc['id'])));
    $fresh = comp_find((int)$pc['id']);
    grant_invite($fresh);
    q('UPDATE competitions SET invite_expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE id = ?', [$pc['id']]);
    check('expired invite blocks access', !comp_viewable(comp_find((int)$pc['id'])));
    fresh_session();
    grant_creator($fresh);
    check('creator keeps access independently of invite', comp_viewable(comp_find((int)$pc['id'])));
    comp_rotate_manage((int)$pc['id']);
    check('rotating the manage link revokes creator grant', !is_creator(comp_find((int)$pc['id'])));

    section('Reports and moderation');
    $mr = make_comp([], 3);
    $mc = comp_find((int)$mr['comp']['id']);
    q("UPDATE competitions SET moderation_status = 'approved' WHERE id = ?", [$mc['id']]);
    $mc = comp_find((int)$mc['id']);
    $mp = comp_participants((int)$mc['id']);
    require_once APP_ROOT . '/app/controllers/public.php';
    for ($i = 1; $i <= 3; $i++) {
        q('INSERT INTO reports (competition_id, participant_id, reason) VALUES (?,?,?)', [$mc['id'], $mp[0]['id'], 'inappropriate']);
        $p0 = q_one('SELECT * FROM participants WHERE id = ?', [$mp[0]['id']]);
        report_auto_triage(comp_find((int)$mc['id']), $p0, 'inappropriate');
    }
    $p0 = q_one('SELECT * FROM participants WHERE id = ?', [$mp[0]['id']]);
    check('3 reports escalate and hide the photo', $p0['image_status'] === 'escalated' && !participant_image_servable($p0));
    check('hidden contender excluded from matchups', count(playable_participants((int)$mc['id'])) === 2);
    check('match with 3 open reports is delisted', comp_find((int)$mc['id'])['moderation_status'] === 'pending');
    $minor = q_one('SELECT * FROM participants WHERE id = ?', [$mp[1]['id']]);
    report_auto_triage(null, $minor, 'minor');
    check('a single "under 18" report hides the photo immediately', q_val('SELECT image_status FROM participants WHERE id = ?', [$mp[1]['id']]) === 'escalated');
    $file = media_locate($mp[2]['image_file']);
    participant_remove_image((int)$mp[2]['id'], 'test', null);
    check('removing a participant deletes the photo file', $file && !is_file($file));
    check('removed participant leaves rankings', count(ranking_compute((int)$mc['id'])['rows']) === 2);

    section('Deletion');
    $dc = make_comp([], 2);
    $files = array_map(static fn($p) => media_locate($p['image_file']), comp_participants((int)$dc['comp']['id']));
    comp_soft_delete((int)$dc['comp']['id']);
    fresh_session();
    grant_creator($dc['comp']);
    check('deleted match not viewable even by creator', !comp_viewable(comp_find((int)$dc['comp']['id'])));
    check('deleted match photo files removed', !array_filter($files, 'is_file'));

    section('Advertising');
    $mk = static function (int $prio, string $start, string $end, string $status = 'active') use (&$adIds): int {
        q("INSERT INTO ad_campaigns (public_id, name, advertiser, creative_type, headline, destination_url, placement, priority, status, starts_at, ends_at)
           VALUES (?,?,?,?,?,?,?,?,?,?,?)", [random_id(12), '[test] ad', 'Test Co', 'text', 'Hello', 'https://example.com', 'footer_banner', $prio, $status, $start, $end]);
        return $adIds[] = (int)db()->lastInsertId();
    };
    $past = $mk(9, gmdate('Y-m-d H:i:s', time() - 86400 * 10), gmdate('Y-m-d H:i:s', time() - 86400));
    $future = $mk(9, gmdate('Y-m-d H:i:s', time() + 86400), gmdate('Y-m-d H:i:s', time() + 86400 * 5));
    $paused = $mk(9, gmdate('Y-m-d H:i:s', time() - 60), gmdate('Y-m-d H:i:s', time() + 86400), 'paused');
    $low = $mk(2, gmdate('Y-m-d H:i:s', time() - 60), gmdate('Y-m-d H:i:s', time() + 86400));
    $high = $mk(7, gmdate('Y-m-d H:i:s', time() - 60), gmdate('Y-m-d H:i:s', time() + 86400));
    $picked = ad_pick('footer_banner');
    check('highest running priority is served (expired/future/paused skipped)', $picked && (int)$picked['id'] === $high);
    ad_bump($high, 'impressions');
    ad_bump($high, 'impressions');
    ad_bump($high, 'clicks');
    $tot = ad_totals($high);
    check('impressions, clicks and CTR computed from stats', $tot['impressions'] === 2 && $tot['clicks'] === 1 && abs($tot['ctr'] - 50) < 0.001);
    check('expired campaign labelled Expired', ad_state_label(q_one('SELECT * FROM ad_campaigns WHERE id = ?', [$past])) === 'Expired');

    section('Rate limiting');
    $key = 'test_' . random_id(6);
    $results = [];
    for ($i = 0; $i < 4; $i++) {
        $results[] = rate_limit($key, 3, 60, 'subject-' . $key);
    }
    check('allows up to the limit then blocks', $results === [true, true, true, false]);

    section('Admin permissions');
    $roles = ['super_admin' => ['ads', 'settings', 'moderation'], 'admin' => ['moderation', 'votes.void'], 'moderator' => ['moderation', 'reports'], 'ad_manager' => ['ads']];
    foreach ($roles as $role => $allowed) {
        foreach ($allowed as $perm) {
            check("$role can $perm", admin_can($perm, ['role' => $role]));
        }
    }
    check('moderator cannot manage ads', !admin_can('ads', ['role' => 'moderator']));
    check('moderator cannot delete competitions', !admin_can('competitions.delete', ['role' => 'moderator']));
    check('ad manager cannot moderate', !admin_can('moderation', ['role' => 'ad_manager']));
    check('admin cannot manage administrators', !admin_can('admins', ['role' => 'admin']));
    check('admin cannot change settings', !admin_can('settings', ['role' => 'admin']));

    section('Housekeeping');
    q('UPDATE votes SET created_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 400 DAY) WHERE competition_id = ?', [$c['id']]);
    run_housekeeping();
    check('IP-derived keys cleared after retention', (int)q_val('SELECT COUNT(*) FROM votes WHERE competition_id = ? AND ip_hash IS NOT NULL', [$c['id']]) === 0);
    check('expired active matches marked closed', comp_find((int)$c['id'])['status'] === 'closed');
} catch (Throwable $e) {
    $fail++;
    echo "\n\033[31mUnexpected error: " . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\033[0m\n";
} finally {
    foreach ($created as $id) {
        foreach (q_all('SELECT image_file, thumb_file FROM participants WHERE competition_id = ?', [$id]) as $p) {
            delete_media($p['image_file']);
            delete_media($p['thumb_file']);
        }
        q('DELETE FROM reports WHERE competition_id = ?', [$id]);
        q('DELETE FROM competitions WHERE id = ?', [$id]);
    }
    if ($adIds) {
        q('DELETE FROM ad_campaigns WHERE id IN (' . placeholders($adIds) . ')', $adIds);
    }
    foreach ($tmpFiles as $f) {
        @unlink($f);
    }
}

echo "\n" . ($fail ? "\033[31m" : "\033[32m") . "$pass passed, $fail failed\033[0m\n";
exit($fail ? 1 : 0);
