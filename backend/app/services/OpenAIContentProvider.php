<?php

declare(strict_types=1);

final class OpenAIContentProvider
{
    public function isAvailable(): bool
    {
        return openai_api_key() !== '' || openai_base_url() !== 'https://api.openai.com/v1';
    }

    public function generate(array $product): array
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException('OPENAI_API_KEY chua duoc cau hinh.');
        }

        $payload = [
            'model' => openai_model(),
            'temperature' => 0.8,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Ban la copywriter affiliate. Luon tra ve JSON hop le voi cac key: title, body, hashtags, call_to_action.'
                ],
                [
                    'role' => 'user',
                    'content' => $this->buildPrompt($product),
                ],
            ],
        ];

        $ch = curl_init(openai_base_url() . '/chat/completions');
        $headers = ['Content-Type: application/json'];
        if (openai_api_key() !== '') {
            $headers[] = 'Authorization: Bearer ' . openai_api_key();
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => OPENAI_TIMEOUT_SECONDS,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Loi curl OpenAI: ' . $curlError);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            $content = $this->decodeEventStreamContent((string)$response);
            if ($httpCode >= 400 || $content === '') {
                throw new RuntimeException('Khong giai ma duoc response OpenAI-compatible. HTTP ' . $httpCode);
            }
        } else {
            if ($httpCode >= 400) {
                $message = $decoded['error']['message'] ?? ('HTTP ' . $httpCode);
                throw new RuntimeException('OpenAI-compatible tra ve loi: ' . $message);
            }

            $content = $decoded['choices'][0]['message']['content'] ?? '';
        }

        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException('OpenAI-compatible khong tra ve noi dung hop le.');
        }

        $structured = json_decode($content, true);
        if (!is_array($structured)) {
            throw new RuntimeException('OpenAI khong tra ve JSON hop le.');
        }

        return [
            'title' => trim((string)($structured['title'] ?? '')),
            'body' => trim((string)($structured['body'] ?? '')),
            'hashtags' => trim((string)($structured['hashtags'] ?? '')),
            'call_to_action' => trim((string)($structured['call_to_action'] ?? '')),
            'notes' => 'Sinh boi OpenAI API',
        ];
    }


    private function decodeEventStreamContent(string $response): string
    {
        $content = '';
        foreach (preg_split('/\R/', $response) ?: [] as $line) {
            $line = trim($line);
            if (!str_starts_with($line, 'data:')) {
                continue;
            }
            $payload = trim(substr($line, 5));
            if ($payload === '' || $payload === '[DONE]') {
                continue;
            }
            $chunk = json_decode($payload, true);
            if (!is_array($chunk)) {
                continue;
            }
            $delta = $chunk['choices'][0]['delta']['content'] ?? '';
            if (is_string($delta)) {
                $content .= $delta;
            }
            $message = $chunk['choices'][0]['message']['content'] ?? '';
            if (is_string($message)) {
                $content .= $message;
            }
        }
        return trim($content);
    }

    private function buildPrompt(array $product): string
    {
        $price = number_format((float)($product['price'] ?? 0), 0, ',', '.');

        return implode("\n", [
            'Viet noi dung affiliate bang tieng Viet tu nhien, gon, de post Fanpage.',
            'Thong tin san pham:',
            '- Ten: ' . ($product['product_name'] ?? ''),
            '- Gia: ' . $price . ' VND',
            '- Nen tang: ' . ($product['source_platform'] ?? ''),
            '- Link affiliate: ' . ($product['affiliate_url'] ?? ''),
            '',
            'Yeu cau:',
            '- Tieu de 1 dong hap dan',
            '- Body 120-220 tu',
            '- 4-8 hashtag',
            '- 1 CTA ngan',
            '- Khong dung markdown',
            '- Tra ve JSON voi key: title, body, hashtags, call_to_action',
        ]);
    }
}
