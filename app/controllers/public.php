<?php
/**
 * Public page and API handlers.
 */
declare(strict_types=1);

function require_not_blocked(): void
{
    if (is_blocked()) {
        render_error(429, 'Activity from your network was temporarily paused because of unusual traffic. Please try again later.');
    }
}

/* ============================================================ Home / discovery */

function page_home(): void
{
    $featured = comp_listing(['featured' => true, 'sort' => 'activity'], 6);
    $trending = comp_listing(['sort' => 'trending', 'status' => 'open'], 6);
    $recent = comp_listing(['sort' => 'recent'], 6);
    render('home', [
        'title' => 'FaceMatch — photo face-offs by XEVION',
        'featured' => $featured,
        'trending' => $trending,
        'recent' => $recent,
        'categoryCounts' => category_counts(),
        'isEmpty' => !$featured && !$trending && !$recent,
        'bodyClass' => 'page-home',
    ]);
}

function explore_filters(?string $category = null): array
{
    $sort = (string)input('sort', 'recent');
    $status = (string)input('status', '');
    return [
        'category' => $category ?? (string)input('category', ''),
        'sort' => in_array($sort, ['recent', 'trending', 'popular', 'closing', 'activity'], true) ? $sort : 'recent',
        'status' => in_array($status, ['open', 'closed'], true) ? $status : '',
        'q' => str_input('q', 60),
    ];
}

function page_explore(?string $category = null): void
{
    $f = explore_filters($category);
    $perPage = 12;
    $page = max(1, (int)input('page', 1));
    $total = comp_listing_count($f);
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    render('explore', [
        'title' => $category ? category_label(['category' => $category]) . ' matches' : 'Explore matches',
        'filters' => $f,
        'items' => comp_listing($f, $perPage, ($page - 1) * $perPage),
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'categoryCounts' => category_counts(),
        'fixedCategory' => $category,
    ]);
}

function page_category(string $slug): void
{
    if (!isset(categories()[$slug])) {
        not_found();
    }
    page_explore($slug);
}

/* ============================================================ Create */

function duration_options(): array
{
    $max = max(1, setting_int('max_duration_days', 30)) * 24;
    $all = [1 => '1 hour', 6 => '6 hours', 12 => '12 hours', 24 => '1 day', 72 => '3 days', 168 => '7 days', 336 => '14 days', 720 => '30 days'];
    return array_filter($all, static fn($h) => $h <= $max, ARRAY_FILTER_USE_KEY);
}

function invite_expiry_options(): array
{
    return ['' => 'Never (until revoked)', '24' => '24 hours', '168' => '7 days', '720' => '30 days'];
}

function page_create(): void
{
    $errors = [];
    $old = [];
    if (is_post()) {
        // post_max_size exceeded: PHP silently drops the whole body.
        if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $errors['form'] = 'The upload was too large in total. Use smaller photos or fewer participants.';
        } else {
            csrf_require();
            require_not_blocked();
            [$errors, $old] = handle_create();
        }
    }
    render('create', [
        'title' => 'Start a match',
        'errors' => $errors,
        'old' => $old,
        'durations' => duration_options(),
        'inviteExpiry' => invite_expiry_options(),
        'minP' => setting_int('min_participants', 2),
        'maxP' => setting_int('max_participants', 20),
        'maxMb' => setting_int('max_upload_mb', 5),
        'bodyClass' => 'page-create',
    ]);
}

