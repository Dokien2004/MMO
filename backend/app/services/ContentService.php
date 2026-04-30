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
                $content['site_id'] = APP_SITE_ID;
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

        return [
            'id' => $contentId,
            'site_id' => (int)($product['site_id'] ?? APP_SITE_ID),
            'product_id' => (int)$product['id'],
            'affiliate_link_id' => null,
            'title' => $providerPayload['title'],
            'body' => $providerPayload['body'],
            'hashtags' => $providerPayload['hashtags'],
            'call_to_action' => $providerPayload['call_to_action'],
            'ai_provider' => $providerPayload['provider_used'],
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
            foreach (['gemini', 'openai'] as $aiProvider) {
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
                : $this->openAIProvider->generate($product);

            return [
                'title' => $result['title'] !== '' ? $result['title'] : ('Review nhanh: ' . $product['product_name']),
                'body' => $result['body'] !== '' ? $result['body'] : $this->buildTemplateBody($product),
                'hashtags' => $result['hashtags'] !== '' ? $result['hashtags'] : $this->buildTemplateHashtags($product),
                'call_to_action' => $result['call_to_action'] !== '' ? $result['call_to_action'] : 'Nhấn vào link để xem chi tiết và giá mới nhất.',
                'notes' => $result['notes'] ?? ('Sinh boi ' . $provider . ' API'),
                'provider_used' => $provider,
            ];
        } catch (Throwable $throwable) {
            $this->taskLogService->create('ai_content_provider_failed', 'failed', [
                'product_id' => (int)($product['id'] ?? 0),
                'provider' => $provider,
            ], [], $throwable->getMessage());
            return null;
        }
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
        return implode("\n\n", [
            $product['product_name'] . ' dang la mot lua chon de test MVP affiliate theo dot.',
            'Gia tham khao hien tai la ' . $price . ' VND, phu hop de tao bai review ngan va de doc.',
            'Diem noi bat: gon nhe, de gioi thieu, de gan vao bai dang Fanpage thu cong.',
            'Xem san pham tai day: ' . $product['affiliate_url'],
        ]);
    }

    private function buildTemplateHashtags(array $product): string
    {
        return '#affiliate #mvp #reviewnhanh #' . preg_replace('/[^a-z0-9]+/i', '', strtolower((string)$product['source_platform']));
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
        $ids = array_map(static function (array $content): int {
            return (int)($content['id'] ?? 0);
        }, $contents);
        return empty($ids) ? 1 : (max($ids) + 1);
    }
}
