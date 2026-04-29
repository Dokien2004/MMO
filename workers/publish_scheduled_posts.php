<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/app/bootstrap.php';

$limit = max(1, min(50, (int)($argv[1] ?? 10)));

$service = new PostingService();
$result = $service->publishDueScheduledPosts($limit);

echo json_encode([
    'success' => true,
    'worker' => 'publish_scheduled_posts',
    'count' => $result['count'],
    'fanpage_api_available' => $service->fanpageApiAvailable(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
