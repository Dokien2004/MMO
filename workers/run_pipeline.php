<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/app/bootstrap.php';

$settingsService = new AutomationSettingsService();
$settings = $settingsService->get();

$platform = $argv[1] ?? 'affiliate_api';
$sourceFile = $argv[2] ?? (STORAGE_PATH . '/data/sources/sample_products.json');
$campaignCode = $argv[3] ?? (string)$settings['default_campaign_code'];
$provider = $argv[4] ?? (string)$settings['default_content_provider'];
$channel = $argv[5] ?? (string)$settings['default_channel'];
$limit = max(1, min(50, (int)($argv[6] ?? $settings['sync_limit'])));

if (!file_exists($sourceFile)) {
    fwrite(STDERR, "Source file not found: {$sourceFile}\n");
    exit(1);
}

$payload = json_decode((string)file_get_contents($sourceFile), true);
if (!is_array($payload)) {
    fwrite(STDERR, "Invalid JSON payload in {$sourceFile}\n");
    exit(1);
}

if (!empty($settings['top_selling_only'])) {
    $minSoldCount = (int)($settings['min_sold_count'] ?? 0);
    $payload = array_values(array_filter($payload, static function (array $product) use ($minSoldCount): bool {
        $soldCount = (int)($product['sold_count'] ?? $product['order_count'] ?? $product['sales_count'] ?? 0);
        return $soldCount >= $minSoldCount;
    }));
}

usort($payload, static function (array $left, array $right): int {
    $leftSold = (int)($left['sold_count'] ?? $left['order_count'] ?? $left['sales_count'] ?? 0);
    $rightSold = (int)($right['sold_count'] ?? $right['order_count'] ?? $right['sales_count'] ?? 0);
    return $rightSold <=> $leftSold;
});

$productService = new ProductSyncService();
$linkService = new AffiliateLinkService();
$contentService = new ContentService();
$postingService = new PostingService();

$sync = $productService->syncBatch($platform, $payload);
$links = $linkService->generateForEligibleProducts($campaignCode, $limit);
$contents = $contentService->generateForEligibleProducts($limit, $provider);

if (!empty($settings['auto_approve'])) {
    foreach ($contentService->recentContents($limit) as $content) {
        if (($content['status'] ?? '') === 'draft') {
            $contentService->approveContent((int)$content['id']);
        }
    }
}

$posts = ['count' => 0];
if (!empty($settings['auto_schedule'])) {
    $posts = $postingService->scheduleForApprovedContents($limit, $channel);
}

$published = ['count' => 0];
if (!empty($settings['auto_publish']) && $channel === 'fanpage_api') {
    $published = $postingService->publishDueScheduledPosts($limit);
}

echo json_encode([
    'success' => true,
    'worker' => 'run_pipeline',
    'sync' => $sync['summary'],
    'links' => $links['count'],
    'contents' => $contents['count'],
    'posts' => $posts['count'],
    'published' => $published['count'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
