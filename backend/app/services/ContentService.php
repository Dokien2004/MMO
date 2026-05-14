<?php

declare(strict_types=1);

require_once __DIR__ . '/DatabaseStorage.php';
require_once __DIR__ . '/ProductSyncService.php';
require_once __DIR__ . '/AffiliateLinkService.php';
require_once __DIR__ . '/TaskLogService.php';
require_once __DIR__ . '/OpenAIContentProvider.php';
require_once __DIR__ . '/GeminiContentProvider.php';

final class ContentService
{
    private DatabaseStorage $storage;
    private ProductSyncService $productSyncService;
    private AffiliateLinkService $affiliateLinkService;
    private TaskLogService $taskLogService;
    private OpenAIContentProvider $openAIProvider;
    private GeminiContentProvider $geminiProvider;
    private string $fileName = 'generated_contents.json';

    public function __construct()
    {
        $this->storage = new DatabaseStorage();
        $this->productSyncService = new ProductSyncService();
        $this->affiliateLinkService = new AffiliateLinkService();
        $this->taskLogService = new TaskLogService();
        $this->openAIProvider = new OpenAIContentProvider();
        $this->geminiProvider = new GeminiContentProvider();
    }

    public function generateDraftForProduct(int $productId, string $provider = 'template_engine'): array
    {
        $product = $this->productSyncService->findProductById($productId);
        if ($product === null) {
            throw new InvalidArgumentException('Khong tim thay san pham de sinh noi dung.');
        }

        if (empty($product['affiliate_url'] ?? '')) {
            throw new InvalidArgumentException('San pham chua co affiliate link.');
        }

        $contentList = $this->allContents();
        $existing = $this->findByProductId($productId);
        $record = $this->buildDraftRecord($product, $provider, $existing['id'] ?? $this->nextId($contentList));

        $contentList = $this->upsertContent($contentList, $record);
        $this->saveContents($contentList);
        $this->productSyncService->updateProduct($productId, [
            'status' => 'content_ready',
            'content_status' => 'draft',
        ]);

        $this->taskLogService->create('generate_content_draft', 'success', [
            'product_id' => $productId,
            'provider' => $provider,
        ], [
            'content_id' => $record['id'],
            'title' => $record['title'],
            'provider_used' => $record['ai_provider'],
        ]);

        return $record;
    }

    public function approveContent(int $contentId): array
    {
        return $this->changeStatus($contentId, 'approved');
    }

    public function rejectContent(int $contentId): array
    {
        return $this->changeStatus($contentId, 'rejected');
    }

    public function markUsed(int $contentId): array
    {
        return $this->changeStatus($contentId, 'used');
    }

    public function generateForEligibleProducts(int $limit = 10, string $provider = 'template_engine'): array
    {
        $products = $this->productSyncService->allProducts();
        $generated = [];

        foreach ($products as $product) {
            if (($product['status'] ?? '') !== 'linked' || empty($product['affiliate_url'] ?? '')) {
                continue;
            }

            $generated[] = $this->generateDraftForProduct((int)$product['id'], $provider);
            if (count($generated) >= $limit) {
                break;
            }
        }

        return [
            'count' => count($generated),
            'contents' => $generated,
        ];
    }

    public function allContents(): array
    {
        $contents = $this->storage->read($this->fileName);
        foreach ($contents as &$content) {
            if (!isset($content['site_id'])) {
                $content['site_id'] = currentSiteId();
            }
        }
        unset($content);
        usort($contents, static function (array $left, array $right): int {
            return strcmp((string)($right['updated_at'] ?? ''), (string)($left['updated_at'] ?? ''));
        });
        return $contents;
    }

    public function recentContents(int $limit = 10): array
    {
        return array_slice($this->allContents(), 0, $limit);
    }

    public function findById(int $contentId): ?array
    {
        foreach ($this->allContents() as $content) {
            if ((int)($content['id'] ?? 0) === $contentId) {
                return $content;
            }
        }
        return null;
    }

    public function summary(): array
    {
        $contents = $this->allContents();
        return [
            'total' => count($contents),
            'draft' => count(array_filter($contents, static fn(array $row): bool => ($row['status'] ?? '') === 'draft')),
            'approved' => count(array_filter($contents, static fn(array $row): bool => ($row['status'] ?? '') === 'approved')),
            'rejected' => count(array_filter($contents, static fn(array $row): bool => ($row['status'] ?? '') === 'rejected')),
            'used' => count(array_filter($contents, static fn(array $row): bool => ($row['status'] ?? '') === 'used')),
        ];
    }

