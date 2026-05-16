<?php

declare(strict_types=1);

final class GeminiContentProvider
{
    public function isAvailable(): bool
    {
        return OPENAI_BASE_URL !== '';
    }

    public function generate(array $product): array
    {
        if (!OPENAI_BASE_URL) {
            throw new RuntimeException('OPENAI_BASE_URL (9router) chua duoc cau hinh.');
        }

        $promptService = new PromptTemplateService();
        $userMsg = $promptService->renderForProduct('content_text', $product)
            ?? $this->buildPrompt($product);

        $model = 'gemini/' . str_replace('gemini/', '', gemini_model());

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Ban la copywriter affiliate tieng Viet. Tra loi CHI json hop le, khong markdown, voi cac key: title, body, hashtags, call_to_action. Body 120-220 tu, 4-8 hashtag, CTA ngan.",
                ],
                [
                    'role' => 'user',
                    'content' => $userMsg,
                ],
            ],
            'temperature' => 0.8,
            'max_tokens' => 2048,
            'stream' => false,
        ];

        $url = rtrim(OPENAI_BASE_URL, '/') . '/chat/completions';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . (OPENAI_API_KEY ?: 'sk-local-9router'),
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => GEMINI_TIMEOUT_SECONDS,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Loi curl 9router: ' . $curlError);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Khong giai ma duoc response tu 9router.');
        }

        if ($httpCode >= 400) {
            $message = $decoded['error']['message'] ?? ($decoded['message'] ?? ('HTTP ' . $httpCode));
            throw new RuntimeException('9router tra ve loi: ' . $message);
        }

        $content = $decoded['choices'][0]['message']['content'] ?? '';
        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException('9router khong tra ve noi dung hop le.');
        }

        $structured = $this->decodeJsonText($content);
        if (!is_array($structured)) {
            throw new RuntimeException('9router khong tra ve JSON hop le: ' . substr($content, 0, 200));
        }

        return [
            'title' => trim((string)($structured['title'] ?? '')),
            'body' => trim((string)($structured['body'] ?? '')),
            'hashtags' => trim((string)($structured['hashtags'] ?? '')),
            'call_to_action' => trim((string)($structured['call_to_action'] ?? '')),
            'notes' => 'Sinh boi Gemini qua 9router (' . gemini_model() . ')',
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
            'Ban la copywriter affiliate tieng Viet. Hay viet content tu dong theo san pham de dang Fanpage.',
            'Chi tra ve JSON hop le, khong markdown, voi cac key: title, body, hashtags, call_to_action.',
            '',
            'Thong tin san pham:',
            '- Ten: ' . ($product['product_name'] ?? ''),
            '- Gia: ' . $price . ' VND',
            '- Luot mua/ban: ' . $soldCount,
            '- Nen tang: ' . ($product['source_platform'] ?? ''),
            '- Link affiliate: ' . ($product['affiliate_url'] ?? ''),
            '- Ghi chu: ' . ($product['notes'] ?? ''),
            '',
            'Yeu cau noi dung:',
            '- Tieu de 1 dong hap dan, khong phong dai qua muc',
            '- Body 120-220 tu, giong tu nhien, ro loi ich, co nhac gia neu co',
            '- 4-8 hashtag lien quan',
            '- CTA ngan, thuc day bam link xem chi tiet',
            '- Khong bia thong so ky thuat neu du lieu san pham khong co',
        ]);
    }
}