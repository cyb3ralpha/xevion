<?php
/**
 * Administrator dashboard handlers. Every handler calls admin_require() with the
 * permission it needs; every state-changing action verifies CSRF and writes the audit log.
 */
declare(strict_types=1);

function admin_render(string $tpl, array $vars = []): void
{
    $vars['admin'] = admin_current();
    render('admin/' . $tpl, $vars, 'admin/layout');
}

function admin_post_guard(): void
{
    if (!is_post()) {
        return;
    }
    csrf_require();
}

function admin_back(string $fallback): never
{
    $to = str_input('back', 300);
    if ($to !== '' && str_starts_with($to, base_path() . '/admin') && !str_contains($to, '//')) {
        redirect($to);
    }
    redirect($fallback);
}

function paginate(int $total, int $per = 30): array
{
    $pages = max(1, (int)ceil($total / $per));
    $page = min($pages, max(1, (int)input('page', 1)));
    return [$page, $pages, ($page - 1) * $per, $per];
}

/* ============================================================ auth */

function admin_login(): void
{
    if (admin_current()) {
        redirect(admin_url());
    }
    $error = null;
    $email = '';
    if (is_post()) {
        csrf_require();
        $email = str_input('email', 190);
        $error = admin_attempt_login($email, (string)($_POST['password'] ?? ''));
        if ($error === null) {
            $to = $_SESSION['after_login'] ?? admin_url();
            unset($_SESSION['after_login']);
            redirect(is_string($to) && str_starts_with($to, base_path() . '/admin') ? $to : admin_url());
        }
    }
    render('admin/login', ['title' => 'Sign in', 'error' => $error, 'email' => $email], null);
}

function admin_logout_page(): void
{
    if (is_post()) {
        csrf_require();
        if ($a = admin_current()) {
            audit('admin.logout', 'admin', (int)$a['id']);
        }
        admin_logout('You have signed out.');
    }
    redirect(admin_url('login'));
}

function admin_account(): void
{
    $me = admin_require();
    $error = null;
    if (is_post()) {
        csrf_require();
        $row = q_one('SELECT password_hash FROM admins WHERE id = ?', [$me['id']]);
        $new = (string)($_POST['new_password'] ?? '');
        if (!password_verify((string)($_POST['current_password'] ?? ''), $row['password_hash'])) {
            $error = 'Your current password is not correct.';
        } elseif ($new !== ($_POST['new_password2'] ?? '')) {
            $error = 'The new passwords do not match.';
        } elseif ($pe = password_policy_error($new)) {
            $error = $pe;
        } elseif (password_verify($new, $row['password_hash'])) {
            $error = 'Choose a password different from your current one.';
        } else {
            $now = now_utc();
            q('UPDATE admins SET password_hash = ?, password_changed_at = ?, must_change_password = 0 WHERE id = ?',
                [password_hash($new, PASSWORD_DEFAULT), $now, $me['id']]);
            session_regenerate_id(true);
            $_SESSION['admin_pwv'] = $now;
            audit('admin.password_changed', 'admin', (int)$me['id']);
            flash('success', 'Password updated.');
            redirect(admin_url('account'));
        }
    }
    admin_render('account', ['title' => 'My account', 'error' => $error, 'me' => $me]);
}

/* ============================================================ dashboard */

