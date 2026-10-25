<?php
/**
 * Competitions, participants and access control.
 *
 * Access rules (enforced server-side for pages, the voting API and the media endpoint):
 *   - deleted              → nobody (404)
 *   - suspended            → nobody on public pages (creator sees a notice on the manage page)
 *   - public + rejected    → creator only
 *   - public + pending     → anyone with the link (unlisted until approved)
 *   - public + approved    → anyone, listed in discovery
 *   - private              → only browsers holding a valid invite grant or the creator grant
 * Grants are stored in the session together with the token hash that produced them,
 * so revoking/regenerating a link immediately invalidates every existing grant.
 */
declare(strict_types=1);

const LISTABLE_SQL = "c.visibility = 'public' AND c.moderation_status = 'approved' AND c.status IN ('active','closed')";
const SERVABLE_IMAGE_STATUSES = ['pending', 'approved', 'flagged'];

function comp_find_by_slug(string $slug): ?array
{
    if (!preg_match('/^[A-Za-z0-9]{12}$/', $slug)) {
        return null;
    }
    return q_one('SELECT * FROM competitions WHERE slug = ?', [$slug]);
}

function comp_find(int $id): ?array
{
    return q_one('SELECT * FROM competitions WHERE id = ?', [$id]);
}

function comp_effective_status(array $c): string
{
    if ($c['status'] === 'active' && strtotime($c['closes_at'] . ' UTC') <= time()) {
        return 'closed';
    }
    return $c['status'];
}

function comp_is_open(array $c): bool
{
    return comp_effective_status($c) === 'active';
}

function status_label(string $s): string
{
    return ['active' => 'Live', 'closed' => 'Closed', 'suspended' => 'Suspended', 'deleted' => 'Deleted'][$s] ?? ucfirst($s);
}

/* ---------- grants ---------- */

function grant_invite(array $c): void
{
    $_SESSION['grants'][(int)$c['id']] = (string)$c['invite_token_hash'];
}

function grant_creator(array $c): void
{
    $_SESSION['creator'][(int)$c['id']] = (string)$c['manage_token_hash'];
}

function is_creator(array $c): bool
{
    $g = $_SESSION['creator'][(int)$c['id']] ?? null;
    return is_string($g) && hash_equals((string)$c['manage_token_hash'], $g);
}

function invite_valid(array $c): bool
{
    if (empty($c['invite_token_hash'])) {
        return false;
    }
    return empty($c['invite_expires_at']) || strtotime($c['invite_expires_at'] . ' UTC') > time();
}

function has_invite_access(array $c): bool
{
    $g = $_SESSION['grants'][(int)$c['id']] ?? null;
    return is_string($g) && invite_valid($c) && hash_equals((string)$c['invite_token_hash'], $g);
}

/** Can the current browser see this competition on public pages? */
function comp_viewable(array $c): bool
{
    if ($c['status'] === 'deleted' || $c['status'] === 'suspended') {
        return false;
    }
    if (is_creator($c)) {
        return true;
    }
    if ($c['visibility'] === 'private') {
        return has_invite_access($c);
    }
    return $c['moderation_status'] !== 'rejected';
}

function comp_is_listed(array $c): bool
{
    return $c['visibility'] === 'public' && $c['moderation_status'] === 'approved' && in_array($c['status'], ['active', 'closed'], true);
}

/** Results visibility, enforced for pages and API responses alike. */
function comp_results_visible(array $c): bool
{
    if (!comp_viewable($c)) {
        return false;
    }
    if (is_creator($c)) {
        return true;
    }
    return match ($c['results_visibility']) {
        'live' => true,
        'after_close' => comp_effective_status($c) === 'closed',
        default => false,
    };
}

function results_visibility_label(string $v): string
{
    return [
        'live' => 'Visible during voting',
        'after_close' => 'Revealed when voting closes',
        'restricted' => 'Only the organiser can see results',
    ][$v] ?? $v;
}

/** Load a competition by slug and 404 unless the current browser may view it. */
function comp_require_viewable(string $slug): array
{
    $c = comp_find_by_slug($slug);
    if (!$c || !comp_viewable($c)) {
        not_found(); // identical response for "missing" and "not allowed"
    }
    return $c;
}

/* ---------- participants ---------- */

function comp_participants(int $compId): array
{
    return q_all('SELECT * FROM participants WHERE competition_id = ? AND is_removed = 0 ORDER BY position, id', [$compId]);
}

function participant_image_servable(array $p): bool
{
    return !(int)$p['is_removed'] && in_array($p['image_status'], SERVABLE_IMAGE_STATUSES, true) && !empty($p['image_file']);
}

function participant_img(array $p, string $size = 'thumb'): ?string
{
    if (!participant_image_servable($p)) {
        return null;
    }
    return url('/media/' . $p['public_id'] . '/' . ($size === 'full' ? 'full' : 'thumb'));
}