/** @return array{0: array, 1: array} errors, old input */
function handle_create(): array
{
    $e = [];
    $old = [
        'title' => str_input('title', 200),
        'category' => str_input('category', 32),
        'custom_category' => str_input('custom_category', 80),
        'description' => str_input('description', 1200),
        'instructions' => str_input('instructions', 600),
        'visibility' => str_input('visibility', 10),
        'results_visibility' => str_input('results_visibility', 20),
        'duration' => str_input('duration', 10),
        'custom_close' => str_input('custom_close', 30),
        'tz_offset' => (int)input('tz_offset', 0),
        'invite_expiry' => str_input('invite_expiry', 5),
        'use_access_code' => !empty($_POST['use_access_code']),
        'names' => array_map(static fn($n) => is_string($n) ? trim(mb_substr($n, 0, 80)) : '', (array)($_POST['names'] ?? [])),
    ];

    $len = static fn(string $s) => mb_strlen($s);
    if ($len($old['title']) < 3 || $len($old['title']) > 120) {
        $e['title'] = 'Title must be 3–120 characters.';
    } elseif ($t = find_blocked_term($old['title'])) {
        $e['title'] = 'The title contains language that isn’t allowed (“' . $t . '”).';
    }
    if (!isset(categories()[$old['category']])) {
        $e['category'] = 'Choose a category.';
    } elseif ($old['category'] === 'custom') {
        if ($len($old['custom_category']) < 3 || $len($old['custom_category']) > 40) {
            $e['custom_category'] = 'Custom category must be 3–40 characters.';
        } elseif ($t = find_blocked_term($old['custom_category'])) {
            $e['custom_category'] = 'That category isn’t allowed (“' . $t . '”). Categories must not be abusive, sexual, discriminatory or target appearance negatively.';
        }
    }
    if ($len($old['description']) > 600) {
        $e['description'] = 'Description can be at most 600 characters.';
    } elseif ($t = find_blocked_term($old['description'])) {
        $e['description'] = 'The description contains language that isn’t allowed (“' . $t . '”).';
    }
    if ($len($old['instructions']) > 300) {
        $e['instructions'] = 'Instructions can be at most 300 characters.';
    } elseif ($t = find_blocked_term($old['instructions'])) {
        $e['instructions'] = 'The instructions contain language that isn’t allowed (“' . $t . '”).';
    }
    if (!in_array($old['visibility'], ['public', 'private'], true)) {
        $e['visibility'] = 'Choose public or private.';
    }
    if (!in_array($old['results_visibility'], ['live', 'after_close', 'restricted'], true)) {
        $e['results_visibility'] = 'Choose when results are shown.';
    }

    // Closing time
    $closesTs = null;
    $maxTs = time() + max(1, setting_int('max_duration_days', 30)) * 86400;
    if ($old['duration'] === 'custom') {
        $local = DateTime::createFromFormat('Y-m-d\TH:i', $old['custom_close'], new DateTimeZone('UTC'));
        if (!$local) {
            $e['duration'] = 'Pick a valid closing date and time.';
        } else {
            // tz_offset is the browser's getTimezoneOffset() in minutes (UTC - local).
            $offset = max(-840, min(840, $old['tz_offset']));
            $closesTs = $local->getTimestamp() + $offset * 60;
            if ($closesTs < time() + 3600) {
                $e['duration'] = 'The closing time must be at least 1 hour from now.';
            } elseif ($closesTs > $maxTs + 60) {
                $e['duration'] = 'The closing time can be at most ' . setting_int('max_duration_days', 30) . ' days from now.';
            }
        }
    } elseif (isset(duration_options()[(int)$old['duration']])) {
        $closesTs = time() + (int)$old['duration'] * 3600;
    } else {
        $e['duration'] = 'Choose how long voting stays open.';
    }

    if (empty($_POST['confirm_rights'])) {
        $e['confirm_rights'] = 'Please confirm you have permission to upload and share these photos.';
    }
    if (empty($_POST['confirm_adults'])) {
        $e['confirm_adults'] = 'Please confirm everyone pictured is 18 or older and agreed to take part.';
    }
    if (empty($_POST['accept_terms'])) {
        $e['accept_terms'] = 'Please accept the Terms of Service.';
    }

    // Participants
    $minP = setting_int('min_participants', 2);
    $maxP = setting_int('max_participants', 20);
    $files = files_list('photos');
    $names = $old['names'];
    $count = max(count($names), count($files));
    $pe = [];
    if ($count < $minP) {
        $e['participants'] = "Add at least $minP participants.";
    } elseif ($count > $maxP) {
        $e['participants'] = "A match can have at most $maxP participants.";
    }
    $seen = [];
    for ($i = 0; $i < $count && $count <= $maxP; $i++) {
        $n = $names[$i] ?? '';
        if ($len($n) < 1 || $len($n) > 40) {
            $pe[$i][] = 'Name must be 1–40 characters.';
        } elseif ($t = find_blocked_term($n)) {
            $pe[$i][] = 'Name contains language that isn’t allowed (“' . $t . '”).';
        } elseif (isset($seen[mb_strtolower($n)])) {
            $pe[$i][] = 'Each participant needs a different name.';
        }
        $seen[mb_strtolower($n)] = true;
        if (!isset($files[$i]) || (int)$files[$i]['error'] === UPLOAD_ERR_NO_FILE) {
            $pe[$i][] = 'Photo is missing.';
        }
    }
    if ($pe) {
        $e['people'] = $pe;
    }
    if ($e) {
        return [$e, $old];
    }

    if (!rate_limit('create_hour', setting_int('creations_per_hour', 5), 3600)
        || !rate_limit('create_day', setting_int('creations_per_day', 15), 86400)) {
        $e['form'] = 'You have created several matches recently. Please wait a while before creating another.';
        return [$e, $old];
    }
    $mb = (int)ceil(array_sum(array_map(static fn($f) => (int)($f['size'] ?? 0), $files)) / 1048576);
    if (!rate_limit('upload_mb_day', 300, 86400, null, max(1, $mb))) {
        $e['form'] = 'Daily upload quota reached for your network. Please try again tomorrow.';
        return [$e, $old];
    }

    // Process images (after all text validation passes).
    $stored = [];
    try {
        for ($i = 0; $i < $count; $i++) {
            try {
                $paths = store_participant_photo($files[$i]);
                $stored[] = ['name' => $names[$i], 'image' => $paths['image'], 'thumb' => $paths['thumb']];
            } catch (UploadError $ue) {
                $pe[$i][] = 'Photo ' . $ue->getMessage() . '.';
            }
        }
        if ($pe) {
            throw new UploadError('photos');
        }
        $accessCode = ($old['visibility'] === 'private' && $old['use_access_code']) ? strtoupper(random_id(8)) : null;
        $inviteExp = ($old['visibility'] === 'private' && isset(invite_expiry_options()[$old['invite_expiry']]) && $old['invite_expiry'] !== '')
            ? gmdate('Y-m-d H:i:s', time() + (int)$old['invite_expiry'] * 3600) : null;
        $res = comp_create([
            'title' => $old['title'],
            'category' => $old['category'],
            'custom_category' => $old['category'] === 'custom' ? $old['custom_category'] : null,
            'description' => $old['description'],
            'instructions' => $old['instructions'],
            'visibility' => $old['visibility'],
            'results_visibility' => $old['results_visibility'],
            'closes_at' => gmdate('Y-m-d H:i:s', (int)$closesTs),
            'invite_expires_at' => $inviteExp,
            'access_code' => $accessCode,
        ], $stored);
    } catch (Throwable $ex) {
        foreach ($stored as $s) {
            delete_media($s['image']);
            delete_media($s['thumb']);
        }
        if ($ex instanceof UploadError) {
            $e['people'] = $pe;
            $e['form'] = 'Some photos could not be accepted. Fix the marked rows and choose those photos again.';
            return [$e, $old];
        }
        throw $ex;
    }

    $c = $res['comp'];
    grant_creator($c);
    if ($c['visibility'] === 'private') {
        grant_invite($c);
    }
    audit('competition.create', 'competition', (int)$c['id'], ['visibility' => $c['visibility'], 'participants' => count($stored)], null, 'creator');
    $_SESSION['created'] = [
        'slug' => $c['slug'],
        'manage_token' => $res['manage_token'],
        'invite_token' => $res['invite_token'],
        'access_code' => $accessCode,
        'at' => time(),
    ];
    redirect('/created');
}