function admin_dashboard(): void
{
    admin_require('dashboard');
    $m = [];
    if (admin_can('competitions.view')) {
        $m['competitions'] = (int)q_val("SELECT COUNT(*) FROM competitions WHERE status <> 'deleted'");
        $m['active'] = (int)q_val("SELECT COUNT(*) FROM competitions WHERE status = 'active' AND closes_at > UTC_TIMESTAMP()");
        $m['closed'] = (int)q_val("SELECT COUNT(*) FROM competitions WHERE status = 'closed' OR (status = 'active' AND closes_at <= UTC_TIMESTAMP())");
        $m['pending'] = (int)q_val("SELECT COUNT(*) FROM competitions WHERE visibility = 'public' AND moderation_status = 'pending' AND status IN ('active','closed')");
        $m['suspended'] = (int)q_val("SELECT COUNT(*) FROM competitions WHERE status = 'suspended'");
        $m['votes'] = (int)q_val("SELECT COUNT(*) FROM votes WHERE status = 'counted'");
        $m['votes_24h'] = (int)q_val("SELECT COUNT(*) FROM votes WHERE status = 'counted' AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)");
        $m['images'] = (int)q_val('SELECT COUNT(*) FROM participants WHERE image_file IS NOT NULL');
        $m['queue'] = (int)q_val("SELECT COUNT(*) FROM participants p JOIN competitions c ON c.id = p.competition_id
                                  WHERE p.image_status IN ('pending','flagged','escalated') AND p.is_removed = 0 AND c.status <> 'deleted'");
        $daily = q_all("SELECT DATE(created_at) AS d, COUNT(*) AS n FROM votes WHERE status = 'counted'
                        AND created_at >= DATE_SUB(UTC_DATE(), INTERVAL 13 DAY) GROUP BY DATE(created_at)");
        $byDay = array_column($daily, 'n', 'd');
        $series = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = gmdate('Y-m-d', time() - $i * 86400);
            $series[$d] = (int)($byDay[$d] ?? 0);
        }
        $m['series'] = $series;
    }
    if (admin_can('reports')) {
        $m['reports'] = (int)q_val("SELECT COUNT(*) FROM reports WHERE status IN ('open','investigating')");
    }
    if (admin_can('ads') || admin_can('ads.reports')) {
        $m['ads_active'] = (int)q_val("SELECT COUNT(*) FROM ad_campaigns WHERE status = 'active' AND starts_at <= UTC_TIMESTAMP() AND ends_at > UTC_TIMESTAMP()");
        $m['ads_30'] = ad_totals(null, 30);
        $m['ads_all'] = ad_totals();
    }
    if (admin_can('messages')) {
        $m['messages'] = (int)q_val("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'");
    }
    admin_render('dashboard', ['title' => 'Dashboard', 'm' => $m]);
}

/* ============================================================ competitions */

function admin_competitions(): void
{
    admin_require('competitions.view');
    $f = [
        'q' => str_input('q', 80),
        'status' => str_input('status', 20),
        'visibility' => str_input('visibility', 10),
        'moderation' => str_input('moderation', 10),
    ];
    $where = ['1=1'];
    $params = [];
    if ($f['q'] !== '') {
        $where[] = '(c.title LIKE ? OR c.slug = ?)';
        $params[] = '%' . addcslashes($f['q'], '%_\\') . '%';
        $params[] = $f['q'];
    }
    if ($f['status'] === 'open') {
        $where[] = "c.status = 'active' AND c.closes_at > UTC_TIMESTAMP()";
    } elseif ($f['status'] === 'closed') {
        $where[] = "(c.status = 'closed' OR (c.status = 'active' AND c.closes_at <= UTC_TIMESTAMP()))";
    } elseif (in_array($f['status'], ['suspended', 'deleted'], true)) {
        $where[] = 'c.status = ?';
        $params[] = $f['status'];
    } else {
        $where[] = "c.status <> 'deleted'";
    }
    if (in_array($f['visibility'], ['public', 'private'], true)) {
        $where[] = 'c.visibility = ?';
        $params[] = $f['visibility'];
    }
    if (in_array($f['moderation'], ['pending', 'approved', 'rejected'], true)) {
        $where[] = 'c.moderation_status = ?';
        $params[] = $f['moderation'];
    }
    $w = implode(' AND ', $where);
    $total = (int)q_val("SELECT COUNT(*) FROM competitions c WHERE $w", $params);
    [$page, $pages, $off, $per] = paginate($total);
    $rows = q_all("SELECT c.*, (SELECT COUNT(*) FROM participants p WHERE p.competition_id = c.id AND p.is_removed = 0) AS participant_count,
                          (SELECT COUNT(*) FROM reports r WHERE r.competition_id = c.id AND r.status IN ('open','investigating')) AS open_reports
                   FROM competitions c WHERE $w ORDER BY c.id DESC LIMIT $per OFFSET $off", $params);
    admin_render('competitions', ['title' => 'Competitions', 'rows' => $rows, 'f' => $f, 'total' => $total, 'page' => $page, 'pages' => $pages]);
}

function admin_competition(): void
{
    admin_require('competitions.view');
    $id = (int)input('id', 0);
    $c = comp_find($id);
    if (!$c) {
        render_error(404);
    }
    if (is_post()) {
        csrf_require();
        admin_competition_action($c, str_input('action', 40));
        redirect(admin_url('competition', ['id' => $id]));
    }
    $parts = q_all('SELECT p.*, (SELECT COUNT(*) FROM reports r WHERE r.participant_id = p.id AND r.status IN (\'open\',\'investigating\')) AS open_reports
                    FROM participants p WHERE p.competition_id = ? ORDER BY p.position, p.id', [$id]);
    $ranking = ranking_compute($id);
    $voteStats = q_one("SELECT SUM(status = 'counted') AS counted, SUM(status = 'voided') AS voided,
                               COUNT(DISTINCT voter_hash) AS voters FROM votes WHERE competition_id = ?", [$id]);
    $heavy = q_all("SELECT voter_hash, COUNT(*) AS n, MIN(created_at) AS first_at, MAX(created_at) AS last_at
                    FROM votes WHERE competition_id = ? AND status = 'counted' GROUP BY voter_hash ORDER BY n DESC LIMIT 8", [$id]);
    $heavyIp = q_all("SELECT ip_hash, COUNT(*) AS n, COUNT(DISTINCT voter_hash) AS browsers FROM votes
                      WHERE competition_id = ? AND status = 'counted' AND ip_hash IS NOT NULL GROUP BY ip_hash HAVING browsers > 1 ORDER BY n DESC LIMIT 5", [$id]);
    $reports = q_all('SELECT r.*, p.display_name FROM reports r LEFT JOIN participants p ON p.id = r.participant_id WHERE r.competition_id = ? ORDER BY r.id DESC LIMIT 50', [$id]);
    $partIds = array_column($parts, 'id');
    $auditParams = array_merge([$id], $partIds ?: [0]);
    $history = q_all("SELECT l.*, a.name AS admin_name FROM audit_log l LEFT JOIN admins a ON a.id = l.admin_id
                      WHERE (l.target_type = 'competition' AND l.target_id = ?) OR (l.target_type = 'participant' AND l.target_id IN (" . placeholders($partIds ?: [0]) . '))
                      ORDER BY l.id DESC LIMIT 100', $auditParams);
    admin_render('competition', [
        'title' => $c['title'], 'c' => $c, 'parts' => $parts, 'ranking' => $ranking, 'voteStats' => $voteStats,
        'heavy' => $heavy, 'heavyIp' => $heavyIp, 'reports' => $reports, 'history' => $history,
        'durations' => [24 => '1 day', 72 => '3 days', 168 => '7 days', 336 => '14 days', 720 => '30 days'],
    ]);
}

function admin_competition_action(array $c, string $action): void
{
    $id = (int)$c['id'];
    $need = static function (string $perm) {
        if (!admin_can($perm)) {
            render_error(403, 'Your role cannot perform this action.');
        }
    };
    if ($c['status'] === 'deleted' && $action !== 'noop') {
        flash('error', 'This competition was deleted; no further changes are possible.');
        return;
    }
    switch ($action) {
        case 'approve':
        case 'reject':
            $need('moderation');
            $new = $action === 'approve' ? 'approved' : 'rejected';
            q('UPDATE competitions SET moderation_status = ?' . ($new === 'rejected' ? ', is_featured = 0' : '') . ' WHERE id = ?', [$new, $id]);
            if ($new === 'approved') {
                q("UPDATE participants SET image_status = 'approved', moderated_at = UTC_TIMESTAMP(), moderated_by = ?
                   WHERE competition_id = ? AND image_status = 'pending'", [admin_current()['id'], $id]);
            }
            audit('competition.' . $action, 'competition', $id, ['note' => str_input('note', 300)]);
            flash('success', $new === 'approved' ? 'Approved for public listing.' : 'Rejected for public listing.');
            break;
        case 'feature':
        case 'unfeature':
            $need('competitions.manage');
            if ($action === 'feature' && !comp_is_listed($c)) {
                flash('error', 'Only approved public competitions can be featured.');
                break;
            }
            q('UPDATE competitions SET is_featured = ? WHERE id = ?', [$action === 'feature' ? 1 : 0, $id]);
            audit('competition.' . $action, 'competition', $id);
            flash('success', $action === 'feature' ? 'Featured on the homepage.' : 'No longer featured.');
            break;
        case 'suspend':
            $need('competitions.suspend');
            $reason = str_input('reason', 300);
            q("UPDATE competitions SET status = 'suspended', is_featured = 0 WHERE id = ?", [$id]);
            audit('competition.suspend', 'competition', $id, ['reason' => $reason, 'previous' => $c['status']]);
            flash('success', 'Competition suspended. It is hidden from everyone except admins.');
            break;
        case 'restore':
            $need('competitions.suspend');
            if ($c['status'] !== 'suspended') {
                break;
            }
            $to = strtotime($c['closes_at'] . ' UTC') > time() ? 'active' : 'closed';
            q('UPDATE competitions SET status = ? WHERE id = ?', [$to, $id]);
            audit('competition.restore', 'competition', $id, ['to' => $to]);
            flash('success', 'Competition restored.');
            break;
        case 'close':
            $need('competitions.manage');
            if (comp_is_open($c)) {
                q("UPDATE competitions SET status = 'closed', closed_at = UTC_TIMESTAMP() WHERE id = ?", [$id]);
                audit('competition.close', 'competition', $id);
                flash('success', 'Voting closed.');
            }
            break;
        case 'reopen':
            $need('competitions.manage');
            $hours = (int)input('hours', 0);
            if ($c['status'] === 'suspended' || $hours < 1 || $hours > 24 * 90) {
                flash('error', 'Cannot reopen: restore the competition first and choose a duration.');
                break;
            }
            q("UPDATE competitions SET status = 'active', closed_at = NULL, closes_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? HOUR) WHERE id = ?", [$hours, $id]);
            audit('competition.reopen', 'competition', $id, ['hours' => $hours]);
            flash('success', 'Voting reopened.');
            break;
        case 'visibility':
            $need('competitions.manage');
            $v = str_input('visibility', 10);
            if (!in_array($v, ['public', 'private'], true) || $v === $c['visibility']) {
                break;
            }
            if ($v === 'private') {
                q("UPDATE competitions SET visibility = 'private', is_featured = 0 WHERE id = ?", [$id]);
                comp_rotate_invite($id, null);
            } else {
                q("UPDATE competitions SET visibility = 'public', invite_token_hash = NULL, invite_token_enc = NULL, invite_expires_at = NULL,
                   access_code_hash = NULL, moderation_status = 'approved' WHERE id = ?", [$id]);
            }
            audit('competition.visibility', 'competition', $id, ['from' => $c['visibility'], 'to' => $v]);
            flash('success', $v === 'private' ? 'Now private. A new invite link is available to the creator on their management page.' : 'Now public and listed.');
            break;
        case 'edit':
            $need('competitions.manage');
            $title = str_input('title', 120);
            $desc = str_input('description', 600);
            $instr = str_input('instructions', 300);
            $custom = str_input('custom_category', 40);
            $sponsor = str_input('sponsor_name', 80);
            $sponsorUrl = str_input('sponsor_url', 500);
            if (mb_strlen($title) < 3) {
                flash('error', 'Title must be at least 3 characters.');
                break;
            }
            if ($sponsorUrl !== '' && !safe_external_url($sponsorUrl)) {
                flash('error', 'Sponsor URL must be a full http(s) address.');
                break;
            }
            $changes = [];
            foreach (['title' => $title, 'description' => $desc, 'instructions' => $instr, 'sponsor_name' => $sponsor, 'sponsor_url' => $sponsorUrl] as $k => $v) {
                if ((string)$c[$k] !== $v) {
                    $changes[$k] = ['from' => $c[$k], 'to' => $v];
                }
            }
            if ($c['category'] === 'custom' && $custom !== '' && $custom !== $c['custom_category']) {
                $changes['custom_category'] = ['from' => $c['custom_category'], 'to' => $custom];
            }
            q('UPDATE competitions SET title = ?, description = ?, instructions = ?, sponsor_name = ?, sponsor_url = ?, custom_category = ? WHERE id = ?',
                [$title, $desc ?: null, $instr ?: null, $sponsor ?: null, $sponsorUrl ?: null, $c['category'] === 'custom' ? ($custom ?: $c['custom_category']) : null, $id]);
            if ($changes) {
                audit('competition.edit', 'competition', $id, $changes);
            }
            flash('success', 'Details saved.');
            break;
        case 'remove_participant':
            $need('moderation');
            $pid = (int)input('participant_id', 0);
            $p = q_one('SELECT * FROM participants WHERE id = ? AND competition_id = ?', [$pid, $id]);
            if ($p && !$p['is_removed']) {
                $reason = str_input('reason', 255) ?: 'Removed by moderator';
                participant_remove_image($pid, $reason, (int)admin_current()['id']);
                audit('participant.remove', 'participant', $pid, ['competition' => $id, 'reason' => $reason]);
                flash('success', 'Participant and photo removed. Their votes no longer count toward rankings.');
            }
            break;
        case 'void_voter':
            $need('votes.void');
            $vh = str_input('voter_hash', 64);
            $reason = str_input('reason', 255);
            if (!preg_match('/^[0-9a-f]{64}$/', $vh) || mb_strlen($reason) < 5) {
                flash('error', 'Give a reason (at least 5 characters) for voiding votes.');
                break;
            }
            $n = db_tx(function () use ($id, $vh, $reason) {
                $n = q("UPDATE votes SET status = 'voided', voided_reason = ?, voided_by = ?, voided_at = UTC_TIMESTAMP()
                        WHERE competition_id = ? AND voter_hash = ? AND status = 'counted'", [$reason, admin_current()['id'], $id, $vh])->rowCount();
                q("UPDATE competitions SET votes_total = (SELECT COUNT(*) FROM votes WHERE competition_id = ? AND status = 'counted') WHERE id = ?", [$id, $id]);
                return $n;
            });
            audit('votes.void', 'competition', $id, ['voter' => substr($vh, 0, 12), 'count' => $n, 'reason' => $reason]);
            flash('success', "$n votes voided. They remain on record but no longer count.");
            break;
        case 'recount':
            $need('competitions.manage');
            q("UPDATE competitions SET votes_total = (SELECT COUNT(*) FROM votes WHERE competition_id = ? AND status = 'counted') WHERE id = ?", [$id, $id]);
            audit('competition.recount', 'competition', $id);
            flash('success', 'Vote counter recalculated from vote records.');
            break;
        case 'delete':
            $need('competitions.delete');
            if (str_input('confirm', 20) !== $c['slug']) {
                flash('error', 'Type the competition ID exactly to confirm deletion.');
                break;
            }
            comp_soft_delete($id);
            audit('competition.delete', 'competition', $id, ['reason' => str_input('reason', 300)]);
            flash('success', 'Competition deleted and photo files removed.');
            break;
    }
}

/* ============================================================ moderation */

function admin_moderation(): void
{
    $me = admin_require('moderation');
    if (is_post()) {
        csrf_require();
        $pid = (int)input('participant_id', 0);
        $action = str_input('action', 20);
        $p = q_one('SELECT * FROM participants WHERE id = ?', [$pid]);
        if ($p && !$p['is_removed']) {
            $map = ['approve' => 'approved', 'reject' => 'rejected', 'escalate' => 'escalated'];
            if (isset($map[$action])) {
                q('UPDATE participants SET image_status = ?, moderated_at = UTC_TIMESTAMP(), moderated_by = ? WHERE id = ?', [$map[$action], $me['id'], $pid]);
                audit('participant.' . $action, 'participant', $pid, ['competition' => (int)$p['competition_id'], 'from' => $p['image_status']]);
                if ($action === 'approve' && input('resolve_reports') === '1') {
                    q("UPDATE reports SET status = 'dismissed', handled_by = ?, handled_at = UTC_TIMESTAMP(), resolution_note = 'Photo reviewed and approved'
                       WHERE participant_id = ? AND status IN ('open','investigating')", [$me['id'], $pid]);
                }
                flash('success', 'Photo ' . $map[$action] . '.');
            } elseif ($action === 'remove') {
                participant_remove_image($pid, str_input('reason', 255) ?: 'Removed by moderator', (int)$me['id']);
                q("UPDATE reports SET status = 'resolved', handled_by = ?, handled_at = UTC_TIMESTAMP(), resolution_note = 'Photo removed'
                   WHERE participant_id = ? AND status IN ('open','investigating')", [$me['id'], $pid]);
                audit('participant.remove', 'participant', $pid, ['competition' => (int)$p['competition_id']]);
                flash('success', 'Photo deleted and participant removed.');
            }
        }
        admin_back(admin_url('moderation'));
    }
    $status = str_input('status', 12);
    $statuses = in_array($status, ['pending', 'flagged', 'escalated', 'rejected'], true) ? [$status] : ['pending', 'flagged', 'escalated'];
    $rows = q_all('SELECT p.*, c.title, c.slug, c.visibility, c.id AS cid, c.moderation_status,
                          (SELECT COUNT(*) FROM reports r WHERE r.participant_id = p.id AND r.status IN (\'open\',\'investigating\')) AS open_reports,
                          (SELECT GROUP_CONCAT(DISTINCT r.reason) FROM reports r WHERE r.participant_id = p.id AND r.status IN (\'open\',\'investigating\')) AS reasons
                   FROM participants p JOIN competitions c ON c.id = p.competition_id
                   WHERE p.is_removed = 0 AND c.status <> \'deleted\' AND p.image_status IN (' . placeholders($statuses) . ')
                   ORDER BY FIELD(p.image_status, \'escalated\', \'flagged\', \'pending\', \'rejected\'), open_reports DESC, p.uploaded_at ASC LIMIT 120', $statuses);
    $pendingComps = q_all("SELECT c.*, (SELECT COUNT(*) FROM participants p WHERE p.competition_id = c.id AND p.is_removed = 0) AS participant_count
                           FROM competitions c WHERE c.visibility = 'public' AND c.moderation_status = 'pending' AND c.status IN ('active','closed')
                           ORDER BY c.created_at ASC LIMIT 50");
    admin_render('moderation', ['title' => 'Moderation queue', 'rows' => $rows, 'status' => $status, 'pendingComps' => $pendingComps]);
}

function admin_media(): void
{
    $me = admin_require();
    if ($pub = str_input('p', 16)) {
        if (!admin_can('competitions.view') && !admin_can('moderation') && !admin_can('reports')) {
            render_error(403);
        }
        $p = q_one('SELECT * FROM participants WHERE public_id = ?', [$pub]);
        $file = $p ? media_abs_path(str_input('s', 5) === 'full' ? $p['image_file'] : $p['thumb_file']) : null;
    } elseif ($ad = str_input('ad', 12)) {
        if (!admin_can('ads')) {
            render_error(403);
        }
        $row = q_one('SELECT * FROM ad_campaigns WHERE public_id = ?', [$ad]);
        $file = $row ? media_abs_path(str_input('v', 1) === 'm' ? $row['mobile_image_file'] : $row['image_file']) : null;
    } else {
        $file = null;
    }
    if (!$file) {
        render_error(404);
    }
    send_media($file, true);
}

/* ============================================================ reports */

function admin_reports(): void
{
    $me = admin_require('reports');
    if (is_post()) {
        csrf_require();
        $rid = (int)input('report_id', 0);
        $action = str_input('action', 20);
        $map = ['investigate' => 'investigating', 'resolve' => 'resolved', 'dismiss' => 'dismissed', 'reopen' => 'open'];
        $r = q_one('SELECT * FROM reports WHERE id = ?', [$rid]);
        if ($r && isset($map[$action])) {
            $note = str_input('note', 500);
            q('UPDATE reports SET status = ?, resolution_note = COALESCE(NULLIF(?, \'\'), resolution_note), handled_by = ?, handled_at = UTC_TIMESTAMP() WHERE id = ?',
                [$map[$action], $note, $me['id'], $rid]);
            audit('report.' . $action, 'report', $rid, ['note' => $note]);
            flash('success', 'Report marked ' . $map[$action] . '.');
        }
        admin_back(admin_url('reports'));
    }
    $status = str_input('status', 15) ?: 'active';
    $where = $status === 'active' ? "r.status IN ('open','investigating')" : (in_array($status, ['open', 'investigating', 'resolved', 'dismissed'], true) ? 'r.status = ?' : '1=1');
    $params = in_array($status, ['open', 'investigating', 'resolved', 'dismissed'], true) ? [$status] : [];
    $total = (int)q_val("SELECT COUNT(*) FROM reports r WHERE $where", $params);
    [$page, $pages, $off, $per] = paginate($total);
    $rows = q_all("SELECT r.*, c.title, c.slug, c.id AS cid, p.display_name, p.public_id, p.image_status, p.is_removed, a.name AS handler
                   FROM reports r LEFT JOIN competitions c ON c.id = r.competition_id LEFT JOIN participants p ON p.id = r.participant_id
                   LEFT JOIN admins a ON a.id = r.handled_by WHERE $where ORDER BY (r.reason = 'minor') DESC, r.id DESC LIMIT $per OFFSET $off", $params);
    admin_render('reports', ['title' => 'Reports', 'rows' => $rows, 'status' => $status, 'page' => $page, 'pages' => $pages, 'total' => $total]);
}

/* ============================================================ ads */

function admin_ads(): void
{
    admin_require('ads');
    $rows = q_all('SELECT a.*, COALESCE(SUM(s.impressions),0) AS impressions, COALESCE(SUM(s.clicks),0) AS clicks
                   FROM ad_campaigns a LEFT JOIN ad_stats_daily s ON s.campaign_id = a.id
                   GROUP BY a.id ORDER BY (a.status = \'archived\'), a.ends_at DESC');
    admin_render('ads', ['title' => 'Advertising', 'rows' => $rows]);
}

function admin_ad(): void
{
    $me = admin_require('ads');
    $id = (int)input('id', 0);
    $ad = $id ? q_one('SELECT * FROM ad_campaigns WHERE id = ?', [$id]) : null;
    if ($id && !$ad) {
        render_error(404);
    }
    $errors = [];
    $old = $ad ?? ['name' => '', 'advertiser' => '', 'creative_type' => 'image', 'headline' => '', 'body_text' => '', 'cta_label' => '',
        'destination_url' => 'https://', 'placement' => 'top_banner', 'priority' => 5, 'status' => 'draft',
        'starts_at' => now_utc(), 'ends_at' => gmdate('Y-m-d H:i:s', time() + 30 * 86400), 'budget_amount' => '', 'budget_currency' => 'USD', 'notes' => ''];

    if (is_post()) {
        csrf_require();
        if (str_input('action', 20) === 'status' && $ad) {
            $st = str_input('status', 10);
            if (in_array($st, ['draft', 'active', 'paused', 'archived'], true)) {
                if ($st === 'active' && !$ad['image_file'] && $ad['creative_type'] === 'image') {
                    flash('error', 'Upload a banner image before activating.');
                } else {
                    q('UPDATE ad_campaigns SET status = ? WHERE id = ?', [$st, $id]);
                    audit('ad.status', 'ad', $id, ['from' => $ad['status'], 'to' => $st]);
                    flash('success', 'Campaign status: ' . $st . '.');
                }
            }
            redirect(admin_url('ad', ['id' => $id]));
        }
        $in = [
            'name' => str_input('name', 120), 'advertiser' => str_input('advertiser', 120),
            'creative_type' => str_input('creative_type', 10), 'headline' => str_input('headline', 90),
            'body_text' => str_input('body_text', 160), 'cta_label' => str_input('cta_label', 30),
            'destination_url' => str_input('destination_url', 500), 'placement' => str_input('placement', 20),
            'priority' => (int)input('priority', 5), 'status' => str_input('status', 10),
            'starts_at' => str_input('starts_at', 20), 'ends_at' => str_input('ends_at', 20),
            'budget_amount' => str_input('budget_amount', 20), 'budget_currency' => strtoupper(str_input('budget_currency', 3)), 'notes' => str_input('notes', 2000),
        ];
        $old = array_merge($old, $in);
        if (mb_strlen($in['name']) < 2) { $errors['name'] = 'Campaign name is required.'; }
        if (mb_strlen($in['advertiser']) < 2) { $errors['advertiser'] = 'Advertiser name is required.'; }
        if (!in_array($in['creative_type'], ['image', 'text'], true)) { $errors['creative_type'] = 'Choose a creative type.'; }
        if (!safe_external_url($in['destination_url'])) { $errors['destination_url'] = 'Use a full http:// or https:// address (no javascript:, data: or other schemes).'; }
        if (!in_array($in['placement'], ['top_banner', 'footer_banner'], true)) { $errors['placement'] = 'Choose a placement.'; }
        if ($in['priority'] < 1 || $in['priority'] > 10) { $errors['priority'] = 'Priority must be 1–10.'; }
        if (!in_array($in['status'], ['draft', 'active', 'paused', 'archived'], true)) { $errors['status'] = 'Choose a status.'; }
        $s = strtotime($in['starts_at'] . ' UTC');
        $e = strtotime($in['ends_at'] . ' UTC');
        if (!$s || !$e || $e <= $s) { $errors['ends_at'] = 'End must be after start (both in UTC).'; }
        if ($in['creative_type'] === 'text' && mb_strlen($in['headline']) < 3) { $errors['headline'] = 'Text ads need a headline.'; }
        if ($in['budget_amount'] !== '' && !preg_match('/^\d{1,10}(\.\d{1,2})?$/', $in['budget_amount'])) { $errors['budget_amount'] = 'Budget must be a number like 1500 or 1500.00.'; }
        if ($in['budget_amount'] !== '' && !preg_match('/^[A-Z]{3}$/', $in['budget_currency'])) { $errors['budget_amount'] = 'Use a 3-letter currency code.'; }

        $newImg = $newMobile = null;
        foreach (['image' => false, 'mobile_image' => true] as $field => $mobile) {
            $f = $_FILES[$field] ?? null;
            if ($f && (int)$f['error'] !== UPLOAD_ERR_NO_FILE && !$errors) {
                try {
                    $path = store_ad_creative($f, $mobile);
                    $mobile ? $newMobile = $path : $newImg = $path;
                } catch (UploadError $ue) {
                    $errors[$field] = ($mobile ? 'Mobile banner ' : 'Banner ') . $ue->getMessage() . '.';
                }
            }
        }
        $hasImage = $newImg || ($ad['image_file'] ?? null);
        if (!$errors && $in['creative_type'] === 'image' && !$hasImage && $in['status'] === 'active') {
            $errors['image'] = 'Image campaigns need a banner before they can be active.';
        }
        if ($errors) {
            delete_media($newImg);
            delete_media($newMobile);
        } else {
            $vals = [$in['name'], $in['advertiser'], $in['creative_type'], $in['headline'] ?: null, $in['body_text'] ?: null, $in['cta_label'] ?: null,
                safe_external_url($in['destination_url']), $in['placement'], $in['priority'], $in['status'], gmdate('Y-m-d H:i:s', $s), gmdate('Y-m-d H:i:s', $e),
                $in['budget_amount'] !== '' ? $in['budget_amount'] : null, $in['budget_amount'] !== '' ? $in['budget_currency'] : null, $in['notes'] ?: null];
            if ($ad) {
                q('UPDATE ad_campaigns SET name=?, advertiser=?, creative_type=?, headline=?, body_text=?, cta_label=?, destination_url=?, placement=?,
                   priority=?, status=?, starts_at=?, ends_at=?, budget_amount=?, budget_currency=?, notes=? WHERE id = ?', array_merge($vals, [$id]));
                if ($newImg) { delete_media($ad['image_file']); q('UPDATE ad_campaigns SET image_file = ? WHERE id = ?', [$newImg, $id]); }
                if ($newMobile) { delete_media($ad['mobile_image_file']); q('UPDATE ad_campaigns SET mobile_image_file = ? WHERE id = ?', [$newMobile, $id]); }
                if (input('remove_mobile') === '1' && !$newMobile) { delete_media($ad['mobile_image_file']); q('UPDATE ad_campaigns SET mobile_image_file = NULL WHERE id = ?', [$id]); }
                audit('ad.update', 'ad', $id, ['status' => $in['status']]);
            } else {
                q('INSERT INTO ad_campaigns (name, advertiser, creative_type, headline, body_text, cta_label, destination_url, placement, priority, status,
                   starts_at, ends_at, budget_amount, budget_currency, notes, public_id, image_file, mobile_image_file, created_by)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', array_merge($vals, [random_id(12), $newImg, $newMobile, $me['id']]));
                $id = (int)db()->lastInsertId();
                audit('ad.create', 'ad', $id, ['name' => $in['name']]);
            }
            flash('success', 'Campaign saved.');
            redirect(admin_url('ad', ['id' => $id]));
        }
    }
    $daily = $ad ? q_all('SELECT * FROM ad_stats_daily WHERE campaign_id = ? AND day >= DATE_SUB(UTC_DATE(), INTERVAL 29 DAY) ORDER BY day DESC', [$id]) : [];
    admin_render('ad', ['title' => $ad ? 'Campaign: ' . $ad['name'] : 'New campaign', 'ad' => $ad, 'old' => $old, 'errors' => $errors,
        'daily' => $daily, 'totals' => $ad ? ad_totals($id) : null]);
}

/* ============================================================ admins */

function admin_admins(): void
{
    $me = admin_require('admins');
    $shown = null;
    if (is_post()) {
        csrf_require();
        $action = str_input('action', 20);
        $tid = (int)input('admin_id', 0);
        $target = $tid ? q_one('SELECT * FROM admins WHERE id = ?', [$tid]) : null;
        $activeSupers = (int)q_val("SELECT COUNT(*) FROM admins WHERE role = 'super_admin' AND is_active = 1");
        $isLastSuper = $target && $target['role'] === 'super_admin' && (int)$target['is_active'] && $activeSupers <= 1;
        switch ($action) {
            case 'create':
                $email = strtolower(str_input('email', 190));
                $name = str_input('name', 80);
                $role = str_input('role', 20);
                if (!valid_email($email) || mb_strlen($name) < 2 || !isset(admin_roles()[$role])) {
                    flash('error', 'Enter a valid email, name and role.');
                } elseif (q_val('SELECT 1 FROM admins WHERE email = ?', [$email])) {
                    flash('error', 'An administrator with that email already exists.');
                } else {
                    $temp = random_token(12);
                    q('INSERT INTO admins (email, name, password_hash, role, must_change_password, password_changed_at) VALUES (?,?,?,?,1,UTC_TIMESTAMP())',
                        [$email, $name, password_hash($temp, PASSWORD_DEFAULT), $role]);
                    $newId = (int)db()->lastInsertId();
                    audit('admin.create', 'admin', $newId, ['email' => $email, 'role' => $role]);
                    $_SESSION['admin_temp_pw'] = ['email' => $email, 'pw' => $temp];
                    flash('success', 'Administrator created. Give them the temporary password below through a secure channel.');
                }
                break;
            case 'role':
                $role = str_input('role', 20);
                if ($target && isset(admin_roles()[$role]) && $target['role'] !== $role) {
                    if ($isLastSuper) { flash('error', 'You cannot demote the last active super administrator.'); break; }
                    q('UPDATE admins SET role = ? WHERE id = ?', [$role, $tid]);
                    audit('admin.role', 'admin', $tid, ['from' => $target['role'], 'to' => $role]);
                    flash('success', 'Role updated.');
                }
                break;
            case 'toggle':
                if ($target) {
                    if ((int)$target['id'] === (int)$me['id']) { flash('error', 'You cannot deactivate yourself.'); break; }
                    if ($isLastSuper) { flash('error', 'You cannot deactivate the last active super administrator.'); break; }
                    $new = (int)$target['is_active'] ? 0 : 1;
                    q('UPDATE admins SET is_active = ? WHERE id = ?', [$new, $tid]);
                    audit($new ? 'admin.activate' : 'admin.deactivate', 'admin', $tid);
                    flash('success', $new ? 'Account activated.' : 'Account deactivated. Their sessions end on the next request.');
                }
                break;
            case 'reset':
                if ($target && (int)$target['id'] !== (int)$me['id']) {
                    $temp = random_token(12);
                    q('UPDATE admins SET password_hash = ?, must_change_password = 1, password_changed_at = UTC_TIMESTAMP() WHERE id = ?',
                        [password_hash($temp, PASSWORD_DEFAULT), $tid]);
                    audit('admin.password_reset', 'admin', $tid);
                    $_SESSION['admin_temp_pw'] = ['email' => $target['email'], 'pw' => $temp];
                    flash('success', 'Temporary password issued. They must change it at next sign-in.');
                }
                break;
        }
        redirect(admin_url('admins'));
    }
    $shown = $_SESSION['admin_temp_pw'] ?? null;
    unset($_SESSION['admin_temp_pw']);
    admin_render('admins', ['title' => 'Administrators', 'rows' => q_all('SELECT * FROM admins ORDER BY is_active DESC, role, name'), 'shown' => $shown, 'me' => $me]);
}

/* ============================================================ settings & blocks */

function settings_schema(): array
{
    return [
        'contact_email' => ['Public contact email', 'email'],
        'min_participants' => ['Minimum contenders per match', 'int', 2, 10],
        'max_participants' => ['Maximum contenders per match', 'int', 2, 50],
        'max_duration_days' => ['Longest voting period (days)', 'int', 1, 365],
        'max_upload_mb' => ['Max photo size (MB)', 'int', 1, 20],
        'public_requires_approval' => ['Public matches need approval before listing', 'bool'],
        'creations_per_hour' => ['Matches one network may create per hour', 'int', 1, 100],
        'creations_per_day' => ['Matches one network may create per day', 'int', 1, 500],
        'votes_per_minute' => ['Votes per minute per network', 'int', 5, 600],
        'ip_pair_limit_per_day' => ['Votes on the same pair per network per day', 'int', 1, 1000],
        'report_escalate_threshold' => ['Open reports that hide a photo / delist a match', 'int', 1, 50],
        'ip_retention_days' => ['Keep IP-derived keys for (days)', 'int', 1, 365],
        'show_house_ad' => ['Show “advertise with us” when no campaign is running', 'bool'],
        'blocked_terms' => ['Blocked words and phrases (one per line)', 'text'],
    ];
}

function admin_settings(): void
{
    admin_require('settings');
    $errors = [];
    if (is_post()) {
        csrf_require();
        $changed = [];
        $values = [];
        foreach (settings_schema() as $key => $def) {
            $raw = $_POST[$key] ?? '';
            $raw = is_string($raw) ? trim($raw) : '';
            switch ($def[1]) {
                case 'int':
                    if (!preg_match('/^\d+$/', $raw) || (int)$raw < $def[2] || (int)$raw > $def[3]) {
                        $errors[$key] = "Enter a whole number from {$def[2]} to {$def[3]}.";
                    }
                    break;
                case 'bool':
                    $raw = !empty($_POST[$key]) ? '1' : '0';
                    break;
                case 'email':
                    if ($raw !== '' && !valid_email($raw)) {
                        $errors[$key] = 'Enter a valid email or leave empty.';
                    }
                    break;
                case 'text':
                    $raw = implode("\n", array_filter(array_map('trim', explode("\n", str_replace("\r", '', mb_substr($raw, 0, 20000))))));
                    break;
            }
            $values[$key] = $raw;
        }
        if (!$errors && (int)$values['min_participants'] > (int)$values['max_participants']) {
            $errors['min_participants'] = 'Minimum cannot be larger than maximum.';
        }
        if (!$errors) {
            foreach ($values as $k => $v) {
                if ((string)setting($k) !== $v) {
                    $changed[$k] = $k === 'blocked_terms' ? 'updated' : ['from' => setting($k), 'to' => $v];
                    setting_save($k, $v);
                }
            }
            if ($changed) {
                audit('settings.update', 'settings', null, $changed);
            }
            flash('success', 'Settings saved.');
            redirect(admin_url('settings'));
        }
    }
    admin_render('settings', ['title' => 'Settings', 'schema' => settings_schema(), 'errors' => $errors, 'values' => $_POST ?: settings_all()]);
}

function admin_blocks(): void
{
    $me = admin_require('blocks');
    if (is_post()) {
        csrf_require();
        $bid = (int)input('block_id', 0);
        q('UPDATE abuse_blocks SET expires_at = UTC_TIMESTAMP() WHERE id = ?', [$bid]);
        audit('block.lift', 'block', $bid);
        flash('success', 'Block lifted.');
        redirect(admin_url('blocks'));
    }
    $rows = q_all('SELECT b.*, a.name AS admin_name FROM abuse_blocks b LEFT JOIN admins a ON a.id = b.created_by
                   WHERE b.expires_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY) ORDER BY b.expires_at DESC LIMIT 200');
    admin_render('blocks', ['title' => 'Abuse blocks', 'rows' => $rows]);
}

/* ============================================================ audit & messages */

function admin_audit(): void
{
    admin_require('audit.view');
    $f = ['action' => str_input('action', 64), 'target_type' => str_input('target_type', 32), 'target_id' => (int)input('target_id', 0)];
    $where = ['1=1'];
    $params = [];
    if ($f['action'] !== '') { $where[] = 'l.action LIKE ?'; $params[] = addcslashes($f['action'], '%_\\') . '%'; }
    if ($f['target_type'] !== '') { $where[] = 'l.target_type = ?'; $params[] = $f['target_type']; }
    if ($f['target_id'] > 0) { $where[] = 'l.target_id = ?'; $params[] = $f['target_id']; }
    $w = implode(' AND ', $where);
    $total = (int)q_val("SELECT COUNT(*) FROM audit_log l WHERE $w", $params);
    [$page, $pages, $off, $per] = paginate($total, 50);
    $rows = q_all("SELECT l.*, a.name AS admin_name FROM audit_log l LEFT JOIN admins a ON a.id = l.admin_id WHERE $w ORDER BY l.id DESC LIMIT $per OFFSET $off", $params);
    admin_render('audit', ['title' => 'Audit log', 'rows' => $rows, 'f' => $f, 'page' => $page, 'pages' => $pages, 'total' => $total]);
}

function admin_messages(): void
{
    admin_require('messages');
    if (is_post()) {
        csrf_require();
        $mid = (int)input('message_id', 0);
        $st = str_input('status', 10);
        if (in_array($st, ['new', 'read', 'archived'], true)) {
            q('UPDATE contact_messages SET status = ? WHERE id = ?', [$st, $mid]);
            audit('message.' . $st, 'message', $mid);
        }
        admin_back(admin_url('messages'));
    }
    $status = str_input('status', 10) ?: 'inbox';
    $where = $status === 'archived' ? "status = 'archived'" : "status IN ('new','read')";
    $rows = q_all("SELECT * FROM contact_messages WHERE $where ORDER BY (status = 'new') DESC, id DESC LIMIT 200");
    admin_render('messages', ['title' => 'Messages', 'rows' => $rows, 'status' => $status]);
}
