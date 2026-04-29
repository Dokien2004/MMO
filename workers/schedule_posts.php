<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/app/bootstrap.php';

$channel = $argv[1] ?? 'fanpage_manual';
$limit = max(1, min(50, (int)($argv[2] ?? 10)));

$service = new PostingService();
$result = $service->scheduleForApprovedContents($limit, $channel);

echo json_encode([
    'success' => true,
    'worker' => 'schedule_posts',
    'count' => $result['count'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
