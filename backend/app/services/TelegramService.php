<?php

declare(strict_types=1);

/**
 * TelegramService — gửi thông báo qua Telegram Bot.
 */
class TelegramService
{
    private string $botToken;
    private string $chatId;

    public function __construct()
    {
        $this->botToken = telegram_bot_token();
        $this->chatId   = telegram_chat_id();
    }

    public function isConfigured(): bool
    {
        return $this->botToken !== '' && $this->chatId !== '';
    }

    /**
     * Gửi tin nhắn text.
     * @param string $text Nội dung (hỗ trợ emoji, không cần escape markdown)
     */
    public function sendMessage(string $text): bool
    {
        if (!$this->isConfigured()) {
            error_log('[Telegram] Not configured (missing bot_token or chat_id)');
            return false;
        }

        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
        $payload = [
            'chat_id'    => $this->chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log('[Telegram] sendMessage failed, HTTP ' . $httpCode . ': ' . $response);
            return false;
        }

        return true;
    }

    /**
     * Gửi thông báo captcha Shopee — kèm RustDesk ID + password để Kiên connect.
     */
    public function sendCaptchaAlert(
        string $rustdeskId,
        string $rustdeskPassword,
        string $shopeeSessionStatus = 'chưa rõ'
    ): bool {
        $msg = "🔴 <b>CẦN XÁC MINH CAPTCHA SHOPEE</b>\n\n";
        $msg .= "⚠️ Trình duyệt Shopee cần xác minh captcha.\n";
        $msg .= "📋 Trạng thái session: {$shopeeSessionStatus}\n\n";
        $msg .= "🖥️ <b>Kết nối qua RustDesk:</b>\n";
        $msg .= "ID: <code>{$rustdeskId}</code>\n";
        $msg .= "Pass: <code>{$rustdeskPassword}</code>\n\n";
        $msg .= "Vào RustDesk → nhập ID → đăng nhập → xác minh captcha trên trình duyệt đang mở.";

        return $this->sendMessage($msg);
    }

    public function sendScrapeInterventionRequest(string $jobId, string $rustdeskId, string $rustdeskPassword, string $reason = ''): bool
    {
        $msg = "🟡 <b>CHỜ KIÊN XÁC NHẬN ĐỂ CÀO SHOPEE</b>\n\n";
        $msg .= "Job: <code>{$jobId}</code>\n";
        if ($reason !== '') {
            $msg .= "Lý do: {$reason}\n\n";
        }
        $msg .= "🖥️ <b>RustDesk vào server:</b>\n";
        $msg .= "ID: <code>{$rustdeskId}</code>\n";
        $msg .= "Pass: <code>{$rustdeskPassword}</code>\n\n";
        $msg .= "Việc cần làm:\n";
        $msg .= "1) Vào server bằng RustDesk\n";
        $msg .= "2) Mở Chrome/Shopee\n";
        $msg .= "3) Đăng nhập Shopee và vượt captcha nếu có\n";
        $msg .= "4) Nhắn lại bot đúng chữ: <b>xong</b>\n\n";
        $msg .= "Sau khi nhận chữ <b>xong</b>, bot sẽ tự bắt đầu cào dữ liệu.";

        return $this->sendMessage($msg);
    }

    public function getUpdates(int $offset = 0, int $timeout = 20): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $url = "https://api.telegram.org/bot{$this->botToken}/getUpdates?" . http_build_query([
            'offset' => $offset,
            'timeout' => $timeout,
            'allowed_updates' => json_encode(['message'], JSON_UNESCAPED_UNICODE),
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout + 5,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !is_string($response)) {
            return [];
        }
        $decoded = json_decode($response, true);
        return is_array($decoded['result'] ?? null) ? $decoded['result'] : [];
    }

    public function latestUpdateOffset(): int
    {
        $updates = $this->getUpdates(0, 1);
        $max = 0;
        foreach ($updates as $update) {
            $id = (int)($update['update_id'] ?? 0);
            if ($id > $max) $max = $id;
        }
        return $max + 1;
    }

    public function configuredChatId(): string
    {
        return $this->chatId;
    }

    /**
     * Gửi thông báo scraper bắt đầu / kết thúc.
     */
    public function sendScraperUpdate(string $message): bool
    {
        return $this->sendMessage("📦 <b>Scraper Update</b>\n\n{$message}");
    }

    /**
     * Gửi thông báo lỗi nghiêm trọng.
     */
    public function sendAlert(string $title, string $details = ''): bool
    {
        $msg = "🚨 <b>{$title}</b>";
        if ($details !== '') {
            $msg .= "\n\n{$details}";
        }
        return $this->sendMessage($msg);
    }
}
