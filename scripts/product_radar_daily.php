#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../backend/app/bootstrap.php';

$options = getopt('', [
    'site::',
    'category::',
    'pages::',
    'limit::',
    'dry-run',
]);

$siteId = max(1, (int)($options['site'] ?? 1));
$categoryId = isset($options['category']) ? (int)$options['category'] : 11035567; // Thời trang nam
$pages = max(1, min(3, (int)($options['pages'] ?? 1)));
$limit = max(10, min(100, (int)($options['limit'] ?? 100)));
$dryRun = array_key_exists('dry-run', $options);

$_SESSION['site_id'] = $siteId;

if (getenv('SHOPEE_CLOAK_LIMIT') === false || getenv('SHOPEE_CLOAK_LIMIT') === '') {
    putenv('SHOPEE_CLOAK_LIMIT=' . $limit);
    $_ENV['SHOPEE_CLOAK_LIMIT'] = (string)$limit;
}
if (getenv('SHOPEE_CLOAK_JOB_DELAY_MIN') === false || getenv('SHOPEE_CLOAK_JOB_DELAY_MIN') === '') {
    putenv('SHOPEE_CLOAK_JOB_DELAY_MIN=45000');
    $_ENV['SHOPEE_CLOAK_JOB_DELAY_MIN'] = '45000';
}
if (getenv('SHOPEE_CLOAK_JOB_DELAY_MAX') === false || getenv('SHOPEE_CLOAK_JOB_DELAY_MAX') === '') {
    putenv('SHOPEE_CLOAK_JOB_DELAY_MAX=90000');
    $_ENV['SHOPEE_CLOAK_JOB_DELAY_MAX'] = '90000';
}

$scraper = new ScraperService();
$categories = $scraper->getCategories();
$categoryName = $categories[$categoryId] ?? ('Category #' . $categoryId);

if ($dryRun) {
    echo json_encode([
        'ok' => true,
        'dry_run' => true,
        'site_id' => $siteId,
        'category_id' => $categoryId,
        'category_name' => $categoryName,
        'pages' => $pages,
        'limit' => $limit,
        'note' => 'Dry-run only. Remove --dry-run to crawl and store market snapshots.',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

try {
    $result = $scraper->scrapeTrending('shopee', [$categoryId], 0, $pages);
    $radar = $scraper->buildProductRadar(30);

    (new TaskLogService())->create('product_radar_daily', empty($result['errors']) ? 'success' : 'failed', [
        'site_id' => $siteId,
        'category_id' => $categoryId,
        'category_name' => $categoryName,
        'pages' => $pages,
        'limit' => $limit,
    ], [
        'scrape' => $result,
        'radar_count' => $radar['count'] ?? 0,
        'top_scores' => array_map(static fn(array $item): array => [
            'product_id' => $item['product_id'] ?? 0,
            'score' => $item['score'] ?? 0,
            'name' => $item['name'] ?? '',
            'run_rate_7d' => $item['run_rate_7d'] ?? 0,
        ], array_slice($radar['opportunities'] ?? [], 0, 10)),
    ], implode('; ', $result['errors'] ?? []));

    echo json_encode([
        'ok' => empty($result['errors']),
        'site_id' => $siteId,
        'category_id' => $categoryId,
        'category_name' => $categoryName,
        'scrape' => $result,
        'radar_count' => $radar['count'] ?? 0,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(empty($result['errors']) ? 0 : 2);
} catch (Throwable $e) {
    (new TaskLogService())->create('product_radar_daily', 'failed', [
        'site_id' => $siteId,
        'category_id' => $categoryId,
        'category_name' => $categoryName,
    ], [], $e->getMessage());
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
