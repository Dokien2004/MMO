<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/app/bootstrap.php';

$service = new ScraperService();

try {
    $result = $service->scrapeTrending('shopee', [11036030], 100, 1);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . PHP_EOL);
    exit(1);
}
