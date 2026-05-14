#!/usr/bin/env php
<?php

/**
 * Daily cron: Score all eligible products using AI prediction.
 *
 * Usage:
 *   php workers/score_products_daily.php [limit]
 *
 * Example crontab:
 *   0 6 * * * /usr/bin/php /path/to/workers/score_products_daily.php 50
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/backend/app/bootstrap.php';

$limit = max(10, min(200, (int)($argv[1] ?? 50)));
$startTime = microtime(true);

echo "[SCORING] " . date('Y-m-d H:i:s') . " — Starting product scoring (limit: {$limit})...\n";

try {
    $scoringService = new ProductScoringService();
    $result = $scoringService->scoreAllProducts($limit);

    $elapsed = round(microtime(true) - $startTime, 2);
    echo "[SCORING] Scored: {$result['scored']} products in {$elapsed}s\n";

    if (!empty($result['errors'])) {
        echo "[SCORING] Errors (" . count($result['errors']) . "):\n";
        foreach (array_slice($result['errors'], 0, 10) as $err) {
            echo "  - {$err}\n";
        }
    }

    // Log to task_logs
    $taskLogService = new TaskLogService();
    $taskLogService->create(
        'score_products_daily',
        empty($result['errors']) ? 'success' : 'failed',
        ['limit' => $limit],
        $result,
        implode('; ', array_slice($result['errors'], 0, 5))
    );

    echo "[SCORING] Done.\n";
} catch (\Throwable $e) {
    echo "[SCORING] FATAL: {$e->getMessage()}\n";
    error_log("[SCORING] Fatal error: {$e->getMessage()}");
    exit(1);
}
