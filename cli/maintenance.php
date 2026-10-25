<?php
/**
 * Scheduled maintenance — run from cron every 15 minutes:
 *   php /path/to/facematch/cli/maintenance.php
 * Closes expired competitions, purges old rate-limit rows, clears IP-derived keys
 * older than the retention period and archives long-finished ad campaigns.
 */
declare(strict_types=1);

require __DIR__ . '/_cli.php';
require dirname(__DIR__) . '/app/bootstrap.php';

$r = run_housekeeping();
echo gmdate('c') . ' ' . json_encode($r) . "\n";
