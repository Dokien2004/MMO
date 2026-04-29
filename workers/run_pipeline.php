<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/app/bootstrap.php';

$platform = $argv[1] ?? 'affiliate_api';
$sourceFile = $argv[2] ?? (STORAGE_PATH . '/data/sources/sample_products.json');
$campaignCode = $argv[3] ?? 'MVP-LAPTOP';
$provider = $argv[4] ?? 'template_engine';
$channel = $argv[5] ?? 'fanpage_manual';
$limit = max(1, min(50, (int)($argv[6] ?? 10)));

if (!file_exists($sourceFile)) {
    fwrite(STDERR, "Source file not found: {$sourceFile}\n");
    exit(1);
}

$payload = json_decode((string)file_get_contents($sourceFile), true);
if (!is_array($payload)) {
    fwrite(STDERR, "Invalid JSON payload in {$sourceFile}\n");
    exit(1);
}

$productService = new ProductSyncService();
$linkService = new AffiliateLinkService();
$contentService = new ContentService();
$postingService = new PostingService();

$sync = $productService->syncBatch($platform, $payload);
$links = $linkService->generateForEligibleProducts($campaignCode, $limit);
$contents = $contentService->generateForEligibleProducts($limit, $provider);

foreach ($contentService->recentContents($limit) as $content) {
    if (($content['status'] ?? '') === 'draft') {
        $contentService->approveContent((int)$content['id']);
    }
}

$posts = $postingService->scheduleForApprovedContents($limit, $channel);

echo json_encode([
    'success' => true,
    'worker' => 'run_pipeline',
    'sync' => $sync['summary'],
    'links' => $links['count'],
    'contents' => $contents['count'],
    'posts' => $posts['count'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
