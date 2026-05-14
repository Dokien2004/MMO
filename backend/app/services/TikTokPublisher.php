<?php

declare(strict_types=1);

/**
 * TikTokPublisher — Upload videos to TikTok using browser automation.
 *
 * Strategy: Uses a Node.js Playwright script to automate TikTok video uploads.
 * Cookie-based session authentication.
 *
 * Required: Node.js + Playwright installed on server.
 * Video must be in 9:16 format (vertical).
 */
final class TikTokPublisher
{
    private string $scriptPath;

    public function __construct()
    {
        $this->scriptPath = BASE_PATH . '/backend/scripts/tiktok_upload.js';
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
     * Upload a video to TikTok.
     *
     * @param array $content Content data (must include video media)
     * @param array $channel Channel config (cookie_data, channel_id = @username)
     * @return array Result with status and message
     */
    public function publish(array $content, array $channel): array
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException('TikTok Publisher chưa sẵn sàng. Cần Node.js + Playwright.');
        }

        $cookieData = trim((string)($channel['cookie_data'] ?? ''));
        if ($cookieData === '') {
            throw new InvalidArgumentException('Thiếu cookie data cho TikTok.');
        }

        $videoPath = $this->resolveVideoPath($content);
        if ($videoPath === '') {
            throw new InvalidArgumentException('Nội dung phải có video để upload lên TikTok.');
        }

        $caption = $this->buildCaption($content);

        $payload = json_encode([
            'cookies'    => $cookieData,
            'video_path' => $videoPath,
            'caption'    => $caption,
            'username'   => trim((string)($channel['channel_id'] ?? '')),
        ], JSON_UNESCAPED_UNICODE);

        $payloadFile = STORAGE_PATH . '/tmp/tiktok-' . bin2hex(random_bytes(8)) . '.json';
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
                throw new RuntimeException('Upload TikTok thất bại: ' . mb_substr($outputStr, 0, 500));
            }

            $result = json_decode($outputStr, true);
            if (!is_array($result)) {
                if (stripos($outputStr, 'success') !== false || stripos($outputStr, 'uploaded') !== false) {
                    return [
                        'success' => true,
                        'message' => 'Đã upload video lên TikTok thành công.',
                        'raw_output' => mb_substr($outputStr, 0, 200),
                    ];
                }
                throw new RuntimeException('Kết quả không hợp lệ: ' . mb_substr($outputStr, 0, 300));
            }

            return [
                'success' => (bool)($result['success'] ?? false),
                'message' => (string)($result['message'] ?? 'Đã upload video.'),
                'video_url' => (string)($result['video_url'] ?? ''),
                'remote_post_id' => (string)($result['video_id'] ?? ''),
            ];
        } finally {
            @unlink($payloadFile);
        }
    }

    /**
     * Build TikTok caption from content fields.
     */
    private function buildCaption(array $content): string
    {
        $parts = array_filter([
            (string)($content['title'] ?? ''),
            (string)($content['call_to_action'] ?? ''),
            (string)($content['hashtags'] ?? ''),
        ]);
        $caption = trim(implode(" ", $parts));
        // TikTok caption limit: ~2200 chars
        return mb_substr($caption, 0, 2200);
    }

    /**
     * Resolve video to local filesystem path.
     */
    private function resolveVideoPath(array $content): string
    {
        if (($content['media_type'] ?? '') !== 'video') {
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
