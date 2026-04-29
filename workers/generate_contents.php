<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/app/bootstrap.php';

$provider = $argv[1] ?? 'template_engine';
$limit = max(1, min(50, (int)($argv[2] ?? 10)));

$service = new ContentService();
$result = $service->generateForEligibleProducts($limit, $provider);

echo json_encode([
    'success' => true,
    'worker' => 'generate_contents',
    'count' => $result['count'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
