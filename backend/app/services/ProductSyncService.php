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
                $item['site_id'] = currentSiteId();
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
                $record['affiliate_url'] = trim((string)($product['affiliate_url'] ?? '')) !== ''
                    ? trim((string)$product['affiliate_url'])
                    : ($indexed[$key]['affiliate_url'] ?? '');
                $record['content_status'] = $indexed[$key]['content_status'] ?? 'none';
                if (($record['affiliate_url'] ?? '') !== '' && ($record['status'] ?? 'new') === 'new') {
                    $record['status'] = 'linked';
                }
                if (!isset($product['sold_count']) && !isset($product['order_count']) && !isset($product['sales_count'])) {
                    $record['sold_count'] = (int)($indexed[$key]['sold_count'] ?? 0);
                }
                $updated++;
            } else {
                $record['id'] = $this->nextId($indexed);
                $record['created_at'] = date('c');
                $record['affiliate_url'] = trim((string)($record['affiliate_url'] ?? $product['affiliate_url'] ?? ''));
                if ($record['affiliate_url'] !== '' && ($record['status'] ?? 'new') === 'new') {
                    $record['status'] = 'linked';
                }
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
                $product['site_id'] = currentSiteId();
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

    public function createManualProduct(array $input): array
    {
        $platform = $this->sanitizePlatform((string)($input['source_platform'] ?? 'manual'));
        $result = $this->syncBatch($platform, [[
            'source_product_id' => trim((string)($input['source_product_id'] ?? '')),
            'product_name' => trim((string)($input['product_name'] ?? '')),
            'product_url' => trim((string)($input['product_url'] ?? '')),
            'price' => (float)($input['price'] ?? 0),
            'sold_count' => (int)($input['sold_count'] ?? 0),
            'affiliate_url' => trim((string)($input['affiliate_url'] ?? '')),
            'status' => trim((string)($input['status'] ?? 'new')),
            'notes' => trim((string)($input['notes'] ?? '')),
        ]]);

        return $result['products'][0] ?? [];
    }

    public function importProductsFromFile(string $tmpPath, string $originalName, string $platform = 'manual'): array
    {
        if (!is_file($tmpPath)) {
            throw new InvalidArgumentException('Không tìm thấy file import.');
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (in_array($extension, ['csv', 'txt'], true)) {
            $rows = $this->parseCsvFile($tmpPath);
        } elseif ($extension === 'xlsx') {
            $rows = $this->parseXlsxFile($tmpPath);
        } else {
            throw new InvalidArgumentException('Chỉ hỗ trợ import CSV hoặc XLSX.');
        }

        if (empty($rows)) {
            throw new InvalidArgumentException('File import không có dòng sản phẩm hợp lệ.');
        }

        return $this->syncBatch($this->sanitizePlatform($platform), $rows);
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

    private function parseCsvFile(string $tmpPath): array
    {
        $handle = fopen($tmpPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Không mở được file CSV.');
        }

        $headers = null;
        $rows = [];
        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            if ($headers === null) {
                $headers = $this->normalizeHeaders($data);
                continue;
            }
            if ($this->isEmptyImportRow($data)) {
                continue;
            }
            $rows[] = $this->mapImportRow($headers, $data);
        }
        fclose($handle);

        return $rows;
    }

    private function parseXlsxFile(string $tmpPath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($tmpPath) !== true) {
            throw new RuntimeException('Không mở được file XLSX.');
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $xml = simplexml_load_string($sharedXml);
            if ($xml !== false) {
                foreach ($xml->si as $si) {
                    $parts = [];
                    if (isset($si->t)) {
                        $parts[] = (string)$si->t;
                    }
                    foreach ($si->r ?? [] as $run) {
                        $parts[] = (string)($run->t ?? '');
                    }
                    $sharedStrings[] = implode('', $parts);
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXml === false) {
            throw new RuntimeException('File XLSX chưa có sheet1 để import.');
        }

        $xml = simplexml_load_string($sheetXml);
        if ($xml === false) {
            throw new RuntimeException('Không đọc được sheet XLSX.');
        }

        $headers = null;
        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $cell) {
                $ref = (string)($cell['r'] ?? 'A1');
                $index = $this->columnIndexFromCellRef($ref);
                $type = (string)($cell['t'] ?? '');
                $value = (string)($cell->v ?? '');
                if ($type === 's') {
                    $value = $sharedStrings[(int)$value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string)($cell->is->t ?? '');
                }
                $cells[$index] = $value;
            }
            if (empty($cells)) {
                continue;
            }
            ksort($cells);
            $max = max(array_keys($cells));
            $data = [];
            for ($i = 0; $i <= $max; $i++) {
                $data[] = trim((string)($cells[$i] ?? ''));
            }

            if ($headers === null) {
                $headers = $this->normalizeHeaders($data);
                continue;
            }
            if ($this->isEmptyImportRow($data)) {
                continue;
            }
            $rows[] = $this->mapImportRow($headers, $data);
        }

        return $rows;
    }

    private function normalizeHeaders(array $headers): array
    {
        $map = [];
        foreach ($headers as $index => $header) {
            $normalized = strtolower(trim((string)$header));
            $normalized = str_replace([' ', '-', '.', '/', 'đ'], ['_', '_', '_', '_', 'd'], $normalized);
            $aliases = [
                'ten_san_pham' => 'product_name',
                'tên_sản_phẩm' => 'product_name',
                'name' => 'product_name',
                'product_name' => 'product_name',
                'link_goc' => 'product_url',
                'link_gốc' => 'product_url',
                'url' => 'product_url',
                'product_url' => 'product_url',
                'gia' => 'price',
                'giá' => 'price',
                'price' => 'price',
                'luot_ban' => 'sold_count',
                'lượt_bán' => 'sold_count',
                'sold' => 'sold_count',
                'sold_count' => 'sold_count',
                'source_id' => 'source_product_id',
                'source_product_id' => 'source_product_id',
                'ma_sp' => 'source_product_id',
                'affiliate_url' => 'affiliate_url',
                'link_aff' => 'affiliate_url',
                'ghi_chu' => 'notes',
                'notes' => 'notes',
            ];
            if (isset($aliases[$normalized])) {
                $map[$index] = $aliases[$normalized];
            }
        }
        return $map;
    }

    private function mapImportRow(array $headers, array $data): array
    {
        $row = [];
        foreach ($headers as $index => $key) {
            $row[$key] = trim((string)($data[$index] ?? ''));
        }
        return $row;
    }

    private function isEmptyImportRow(array $data): bool
    {
        foreach ($data as $value) {
            if (trim((string)$value) !== '') {
                return false;
            }
        }
        return true;
    }

    private function columnIndexFromCellRef(string $ref): int
    {
        preg_match('/^[A-Z]+/i', $ref, $matches);
        $letters = strtoupper($matches[0] ?? 'A');
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }
        return max(0, $index - 1);
    }

    private function normalizeProduct(string $platform, array $product): array
    {
        $productName = trim((string)($product['product_name'] ?? $product['name'] ?? ''));
        $productUrl = trim((string)($product['product_url'] ?? $product['url'] ?? ''));
        $sourceProductId = trim((string)($product['source_product_id'] ?? $product['source_id'] ?? $product['id'] ?? ''));
        if ($sourceProductId === '' && $productUrl !== '') {
            $sourceProductId = substr(sha1($productUrl), 0, 16);
        }

        if ($sourceProductId === '' || $productName === '' || $productUrl === '') {
            throw new InvalidArgumentException('Mỗi sản phẩm cần có tên sản phẩm và link gốc. Mã sản phẩm có thể để trống, hệ thống sẽ tự tạo từ link.');
        }

        $affiliateUrl = trim((string)($product['affiliate_url'] ?? ''));
        if ($affiliateUrl === '' && $platform === 'shopee') {
            $affiliateUrl = $this->buildShopeeAffiliateUrl($productUrl, $sourceProductId);
        }
        $status = $this->sanitizeStatus((string)($product['status'] ?? 'new'));
        if ($affiliateUrl !== '' && $status === 'new') {
            $status = 'linked';
        }

        return [
            'site_id' => currentSiteId(),
            'source_platform' => $platform,
            'source_product_id' => $sourceProductId,
            'product_name' => $productName,
            'product_url' => $productUrl,
            'price' => $this->parseNumericValue($product['price'] ?? 0),
            'sold_count' => max(0, (int)$this->parseNumericValue($product['sold_count'] ?? $product['order_count'] ?? $product['sales_count'] ?? 0)),
            'affiliate_url' => $affiliateUrl,
            'status' => $status,
            'notes' => trim((string)($product['notes'] ?? '')),
        ];
    }

    private function buildShopeeAffiliateUrl(string $productUrl, string $sourceProductId): string
    {
        $affiliateId = trim(shopee_affiliate_id());
        if ($affiliateId === '' || $productUrl === '') {
            return '';
        }

        $subId = strtolower('mmo-' . currentSiteId() . '-' . $sourceProductId);
        $subId = preg_replace('/[^a-z0-9_-]+/', '-', $subId) ?: 'mmo-shopee';

        return 'https://s.shopee.vn/an_redir?origin_link='
            . rawurlencode($productUrl)
            . '&affiliate_id=' . rawurlencode($affiliateId)
            . '&sub_id=' . rawurlencode(trim($subId, '-'));
    }

    private function parseNumericValue(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float)$value;
        }
        $text = trim((string)$value);
        if ($text === '') {
            return 0.0;
        }
        $text = str_replace(["\xc2\xa0", '₫', 'đ', 'Đ', ' '], '', $text);
        if (str_contains($text, ',') && str_contains($text, '.')) {
            $text = str_replace('.', '', $text);
            $text = str_replace(',', '.', $text);
        } elseif (substr_count($text, '.') > 1) {
            $text = str_replace('.', '', $text);
        } elseif (substr_count($text, ',') > 0) {
            $text = str_replace(',', '.', $text);
        }
        $text = preg_replace('/[^0-9.\-]/', '', $text) ?? '0';
        return is_numeric($text) ? (float)$text : 0.0;
    }

    private function sanitizePlatform(string $platform): string
    {
        $allowed = ['affiliate_api', 'shopee', 'tiktokshop', 'lazada', 'tiki', 'manual'];
        return in_array($platform, $allowed, true) ? $platform : 'affiliate_api';
    }

    private function sanitizeStatus(string $status): string
    {
        $allowed = ['new', 'linked', 'content_ready', 'posted', 'archived'];
        return in_array($status, $allowed, true) ? $status : 'new';
    }

    private function nextId(array $indexed): int
    {
        $max = 0;
        foreach ($indexed as $record) {
            $max = max($max, (int)($record['id'] ?? 0));
        }
        return $max + 1;
    }
}