function playable_participants(int $compId): array
{
    return array_values(array_filter(comp_participants($compId), 'participant_image_servable'));
}

/* ---------- links ---------- */

function comp_public_path(array $c): string
{
    return '/m/' . $c['slug'];
}

/** Share URL appropriate to visibility (null if a private invite is revoked/expired). */
function comp_share_url(array $c): ?string
{
    if ($c['visibility'] === 'public') {
        return abs_url(comp_public_path($c));
    }
    if (!invite_valid($c)) {
        return null;
    }
    $tok = decrypt_value($c['invite_token_enc'] ?? null);
    return $tok ? abs_url('/p/' . $tok) : null;
}

function manage_token_hash(string $token): string
{
    return keyed_hash($token, 'manage');
}

function invite_token_hash(string $token): string
{
    return keyed_hash($token, 'invite');
}

/** Rotate the private invite link. Returns the new raw token. */
function comp_rotate_invite(int $compId, ?string $expiresAt): string
{
    $tok = random_token(24);
    q('UPDATE competitions SET invite_token_hash = ?, invite_token_enc = ?, invite_expires_at = ? WHERE id = ?',
        [invite_token_hash($tok), encrypt_value($tok), $expiresAt, $compId]);
    return $tok;
}

/** Rotate the creator management link. Returns the new raw token. */
function comp_rotate_manage(int $compId): string
{
    $tok = random_token(32);
    q('UPDATE competitions SET manage_token_hash = ? WHERE id = ?', [manage_token_hash($tok), $compId]);
    return $tok;
}

/* ---------- creation ---------- */

/**
 * Insert a competition and its participants in one transaction.
 * $data keys: title, category, custom_category, description, instructions, visibility,
 *             results_visibility, closes_at, invite_expires_at, access_code (raw|null)
 * $people: list of ['name' => string, 'image' => rel, 'thumb' => rel]
 * @return array{comp: array, manage_token: string, invite_token: ?string}
 */
function comp_create(array $data, array $people): array
{
    $isPublic = $data['visibility'] === 'public';
    $needsReview = $isPublic && (setting('public_requires_approval', '1') === '1' || $data['category'] === 'custom');
    $moderation = $isPublic ? ($needsReview ? 'pending' : 'approved') : 'approved';
    $imageStatus = $isPublic ? 'pending' : 'approved';
    $manage = random_token(32);
    $invite = $isPublic ? null : random_token(24);

    return db_tx(function () use ($data, $people, $moderation, $imageStatus, $manage, $invite, $isPublic) {
        for ($attempt = 0; ; $attempt++) {
            $slug = random_id(12);
            try {
                q('INSERT INTO competitions
                    (slug, title, category, custom_category, description, instructions, visibility, status, moderation_status,
                     results_visibility, closes_at, manage_token_hash, invite_token_hash, invite_token_enc, invite_expires_at,
                     access_code_hash, creator_ip_hash)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
                    $slug, $data['title'], $data['category'], $data['custom_category'] ?: null,
                    $data['description'] ?: null, $data['instructions'] ?: null, $data['visibility'], 'active', $moderation,
                    $data['results_visibility'], $data['closes_at'], manage_token_hash($manage),
                    $invite ? invite_token_hash($invite) : null, $invite ? encrypt_value($invite) : null,
                    $isPublic ? null : ($data['invite_expires_at'] ?? null),
                    !empty($data['access_code']) ? password_hash($data['access_code'], PASSWORD_DEFAULT) : null,
                    PHP_SAPI === 'cli' ? null : ip_hash(),
                ]);
                break;
            } catch (PDOException $e) {
                if (!is_duplicate_key($e) || $attempt >= 3) {
                    throw $e;
                }
            }
        }
        $id = (int)db()->lastInsertId();
        foreach (array_values($people) as $i => $p) {
            q('INSERT INTO participants (competition_id, public_id, display_name, position, image_file, thumb_file, image_status)
               VALUES (?,?,?,?,?,?,?)', [$id, random_id(16), $p['name'], $i, $p['image'], $p['thumb'], $imageStatus]);
        }
        return ['comp' => comp_find($id), 'manage_token' => $manage, 'invite_token' => $invite];
    });
}

/* ---------- discovery ---------- */

/**
 * Public listing with filters. Only approved public competitions ever appear here.
 * $f: category, status (open|closed|''), sort (recent|trending|popular|closing|activity), q, featured (bool)
 */
