<?php

declare(strict_types=1);

require_once __DIR__ . '/DatabaseStorage.php';
require_once __DIR__ . '/ProductSyncService.php';
require_once __DIR__ . '/TaskLogService.php';

final class AffiliateLinkService
{
    private DatabaseStorage $storage;
    private ProductSyncService $productSyncService;
    private TaskLogService $taskLogService;
    private string $fileName = 'affiliate_links.json';

    public function __construct()
    {
        $this->storage = new DatabaseStorage();
        $this->productSyncService = new ProductSyncService();
        $this->taskLogService = new TaskLogService();
    }

    public function generateForProduct(int $productId, string $campaignCode = 'MVP-LAPTOP'): array
    {
        $product = $this->productSyncService->findProductById($productId);
        if ($product === null) {
            throw new InvalidArgumentException('Khong tim thay san pham can tao affiliate link.');
        }

        $links = $this->allLinks();
        $linkId = $this->nextId($links);
        $trackingCode = $this->buildTrackingCode($product, $campaignCode);
        $separator = str_contains((string)$product['product_url'], '?') ? '&' : '?';
        $affiliateUrl = (string)$product['product_url'] . $separator . 'aff=' . rawurlencode($trackingCode);

        $linkRecord = [
            'id' => $linkId,
            'site_id' => (int)($product['site_id'] ?? APP_SITE_ID),
            'product_id' => (int)$product['id'],
            'source_platform' => (string)$product['source_platform'],
            'original_url' => (string)$product['product_url'],
            'affiliate_url' => $affiliateUrl,
            'campaign_code' => $campaignCode,
            'status' => 'active',
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];

        $links = $this->upsertLink($links, $linkRecord);
        $this->saveLinks($links);
        $this->syncProductLinkState((int)$product['id'], $affiliateUrl);

        $this->taskLogService->create('generate_affiliate_link', 'success', [
            'product_id' => (int)$product['id'],
            'campaign_code' => $campaignCode,
        ], [
            'affiliate_url' => $affiliateUrl,
        ]);

        return $linkRecord;
    }

    public function generateForEligibleProducts(string $campaignCode = 'MVP-LAPTOP', int $limit = 10): array
    {
        $products = $this->productSyncService->allProducts();
        $generated = [];

        foreach ($products as $product) {
            if (($product['status'] ?? 'new') !== 'new' || !empty($product['affiliate_url'] ?? '')) {
                continue;
            }

            $generated[] = $this->generateForProduct((int)$product['id'], $campaignCode);
            if (count($generated) >= $limit) {
                break;
            }
        }

        return [
            'count' => count($generated),
            'links' => $generated,
        ];
    }

    public function allLinks(): array
    {
        $links = $this->storage->read($this->fileName);
        foreach ($links as &$link) {
            if (!isset($link['site_id'])) {
                $link['site_id'] = APP_SITE_ID;
            }
        }
        unset($link);
        usort($links, static function (array $left, array $right): int {
            return strcmp((string)($right['updated_at'] ?? ''), (string)($left['updated_at'] ?? ''));
        });
        return $links;
    }

    public function recentLinks(int $limit = 10): array
    {
        return array_slice($this->allLinks(), 0, $limit);
    }

    public function summary(): array
    {
        $links = $this->allLinks();
        return [
            'total' => count($links),
            'active' => count(array_filter($links, static fn(array $link): bool => ($link['status'] ?? '') === 'active')),
            'expired' => count(array_filter($links, static fn(array $link): bool => ($link['status'] ?? '') === 'expired')),
            'error' => count(array_filter($links, static fn(array $link): bool => ($link['status'] ?? '') === 'error')),
        ];
    }

    private function syncProductLinkState(int $productId, string $affiliateUrl): void
    {
        $products = $this->productSyncService->allProducts();
        foreach ($products as &$product) {
            if ((int)($product['id'] ?? 0) !== $productId) {
                continue;
            }
            $product['affiliate_url'] = $affiliateUrl;
            if (($product['status'] ?? 'new') === 'new') {
                $product['status'] = 'linked';
            }
            $product['updated_at'] = date('c');
        }
        unset($product);

        $this->productSyncService->saveProducts($products);
    }

    private function upsertLink(array $links, array $linkRecord): array
    {
        foreach ($links as $index => $link) {
            if ((int)($link['product_id'] ?? 0) === (int)$linkRecord['product_id']) {
                $linkRecord['id'] = (int)$link['id'];
                $linkRecord['created_at'] = (string)$link['created_at'];
                $links[$index] = $linkRecord;
                return $links;
            }
        }

        array_unshift($links, $linkRecord);
        return $links;
    }

    private function saveLinks(array $links): void
    {
        usort($links, static function (array $left, array $right): int {
            return strcmp((string)($right['updated_at'] ?? ''), (string)($left['updated_at'] ?? ''));
        });
        $this->storage->write($this->fileName, $links);
    }

    private function nextId(array $links): int
    {
        $ids = array_map(static function (array $link): int {
            return (int)($link['id'] ?? 0);
        }, $links);
        return empty($ids) ? 1 : (max($ids) + 1);
    }

    private function buildTrackingCode(array $product, string $campaignCode): string
    {
        return strtolower($campaignCode) . '-' . $product['source_platform'] . '-' . $product['source_product_id'];
    }
}