function page_created(): void
{
    $info = $_SESSION['created'] ?? null;
    if (!$info || $info['at'] < time() - 3600) {
        redirect('/create');
    }
    $c = comp_find_by_slug($info['slug']);
    if (!$c || $c['status'] === 'deleted') {
        redirect('/create');
    }
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex');
    render('created', [
        'title' => 'Your match is ready',
        'c' => $c,
        'shareUrl' => $c['visibility'] === 'public' ? abs_url(comp_public_path($c)) : abs_url('/p/' . $info['invite_token']),
        'manageUrl' => abs_url('/manage/' . $info['manage_token']),
        'accessCode' => $info['access_code'],
        'noindex' => true,
    ]);
}

/* ============================================================ Match pages */

function match_common(array $c): array
{
    if ($c['visibility'] === 'private') {
        send_security_headers(true);
        header('Referrer-Policy: no-referrer');
    }
    return [
        'c' => $c,
        'noindex' => $c['visibility'] === 'private' || !comp_is_listed($c),
        'effective' => comp_effective_status($c),
        'creator' => is_creator($c),
        'resultsVisible' => comp_results_visible($c),
    ];
}

function page_match(string $slug): void
{
    $c = comp_require_viewable($slug);
    $parts = comp_participants((int)$c['id']);
    render('match', match_common($c) + [
        'title' => $c['title'],
        'participants' => $parts,
        'shareUrl' => $c['visibility'] === 'public' ? comp_share_url($c) : (is_creator($c) ? comp_share_url($c) : null),
        'description' => $c['description'] ?: (categories()[$c['category']]['prompt'] ?? ''),
    ]);
}

