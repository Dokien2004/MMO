<?php

declare(strict_types=1);

final class ImageMediaService
{
    private ContentService $contentService;
    private TaskLogService $taskLogService;
    private string $lastModelUsed = '';

    public function __construct()
    {
        $this->contentService = new ContentService();
        $this->taskLogService = new TaskLogService();
    }

    public function generateForContent(int $contentId): array
    {
        $content = $this->contentService->findById($contentId);
        if ($content === null) {
            throw new InvalidArgumentException('Khong tim thay content de tao anh.');
        }

        $prompt = trim((string)($content['media_prompt'] ?? ''));
        if ($prompt === '') {
            $prompt = $this->fallbackPrompt($content);
        }

        $imageBinary = $this->requestImage($prompt, $content);
        $relativeUrl = $this->saveImage($contentId, $imageBinary);
        $updated = $this->contentService->attachImage($contentId, $relativeUrl, $prompt, 'ready');

        $this->taskLogService->create('generate_content_image', 'success', [
            'content_id' => $contentId,
            'model' => $this->lastModelUsed !== '' ? $this->lastModelUsed : image_model(),
        ], [
            'media_url' => $relativeUrl,
        ]);

        return $updated;
    }

    public function generateForPendingContents(int $limit = 5, string $platform = 'general'): array
    {
        $limit = max(1, min(20, $limit));
        $contents = $this->contentService->allContents();
        $generated = [];
        $errors = [];

        foreach ($contents as $content) {
            if (count($generated) >= $limit) {
                break;
            }
            if (!empty($content['image_url'] ?? '') && !empty($content['image_status'] ?? '') && $content['image_status'] !== 'failed') {
                continue;
            }
            if ($platform !== 'general' && ($content['platform'] ?? 'general') !== $platform) {
                continue;
            }

            $contentId = (int)($content['id'] ?? 0);
            if ($contentId <= 0) {
                continue;
            }

            try {
                $generated[] = $this->generateForContent($contentId);
            } catch (Throwable $throwable) {
                $errors[] = [
                    'content_id' => $contentId,
                    'error' => $throwable->getMessage(),
                ];
            }
        }

        return [
            'count' => count($generated),
            'generated' => $generated,
            'errors' => $errors,
        ];
    }

    private function requestImage(string $prompt, array $content = []): string
    {
        if (image_provider() === 'meigen') {
            // Try to get a better Meigen prompt from gallery
            $enriched = $this->enrichPromptWithMeigen($prompt, $content);
            try {
                $this->lastModelUsed = 'meigen:' . meigen_model();
                return $this->requestMeiGenImage($enriched);
            } catch (RuntimeException $exception) {
                $this->taskLogService->create('generate_content_image_fallback', 'warning', [
                    'from_model' => 'meigen:' . meigen_model(),
                    'to_model' => image_model(),
                ], [], $exception->getMessage());
            }
        }

        return $this->requestDirectImage($prompt);
    }

    /**
     * Enrich image prompt using Meigen gallery prompts.
     * Uses platform and product info from content to find the best Meigen prompt.
     */
    private function enrichPromptWithMeigen(string $basePrompt, array $content): string
    {
        try {
            $meigenSvc = new MeigenPromptService();

            $platform = $content['platform'] ?? $content['channel_type'] ?? 'facebook';
            $productId = (int)($content['product_id'] ?? 0);

            // Get product info for better prompt matching
            $productName = '';
            if ($productId > 0) {
                $productService = new ProductSyncService();
                $product = $productService->findProductById($productId);
                if ($product !== null) {
                    $productName = $product['name'] ?? $product['product_name'] ?? '';
                }
            }

            if ($productName === '') {
                $productName = $this->extractProductFromPrompt($basePrompt);
            }

            $productData = [
                'product_name' => $productName,
                'price' => $content['price'] ?? '',
            ];

            $meigenPrompt = $meigenSvc->bestPromptFor($platform, $productData);

            if ($meigenPrompt !== null && mb_strlen($meigenPrompt) > 30) {
                $this->taskLogService->create('meigen_prompt_enriched', 'info', [
                    'content_id' => $content['id'] ?? 0,
                    'platform' => $platform,
                    'product' => $productName,
                    'original_len' => mb_strlen($basePrompt),
                    'enriched_len' => mb_strlen($meigenPrompt),
                ]);
                return $meigenPrompt;
            }
        } catch (Throwable $e) {
            // Silently fall back to original prompt
        }

        return $basePrompt;
    }

