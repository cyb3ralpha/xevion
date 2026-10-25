<?php
/**
 * Append-only audit trail. Administrators have no UI to edit or delete entries.
 */
declare(strict_types=1);

function audit(string $action, ?string $targetType = null, int|string|null $targetId = null, array $details = [], ?int $adminId = null, string $actor = 'admin'): void
{
    if ($adminId === null && $actor === 'admin' && function_exists('admin_current')) {
        $adminId = admin_current()['id'] ?? null;
    }
    q('INSERT INTO audit_log (admin_id, actor, action, target_type, target_id, details, ip_hash) VALUES (?,?,?,?,?,?,?)', [
        $adminId,
        $actor,
        $action,
        $targetType,
        $targetId === null ? null : (int)$targetId,
        $details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        PHP_SAPI === 'cli' ? null : ip_hash(),
    ]);
}
