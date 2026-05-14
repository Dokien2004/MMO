<?php

declare(strict_types=1);

final class FacebookPagePublisher
{
    public function isAvailable(): bool
    {
        return facebook_page_id() !== '' && facebook_page_access_token() !== '';
    }

    public function checkToken(): array
    {
        if (!$this->isAvailable()) {
            return [
                'ok' => false,
                'message' => 'Chưa cấu hình Facebook Page ID hoặc Page Access Token.',
                'code' => null,
                'subcode' => null,
            ];
        }

        $identityUrl = sprintf(
            'https://graph.facebook.com/%s/me?%s',
            FACEBOOK_GRAPH_VERSION,
            http_build_query([
                'fields' => 'id,name',
                'access_token' => facebook_page_access_token(),
            ])
        );
        $identity = $this->graphGet($identityUrl);
        if (!$identity['ok']) {
            return $identity;
        }

        $tokenOwnerId = (string)($identity['data']['id'] ?? '');
        if ($tokenOwnerId !== '' && $tokenOwnerId !== facebook_page_id()) {
            return [
                'ok' => false,
                'message' => 'Token hiện tại là User Token/App Token, chưa phải Page Access Token. Cần lấy token Page từ: /me/accounts → Page Ép Phê → access_token, rồi dán access_token đó vào ô Facebook Page Access Token.',
                'token_owner_id' => $tokenOwnerId,
                'token_owner_name' => (string)($identity['data']['name'] ?? ''),
                'page_id' => facebook_page_id(),
                'code' => null,
                'subcode' => null,
            ];
        }

        $url = sprintf(
            'https://graph.facebook.com/%s/%s?%s',
            FACEBOOK_GRAPH_VERSION,
            rawurlencode(facebook_page_id()),
            http_build_query([
                'fields' => 'id,name,can_post',
                'access_token' => facebook_page_access_token(),
            ])
        );

        $pageCheck = $this->graphGet($url);
        if (!$pageCheck['ok']) {
            return $pageCheck;
        }
        $decoded = $pageCheck['data'];

        $canPost = (bool)($decoded['can_post'] ?? false);

        return [
            'ok' => $canPost,
            'message' => $canPost
                ? 'Token Facebook còn dùng được cho Page: ' . (string)($decoded['name'] ?? facebook_page_id())
                : 'Token đọc được Page nhưng chưa thấy quyền đăng bài. Cần lấy token Page từ: /me/accounts → Page Ép Phê → access_token.',
            'page_id' => (string)($decoded['id'] ?? facebook_page_id()),
            'page_name' => (string)($decoded['name'] ?? ''),
            'can_post' => $canPost,
            'code' => null,
            'subcode' => null,
        ];
    }