    private function buildDraftRecord(array $product, string $provider, int $contentId): array
    {
        $providerPayload = $this->resolveProviderPayload($product, $provider);
        $affiliateLink = $this->affiliateLinkService->findLinkByProductId((int)$product['id']);

        return [
            'id' => $contentId,
            'site_id' => (int)($product['site_id'] ?? currentSiteId()),
            'product_id' => (int)$product['id'],
            'affiliate_link_id' => $affiliateLink !== null ? (int)$affiliateLink['id'] : null,
            'title' => $providerPayload['title'],
            'body' => $providerPayload['body'],
            'hashtags' => $providerPayload['hashtags'],
            'call_to_action' => $providerPayload['call_to_action'],
            'ai_provider' => $providerPayload['provider_used'],
            'media_type' => 'none',
            'media_url' => '',
            'media_prompt' => $this->buildMediaPrompt($product, $providerPayload),
            'media_status' => 'pending',
            'status' => 'draft',
            'notes' => $providerPayload['notes'],
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];
    }

    private function resolveProviderPayload(array $product, string $provider): array
    {
        $normalized = strtolower(trim($provider));
        if ($normalized === 'auto') {
            foreach (['openai', 'gemini'] as $aiProvider) {
                $payload = $this->tryAiProvider($product, $aiProvider);
                if ($payload !== null) {
                    return $payload;
                }
            }
            return $this->templatePayload($product, 'Fallback ve template_engine do chua co AI provider kha dung.');
        }

        if (in_array($normalized, ['gemini', 'openai'], true)) {
            $payload = $this->tryAiProvider($product, $normalized);
            if ($payload !== null) {
                return $payload;
            }
            return $this->templatePayload($product, 'Fallback ve template_engine do ' . strtoupper($normalized) . ' loi hoac chua cau hinh.');
        }

        return $this->templatePayload($product, 'Sinh boi template noi bo. Co the thay bang API AI that sau.');
    }

    private function tryAiProvider(array $product, string $provider): ?array
    {
        try {
            $result = $provider === 'gemini'
                ? $this->geminiProvider->generate($product)
                : $this->generateWithOpenAiFallback($product);

            return [
                'title' => $result['title'] !== '' ? $result['title'] : ('Review nhanh: ' . $product['product_name']),
                'body' => $result['body'] !== '' ? $result['body'] : $this->buildTemplateBody($product),
                'hashtags' => $result['hashtags'] !== '' ? $result['hashtags'] : $this->buildTemplateHashtags($product),
                'call_to_action' => $result['call_to_action'] !== '' ? $result['call_to_action'] : 'Nhấn vào link để xem chi tiết và giá mới nhất.',
                'notes' => $result['notes'] ?? ('Sinh boi ' . $provider . ' API'),
                'provider_used' => $provider === 'openai' ? (string)($result['provider_used'] ?? 'openai') : $provider,
            ];
        } catch (Throwable $throwable) {
            $this->taskLogService->create('ai_content_provider_failed', 'failed', [
                'product_id' => (int)($product['id'] ?? 0),
                'provider' => $provider,
            ], [], $throwable->getMessage());
            return null;
        }
    }

    private function generateWithOpenAiFallback(array $product): array
    {
        $models = array_values(array_unique(array_filter([
            openai_model(),
            ...openai_fallback_models(),
        ], static fn(string $model): bool => trim($model) !== '')));

        $lastError = null;
        foreach ($models as $index => $model) {
            try {
                $provider = new OpenAIContentProvider($model, openai_base_url(), openai_api_key());
                $result = $provider->generate($product);
                $result['provider_used'] = 'openai:' . $model;

                if ($index > 0) {
                    $this->taskLogService->create('ai_content_provider_fallback_success', 'success', [
                        'product_id' => (int)($product['id'] ?? 0),
                        'model' => $model,
                    ]);
                }

                return $result;
            } catch (Throwable $throwable) {
                $lastError = $throwable;
                $next = $models[$index + 1] ?? '';
                $this->taskLogService->create('ai_content_provider_fallback', 'warning', [
                    'product_id' => (int)($product['id'] ?? 0),
                    'from_model' => $model,
                    'to_model' => $next,
                ], [], $throwable->getMessage());

                if ($next !== '') {
                    continue;
                }
            }
        }

        throw $lastError ?? new RuntimeException('Khong co OpenAI-compatible model kha dung.');
    }

    private function templatePayload(array $product, string $notes): array
    {
        return [
            'title' => 'Review nhanh: ' . $product['product_name'],
            'body' => $this->buildTemplateBody($product),
            'hashtags' => $this->buildTemplateHashtags($product),
            'call_to_action' => 'Nhấn vào link để xem chi tiết và giá mới nhất.',
            'notes' => $notes,
            'provider_used' => 'template_engine',
        ];
    }