function page_play(string $slug): void
{
    $c = comp_require_viewable($slug);
    $voter = voter_hash();
    if (is_post()) {
        // No-JavaScript fallback: classic form post, then redirect back (PRG).
        csrf_require();
        require_not_blocked();
        if (!rate_limit('vote_min', setting_int('votes_per_minute', 40), 60)) {
            flash('error', 'You are voting very fast. Please slow down for a moment.');
        } else {
            $r = vote_cast($c, str_input('token', 64), str_input('choice', 16), str_input('key', 36), $voter, ip_hash());
            flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Vote counted.' : ($r['message'] ?? 'Vote not counted.'));
        }
        redirect('/m/' . $c['slug'] . '/play');
    }
    $next = vote_next_pair($c, $voter);
    render('play', match_common($c) + [
        'title' => 'Vote · ' . $c['title'],
        'next' => $next,
        'idem' => uuid4(),
        'bodyClass' => 'page-play',
    ]);
}

function page_results(string $slug): void
{
    $c = comp_require_viewable($slug);
    $common = match_common($c);
    $ranking = $common['resultsVisible'] ? ranking_compute((int)$c['id']) : null;
    render('results', $common + [
        'title' => 'Results · ' . $c['title'],
        'ranking' => $ranking,
        'winner' => $ranking ? ranking_winner($c, $ranking) : null,
        'shareUrl' => $c['visibility'] === 'public' ? abs_url('/m/' . $c['slug'] . '/results') : null,
        'bodyClass' => 'page-results',
    ]);
}

/* ============================================================ Private invites */

function page_invite(string $token): void
{
    header('Referrer-Policy: no-referrer');
    send_security_headers(true);
    $c = q_one('SELECT * FROM competitions WHERE invite_token_hash = ?', [invite_token_hash($token)]);
    if (!$c || !invite_valid($c) || in_array($c['status'], ['deleted', 'suspended'], true)) {
        rate_limit('invite_miss', 30, 3600);
        not_found();
    }
    if (empty($c['access_code_hash']) || has_invite_access($c) || is_creator($c)) {
        grant_invite($c);
        redirect('/m/' . $c['slug']);
    }
    $error = null;
    if (is_post()) {
        csrf_require();
        $okRate = rate_limit('access_code', 5, 900, ip_hash() . '|' . $c['id'])
               && rate_limit('access_code_s', 8, 900, session_id() . '|' . $c['id']);
        if (!$okRate) {
            $error = 'Too many attempts. Wait 15 minutes and try again.';
        } elseif (password_verify(strtoupper(str_input('code', 40)), (string)$c['access_code_hash'])) {
            session_regenerate_id(true);
            grant_invite($c);
            redirect('/m/' . $c['slug']);
        } else {
            $error = 'That access code is not correct.';
        }
    }
    render('access_code', ['title' => 'Private match', 'error' => $error, 'noindex' => true, 'actionUrl' => url('/p/' . $token)]);
}

