<?php

declare(strict_types=1);

require_once __DIR__ . '/backend/app/config/config.php';
require_once __DIR__ . '/backend/app/services/DatabaseStorage.php';

$storage = new DatabaseStorage();
$files = [
    'products.json',
    'affiliate_links.json',
    'generated_contents.json',
    'scheduled_posts.json',
    'task_logs.json',
];

foreach ($files as $file) {
    $path = DATA_PATH . '/' . $file;
    $payload = [];
    if (is_file($path)) {
        $decoded = json_decode((string)file_get_contents($path), true);
        $payload = is_array($decoded) ? $decoded : [];
    }
    $storage->write($file, $payload);
    echo $file . ': ' . count($payload) . " rows\n";
}