    private function buildTemplateBody(array $product): string
    {
        $price = number_format((float)($product['price'] ?? 0), 0, ',', '.');
        $soldCount = number_format((int)($product['sold_count'] ?? 0), 0, ',', '.');
        $soldLine = (int)($product['sold_count'] ?? 0) > 0
            ? 'Sản phẩm đang có khoảng ' . $soldCount . ' lượt bán, phù hợp để ưu tiên làm nội dung affiliate vì đã có tín hiệu nhu cầu tốt.'
            : 'Sản phẩm phù hợp để làm bài giới thiệu ngắn, dễ đọc và dễ gắn vào Fanpage.';

        return implode("\n\n", [
            $product['product_name'] . ' là một lựa chọn đáng chú ý để đưa vào danh sách gợi ý mua sắm hôm nay.',
            'Giá tham khảo hiện tại khoảng ' . $price . ' VND. Boss nên kiểm tra lại giá tại thời điểm đăng vì sàn có thể thay đổi theo khuyến mãi.',
            $soldLine,
            'Nếu bạn đang tìm một sản phẩm dễ chia sẻ, có link mua rõ ràng và phù hợp để đăng bài review ngắn, đây là lựa chọn nên thử.',
            'Xem chi tiết tại đây: ' . $product['affiliate_url'],
        ]);
    }

    private function buildTemplateHashtags(array $product): string
    {
        return '#affiliate #mvp #reviewnhanh #' . preg_replace('/[^a-z0-9]+/i', '', strtolower((string)$product['source_platform']));
    }

    private function buildMediaPrompt(array $product, array $providerPayload): string
    {
        $price = number_format((float)($product['price'] ?? 0), 0, ',', '.');
        return implode(' ', [
            'Ảnh quảng cáo affiliate vuông 1:1, phong cách hiện đại, sạch, phù hợp đăng Facebook.',
            'Sản phẩm chính: ' . (string)($product['product_name'] ?? ''),
            'Giá tham khảo: ' . $price . ' VND.',
            'Bố cục: sản phẩm nổi bật ở trung tâm, nền sáng, điểm nhấn màu cam Shopee, có khoảng trống cho tiêu đề ngắn.',
            'Không dùng logo thương hiệu nếu không chắc bản quyền, không bịa thông số, không chèn chữ quá nhiều.',
            'Ý tưởng tiêu đề: ' . (string)($providerPayload['title'] ?? ''),
        ]);
    }

    public function attachMedia(int $contentId, string $mediaType, string $mediaUrl, string $mediaPrompt = '', string $mediaStatus = 'ready'): array
    {
        $contents = $this->allContents();
        foreach ($contents as &$content) {
            if ((int)($content['id'] ?? 0) !== $contentId) {
                continue;
            }
            $content['media_type'] = $mediaType;
            $content['media_url'] = $mediaUrl;
            if ($mediaPrompt !== '') {
                $content['media_prompt'] = $mediaPrompt;
            }
            $content['media_status'] = $mediaStatus;
            $content['updated_at'] = date('c');
            $this->saveContents($contents);
            return $content;
        }
        unset($content);

        throw new InvalidArgumentException('Khong tim thay content can gan media.');
    }

    private function changeStatus(int $contentId, string $status): array
    {
        $contents = $this->allContents();
        foreach ($contents as &$content) {
            if ((int)($content['id'] ?? 0) !== $contentId) {
                continue;
            }
            $content['status'] = $status;
            $content['updated_at'] = date('c');
            $this->saveContents($contents);
            $this->syncProductContentState((int)$content['product_id'], $status);
            $this->taskLogService->create('content_status_change', 'success', [
                'content_id' => $contentId,
                'status' => $status,
            ], [
                'product_id' => (int)$content['product_id'],
            ]);
            return $content;
        }
        unset($content);

        throw new InvalidArgumentException('Khong tim thay content can cap nhat trang thai.');
    }

    private function syncProductContentState(int $productId, string $contentStatus): void
    {
        $changes = ['content_status' => $contentStatus];
        if ($contentStatus === 'approved') {
            $changes['status'] = 'content_ready';
        } elseif ($contentStatus === 'rejected') {
            $changes['status'] = 'linked';
        } elseif ($contentStatus === 'used') {
            $changes['status'] = 'posted';
        }
        $this->productSyncService->updateProduct($productId, $changes);
    }

    private function findByProductId(int $productId): ?array
    {
        foreach ($this->allContents() as $content) {
            if ((int)($content['product_id'] ?? 0) === $productId) {
                return $content;
            }
        }
        return null;
    }

    private function upsertContent(array $contents, array $record): array
    {
        foreach ($contents as $index => $content) {
            if ((int)($content['product_id'] ?? 0) === (int)$record['product_id']) {
                $record['id'] = (int)$content['id'];
                $record['created_at'] = (string)$content['created_at'];
                $contents[$index] = $record;
                return $contents;
            }
        }

        array_unshift($contents, $record);
        return $contents;
    }

    private function saveContents(array $contents): void
    {
        usort($contents, static function (array $left, array $right): int {
            return strcmp((string)($right['updated_at'] ?? ''), (string)($left['updated_at'] ?? ''));
        });
        $this->storage->write($this->fileName, $contents);
    }

    private function nextId(array $contents): int
    {
        return $this->storage->nextId($this->fileName);
    }
}
