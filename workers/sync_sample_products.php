<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/app/bootstrap.php';

$platform = $argv[1] ?? 'affiliate_api';
$sourceFile = $argv[2] ?? (STORAGE_PATH . '/data/sources/sample_products.json');

if (!file_exists($sourceFile)) {
    fwrite(STDERR, "Source file not found: {$sourceFile}\n");
    exit(1);
}

$payload = json_decode((string)file_get_contents($sourceFile), true);
if (!is_array($payload)) {
    fwrite(STDERR, "Invalid JSON payload in {$sourceFile}\n");
    exit(1);
}

$service = new ProductSyncService();
$result = $service->syncBatch($platform, $payload);

echo json_encode([
    'success' => true,
    'worker' => 'sync_sample_products',
    'summary' => $result['summary'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