/* ============================================================ Creator management */

function page_manage(string $token): void
{
    header('Referrer-Policy: no-referrer');
    send_security_headers(true);
    $c = q_one('SELECT * FROM competitions WHERE manage_token_hash = ?', [manage_token_hash($token)]);
    if (!$c || $c['status'] === 'deleted') {
        rate_limit('manage_miss', 30, 3600);
        not_found();
    }
    grant_creator($c);
    if ($c['visibility'] === 'private' && invite_valid($c)) {
        grant_invite($c);
    }
    $id = (int)$c['id'];
    $newLinks = $_SESSION['manage_new'][$id] ?? [];
    unset($_SESSION['manage_new'][$id]);

    if (is_post()) {
        csrf_require();
        if (!rate_limit('manage_post', 60, 3600)) {
            render_error(429);
        }
        $action = str_input('action', 30);
        $locked = $c['status'] === 'suspended';
        $self = '/manage/' . $token;
        switch ($action) {
            case 'close':
                if (!$locked && comp_is_open($c)) {
                    q("UPDATE competitions SET status = 'closed', closed_at = UTC_TIMESTAMP() WHERE id = ?", [$id]);
                    audit('competition.close', 'competition', $id, [], null, 'creator');
                    flash('success', 'Voting is now closed.');
                }
                break;
            case 'reopen':
                $hours = (int)input('duration', 0);
                if ($locked) {
                    flash('error', 'This match is suspended by moderators and cannot be reopened.');
                } elseif (!isset(duration_options()[$hours])) {
                    flash('error', 'Choose a valid duration.');
                } else {
                    q("UPDATE competitions SET status = 'active', closed_at = NULL, closes_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? HOUR) WHERE id = ?", [$hours, $id]);
                    audit('competition.reopen', 'competition', $id, ['hours' => $hours], null, 'creator');
                    flash('success', 'Voting is open until the new closing time.');
                }
                break;
            case 'results':
                $rv = str_input('results_visibility', 20);
                if (in_array($rv, ['live', 'after_close', 'restricted'], true)) {
                    q('UPDATE competitions SET results_visibility = ? WHERE id = ?', [$rv, $id]);
                    audit('competition.results_visibility', 'competition', $id, ['value' => $rv], null, 'creator');
                    flash('success', 'Results setting saved.');
                }
                break;
            case 'rotate_invite':
                if ($c['visibility'] === 'private' && !$locked) {
                    $exp = str_input('invite_expiry', 5);
                    $expAt = ($exp !== '' && isset(invite_expiry_options()[$exp])) ? gmdate('Y-m-d H:i:s', time() + (int)$exp * 3600) : null;
                    comp_rotate_invite($id, $expAt);
                    audit('competition.invite_rotated', 'competition', $id, ['expires' => $expAt], null, 'creator');
                    flash('success', 'New invite link created. The old link no longer works.');
                }
                break;
            case 'revoke_invite':
                if ($c['visibility'] === 'private') {
                    q('UPDATE competitions SET invite_token_hash = NULL, invite_token_enc = NULL, invite_expires_at = NULL WHERE id = ?', [$id]);
                    audit('competition.invite_revoked', 'competition', $id, [], null, 'creator');
                    flash('success', 'Invite link revoked. Nobody can open the match until you create a new link.');
                }
                break;
            case 'access_code':
                if ($c['visibility'] === 'private') {
                    if (input('mode') === 'remove') {
                        q('UPDATE competitions SET access_code_hash = NULL WHERE id = ?', [$id]);
                        audit('competition.access_code_removed', 'competition', $id, [], null, 'creator');
                        flash('success', 'Access code removed.');
                    } else {
                        $code = strtoupper(random_id(8));
                        q('UPDATE competitions SET access_code_hash = ? WHERE id = ?', [password_hash($code, PASSWORD_DEFAULT), $id]);
                        audit('competition.access_code_set', 'competition', $id, [], null, 'creator');
                        $_SESSION['manage_new'][$id]['code'] = $code;
                        flash('success', 'New access code created.');
                    }
                }
                break;
            case 'rotate_manage':
                $new = comp_rotate_manage($id);
                audit('competition.manage_rotated', 'competition', $id, [], null, 'creator');
                $fresh = comp_find($id);
                grant_creator($fresh);
                $_SESSION['manage_new'][$id]['manage'] = abs_url('/manage/' . $new);
                flash('success', 'New management link created. Save it now — the old one stopped working.');
                redirect('/manage/' . $new);
            case 'delete':
                if (str_input('confirm', 10) !== 'DELETE') {
                    flash('error', 'Type DELETE to confirm.');
                    break;
                }
                comp_soft_delete($id);
                audit('competition.delete', 'competition', $id, [], null, 'creator');
                unset($_SESSION['created']);
                flash('success', 'Your match and its photos were deleted.');
                redirect('/');
        }
        redirect($self);
    }

    $parts = comp_participants($id);
    render('manage', [
        'title' => 'Manage · ' . $c['title'],
        'c' => $c,
        'effective' => comp_effective_status($c),
        'participants' => $parts,
        'shareUrl' => comp_share_url($c),
        'newLinks' => $newLinks,
        'durations' => duration_options(),
        'inviteExpiry' => invite_expiry_options(),
        'actionUrl' => url('/manage/' . $token),
        'openReports' => (int)q_val("SELECT COUNT(*) FROM reports WHERE competition_id = ? AND status IN ('open','investigating')", [$id]),
        'noindex' => true,
    ]);
}

