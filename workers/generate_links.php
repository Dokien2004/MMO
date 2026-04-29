<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/app/bootstrap.php';

$campaignCode = $argv[1] ?? 'MVP-LAPTOP';
$limit = max(1, min(50, (int)($argv[2] ?? 10)));

$service = new AffiliateLinkService();
$result = $service->generateForEligibleProducts($campaignCode, $limit);

echo json_encode([
    'success' => true,
    'worker' => 'generate_links',
    'count' => $result['count'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