    private function graphGet(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => FACEBOOK_TIMEOUT_SECONDS,
        ]);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return [
                'ok' => false,
                'message' => 'Lỗi curl Facebook: ' . $curlError,
                'code' => null,
                'subcode' => null,
            ];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'message' => 'Không giải mã được response Facebook Graph API.',
                'code' => null,
                'subcode' => null,
            ];
        }

        if ($httpCode >= 400 || isset($decoded['error'])) {
            $error = is_array($decoded['error'] ?? null) ? $decoded['error'] : [];
            $message = (string)($error['message'] ?? ('HTTP ' . $httpCode));
            $code = (int)($error['code'] ?? 0);
            $subcode = (int)($error['error_subcode'] ?? 0);

            if ($code === 190 && $subcode === 463) {
                $message = 'Facebook Page Access Token đã hết hạn. Cần tạo/lưu token Page mới.';
            } elseif ($code === 190) {
                $message = 'Facebook Page Access Token không hợp lệ hoặc đã hết hạn: ' . $message;
            } elseif (str_contains(strtolower($message), 'publish_actions')) {
                $message = 'Token/App đang đụng quyền cũ publish_actions đã bị Facebook khai tử. Không cần quyền này. Cần lấy token Page từ: /me/accounts → Page Ép Phê → access_token, rồi dán access_token đó vào ô Facebook Page Access Token.';
            }

            return [
                'ok' => false,
                'message' => $message,
                'code' => $code ?: null,
                'subcode' => $subcode ?: null,
            ];
        }

        return [
            'ok' => true,
            'message' => 'Facebook Graph API OK.',
            'data' => $decoded,
            'code' => null,
            'subcode' => null,
        ];
    }

    public function publish(array $content, array $post): array
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException('FACEBOOK_PAGE_ID hoac FACEBOOK_PAGE_ACCESS_TOKEN chua duoc cau hinh.');
        }

        $message = trim(implode("\n\n", array_filter([
            (string)($content['title'] ?? ''),
            (string)($content['body'] ?? ''),
            (string)($content['call_to_action'] ?? ''),
            (string)($content['hashtags'] ?? ''),
        ])));

        [$endpoint, $fields] = $this->buildPublishRequest($content, $message);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_TIMEOUT => FACEBOOK_TIMEOUT_SECONDS,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Loi curl Facebook: ' . $curlError);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Khong giai ma duoc response Facebook Graph API.');
        }

        if ($httpCode >= 400) {
            throw new RuntimeException($this->formatGraphError($decoded, $httpCode));
        }

        return [
            'facebook_post_id' => (string)($decoded['id'] ?? ''),
            'response' => $decoded,
            'message' => 'Dang bai len Fanpage thanh cong.',
        ];
    }

    public function commentOnPost(string $remotePostId, string $message): array
    {
        $remotePostId = trim($remotePostId);
        $message = trim($message);
        if ($remotePostId === '' || $message === '') {
            throw new InvalidArgumentException('Thieu Facebook post ID hoac noi dung comment.');
        }

        $ch = curl_init(sprintf(
            'https://graph.facebook.com/%s/%s/comments',
            FACEBOOK_GRAPH_VERSION,
            rawurlencode($remotePostId)
        ));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'message' => $message,
                'access_token' => facebook_page_access_token(),
            ]),
            CURLOPT_TIMEOUT => FACEBOOK_TIMEOUT_SECONDS,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Loi curl Facebook khi comment: ' . $curlError);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Khong giai ma duoc response Facebook Graph API khi comment.');
        }

        if ($httpCode >= 400) {
            throw new RuntimeException($this->formatGraphError($decoded, $httpCode));
        }

        return [
            'comment_id' => (string)($decoded['id'] ?? ''),
            'response' => $decoded,
            'message' => 'Đã comment link affiliate vào đúng bài viết.',
        ];
    }

    private function buildPublishRequest(array $content, string $message): array
    {
        $mediaType = (string)($content['media_type'] ?? 'none');
        $mediaUrl = trim((string)($content['media_url'] ?? ''));

        if ($mediaType === 'image' && $mediaUrl !== '') {
            $localPath = $this->resolveLocalMediaPath($mediaUrl);
            if ($localPath !== null) {
                return [
                    $this->graphEndpoint('photos'),
                    [
                        'caption' => $message,
                        'source' => new CURLFile($localPath, mime_content_type($localPath) ?: 'image/png', basename($localPath)),
                        'access_token' => facebook_page_access_token(),
                    ],
                ];
            }

            return [
                $this->graphEndpoint('photos'),
                http_build_query([
                    'caption' => $message,
                    'url' => app_absolute_url($mediaUrl),
                    'access_token' => facebook_page_access_token(),
                ]),
            ];
        }

        if ($mediaType === 'video' && $mediaUrl !== '') {
            $localPath = $this->resolveLocalMediaPath($mediaUrl);
            if ($localPath !== null) {
                return [
                    $this->graphEndpoint('videos'),
                    [
                        'description' => $message,
                        'source' => new CURLFile($localPath, mime_content_type($localPath) ?: 'video/mp4', basename($localPath)),
                        'access_token' => facebook_page_access_token(),
                    ],
                ];
            }

            return [
                $this->graphEndpoint('videos'),
                http_build_query([
                    'description' => $message,
                    'file_url' => app_absolute_url($mediaUrl),
                    'access_token' => facebook_page_access_token(),
                ]),
            ];
        }

        return [
            $this->graphEndpoint('feed'),
            http_build_query([
                'message' => $message,
                'link' => $this->extractFirstUrl($message),
                'access_token' => facebook_page_access_token(),
            ]),
        ];
    }

    private function resolveLocalMediaPath(string $mediaUrl): ?string
    {
        $mediaUrl = trim($mediaUrl);
        if ($mediaUrl === '' || preg_match('#^https?://#i', $mediaUrl) === 1) {
            return null;
        }

        $path = realpath(BASE_PATH . '/backend/public/' . ltrim($mediaUrl, '/'));
        $publicRoot = realpath(BASE_PATH . '/backend/public');
        if ($path === false || $publicRoot === false || !str_starts_with($path, $publicRoot . DIRECTORY_SEPARATOR) || !is_file($path)) {
            return null;
        }

        return $path;
    }

    private function formatGraphError(array $decoded, int $httpCode): string
    {
        $error = is_array($decoded['error'] ?? null) ? $decoded['error'] : [];
        $message = (string)($error['message'] ?? ('HTTP ' . $httpCode));
        $code = (int)($error['code'] ?? 0);
        $subcode = (int)($error['error_subcode'] ?? 0);

        if ($code === 190 && $subcode === 463) {
            return 'Facebook Page Access Token đã hết hạn. Vào Settings cập nhật token Page mới rồi bấm đăng lại.';
        }
        if ($code === 190) {
            return 'Facebook Page Access Token không hợp lệ hoặc đã hết hạn: ' . $message;
        }
        if (str_contains(strtolower($message), 'publish_actions')) {
            return 'Facebook báo quyền cũ publish_actions đã bị khai tử. Hệ thống không cần quyền này. Token đang lưu phải là Page Access Token, lấy từ: /me/accounts → Page Ép Phê → access_token. Không dùng User Token/App Token. Chi tiết Facebook: ' . $message;
        }
        if (str_contains(strtolower($message), 'permission')) {
            return 'Facebook token thiếu quyền đăng bài Page/comment hoặc token chưa phải Page Access Token. Cần Page Access Token có pages_manage_posts/pages_read_engagement. Chi tiết Facebook: ' . $message;
        }

        return 'Facebook Graph API trả về lỗi: ' . $message;
    }

    private function graphEndpoint(string $edge): string
    {
        return sprintf(
            'https://graph.facebook.com/%s/%s/%s',
            FACEBOOK_GRAPH_VERSION,
            rawurlencode(facebook_page_id()),
            $edge
        );
    }

    private function extractFirstUrl(string $text): string
    {
        if (preg_match('#https?://\S+#', $text, $matches) === 1) {
            return rtrim($matches[0], '.,;)');
        }
        return '';
    }
}
