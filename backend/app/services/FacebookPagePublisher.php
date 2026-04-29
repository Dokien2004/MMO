<?php

declare(strict_types=1);

final class FacebookPagePublisher
{
    public function isAvailable(): bool
    {
        return facebook_page_id() !== '' && facebook_page_access_token() !== '';
    }

    public function publish(array $content, array $post): array
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException('FACEBOOK_PAGE_ID hoac FACEBOOK_PAGE_ACCESS_TOKEN chua duoc cau hinh.');
        }

        $endpoint = sprintf(
            'https://graph.facebook.com/%s/%s/feed',
            FACEBOOK_GRAPH_VERSION,
            rawurlencode(facebook_page_id())
        );

        $message = trim(implode("\n\n", array_filter([
            (string)($content['title'] ?? ''),
            (string)($content['body'] ?? ''),
            (string)($content['call_to_action'] ?? ''),
            (string)($content['hashtags'] ?? ''),
        ])));

        $fields = http_build_query([
            'message' => $message,
            'link' => $content['body'] ?? '',
            'access_token' => facebook_page_access_token(),
        ]);

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
            $message = $decoded['error']['message'] ?? ('HTTP ' . $httpCode);
            throw new RuntimeException('Facebook Graph API tra ve loi: ' . $message);
        }

        return [
            'facebook_post_id' => (string)($decoded['id'] ?? ''),
            'response' => $decoded,
            'message' => 'Dang bai len Fanpage thanh cong.',
        ];
    }
}
