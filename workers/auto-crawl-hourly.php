#!/usr/bin/env php
<?php
/**
 * Auto-crawl worker: chạy mỗi giờ một lần.
 * 1. AI gợi từ khóa mới
 * 2. Crawl 100-200 sản phẩm từ Shopee/Tiki/Lazada
 * 3. Lưu kết quả vào DB
 *
 * Usage:
 *   php workers/auto-crawl-hourly.php
 *   php workers/auto-crawl-hourly.php --site=2
 *   php workers/auto-crawl-hourly.php --dry-run
 *   php workers/auto-crawl-hourly.php --verbose
 *
 * Cron (chạy mỗi 1 tiếng):
 *   0 * * * * cd /home/dokien/.openclaw/workspace/MMO && php workers/auto-crawl-hourly.php >> /var/log/mmo-auto-crawl.log 2>&1
 */

declare(strict_types=1);

require_once __DIR__ . '/../backend/app/bootstrap.php';

$options = getopt('', ['site::', 'dry-run', 'verbose', 'help']);
if (isset($options['help'])) {
    echo "Usage: php auto-crawl-hourly.php [options]\n";
    echo "  --site=N     Site ID (default: từ session)\n";
    echo "  --dry-run    Chạy thử, không lưu vào DB\n";
    echo "  --verbose    In log chi tiết\n";
    exit(0);
}

$dryRun = isset($options['dry-run']);
$verbose = isset($options['verbose']);
$siteId = isset($options['site']) ? (int) $options['site'] : null;

if ($siteId) {
    $_SESSION['site_id'] = $siteId;
}

$log = function(string $msg) use ($verbose) {
    if ($verbose) {
        echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    }
};

$log('=== Auto-crawl worker started ===');
$log('Dry-run: ' . ($dryRun ? 'YES' : 'NO'));

try {
    $autoCrawl = new AutoCrawlService();
    $keywordSvc = new AIKeywordService();

    // Step 1: AI gợi từ khóa mới
    $log('AI đang gợi từ khóa...');
    $suggestions = $keywordSvc->suggestKeywords(5);
    $savedCount = 0;

    foreach ($suggestions as $s) {
        $kw = $s['keyword'] ?? $s;
        $reason = $s['reason'] ?? 'không rõ';
        $log("  Gợi ý: {$kw} | {$reason}");

        if (!$dryRun) {
            $ok = $keywordSvc->saveKeywordForCrawl($kw, 'ai_hourly');
            if ($ok) {
                $savedCount++;
                $log("  → Đã lưu: {$kw}");
            } else {
                $log("  → (đã tồn tại hoặc lỗi, bỏ qua)");
            }
        }
    }
    $log("Đã thêm {$savedCount} từ khóa mới");

    // Step 2: Run auto-crawl
    if (!$dryRun) {
        $log('Bắt đầu crawl...');
        $result = $autoCrawl->runAutoCrawl($siteId);

        $log("Kết quả:");
        $log("  - Từ khóa xử lý: {$result['keywords_processed']}");
        $log("  - Sản phẩm mới: {$result['products_added']}");
        $log("  - Cập nhật: {$result['products_updated']}");

        foreach ($result['platforms'] ?? [] as $pl => $cnt) {
            $log("  - {$pl}: {$cnt}");
        }

        foreach ($result['errors'] ?? [] as $err) {
            $log("ERROR: {$err}");
        }

        $log("Session #{$result['session_id']} | {$result['started_at']} → {$result['finished_at']}");
    }

    $log('=== Auto-crawl worker finished ===');
    exit(0);

} catch (\Throwable $e) {
    $log('FATAL: ' . $e->getMessage());
    exit(1);
}