/* ============================================================ Voting API */

function api_comp_or_fail(string $slug): array
{
    $c = comp_find_by_slug($slug);
    if (!$c || !comp_viewable($c)) {
        json_out(['ok' => false, 'error' => 'not_found', 'message' => 'This match is not available.'], 404);
    }
    return $c;
}

function api_next(): void
{
    header('X-Robots-Tag: noindex');
    $c = api_comp_or_fail(str_input('m', 12));
    if (!rate_limit('api_next', 120, 60)) {
        json_out(['ok' => false, 'error' => 'rate_limited', 'message' => 'Too many requests. Please slow down.'], 429);
    }
    $next = vote_next_pair($c, voter_hash());
    json_out(['ok' => true] + $next + ['results_url' => comp_results_visible($c) ? url('/m/' . $c['slug'] . '/results') : null]);
}

function api_vote(): void
{
    if (!is_post()) {
        json_out(['ok' => false, 'error' => 'method'], 405);
    }
    $body = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($body)) {
        json_out(['ok' => false, 'error' => 'bad_request', 'message' => 'Invalid request.'], 400);
    }
    csrf_require();
    if (is_blocked()) {
        json_out(['ok' => false, 'error' => 'blocked', 'message' => 'Voting from your network is temporarily paused.'], 429);
    }
    $c = api_comp_or_fail((string)($body['m'] ?? ''));
    if (!rate_limit('vote_min', setting_int('votes_per_minute', 40), 60)) {
        json_out(['ok' => false, 'error' => 'rate_limited', 'message' => 'You are voting very fast. Please slow down for a moment.'], 429);
    }
    $r = vote_cast($c, (string)($body['token'] ?? ''), (string)($body['choice'] ?? ''), (string)($body['key'] ?? ''), voter_hash(), ip_hash());
    $status = $r['ok'] ? 200 : match ($r['code']) { 'closed' => 409, 'already_voted' => 409, 'stale' => 409, 'limit' => 429, default => 400 };
    json_out(['ok' => $r['ok'], 'code' => $r['code'], 'message' => $r['message'] ?? null, 'split' => $r['split'] ?? null], $status);
}

