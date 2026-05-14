<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/app/bootstrap.php';

if ($argc < 4) {
    fwrite(STDERR, "Usage: php workers/attach_content_media.php <content_id> <image|video> <public_url_or_path> [status]\n");
    fwrite(STDERR, "Example: php workers/attach_content_media.php 11 image /uploads/ai/content-11.png\n");
    exit(1);
}

$contentId = (int)$argv[1];
$mediaType = strtolower(trim((string)$argv[2]));
$mediaUrl = trim((string)$argv[3]);
$status = trim((string)($argv[4] ?? 'ready')) ?: 'ready';

if ($contentId <= 0) {
    fwrite(STDERR, "content_id khong hop le.\n");
    exit(1);
}

if (!in_array($mediaType, ['image', 'video'], true)) {
    fwrite(STDERR, "media_type chi ho tro image hoac video.\n");
    exit(1);
}

if ($mediaUrl === '') {
    fwrite(STDERR, "media_url khong duoc de trong.\n");
    exit(1);
}

$service = new ContentService();
$content = $service->attachMedia($contentId, $mediaType, $mediaUrl, '', $status);

echo json_encode([
    'success' => true,
    'content_id' => (int)$content['id'],
    'media_type' => $content['media_type'],
    'media_url' => $content['media_url'],
    'media_status' => $content['media_status'],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
