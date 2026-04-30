<?php

declare(strict_types=1);

/**
 * Worker: Chạy scraper cào dữ liệu sản phẩm bán chạy.
 *
 * Usage:
 *   php workers/run_scraper.php              # Chạy tất cả config active
 *   php workers/run_scraper.php --config=3   # Chạy 1 config cụ thể
 */

require_once __DIR__ . '/../backend/app/bootstrap.php';

$configId = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--config=')) {
        $configId = (int)substr($arg, 9);
    }
}

$service = new ScraperService();

echo "═══════════════════════════════════════\n";
echo "  🕷️  Affiliate Scraper Worker\n";
echo "  " . date('Y-m-d H:i:s') . "\n";
echo "═══════════════════════════════════════\n\n";

try {
    if ($configId !== null && $configId > 0) {
        echo "▶ Chạy config #{$configId}...\n";
        $result = $service->runScrapeJob($configId);
        echo "  ✓ Scraped: {$result['scraped']}, Filtered: {$result['filtered']}, Synced: {$result['synced']}\n";
        if (!empty($result['errors'])) {
            echo "  ⚠ Lỗi: " . implode('; ', $result['errors']) . "\n";
        }
    } else {
        echo "▶ Chạy tất cả config active...\n\n";
        $results = $service->runAllActive();

        if (empty($results)) {
            echo "  ℹ Không có config nào đang active.\n";
        }

        foreach ($results as $r) {
            echo "  [{$r['keyword']}] ";
            if (isset($r['error'])) {
                echo "✕ Lỗi: {$r['error']}\n";
            } else {
                echo "✓ Scraped: {$r['result']['scraped']}, Filtered: {$r['result']['filtered']}, Synced: {$r['result']['synced']}\n";
            }
        }
    }

    echo "\n✅ Hoàn tất.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "✕ Error: {$e->getMessage()}\n");
    exit(1);
}
