<?php

declare(strict_types=1);

require_once __DIR__ . '/DatabaseStorage.php';
require_once __DIR__ . '/TaskLogService.php';

final class ProductSyncService
{
    private DatabaseStorage $storage;
    private TaskLogService $taskLogService;
    private string $fileName = 'products.json';

    public function __construct()
    {
        $this->storage = new DatabaseStorage();
        $this->taskLogService = new TaskLogService();
    }

    public function syncBatch(string $platform, array $products): array
    {
        $normalizedPlatform = $this->sanitizePlatform($platform);
        $existing = $this->allProducts();
        $indexed = [];

        foreach ($existing as $item) {
            if (!isset($item['site_id'])) {
                $item['site_id'] = APP_SITE_ID;
            }
            if (!isset($item['sold_count'])) {
                $item['sold_count'] = 0;
            }
            $indexed[$item['source_platform'] . '::' . $item['source_product_id']] = $item;
        }

        $inserted = 0;
        $updated = 0;
        $processed = [];

        foreach ($products as $product) {
            $record = $this->normalizeProduct($normalizedPlatform, $product);
            $key = $record['source_platform'] . '::' . $record['source_product_id'];
            if (isset($indexed[$key])) {
                $record['id'] = $indexed[$key]['id'];
                $record['created_at'] = $indexed[$key]['created_at'];
                $record['affiliate_url'] = $indexed[$key]['affiliate_url'] ?? '';
                $record['content_status'] = $indexed[$key]['content_status'] ?? 'none';
                if (!isset($product['sold_count']) && !isset($product['order_count']) && !isset($product['sales_count'])) {
                    $record['sold_count'] = (int)($indexed[$key]['sold_count'] ?? 0);
                }
                $updated++;
            } else {
                $record['id'] = $this->nextId($indexed);
                $record['created_at'] = date('c');
                $record['affiliate_url'] = '';
                $record['content_status'] = 'none';
                $inserted++;
            }

            $record['updated_at'] = date('c');
            $indexed[$key] = $record;
            $processed[] = $record;
        }

        $this->saveProducts(array_values($indexed));
        $summary = [
            'platform' => $normalizedPlatform,
            'received_count' => count($products),
            'inserted_count' => $inserted,
            'updated_count' => $updated,
            'stored_total' => count($indexed),
        ];

        $this->taskLogService->create('manual_product_sync', 'success', ['platform' => $normalizedPlatform], $summary);

        return [
            'summary' => $summary,
            'products' => $processed,
        ];
    }

    public function allProducts(): array
    {
        $products = $this->storage->read($this->fileName);
        foreach ($products as &$product) {
            if (!isset($product['site_id'])) {
                $product['site_id'] = APP_SITE_ID;
            }
            if (!isset($product['sold_count'])) {
                $product['sold_count'] = 0;
            }
        }
        unset($product);
        usort($products, static function (array $left, array $right): int {
            return strcmp((string)($right['updated_at'] ?? ''), (string)($left['updated_at'] ?? ''));
        });
        return $products;
    }

    public function saveProducts(array $products): void
    {
        usort($products, static function (array $left, array $right): int {
            return strcmp((string)($right['updated_at'] ?? ''), (string)($left['updated_at'] ?? ''));
        });
        $this->storage->write($this->fileName, $products);
    }

    public function findProductById(int $productId): ?array
    {
        foreach ($this->allProducts() as $product) {
            if ((int)($product['id'] ?? 0) === $productId) {
                return $product;
            }
        }
        return null;
    }

    public function updateProduct(int $productId, array $changes): void
    {
        $products = $this->allProducts();
        foreach ($products as &$product) {
            if ((int)($product['id'] ?? 0) !== $productId) {
                continue;
            }
            foreach ($changes as $key => $value) {
                $product[$key] = $value;
            }
            $product['updated_at'] = date('c');
        }
        unset($product);
        $this->saveProducts($products);
    }

    public function recentProducts(int $limit = 10): array
    {
        return array_slice($this->allProducts(), 0, $limit);
    }

    public function dashboardSummary(): array
    {
        $products = $this->allProducts();
        $statusSummary = [
            'total' => count($products),
            'new' => 0,
            'linked' => 0,
            'content_ready' => 0,
            'posted' => 0,
            'archived' => 0,
            'high_demand' => 0,
            'max_sold_count' => 0,
        ];

        foreach ($products as $product) {
            $status = $product['status'] ?? 'new';
            if (isset($statusSummary[$status])) {
                $statusSummary[$status]++;
            }

            $soldCount = (int)($product['sold_count'] ?? 0);
            if ($soldCount >= 50) {
                $statusSummary['high_demand']++;
            }
            if ($soldCount > $statusSummary['max_sold_count']) {
                $statusSummary['max_sold_count'] = $soldCount;
            }
        }

        return $statusSummary;
    }

    public function topSellingProducts(int $limit = 5, int $minSoldCount = 0): array
    {
        $products = array_filter($this->allProducts(), static function (array $product) use ($minSoldCount): bool {
            return (int)($product['sold_count'] ?? 0) >= $minSoldCount;
        });

        usort($products, static function (array $left, array $right): int {
            $soldCompare = (int)($right['sold_count'] ?? 0) <=> (int)($left['sold_count'] ?? 0);
            if ($soldCompare !== 0) {
                return $soldCompare;
            }

            return strcmp((string)($right['updated_at'] ?? ''), (string)($left['updated_at'] ?? ''));
        });

        return array_slice($products, 0, $limit);
    }

    private function normalizeProduct(string $platform, array $product): array
    {
        $sourceProductId = trim((string)($product['source_product_id'] ?? $product['id'] ?? ''));
        $productName = trim((string)($product['product_name'] ?? $product['name'] ?? ''));
        $productUrl = trim((string)($product['product_url'] ?? $product['url'] ?? ''));

        if ($sourceProductId === '' || $productName === '' || $productUrl === '') {
            throw new InvalidArgumentException('Moi san pham can co source_product_id, product_name va product_url.');
        }

        return [
            'site_id' => APP_SITE_ID,
            'source_platform' => $platform,
            'source_product_id' => $sourceProductId,
            'product_name' => $productName,
            'product_url' => $productUrl,
            'price' => (float)($product['price'] ?? 0),
            'sold_count' => max(0, (int)($product['sold_count'] ?? $product['order_count'] ?? $product['sales_count'] ?? 0)),
            'status' => $this->sanitizeStatus((string)($product['status'] ?? 'new')),
            'notes' => trim((string)($product['notes'] ?? '')),
        ];
    }

    private function sanitizePlatform(string $platform): string
    {
        $allowed = ['affiliate_api', 'shopee', 'tiktokshop', 'lazada', 'manual'];
        return in_array($platform, $allowed, true) ? $platform : 'affiliate_api';
    }

    private function sanitizeStatus(string $status): string
    {
        $allowed = ['new', 'linked', 'content_ready', 'posted', 'archived'];
        return in_array($status, $allowed, true) ? $status : 'new';
    }

    private function nextId(array $indexed): int
    {
        $ids = array_map(static function (array $product): int {
            return (int)($product['id'] ?? 0);
        }, $indexed);

        return empty($ids) ? 1 : (max($ids) + 1);
    }
}
