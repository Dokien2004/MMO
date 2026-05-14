#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/app/bootstrap.php';

$limit = max(1, min(20, (int)($argv[1] ?? 1)));
$lockFile = STORAGE_PATH . '/logs/generate_pending_images.lock';
$outFile = STORAGE_PATH . '/logs/generate_pending_images.out.log';

if (!is_dir(dirname($lockFile))) {
    mkdir(dirname($lockFile), 0775, true);
}

$lockHandle = fopen($lockFile, 'c+');
if ($lockHandle === false) {
    fwrite(STDERR, "Cannot open lock file: {$lockFile}\n");
    exit(1);
}

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    file_put_contents($outFile, '[' . date('c') . "] skipped: worker already running\n", FILE_APPEND);
    exit(0);
}

ftruncate($lockHandle, 0);
fwrite($lockHandle, json_encode([
    'pid' => getmypid(),
    'limit' => $limit,
    'started_at' => date('c'),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
fflush($lockHandle);

$startedAt = time();
$taskLogService = new TaskLogService();
$telegramService = new TelegramService();
$taskLogService->create('generate_pending_images_worker', 'started', ['limit' => $limit]);
file_put_contents($outFile, '[' . date('c') . "] started limit={$limit}\n", FILE_APPEND);

try {
    // Background jobs do not need the web session lock.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $service = new ImageMediaService();
    $result = $service->generateForPendingContents($limit);

    $taskLogService->create('generate_pending_images_worker', 'success', ['limit' => $limit], $result);
    $generatedCount = (int)($result['count'] ?? 0);
    $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
    $errorCount = count($errors);
    $elapsedSeconds = max(1, time() - $startedAt);

    file_put_contents($outFile, '[' . date('c') . '] success generated=' . $generatedCount . ' errors=' . $errorCount . "\n", FILE_APPEND);

    $message = "✅ <b>TẠO ẢNH AI XONG</b>\n\n";
    $message .= "Đã tạo: <b>{$generatedCount}/{$limit}</b> ảnh\n";
    $message .= "Lỗi: <b>{$errorCount}</b>\n";
    $message .= "Thời gian: <b>" . gmdate('H:i:s', $elapsedSeconds) . "</b>\n";
    $message .= "\nVào <b>Content</b> refresh trang để xem ảnh mới.";
    if ($errorCount > 0) {
        $firstError = (string)($errors[0]['error'] ?? 'unknown error');
        $message .= "\n\nLỗi đầu tiên: <code>" . htmlspecialchars(mb_substr($firstError, 0, 500), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</code>";
    }
    $telegramService->sendMessage($message);
} catch (Throwable $throwable) {
    $taskLogService->create('generate_pending_images_worker', 'error', ['limit' => $limit], [], $throwable->getMessage());
    file_put_contents($outFile, '[' . date('c') . '] error ' . $throwable->getMessage() . "\n", FILE_APPEND);

    $telegramService->sendAlert(
        'TẠO ẢNH AI BỊ LỖI',
        'Limit: <b>' . (int)$limit . '</b>' . "\n" . '<code>' . htmlspecialchars(mb_substr($throwable->getMessage(), 0, 800), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code>'
    );
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    @unlink($lockFile);
    exit(1);
}

flock($lockHandle, LOCK_UN);
fclose($lockHandle);
@unlink($lockFile);
exit(0);