    private function detectPlatform(string $prompt): string
    {
        $lower = mb_strtolower($prompt);
        if (strpos($lower, 'facebook') !== false) return 'facebook';
        if (strpos($lower, 'tiktok') !== false) return 'tiktok';
        if (strpos($lower, 'instagram') !== false) return 'instagram';
        if (strpos($lower, 'threads') !== false) return 'threads';
        return 'facebook';
    }

    private function extractProductFromPrompt(string $prompt): string
    {
        // Try to extract product name from common patterns
        if (preg_match('/(?:sản phẩm|product)[:\s]+([^\n,\.]+)/ui', $prompt, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/(?:ten|name)[:\s]+([^\n,\.]+)/ui', $prompt, $m)) {
            return trim($m[1]);
        }
        // Return original if can't extract
        return mb_substr($prompt, 0, 50);
    }

    private function requestDirectImage(string $prompt): string
    {
        $baseUrl = rtrim(image_base_url(), '/');
        $models = array_values(array_unique(array_filter([
            image_model(),
            ...image_fallback_models(),
        ], static fn(string $model): bool => trim($model) !== '')));

        $lastError = null;
        foreach ($models as $index => $model) {
            try {
                $this->lastModelUsed = $model;
                return $this->requestImageWithModel($prompt, $baseUrl, $model);
            } catch (RuntimeException $exception) {
                $lastError = $exception;
                if ($index < count($models) - 1 && $this->shouldFallbackToNextImageModel($exception)) {
                    $this->taskLogService->create('generate_content_image_fallback', 'warning', [
                        'from_model' => $model,
                        'to_model' => $models[$index + 1] ?? '',
                    ], [], $exception->getMessage());
                    continue;
                }

                throw $exception;
            }
        }

        throw $lastError ?? new RuntimeException('Khong co model tao anh AI kha dung.');
    }

    private function requestMeiGenImage(string $prompt): string
    {
        $token = meigen_api_token();
        if ($token === '') {
            return $this->requestMeiGenOpenAICompatibleImage($prompt);
        }

        $payload = [
            'prompt' => $prompt,
            'modelId' => meigen_model(),
            'aspectRatio' => meigen_aspect_ratio(),
        ];
        if (meigen_resolution() !== '') {
            $payload['resolution'] = meigen_resolution();
        }
        if (meigen_quality() !== '') {
            $payload['quality'] = meigen_quality();
        }

        $created = $this->postMeiGenJson(meigen_base_url() . '/generate/v2', $payload, $token);
        $generationId = (string)($created['generationId'] ?? '');
        if ($generationId === '') {
            throw new RuntimeException('MeiGen API khong tra ve generationId.');
        }

        $deadline = time() + image_timeout_seconds();
        $statusUrl = meigen_base_url() . '/generate/v2/status/' . rawurlencode($generationId);
        do {
            sleep(3);
            $status = $this->getMeiGenJson($statusUrl, $token);
            $state = strtolower((string)($status['status'] ?? ''));
            if ($state === 'completed') {
                $url = $this->findImageUrl($status);
                if ($url === null) {
                    throw new RuntimeException('MeiGen hoan tat nhung khong tra ve imageUrl/imageUrls.');
                }
                return $this->binaryFromUrl($url);
            }
            if ($state === 'failed') {
                throw new RuntimeException('MeiGen tao anh that bai: ' . (string)($status['error'] ?? 'unknown error'));
            }
        } while (time() < $deadline);

        throw new RuntimeException('MeiGen tao anh qua thoi gian cho phep.');
    }

    private function requestMeiGenOpenAICompatibleImage(string $prompt): string
    {
        $apiKey = image_openai_api_key();
        if ($apiKey === '' && $this->isLocalRouter(image_base_url())) {
            $apiKey = 'sk-local-9router';
        }
        if ($apiKey === '') {
            throw new RuntimeException('Chua cau hinh API key cho MeiGen OpenAI-compatible provider. Neu dung 9router local, dat IMAGE_BASE_URL ve http://127.0.0.1:20128/v1.');
        }

        $models = array_values(array_unique(array_filter([
            image_model(),
            ...image_fallback_models(),
        ], static fn(string $model): bool => trim($model) !== '')));

        $lastError = null;
        foreach ($models as $index => $model) {
            try {
                $this->lastModelUsed = 'meigen-openai:' . $model;

                $payload = [
                    'model' => $model,
                    'prompt' => $prompt,
                    'size' => image_size(),
                    'n' => 1,
                ];

                $headers = [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                ];

                $ch = curl_init(rtrim(image_base_url(), '/') . '/images/generations');
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    CURLOPT_TIMEOUT => image_timeout_seconds(),
                ]);

                $response = $this->decodeHttpJson($ch, 'MeiGen OpenAI-compatible provider');
                return $this->extractImageBinary($response);
            } catch (RuntimeException $exception) {
                $lastError = $exception;
                if ($index < count($models) - 1 && $this->shouldFallbackToNextImageModel($exception)) {
                    $this->taskLogService->create('generate_content_image_fallback', 'warning', [
                        'from_model' => 'meigen-openai:' . $model,
                        'to_model' => 'meigen-openai:' . ($models[$index + 1] ?? ''),
                    ], [], $exception->getMessage());
                    continue;
                }

                throw $exception;
            }
        }

