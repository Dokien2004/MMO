<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/app/services/TikiScraperClient.php';

/**
 * Worker: cào Tiki ra file JSON nguồn, không cần MySQL.
 *
 * Usage:
 *   php workers/scrape_tiki_source.php "laptop" 2 40
 *   php workers/scrape_tiki_source.php "laptop" 2 40 storage/data/sources/tiki_laptop_products.json
 */

$basePath = dirname(__DIR__);
$keyword = trim((string)($argv[1] ?? 'laptop'));
$maxPages = max(1, min(20, (int)($argv[2] ?? 1)));
$limit = max(1, min(100, (int)($argv[3] ?? 40)));
$slug = preg_replace('/[^a-z0-9]+/i', '_', strtolower($keyword)) ?: 'products';
$outputFile = (string)($argv[4] ?? ($basePath . '/storage/data/sources/tiki_' . trim($slug, '_') . '_products.json'));
if (!str_starts_with($outputFile, '/')) {
    $outputFile = $basePath . '/' . ltrim($outputFile, '/');
}

$client = new TikiScraperClient();
$products = [];
$errors = [];

for ($page = 1; $page <= $maxPages; $page++) {
    try {
        $pageProducts = $client->scrapeSearch($keyword, $page, 'sold', $limit);
        $products = array_merge($products, $pageProducts);
    } catch (Throwable $throwable) {
        $errors[] = 'Page ' . $page . ': ' . $throwable->getMessage();
    }

    if ($page < $maxPages) {
        usleep(800_000);
    }
}

$unique = [];
foreach ($products as $product) {
    $key = (string)($product['source_product_id'] ?? '');
    if ($key !== '' && !isset($unique[$key])) {
        $unique[$key] = $product;
    }
}

$products = array_values($unique);
usort($products, static function (array $left, array $right): int {
    return (int)($right['sold_count'] ?? 0) <=> (int)($left['sold_count'] ?? 0);
});

$dir = dirname($outputFile);
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

file_put_contents(
    $outputFile,
    json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

echo json_encode([
    'success' => empty($errors),
    'platform' => 'tiki',
    'keyword' => $keyword,
    'pages' => $maxPages,
    'count' => count($products),
    'output_file' => $outputFile,
    'errors' => $errors,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if (!empty($errors) && count($products) === 0) {
    exit(1);
}
