<?php

declare(strict_types=1);

/**
 * ThreadsPublisher — Post to Threads using browser automation.
 *
 * Strategy: Uses a Node.js Playwright script to automate Threads posts.
 * Cookie-based session authentication via threads.net.
 * Note: Threads uses Meta's infrastructure — cookies from web.facebook.com may work.
 *
 * Required: Node.js + Playwright installed on server.
 * Content: Text posts, images (video support is limited on Threads).
 */
final class ThreadsPublisher
{
    private string $scriptPath;

    public function __construct()
    {
        $this->scriptPath = BASE_PATH . '/backend/scripts/threads_upload.js';
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
     * Publish content to Threads.
     *
     * @param array $content Content data (title, body, call_to_action, media_url, media_type)
     * @param array $channel Channel config (cookie_data, channel_id = username)
     * @return array Result with status and message
     */
    public function publish(array $content, array $channel): array
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException('Threads Publisher chưa sẵn sàng. Cần Node.js + Playwright.');
        }

        $cookieData = trim((string)($channel['cookie_data'] ?? ''));
        if ($cookieData === '') {
            throw new InvalidArgumentException('Thiếu cookie data cho Threads.');
        }

        $username = trim((string)($channel['channel_id'] ?? ''));
        $mediaPath = $this->resolveMediaPath($content);
        $mediaType = (string)($content['media_type'] ?? 'none');

        $caption = $this->buildCaption($content);

        $payload = json_encode([
            'cookies'   => $cookieData,
            'username'  => $username,
            'media_path' => $mediaPath,
            'media_type' => $mediaType,
            'caption'   => $caption,
            'title'     => (string)($content['title'] ?? ''),
        ], JSON_UNESCAPED_UNICODE);

        $payloadFile = STORAGE_PATH . '/tmp/threads-' . bin2hex(random_bytes(8)) . '.json';
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
                throw new RuntimeException('Đăng Threads thất bại: ' . mb_substr($outputStr, 0, 500));
            }

            $result = json_decode($outputStr, true);
            if (!is_array($result)) {
                if (stripos($outputStr, 'success') !== false || stripos($outputStr, 'posted') !== false) {
                    return [
                        'success' => true,
                        'message' => 'Đã đăng bài lên Threads thành công.',
                        'raw_output' => mb_substr($outputStr, 0, 200),
                    ];
                }
                throw new RuntimeException('Kết quả không hợp lệ: ' . mb_substr($outputStr, 0, 300));
            }

            return [
                'success' => (bool)($result['success'] ?? false),
                'message' => (string)($result['message'] ?? 'Đã đăng Threads.'),
                'post_url' => (string)($result['post_url'] ?? ''),
                'post_id' => (string)($result['post_id'] ?? ''),
            ];
        } finally {
            @unlink($payloadFile);
        }
    }

    /**
     * Build Threads caption from content fields.
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
        return mb_substr($caption, 0, 500); // Threads text limit is ~500 chars
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