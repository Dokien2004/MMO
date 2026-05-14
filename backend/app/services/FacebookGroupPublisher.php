<?php

declare(strict_types=1);

/**
 * FacebookGroupPublisher — Post to Facebook Groups using browser automation.
 *
 * Strategy: Uses a Node.js Playwright script to automate posting to Facebook Groups.
 * Cookie-based session authentication (no official API for Groups).
 *
 * Required: Node.js + Playwright installed on server.
 */
final class FacebookGroupPublisher
{
    private string $scriptPath;

    public function __construct()
    {
        $this->scriptPath = BASE_PATH . '/backend/scripts/fb_group_post.js';
    }

    /**
     * Check if the publisher is available (Node.js + Playwright installed).
     */
    public function isAvailable(): bool
    {
        $node = trim((string)shell_exec('command -v node 2>/dev/null'));
        return $node !== '' && file_exists($this->scriptPath);
    }

    /**
     * Publish content to a Facebook Group.
     *
     * @param array $content Content data (title, body, call_to_action, media_url)
     * @param array $channel Channel config (channel_id = group ID, cookie_data = JSON cookies)
     * @return array Result with status and message
     */
    public function publish(array $content, array $channel): array
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException('Facebook Group Publisher chưa sẵn sàng. Cần Node.js + Playwright.');
        }

        $groupId = trim((string)($channel['channel_id'] ?? ''));
        $cookieData = trim((string)($channel['cookie_data'] ?? ''));
        if ($groupId === '' || $cookieData === '') {
            throw new InvalidArgumentException('Thiếu Group ID hoặc cookie data.');
        }

        $message = $this->buildMessage($content);
        $mediaPath = $this->resolveMediaPath($content);

        $payload = json_encode([
            'group_id'    => $groupId,
            'cookies'     => $cookieData,
            'message'     => $message,
            'media_path'  => $mediaPath,
            'media_type'  => (string)($content['media_type'] ?? 'none'),
        ], JSON_UNESCAPED_UNICODE);

        $payloadFile = STORAGE_PATH . '/tmp/fbgroup-' . bin2hex(random_bytes(8)) . '.json';
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
                throw new RuntimeException('Đăng bài vào Group thất bại: ' . mb_substr($outputStr, 0, 500));
            }

            $result = json_decode($outputStr, true);
            if (!is_array($result)) {
                // Script output is not JSON, check for success indicators
                if (stripos($outputStr, 'success') !== false || stripos($outputStr, 'posted') !== false) {
                    return [
                        'success' => true,
                        'message' => 'Đã đăng bài vào Facebook Group thành công.',
                        'raw_output' => mb_substr($outputStr, 0, 200),
                    ];
                }
                throw new RuntimeException('Kết quả không hợp lệ: ' . mb_substr($outputStr, 0, 300));
            }

            return [
                'success' => (bool)($result['success'] ?? false),
                'message' => (string)($result['message'] ?? 'Đã đăng bài.'),
                'post_url' => (string)($result['post_url'] ?? ''),
                'remote_post_id' => (string)($result['post_id'] ?? ''),
            ];
        } finally {
            @unlink($payloadFile);
        }
    }

    /**
     * Build the post message from content fields.
     */
    private function buildMessage(array $content): string
    {
        return trim(implode("\n\n", array_filter([
            (string)($content['title'] ?? ''),
            (string)($content['body'] ?? ''),
            (string)($content['call_to_action'] ?? ''),
            (string)($content['hashtags'] ?? ''),
        ])));
    }

    /**
     * Resolve media to local filesystem path.
     */
    private function resolveMediaPath(array $content): string
    {
        $mediaUrl = trim((string)($content['media_url'] ?? ''));
        if ($mediaUrl === '' || preg_match('#^https?://#i', $mediaUrl) === 1) {
            return '';
        }
        $localPath = realpath(BASE_PATH . '/backend/public/' . ltrim($mediaUrl, '/'));
        return ($localPath !== false && is_file($localPath)) ? $localPath : '';
    }
}
