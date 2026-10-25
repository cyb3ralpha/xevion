<?php
/**
 * Runtime settings stored in the `settings` table, with safe defaults.
 */
declare(strict_types=1);

function setting_defaults(): array
{
    return [
        'contact_email'            => '',
        'min_participants'         => '2',
        'max_participants'         => '20',
        'max_duration_days'        => '30',
        'max_upload_mb'            => '5',
        'public_requires_approval' => '1',
        'creations_per_hour'       => '5',
        'creations_per_day'        => '15',
        'votes_per_minute'         => '40',
        'ip_pair_limit_per_day'    => '8',
        'report_escalate_threshold' => '3',
        'ip_retention_days'        => '30',
        'show_house_ad'            => '1',
        'blocked_terms'            => implode("\n", [
            // Body-shaming / degrading comparisons
            'ugliest', 'ugly', 'fattest', 'fat', 'fatty', 'skinniest', 'dumbest', 'stupidest', 'stupid', 'retard', 'retarded',
            'loser', 'worst looking', 'most hideous', 'hideous', 'disgusting', 'smelliest', 'trash', 'whore', 'slut', 'hoe',
            // Sexualised content
            'sexiest', 'sexy', 'hottest body', 'nude', 'nudes', 'naked', 'boobs', 'tits', 'butt', 'ass', 'booty', 'thicc',
            'porn', 'onlyfans', 'nsfw', 'horny', 'virgin', 'sex',
            // Violence / threats
            'kill', 'die', 'suicide', 'rape', 'murder',
            // Minors in appearance contests
            'kid', 'kids', 'child', 'children', 'baby', 'babies', 'teen', 'teens', 'schoolgirl', 'schoolboy', 'underage',
        ]),
    ];
}

function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = setting_defaults();
        try {
            foreach (q_all('SELECT skey, svalue FROM settings') as $row) {
                $cache[$row['skey']] = (string)$row['svalue'];
            }
        } catch (Throwable $e) {
            // During install the table may not exist yet; defaults are fine.
        }
    }
    return $cache;
}

function setting(string $key, mixed $default = null): mixed
{
    $all = settings_all();
    return $all[$key] ?? $default;
}

function setting_int(string $key, int $default = 0): int
{
    return (int)setting($key, $default);
}

function setting_save(string $key, string $value): void
{
    q('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$key, $value]);
    settings_all(true);
}
