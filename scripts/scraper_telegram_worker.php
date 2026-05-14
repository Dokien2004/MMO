<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/app/bootstrap.php';

$telegram = new TelegramService();
$pending = new PendingScrapeJobService();
$scraper = new ScraperService();

$logFile = STORAGE_PATH . '/logs/scraper_telegram_worker.log';
$stateFile = STORAGE_PATH . '/data/telegram_scraper_state.json';
$lockFile = STORAGE_PATH . '/logs/scraper_telegram_worker.lock';

$lock = fopen($lockFile, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}

$log = static function (string $message) use ($logFile): void {
    file_put_contents($logFile, '[' . date('c') . '] ' . $message . PHP_EOL, FILE_APPEND);
};

$readState = static function () use ($stateFile, $telegram): array {
    if (is_file($stateFile)) {
        $data = json_decode((string)file_get_contents($stateFile), true);
        if (is_array($data)) return $data;
    }
    return ['offset' => $telegram->latestUpdateOffset()];
};

$writeState = static function (array $state) use ($stateFile): void {
    file_put_contents($stateFile, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
};

$isInterventionError = static function (array $errors): bool {
    $message = implode("\n", array_map('strval', $errors));
    return preg_match('/captcha|verification|verify\/traffic|verify\/captcha|Shopee verification|Page Unavailable|Please go back|đăng nhập lại|login|Inspected target navigated or closed/i', $message) === 1;
};

$runJob = static function (array $job, string $trigger) use ($pending, $scraper, $telegram, $log, $isInterventionError, $stateFile): string {
    $sessionCheck = $scraper->checkShopeeSession();
    if (!empty($sessionCheck['alive']) && !empty($sessionCheck['captcha_required'])) {
        $pending->mark($job['id'], 'waiting', [
            'waiting_since' => date('c'),
            'last_error' => (string)($sessionCheck['message'] ?? 'Shopee đang ở trang verify/captcha.'),
        ]);
        file_put_contents($stateFile, json_encode(['offset' => $telegram->latestUpdateOffset()], JSON_PRETTY_PRINT), LOCK_EX);
        $srvInfo = (new ServerInfoService())->get();
        $telegram->sendScrapeInterventionRequest(
            $job['id'],
            $srvInfo['rustdesk_id'] ?? '—',
            defined('RUSTDESK_PASSWORD') ? RUSTDESK_PASSWORD : '',
            'Chrome chuyên dụng vẫn đang ở trang Shopee verify/traffic. Vui lòng xử lý đến khi vào được trang Shopee bình thường rồi nhắn bot "xong".'
        );
        $log('blocked run for job ' . $job['id'] . ' because Shopee captcha/verify is still active');
        return 'waiting';
    }

    $pending->mark($job['id'], 'running', ['started_at' => date('c'), 'trigger' => $trigger]);
    if ($trigger === 'telegram') {
        $telegram->sendScraperUpdate("Đã nhận xác nhận <b>xong</b>. Bắt đầu cào dữ liệu cho job <code>{$job['id']}</code>...");
    } else {
        $telegram->sendScraperUpdate("Bắt đầu cào nền job <code>{$job['id']}</code>...");
    }
    $log('running job ' . $job['id'] . ' trigger=' . $trigger);

    try {
        $payload = $job['payload'] ?? [];
        if (($job['type'] ?? '') === 'trending') {
            $result = $scraper->scrapeTrending(
                (string)($payload['platform'] ?? 'shopee'),
                array_map('intval', (array)($payload['category_ids'] ?? [])),
                (int)($payload['min_sold_count'] ?? 100),
                (int)($payload['max_pages'] ?? 2)
            );
        } elseif (($job['type'] ?? '') === 'config') {
            $result = $scraper->runScrapeJob((int)($payload['config_id'] ?? 0));
        } else {
            throw new RuntimeException('Unknown pending scraper job type.');
        }

        $errorsList = array_values((array)($result['errors'] ?? []));
        $errors = count($errorsList);

        if ($errors > 0 && $isInterventionError($errorsList)) {
            $pending->mark($job['id'], 'waiting', [
                'waiting_since' => date('c'),
                'last_error' => implode("\n", array_slice($errorsList, 0, 3)),
                'result' => $result,
            ]);
            file_put_contents($stateFile, json_encode(['offset' => $telegram->latestUpdateOffset()], JSON_PRETTY_PRINT), LOCK_EX);
            $srvInfo = (new ServerInfoService())->get();
            $telegram->sendScrapeInterventionRequest(
                $job['id'],
                $srvInfo['rustdesk_id'] ?? '—',
                defined('RUSTDESK_PASSWORD') ? RUSTDESK_PASSWORD : '',
                'Đang cào thì Shopee yêu cầu đăng nhập/vượt captcha. Vào Chrome chuyên dụng xử lý rồi nhắn bot "xong" để chạy lại job này.'
            );
            $log('job ' . $job['id'] . ' moved back to waiting due to Shopee intervention error');
            return 'waiting';
        }

        $pending->mark($job['id'], 'done', ['finished_at' => date('c'), 'result' => $result]);
        $summary = "✅ <b>CÀO DỮ LIỆU XONG</b>\n\n" .
            "Job: <code>{$job['id']}</code>\n" .
            "Scraped: <b>" . (int)($result['scraped'] ?? 0) . "</b>\n" .
            "Filtered: <b>" . (int)($result['filtered'] ?? 0) . "</b>\n" .
            "Synced: <b>" . (int)($result['synced'] ?? 0) . "</b>\n" .
            "Errors: <b>{$errors}</b>";
        if ($errors > 0) {
            $preview = htmlspecialchars(implode("\n", array_slice($errorsList, 0, 3)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $summary .= "\n\n⚠️ <b>Lỗi đầu tiên:</b>\n<code>{$preview}</code>";
        }
        $telegram->sendScraperUpdate($summary);
        $log('done job ' . $job['id']);
        return 'done';
    } catch (Throwable $e) {
        $pending->mark($job['id'], 'failed', ['finished_at' => date('c'), 'error' => $e->getMessage()]);
        $telegram->sendAlert('Scraper job lỗi', "Job <code>{$job['id']}</code>\n" . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $log('failed job ' . $job['id'] . ': ' . $e->getMessage());
        return 'failed';
    }
};

$state = $readState();
$deadline = time() + 1800; // 30 phút
$chatId = $telegram->configuredChatId();
$log('worker started offset=' . ($state['offset'] ?? 0));

while (time() < $deadline) {
    $job = $pending->latestRunnable();
    if ($job === null) {
        $log('no runnable job, exit');
        exit(0);
    }

    if (($job['status'] ?? '') === 'queued') {
        $runResult = $runJob($job, 'auto');
        if ($runResult !== 'waiting') {
            exit(0);
        }
        continue;
    }

    $updates = $telegram->getUpdates((int)($state['offset'] ?? 0), 20);
    foreach ($updates as $update) {
        $state['offset'] = max((int)($state['offset'] ?? 0), (int)($update['update_id'] ?? 0) + 1);
        $message = $update['message'] ?? [];
        $fromChat = (string)($message['chat']['id'] ?? '');
        $text = trim(mb_strtolower((string)($message['text'] ?? ''), 'UTF-8'));

        if ($fromChat !== $chatId) {
            continue;
        }

        if (preg_match('/^(xong|xông|song|done|ok|oke|rồi|xong rồi)$/iu', $text)) {
            $writeState($state);
            $job = $pending->latestWaiting();
            if ($job === null) {
                $telegram->sendScraperUpdate('Không còn job nào đang chờ.');
                exit(0);
            }
            $messageDate = (int)($message['date'] ?? time());
            $waitingSince = strtotime((string)($job['waiting_since'] ?? $job['updated_at'] ?? $job['created_at'] ?? 'now')) ?: time();
            if ($messageDate < $waitingSince) {
                $log('ignored stale confirmation for job ' . $job['id']);
                continue;
            }
            $runResult = $runJob($job, 'telegram');
            if ($runResult !== 'waiting') {
                exit(0);
            }
            continue 2;
        }
    }
    $writeState($state);
}

$writeState($state);
$log('timeout waiting for confirmation');