/* ============================================================ Media */

function media_participant(string $publicId, string $size): void
{
    $p = q_one('SELECT * FROM participants WHERE public_id = ?', [$publicId]);
    if (!$p || !participant_image_servable($p)) {
        not_found();
    }
    $c = comp_find((int)$p['competition_id']);
    if (!$c || !comp_viewable($c)) {
        not_found();
    }
    $file = media_locate($size === 'full' ? $p['image_file'] : $p['thumb_file']);
    if (!$file) {
        not_found();
    }
    send_media($file, $c['visibility'] === 'private' || !comp_is_listed($c));
}

function media_ad(string $publicId, string $variant): void
{
    $ad = q_one('SELECT * FROM ad_campaigns WHERE public_id = ?', [$publicId]);
    if (!$ad || !ad_is_live($ad)) {
        not_found();
    }
    $file = media_locate($variant === 'm' ? $ad['mobile_image_file'] : $ad['image_file']);
    if (!$file) {
        not_found();
    }
    send_media($file, false);
}

function page_ad_click(string $publicId): void
{
    ad_handle_click($publicId, str_input('k', 40));
}

/* ============================================================ Reports & contact */

function report_reasons(): array
{
    return [
        'inappropriate' => 'Inappropriate or explicit photo',
        'harassment' => 'Harassment or bullying',
        'unauthorized' => 'My photo was used without permission',
        'minor' => 'Shows someone under 18',
        'abusive_category' => 'Abusive or offensive category / title',
        'impersonation' => 'Impersonation',
        'spam' => 'Spam or scam',
        'other' => 'Something else',
    ];
}

function page_report(): void
{
    $c = null;
    $participant = null;
    $slug = str_input('m', 12);
    if ($slug !== '') {
        $c = comp_find_by_slug($slug);
        if ($c && !comp_viewable($c)) {
            $c = null;
        }
    }
    if ($c) {
        $pid = str_input('p', 16);
        if ($pid !== '') {
            $participant = q_one('SELECT * FROM participants WHERE public_id = ? AND competition_id = ?', [$pid, $c['id']]);
        }
    }
    $errors = [];
    $sent = false;
    if (is_post()) {
        csrf_require();
        require_not_blocked();
        $reason = str_input('reason', 30);
        $details = str_input('details', 1000);
        $email = str_input('email', 190);
        if (!isset(report_reasons()[$reason])) {
            $errors['reason'] = 'Choose a reason.';
        }
        if (!$c && mb_strlen($details) < 10) {
            $errors['details'] = 'Tell us which match or page this is about (paste the link).';
        }
        if ($email !== '' && !valid_email($email)) {
            $errors['email'] = 'That email address is not valid.';
        }
        if (!$errors && !rate_limit('report', 10, 3600)) {
            $errors['form'] = 'You have sent many reports recently. Please try again later.';
        }
        if (!$errors) {
            q('INSERT INTO reports (competition_id, participant_id, reason, details, reporter_email, reporter_ip_hash) VALUES (?,?,?,?,?,?)',
                [$c['id'] ?? null, $participant['id'] ?? null, $reason, $details ?: null, $email ?: null, ip_hash()]);
            report_auto_triage($c, $participant, $reason);
            $sent = true;
        }
    }
    render('report', [
        'title' => 'Report content',
        'c' => $c,
        'participant' => $participant,
        'reasons' => report_reasons(),
        'errors' => $errors,
        'sent' => $sent,
        'noindex' => true,
    ]);
}

/**
 * Automatic safety triage:
 *  - any report on a photo moves it into the moderation queue ("flagged");
 *  - reaching the threshold of open reports (or any "minor" report) hides the photo until reviewed;
 *  - a listed competition reaching the threshold is delisted (moderation → pending).
 */
