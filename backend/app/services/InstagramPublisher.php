<?php

declare(strict_types=1);

/**
 * InstagramPublisher — Post to Instagram using browser automation.
 *
 * Strategy: Uses a Node.js Playwright script to automate Instagram posts.
 * Cookie-based session authentication via web.instagram.com.
 *
 * Required: Node.js + Playwright installed on server.
 * Content: Supports both feed posts (photo) and reels (video).
 */
final class InstagramPublisher
{
    private string $scriptPath;

    public function __construct()
    {
        $this->scriptPath = BASE_PATH . '/backend/scripts/instagram_upload.js';
    }

    /**
     * Check if the publisher is available.
     */
    public function isAvailable(): bool
    {
        $node = trim((string)shell_exec('command -v node 2>/dev/null'));
        return $node !== '' && file_exists($this->scriptPath);
    }

    /**
     * Publish content to Instagram.
     *
     * @param array $content Content data (title, body, call_to_action, media_url, media_type)
     * @param array $channel Channel config (cookie_data, channel_id = username)
     * @return array Result with status and message
     */
    public function publish(array $content, array $channel): array
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException('Instagram Publisher chưa sẵn sàng. Cần Node.js + Playwright.');
        }

        $cookieData = trim((string)($channel['cookie_data'] ?? ''));
        if ($cookieData === '') {
            throw new InvalidArgumentException('Thiếu cookie data cho Instagram.');
        }

        $username = trim((string)($channel['channel_id'] ?? ''));

        $mediaPath = $this->resolveMediaPath($content);
        $mediaType = (string)($content['media_type'] ?? 'none');

        if ($mediaType === 'none' || $mediaPath === '') {
            throw new InvalidArgumentException('Nội dung phải có hình ảnh/video để đăng lên Instagram.');
        }

        $caption = $this->buildCaption($content);

        $payload = json_encode([
            'cookies'   => $cookieData,
            'username'  => $username,
            'media_path' => $mediaPath,
            'media_type' => $mediaType,
            'caption'   => $caption,
            'title'     => (string)($content['title'] ?? ''),
        ], JSON_UNESCAPED_UNICODE);

        $payloadFile = STORAGE_PATH . '/tmp/instagram-' . bin2hex(random_bytes(8)) . '.json';
        file_put_contents($payloadFile, $payload, LOCK_EX);

        try {
            $cmd = sprintf(
                'node %s %s 2>&1',
                escapeshellarg($this->scriptPath),
                escapeshellarg($payloadFile)
            );

            $output = [];
            $exitCode = 0;
            exec($cmd, $output, $exitCode);
            $outputStr = implode("\n", $output);

            if ($exitCode !== 0) {
                throw new RuntimeException('Đăng Instagram thất bại: ' . mb_substr($outputStr, 0, 500));
            }

            $result = json_decode($outputStr, true);
            if (!is_array($result)) {
                if (stripos($outputStr, 'success') !== false || stripos($outputStr, 'posted') !== false || stripos($outputStr, 'uploaded') !== false) {
                    return [
                        'success' => true,
                        'message' => 'Đã đăng bài lên Instagram thành công.',
                        'raw_output' => mb_substr($outputStr, 0, 200),
                    ];
                }
                throw new RuntimeException('Kết quả không hợp lệ: ' . mb_substr($outputStr, 0, 300));
            }

            return [
                'success' => (bool)($result['success'] ?? false),
                'message' => (string)($result['message'] ?? 'Đã đăng Instagram.'),
                'post_url' => (string)($result['post_url'] ?? ''),
                'media_id' => (string)($result['media_id'] ?? ''),
            ];
        } finally {
            @unlink($payloadFile);
        }
    }

    /**
     * Build Instagram caption from content fields.
     */
    private function buildCaption(array $content): string
    {
        $parts = array_filter([
            (string)($content['title'] ?? ''),
            (string)($content['body'] ?? ''),
            (string)($content['call_to_action'] ?? ''),
        ]);
        $hashtags = trim((string)($content['hashtags'] ?? ''));
        $caption = implode("\n\n", $parts);
        if ($hashtags !== '') {
            $caption .= "\n\n" . $hashtags;
        }
        // Instagram caption limit: ~2200 chars
        return mb_substr($caption, 0, 2200);
    }

    /**
     * Resolve media to local filesystem path.
     */
    private function resolveMediaPath(array $content): string
    {
        if (($content['media_type'] ?? '') === 'none') {
            return '';
        }
        $mediaUrl = trim((string)($content['media_url'] ?? ''));
        if ($mediaUrl === '' || preg_match('#^https?://#i', $mediaUrl) === 1) {
            return '';
        }
        $localPath = realpath(BASE_PATH . '/backend/public/' . ltrim($mediaUrl, '/'));
        return ($localPath !== false && is_file($localPath)) ? $localPath : '';
    }
}