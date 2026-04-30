<?php

declare(strict_types=1);

final class GeminiContentProvider
{
    public function isAvailable(): bool
    {
        return gemini_api_key() !== '';
    }

    public function generate(array $product): array
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException('GEMINI_API_KEY chua duoc cau hinh.');
        }

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $this->buildPrompt($product)],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.8,
                'responseMimeType' => 'application/json',
            ],
        ];

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode(gemini_model()) . ':generateContent?key=' . rawurlencode(gemini_api_key());
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => GEMINI_TIMEOUT_SECONDS,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Loi curl Gemini: ' . $curlError);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Khong giai ma duoc response Gemini.');
        }

        if ($httpCode >= 400) {
            $message = $decoded['error']['message'] ?? ('HTTP ' . $httpCode);
            throw new RuntimeException('Gemini tra ve loi: ' . $message);
        }

        $content = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException('Gemini khong tra ve noi dung hop le.');
        }

        $structured = $this->decodeJsonText($content);
        if (!is_array($structured)) {
            throw new RuntimeException('Gemini khong tra ve JSON hop le.');
        }

        return [
            'title' => trim((string)($structured['title'] ?? '')),
            'body' => trim((string)($structured['body'] ?? '')),
            'hashtags' => trim((string)($structured['hashtags'] ?? '')),
            'call_to_action' => trim((string)($structured['call_to_action'] ?? '')),
            'notes' => 'Sinh boi Gemini API (' . gemini_model() . ')',
        ];
    }

    private function decodeJsonText(string $content): ?array
    {
        $content = trim($content);
        $content = preg_replace('/^```(?:json)?\s*/i', '', $content) ?? $content;
        $content = preg_replace('/\s*```$/', '', $content) ?? $content;
        $decoded = json_decode(trim($content), true);
        return is_array($decoded) ? $decoded : null;
    }

    private function buildPrompt(array $product): string
    {
        $price = number_format((float)($product['price'] ?? 0), 0, ',', '.');
        $soldCount = number_format((int)($product['sold_count'] ?? 0));

        return implode("\n", [
            'Bạn là copywriter affiliate tiếng Việt. Hãy viết content tự động theo sản phẩm để đăng Fanpage.',
            'Chỉ trả về JSON hợp lệ, không markdown, với các key: title, body, hashtags, call_to_action.',
            '',
            'Thông tin sản phẩm:',
            '- Tên: ' . ($product['product_name'] ?? ''),
            '- Giá: ' . $price . ' VND',
            '- Lượt mua/bán: ' . $soldCount,
            '- Nền tảng: ' . ($product['source_platform'] ?? ''),
            '- Link affiliate: ' . ($product['affiliate_url'] ?? ''),
            '- Ghi chú: ' . ($product['notes'] ?? ''),
            '',
            'Yêu cầu nội dung:',
            '- Tiêu đề 1 dòng hấp dẫn, không phóng đại quá mức',
            '- Body 120-220 từ, giọng tự nhiên, rõ lợi ích, có nhắc giá nếu có',
            '- 4-8 hashtag liên quan',
            '- CTA ngắn, thúc đẩy bấm link xem chi tiết',
            '- Không bịa thông số kỹ thuật nếu dữ liệu sản phẩm không có',
        ]);
    }
}