function comp_listing(array $f, int $limit = 12, int $offset = 0): array
{
    [$where, $params] = comp_listing_where($f);
    $order = match ($f['sort'] ?? 'recent') {
        'trending' => 'votes_24h DESC, c.last_vote_at DESC, c.id DESC',
        'popular'  => 'c.votes_total DESC, c.id DESC',
        'closing'  => 'c.closes_at ASC, c.id DESC',
        'activity' => 'c.last_vote_at IS NULL, c.last_vote_at DESC, c.id DESC',
        default    => 'c.created_at DESC, c.id DESC',
    };
    $sql = "SELECT c.*,
              (SELECT COUNT(*) FROM participants p WHERE p.competition_id = c.id AND p.is_removed = 0) AS participant_count,
              (SELECT COUNT(*) FROM votes v WHERE v.competition_id = c.id AND v.status = 'counted'
                  AND v.created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)) AS votes_24h
            FROM competitions c WHERE $where ORDER BY $order LIMIT " . (int)$limit . ' OFFSET ' . (int)$offset;
    $rows = q_all($sql, $params);
    if (($f['sort'] ?? '') === 'trending') {
        $rows = array_values(array_filter($rows, static fn($r) => (int)$r['votes_24h'] > 0));
    }
    return attach_previews($rows);
}

function comp_listing_count(array $f): int
{
    [$where, $params] = comp_listing_where($f);
    return (int)q_val("SELECT COUNT(*) FROM competitions c WHERE $where", $params);
}

function comp_listing_where(array $f): array
{
    $where = [LISTABLE_SQL];
    $params = [];
    if (!empty($f['category']) && isset(categories()[$f['category']])) {
        $where[] = 'c.category = ?';
        $params[] = $f['category'];
    }
    $status = $f['status'] ?? '';
    if (($f['sort'] ?? '') === 'closing') {
        $status = 'open';
    }
    if ($status === 'open') {
        $where[] = "c.status = 'active' AND c.closes_at > UTC_TIMESTAMP()";
    } elseif ($status === 'closed') {
        $where[] = "(c.status = 'closed' OR c.closes_at <= UTC_TIMESTAMP())";
    }
    if (!empty($f['featured'])) {
        $where[] = 'c.is_featured = 1';
    }
    if (($f['sort'] ?? '') === 'trending') {
        $where[] = 'c.last_vote_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)';
    }
    $term = trim((string)($f['q'] ?? ''));
    if ($term !== '') {
        $where[] = '(c.title LIKE ? OR c.custom_category LIKE ?)';
        $like = '%' . addcslashes(mb_substr($term, 0, 60), '%_\\') . '%';
        $params[] = $like;
        $params[] = $like;
    }
    return [implode(' AND ', $where), $params];
}

/** Attach the first two displayable participants to each listed competition. */
function attach_previews(array $rows): array
{
    if (!$rows) {
        return [];
    }
    $ids = array_map(static fn($r) => (int)$r['id'], $rows);
    $parts = q_all('SELECT competition_id, public_id, display_name, image_file, image_status, is_removed
                    FROM participants WHERE competition_id IN (' . placeholders($ids) . ') AND is_removed = 0
                    ORDER BY competition_id, position, id', $ids);
    $by = [];
    foreach ($parts as $p) {
        if (participant_image_servable($p) && count($by[$p['competition_id']] ?? []) < 2) {
            $by[$p['competition_id']][] = $p;
        }
    }
    foreach ($rows as &$r) {
        $r['preview'] = $by[$r['id']] ?? [];
    }
    return $rows;
}

function category_counts(): array
{
    $out = [];
    foreach (q_all('SELECT c.category, COUNT(*) AS n FROM competitions c WHERE ' . LISTABLE_SQL . ' GROUP BY c.category') as $r) {
        $out[$r['category']] = (int)$r['n'];
    }
    return $out;
}

/* ---------- deletion ---------- */

/** Soft-delete a competition and remove its image files from disk. */
function comp_soft_delete(int $compId): void
{
    foreach (q_all('SELECT image_file, thumb_file FROM participants WHERE competition_id = ?', [$compId]) as $p) {
        delete_media($p['image_file']);
        delete_media($p['thumb_file']);
    }
    q("UPDATE participants SET image_file = NULL, thumb_file = NULL, image_status = 'removed' WHERE competition_id = ?", [$compId]);
    q("UPDATE competitions SET status = 'deleted', is_featured = 0, invite_token_hash = NULL, invite_token_enc = NULL WHERE id = ?", [$compId]);
}

/** Permanently remove a participant's photo (file deleted, never served again). */
function participant_remove_image(int $participantId, string $reason, ?int $adminId): void
{
    $p = q_one('SELECT * FROM participants WHERE id = ?', [$participantId]);
    if (!$p) {
        return;
    }
    delete_media($p['image_file']);
    delete_media($p['thumb_file']);
    q("UPDATE participants SET image_file = NULL, thumb_file = NULL, image_status = 'removed', is_removed = 1,
         removed_reason = ?, moderated_at = UTC_TIMESTAMP(), moderated_by = ? WHERE id = ?",
        [mb_substr($reason, 0, 255), $adminId, $participantId]);
}