        throw $lastError ?? new RuntimeException('Khong co MeiGen OpenAI-compatible model tao anh kha dung.');
    }

    private function requestImageWithModel(string $prompt, string $baseUrl, string $model): string
    {
        $lowerModel = strtolower($model);

        if (str_contains($lowerModel, 'gemini/')) {
            return $this->requestChatImage($prompt, $baseUrl, $model);
        }

        return $this->requestOpenAIImage($prompt, $baseUrl, $model);
    }

    private function shouldFallbackToNextImageModel(RuntimeException $exception): bool
    {
        if (count(image_fallback_models()) === 0) {
            return false;
        }

        $message = strtolower($exception->getMessage());
        return str_contains($message, 'http 429')
            || str_contains($message, 'http 404')
            || str_contains($message, 'http 500')
            || str_contains($message, 'http 502')
            || str_contains($message, 'http 503')
            || str_contains($message, 'bad gateway')
            || str_contains($message, 'reset after')
            || str_contains($message, 'invalid response')
            || str_contains($message, 'quota')
            || str_contains($message, 'resource_exhausted')
            || str_contains($message, 'rate limit')
            || str_contains($message, 'unsupported')
            || str_contains($message, 'khong hop le')
            || str_contains($message, 'khong tra ve du lieu anh');
    }

    private function requestChatImage(string $prompt, string $baseUrl, string $model): string
    {
        $payload = [
            'model' => $model,
            'messages' => [[
                'role' => 'user',
                'content' => $prompt . "\n\nHãy tạo ảnh đúng yêu cầu. Trả về ảnh, không chỉ mô tả bằng chữ.",
            ]],
            'max_tokens' => 2000,
        ];

        $response = $this->postJson($baseUrl . '/chat/completions', $payload);
        return $this->extractImageBinary($response);
    }

    private function requestOpenAIImage(string $prompt, string $baseUrl, string $model): string
    {
        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'size' => image_size(),
            'n' => 1,
        ];
        if (str_starts_with(strtolower($model), 'dall-e')) {
            $payload['response_format'] = 'b64_json';
        }

        $response = $this->postJson($baseUrl . '/images/generations', $payload);
        return $this->extractImageBinary($response);
    }

    private function postJson(string $endpoint, array $payload): array
    {
        $headers = ['Content-Type: application/json'];
        $apiKey = image_openai_api_key();
        if ($apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        } elseif (!$this->isLocalRouter($endpoint)) {
            throw new InvalidArgumentException('Chua cau hinh IMAGE_OPENAI_API_KEY hoac OPENAI_API_KEY de tao anh AI. Neu dung 9router local, hay dat IMAGE_BASE_URL ve http://127.0.0.1:20128/v1.');
        }

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => image_timeout_seconds(),
        ]);

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $raw === '') {
            throw new RuntimeException('Loi curl tao anh AI: ' . ($error !== '' ? $error : 'empty response'));
        }

        $data = json_decode((string)$raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('API tao anh AI tra ve JSON khong hop le: ' . mb_substr((string)$raw, 0, 300));
        }

        if ($status < 200 || $status >= 300) {
            $message = (string)($data['error']['message'] ?? $data['message'] ?? $raw);
            throw new RuntimeException('API tao anh AI loi HTTP ' . $status . ': ' . $message);
        }

        return $data;
    }

    private function postMeiGenJson(string $endpoint, array $payload, string $token): array
    {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 45,
        ]);

        return $this->decodeHttpJson($ch, 'MeiGen API');
    }

    private function getMeiGenJson(string $endpoint, string $token): array
    {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        return $this->decodeHttpJson($ch, 'MeiGen API');
    }

    private function decodeHttpJson(CurlHandle $ch, string $label): array
    {
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $raw === '') {
            throw new RuntimeException($label . ' loi curl: ' . ($error !== '' ? $error : 'empty response'));
        }

        $data = json_decode((string)$raw, true);
        if (!is_array($data)) {
            throw new RuntimeException($label . ' tra ve JSON khong hop le: ' . mb_substr((string)$raw, 0, 300));
        }

        if ($status < 200 || $status >= 300) {
            $errorValue = $data['error'] ?? null;
            $message = is_array($errorValue)
                ? (string)($errorValue['message'] ?? json_encode($errorValue, JSON_UNESCAPED_UNICODE))
                : (string)($errorValue ?? $data['message'] ?? $raw);
            throw new RuntimeException($label . ' loi HTTP ' . $status . ': ' . $message);
        }

        return $data;
    }

    private function extractImageBinary(array $data): string
    {
        $url = $this->findImageUrl($data);
        if ($url !== null) {
            return $this->binaryFromUrl($url);
        }

        $b64 = $this->findBase64Image($data);
        if ($b64 !== null) {
            $binary = base64_decode($b64, true);
            if ($binary !== false && $binary !== '') {
                return $binary;
            }
        }

        throw new RuntimeException('API tao anh AI khong tra ve du lieu anh. Co the provider chi tra text hoac het quota.');
    }

    private function findImageUrl(mixed $value): ?string
    {
        if (is_string($value)) {
            if (preg_match('#^data:image/[^;]+;base64,#', $value) === 1 || preg_match('#^https?://#i', $value) === 1) {
                return $value;
            }
            return null;
        }
        if (!is_array($value)) {
            return null;
        }
        if (isset($value['url']) && is_string($value['url'])) {
            return $value['url'];
        }
        if (isset($value['image_url'])) {
            if (is_string($value['image_url'])) {
                return $value['image_url'];
            }
            if (is_array($value['image_url']) && isset($value['image_url']['url']) && is_string($value['image_url']['url'])) {
                return $value['image_url']['url'];
            }
        }
        foreach ($value as $child) {
            $found = $this->findImageUrl($child);
            if ($found !== null) {
                return $found;
            }
        }
        return null;
    }

    private function findBase64Image(mixed $value): ?string
    {
        if (is_array($value)) {
            foreach (['b64_json', 'base64', 'data'] as $key) {
                if (isset($value[$key]) && is_string($value[$key]) && strlen($value[$key]) > 500) {
                    return preg_replace('#^data:image/[^;]+;base64,#', '', $value[$key]);
                }
            }
            foreach ($value as $child) {
                $found = $this->findBase64Image($child);
                if ($found !== null) {
                    return $found;
                }
            }
        }
        return null;
    }

    private function binaryFromUrl(string $url): string
    {
        if (preg_match('#^data:image/[^;]+;base64,(.+)$#', $url, $matches) === 1) {
            $binary = base64_decode($matches[1], true);
            if ($binary !== false && $binary !== '') {
                return $binary;
            }
            throw new RuntimeException('Khong decode duoc data URL anh AI.');
        }

        $binary = @file_get_contents($url);
        if ($binary === false || $binary === '') {
            throw new RuntimeException('Khong tai duoc anh AI tu URL provider.');
        }
        return (string)$binary;
    }

    private function isLocalRouter(string $endpoint): bool
    {
        $host = parse_url($endpoint, PHP_URL_HOST);
        return in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
    }

    private function saveImage(int $contentId, string $binary): string
    {
        $uploadDir = BASE_PATH . '/backend/public/uploads/ai';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = 'content-' . $contentId . '-' . date('Ymd-His') . '.png';
        $path = $uploadDir . '/' . $fileName;
        file_put_contents($path, $binary, LOCK_EX);
        chmod($path, 0644);

        return '/uploads/ai/' . $fileName;
    }

    private function fallbackPrompt(array $content): string
    {
        $promptService = new PromptTemplateService();
        $rendered = $promptService->renderForProduct('image_fallback', [], $content);
        if ($rendered !== null) {
            return $rendered;
        }

        // Fallback hardcode khi chưa có template trong DB
        return implode(' ', [
            'Ảnh quảng cáo affiliate vuông 1:1, phong cách hiện đại, sạch, phù hợp đăng Facebook.',
            'Chủ đề nội dung: ' . (string)($content['title'] ?? 'Sản phẩm affiliate'),
            'Không dùng logo thương hiệu nếu không chắc bản quyền, không bịa thông số, không chèn quá nhiều chữ.',
        ]);
    }
}
