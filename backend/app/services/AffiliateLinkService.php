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
    private PDO $pdo;
    private string $fileName = 'affiliate_links.json';

    public function __construct()
    {
        $this->pdo = db_pdo();
        $this->storage = new DatabaseStorage();
        $this->productSyncService = new ProductSyncService();
        $this->taskLogService = new TaskLogService();
    }

    public function generateForProduct(int $productId, string $campaignCode = 'MVP-LAPTOP', string $manualAffiliateUrl = ''): array
    {
        $product = $this->productSyncService->findProductById($productId);
        if ($product === null) {
            throw new InvalidArgumentException('Khong tim thay san pham can tao affiliate link.');
        }

        $affiliateUrl = trim($manualAffiliateUrl);
        if ($affiliateUrl === '') {
            throw new InvalidArgumentException('Shopee không cho tạo link hoa hồng thật chỉ bằng affiliate_id trong web này. Boss hãy lấy link trong App Shopee: Tôi → Chương trình Tiếp thị liên kết → chọn sản phẩm → Chia sẻ để nhận hoa hồng → copy link shope.ee/... rồi dán vào ô Affiliate link.');
        }
        $this->assertValidAffiliateUrl($affiliateUrl, (string)($product['source_platform'] ?? ''));

        $linkRecord = $this->storage->mutate($this->fileName, function (array $links) use ($product, $affiliateUrl, $campaignCode): array {
            $linkRecord = [
                'id' => $this->storage->nextIdForRows($links),
                'site_id' => (int)($product['site_id'] ?? currentSiteId()),
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

            return [
                'rows' => $this->sortLinks($links),
                'result' => $linkRecord,
            ];
        });

        $this->syncProductLinkState((int)$product['id'], $affiliateUrl);

        $this->taskLogService->create('save_affiliate_link', 'success', [
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
            if (($product['status'] ?? 'new') !== 'new' || empty($product['affiliate_url'] ?? '')) {
                continue;
            }

            $generated[] = $this->generateForProduct((int)$product['id'], $campaignCode, (string)$product['affiliate_url']);
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
                $link['site_id'] = currentSiteId();
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

    public function findLinkByProductId(int $productId): ?array
    {
        foreach ($this->allLinks() as $link) {
            if ((int)($link['product_id'] ?? 0) === $productId) {
                return $link;
            }
        }
        return null;
    }

    public function summary(bool $global = false): array
    {
        $where = $global ? '' : 'WHERE site_id = :site_id';
        $stmt = $this->pdo->prepare(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = \'active\' THEN 1 ELSE 0 END) AS active_count,
                SUM(CASE WHEN status = \'expired\' THEN 1 ELSE 0 END) AS expired_count,
                SUM(CASE WHEN status = \'error\' THEN 1 ELSE 0 END) AS error_count
             FROM affiliate_links ' . $where
        );
        $params = [];
        if (!$global) {
            $params[':site_id'] = currentSiteId();
        }
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int)($row['total'] ?? 0),
            'active' => (int)($row['active_count'] ?? 0),
            'expired' => (int)($row['expired_count'] ?? 0),
            'error' => (int)($row['error_count'] ?? 0),
        ];
    }

    private function syncProductLinkState(int $productId, string $affiliateUrl): void
    {
        $changes = ['affiliate_url' => $affiliateUrl];
        $product = $this->productSyncService->findProductById($productId);
        if (($product['status'] ?? 'new') === 'new') {
            $changes['status'] = 'linked';
        }
        $this->productSyncService->updateProduct($productId, $changes);
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
        $this->storage->write($this->fileName, $this->sortLinks($links));
    }

    private function nextId(array $links): int
    {
        return $this->storage->nextIdForRows($links);
    }

    private function sortLinks(array $links): array
    {
        usort($links, static function (array $left, array $right): int {
            return strcmp((string)($right['updated_at'] ?? ''), (string)($left['updated_at'] ?? ''));
        });

        return $links;
    }

    private function assertValidAffiliateUrl(string $affiliateUrl, string $platform): void
    {
        if (!filter_var($affiliateUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Affiliate link không hợp lệ. Hãy dán link đầy đủ bắt đầu bằng https://');
        }

        $host = strtolower((string)parse_url($affiliateUrl, PHP_URL_HOST));
        if ($platform === 'shopee') {
            $allowed = $host === 'shope.ee' || str_ends_with($host, '.shope.ee') || $host === 's.shopee.vn' || $host === 'shopee.vn' || str_ends_with($host, '.shopee.vn');
            if (!$allowed) {
                throw new InvalidArgumentException('Link Shopee Affiliate nên là link rút gọn shope.ee/... lấy từ App Shopee hoặc link tracking chính thức của Shopee.');
            }
        }
    }

    private function buildTrackingCode(array $product, string $campaignCode): string
    {
        $parts = [
            strtolower($campaignCode),
            (string)($product['source_platform'] ?? 'unknown'),
            (string)($product['source_product_id'] ?? $product['id'] ?? 'unknown'),
        ];
        $trackingCode = strtolower(implode('-', $parts));
        $trackingCode = preg_replace('/[^a-z0-9_-]+/', '-', $trackingCode) ?: 'mmo-tracking';
        return trim($trackingCode, '-');
    }
}
