<?php

declare(strict_types=1);

final class OpenAIContentProvider
{
    private ?string $modelOverride;
    private ?string $baseUrlOverride;
    private ?string $apiKeyOverride;

    public function __construct(?string $model = null, ?string $baseUrl = null, ?string $apiKey = null)
    {
        $this->modelOverride = $model !== null && trim($model) !== '' ? trim($model) : null;
        $this->baseUrlOverride = $baseUrl !== null && trim($baseUrl) !== '' ? rtrim(trim($baseUrl), '/') : null;
        $this->apiKeyOverride = $apiKey;
    }

    public function isAvailable(): bool
    {
        return $this->apiKey() !== '' || $this->baseUrl() !== 'https://api.openai.com/v1';
    }

    public function generate(array $product): array
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException('OPENAI_API_KEY chua duoc cau hinh.');
        }

        $promptService = new PromptTemplateService();
        $systemMsg = $promptService->systemPromptFor('content_text')
            ?? 'Ban la copywriter affiliate. Luon tra ve JSON hop le voi cac key: title, body, hashtags, call_to_action.';
        $userMsg = $promptService->renderForProduct('content_text', $product)
            ?? $this->buildPrompt($product);

        $payload = [
            'model' => $this->model(),
            'temperature' => 0.8,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemMsg,
                ],
                [
                    'role' => 'user',
                    'content' => $userMsg,
                ],
            ],
        ];

        $ch = curl_init($this->baseUrl() . '/chat/completions');
        $headers = ['Content-Type: application/json'];
        if ($this->apiKey() !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey();
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => openai_timeout_seconds(),
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

        $structured = $this->decodeJsonText($content);
        if (!is_array($structured)) {
            throw new RuntimeException('OpenAI khong tra ve JSON hop le.');
        }

        return [
            'title' => trim((string)($structured['title'] ?? '')),
            'body' => trim((string)($structured['body'] ?? '')),
            'hashtags' => $this->stringValue($structured['hashtags'] ?? ''),
            'call_to_action' => trim((string)($structured['call_to_action'] ?? '')),
            'notes' => 'Sinh boi OpenAI-compatible API (' . $this->model() . ')',
        ];
    }

    private function stringValue(mixed $value): string
    {
        if (is_array($value)) {
            return trim(implode(' ', array_map(static fn(mixed $item): string => trim((string)$item), $value)));
        }
        return trim((string)$value);
    }

    private function model(): string
    {
        return $this->modelOverride ?? openai_model();
    }

    private function baseUrl(): string
    {
        return $this->baseUrlOverride ?? openai_base_url();
    }

    private function apiKey(): string
    {
        return $this->apiKeyOverride ?? openai_api_key();
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

    private function decodeJsonText(string $content): ?array
    {
        $content = trim($content);
        $content = preg_replace('/^```(?:json)?\s*/i', '', $content) ?? $content;
        $content = preg_replace('/\s*```$/', '', $content) ?? $content;

        $decoded = json_decode(trim($content), true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $candidate = substr($content, $start, $end - $start + 1);
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
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