function report_auto_triage(?array $c, ?array $p, string $reason): void
{
    $threshold = max(1, setting_int('report_escalate_threshold', 3));
    if ($p) {
        $open = (int)q_val("SELECT COUNT(*) FROM reports WHERE participant_id = ? AND status IN ('open','investigating')", [$p['id']]);
        $new = ($open >= $threshold || $reason === 'minor') ? 'escalated' : 'flagged';
        if (in_array($p['image_status'], ['pending', 'approved', 'flagged'], true) && $p['image_status'] !== $new) {
            q('UPDATE participants SET image_status = ? WHERE id = ?', [$new, $p['id']]);
            audit('participant.auto_' . $new, 'participant', (int)$p['id'], ['open_reports' => $open, 'reason' => $reason], null, 'system');
        }
    }
    if ($c && comp_is_listed($c)) {
        $open = (int)q_val("SELECT COUNT(*) FROM reports WHERE competition_id = ? AND status IN ('open','investigating')", [$c['id']]);
        if ($open >= $threshold) {
            q("UPDATE competitions SET moderation_status = 'pending', is_featured = 0 WHERE id = ?", [$c['id']]);
            audit('competition.auto_delisted', 'competition', (int)$c['id'], ['open_reports' => $open], null, 'system');
        }
    }
}

function page_contact(): void
{
    $errors = [];
    $sent = false;
    $old = ['name' => '', 'email' => '', 'topic' => (string)input('topic', 'general'), 'message' => ''];
    $topics = ['general' => 'General question', 'advertising' => 'Advertising & sponsorship', 'privacy' => 'Privacy request', 'takedown' => 'Photo removal request', 'other' => 'Other'];
    if (is_post()) {
        csrf_require();
        require_not_blocked();
        $old = ['name' => str_input('name', 80), 'email' => str_input('email', 190), 'topic' => str_input('topic', 20), 'message' => str_input('message', 3000)];
        if (mb_strlen($old['name']) < 2) {
            $errors['name'] = 'Please enter your name.';
        }
        if (!valid_email($old['email'])) {
            $errors['email'] = 'Please enter a valid email so we can reply.';
        }
        if (!isset($topics[$old['topic']])) {
            $errors['topic'] = 'Choose a topic.';
        }
        if (mb_strlen($old['message']) < 10) {
            $errors['message'] = 'Your message is a little short.';
        }
        if (!empty($_POST['website'])) { // honeypot
            $errors['form'] = 'Message could not be sent.';
        }
        if (!$errors && !rate_limit('contact', 5, 3600)) {
            $errors['form'] = 'You have sent several messages recently. Please try again later.';
        }
        if (!$errors) {
            q('INSERT INTO contact_messages (name, email, topic, message, ip_hash) VALUES (?,?,?,?,?)',
                [$old['name'], $old['email'], $old['topic'], $old['message'], ip_hash()]);
            $sent = true;
        }
    }
    render('contact', ['title' => 'Contact', 'errors' => $errors, 'sent' => $sent, 'old' => $old, 'topics' => $topics,
        'contactEmail' => (string)setting('contact_email', '')]);
}

function page_privacy(): void
{
    render('privacy', ['title' => 'Privacy Policy', 'retention' => setting_int('ip_retention_days', 30), 'contactEmail' => (string)setting('contact_email', '')]);
}

function page_terms(): void
{
    render('terms', ['title' => 'Terms of Service', 'contactEmail' => (string)setting('contact_email', '')]);
}

/* ============================================================ Scheduled maintenance */

/**
 * Called by Vercel Cron (see vercel.json) with "Authorization: Bearer <CRON_SECRET>".
 * On ordinary hosting use cli/maintenance.php from cron instead.
 */
function api_cron(): void
{
    $secret = (string)config('cron_secret', '');
    $sent = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (strlen($secret) < 16 || !hash_equals('Bearer ' . $secret, $sent)) {
        not_found();
    }
    json_out(['ok' => true, 'result' => run_housekeeping()]);
}
