<?php

declare(strict_types=1);

require_once __DIR__ . '/ProductSyncService.php';
require_once __DIR__ . '/TaskLogService.php';

/**
 * ScraperService — Cào dữ liệu sản phẩm bán chạy từ các sàn TMĐT.
 *
 * Hỗ trợ:
 * - Shopee: search theo keyword, trending theo danh mục, daily discover
 * - TikTok Shop: search theo keyword
 * - Lazada: search theo keyword
 *
 * Chế độ Trending: Cào top bán chạy KHÔNG cần nhập từ khóa.
 * Kết quả được đẩy vào ProductSyncService để lưu DB.
 */
final class ScraperService
{
    private ProductSyncService $productSyncService;
    private TaskLogService $taskLogService;
    private PDO $pdo;

    /** Rate limit: tối thiểu N giây giữa mỗi request */
    private float $requestDelay = 1.5;

    /** Max retry khi request fail */
    private int $maxRetries = 3;

    /** User-Agent giả lập trình duyệt */
    private array $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ];

    private string $cookieFile;
    private bool $shopeeSessionBootstrapped = false;
    private string $browserScriptPath;

    /** Danh mục Shopee phổ biến (catid) để cào trending */
    public const SHOPEE_CATEGORIES = [
        11036132 => 'Điện tử',
        11036030 => 'Máy tính & Laptop',
        11036670 => 'Nhà cửa & Đời sống',
        11035567 => 'Thời trang nam',
        11035639 => 'Thời trang nữ',
        11036279 => 'Sức khỏe & Sắc đẹp',
        11036525 => 'Bách hóa online',
        11036594 => 'Phụ kiện & Trang sức',
        11036915 => 'Đồ chơi',
        11036101 => 'Thiết bị điện gia dụng',
        11035853 => 'Giày dép nam',
        11035801 => 'Giày dép nữ',
        11036382 => 'Mẹ & Bé',
        11036812 => 'Thể thao & Du lịch',
    ];

    public function __construct()
    {
        $this->productSyncService = new ProductSyncService();
        $this->taskLogService = new TaskLogService();
        $this->pdo = db_pdo();
        $this->cookieFile = sys_get_temp_dir() . '/mmo_scraper_cookies.txt';
        $this->browserScriptPath = BASE_PATH . '/scripts/shopee_browser_scraper.js';
        $this->ensureConfigTable();
    }

    /**
     * Lấy danh sách categories có sẵn cho trending.
     */
    public function getCategories(): array
    {
        return self::SHOPEE_CATEGORIES;
    }

    // ─── Scraper Configs CRUD ─────────────────────────

    public function allConfigs(): array
    {
        $stmt = $this->pdo->query(
            'SELECT * FROM scraper_configs WHERE site_id = ' . APP_SITE_ID . ' ORDER BY updated_at DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findConfig(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM scraper_configs WHERE id = :id AND site_id = :sid');
        $stmt->execute([':id' => $id, ':sid' => APP_SITE_ID]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function saveConfig(array $data): int
    {
        $id = (int)($data['id'] ?? 0);
        $keyword = trim((string)($data['keyword'] ?? ''));
        $platform = $this->sanitizePlatform((string)($data['platform'] ?? 'shopee'));
        $minSold = max(0, (int)($data['min_sold_count'] ?? 100));
        $maxPages = max(1, min(10, (int)($data['max_pages'] ?? 3)));
        $sortBy = in_array($data['sort_by'] ?? '', ['sold', 'price_asc', 'price_desc', 'relevance'], true)
            ? $data['sort_by'] : 'sold';
        $isActive = (int)($data['is_active'] ?? 1);

        // Keyword rỗng = chế độ trending (cào theo danh mục)
        if ($keyword === '') {
            $keyword = '__trending__';
        }

        if ($id > 0) {
            $stmt = $this->pdo->prepare(
                'UPDATE scraper_configs SET keyword = :kw, platform = :pl, min_sold_count = :ms,
                 max_pages = :mp, sort_by = :sb, is_active = :ia, updated_at = NOW()
                 WHERE id = :id AND site_id = :sid'
            );
            $stmt->execute([
                ':kw' => $keyword, ':pl' => $platform, ':ms' => $minSold,
                ':mp' => $maxPages, ':sb' => $sortBy, ':ia' => $isActive,
                ':id' => $id, ':sid' => APP_SITE_ID,
            ]);
            return $id;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO scraper_configs (site_id, keyword, platform, min_sold_count, max_pages, sort_by, is_active)
             VALUES (:sid, :kw, :pl, :ms, :mp, :sb, :ia)'
        );
        $stmt->execute([
            ':sid' => APP_SITE_ID, ':kw' => $keyword, ':pl' => $platform,
            ':ms' => $minSold, ':mp' => $maxPages, ':sb' => $sortBy, ':ia' => $isActive,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function deleteConfig(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM scraper_configs WHERE id = :id AND site_id = :sid');
        $stmt->execute([':id' => $id, ':sid' => APP_SITE_ID]);
    }

    // ─── Main Scraping Logic ──────────────────────────

    /**
     * Chạy cào dữ liệu cho 1 config cụ thể.
     * @return array{scraped: int, synced: int, errors: string[]}
     */
    public function runScrapeJob(int $configId): array
    {
        $config = $this->findConfig($configId);
        if (!$config) {
            throw new InvalidArgumentException("Config #{$configId} không tồn tại.");
        }

        $platform = $config['platform'];
        $keyword = $config['keyword'];
        $maxPages = (int)$config['max_pages'];
        $minSold = (int)$config['min_sold_count'];
        $sortBy = $config['sort_by'];
        $isTrending = ($keyword === '__trending__');

        $allProducts = [];
        $errors = [];

        $this->taskLogService->create('scraper_run', 'pending', [
            'config_id' => $configId, 'keyword' => $isTrending ? 'TRENDING' : $keyword, 'platform' => $platform,
        ]);

        if ($isTrending) {
            // Chế độ trending: cào top bán chạy theo danh mục
            $allProducts = $this->scrapeTrendingProducts($platform, $maxPages, $minSold, $errors);
        } else {
            // Chế độ keyword: search bình thường
            for ($page = 1; $page <= $maxPages; $page++) {
                try {
                    $products = $this->scrapeSearchPage($platform, $keyword, $page, $sortBy);
                    $allProducts = array_merge($allProducts, $products);
                    if ($page < $maxPages) {
                        usleep((int)($this->requestDelay * 1_000_000));
                    }
                } catch (\Throwable $e) {
                    $errors[] = "Trang {$page}: {$e->getMessage()}";
                    error_log("[SCRAPER] Error page {$page} for '{$keyword}': {$e->getMessage()}");
                }
            }
        }

        // Lọc theo min_sold_count
        $filtered = array_filter($allProducts, static function (array $p) use ($minSold): bool {
            return (int)($p['sold_count'] ?? 0) >= $minSold;
        });

        // Sync vào DB qua ProductSyncService
        $syncResult = ['summary' => ['inserted_count' => 0, 'updated_count' => 0]];
        if (!empty($filtered)) {
            $syncResult = $this->productSyncService->syncBatch($platform, array_values($filtered));
        }

        // Cập nhật last_run
        $this->updateLastRun($configId, $allProducts, $filtered, $syncResult, $errors);

        $label = $isTrending ? 'TRENDING' : $keyword;
        $this->taskLogService->create('scraper_run', empty($errors) ? 'success' : 'failed', [
            'config_id' => $configId, 'keyword' => $label,
        ], [
            'scraped' => count($allProducts),
            'filtered' => count($filtered),
            'synced' => $syncResult['summary']['inserted_count'] + $syncResult['summary']['updated_count'],
        ], implode('; ', $errors) ?: null);

        return [
            'scraped' => count($allProducts),
            'filtered' => count($filtered),
            'synced' => $syncResult['summary']['inserted_count'] + $syncResult['summary']['updated_count'],
            'errors' => $errors,
        ];
    }

    /**
     * Cào trending 1 lần (không cần config) — endpoint nhanh cho UI.
     * @param string   $platform    shopee|tiktokshop|lazada
     * @param int[]    $categoryIds Danh mục Shopee catid (rỗng = tất cả)
     * @param int      $minSold     Ngưỡng lượt mua tối thiểu
     * @param int      $maxPages    Số trang mỗi danh mục
     */
    public function scrapeTrending(string $platform = 'shopee', array $categoryIds = [], int $minSold = 100, int $maxPages = 2): array
    {
        $platform = $this->sanitizePlatform($platform);
        $errors = [];

        $allProducts = $this->scrapeTrendingProducts($platform, $maxPages, $minSold, $errors, $categoryIds);

        // Lọc
        $filtered = array_filter($allProducts, static fn(array $p) => (int)($p['sold_count'] ?? 0) >= $minSold);

        // Sync
        $syncResult = ['summary' => ['inserted_count' => 0, 'updated_count' => 0]];
        if (!empty($filtered)) {
            $syncResult = $this->productSyncService->syncBatch($platform, array_values($filtered));
        }

        $this->taskLogService->create('scraper_trending', empty($errors) ? 'success' : 'failed', [
            'platform' => $platform, 'categories' => count($categoryIds) ?: 'all',
        ], [
            'scraped' => count($allProducts),
            'filtered' => count($filtered),
            'synced' => $syncResult['summary']['inserted_count'] + $syncResult['summary']['updated_count'],
        ], implode('; ', $errors) ?: null);

        return [
            'scraped' => count($allProducts),
            'filtered' => count($filtered),
            'synced' => $syncResult['summary']['inserted_count'] + $syncResult['summary']['updated_count'],
            'errors' => $errors,
        ];
    }

    /**
     * Chạy tất cả configs đang active.
     */
    public function runAllActive(): array
    {
        $configs = array_filter($this->allConfigs(), fn($c) => (int)$c['is_active'] === 1);
        $results = [];
        foreach ($configs as $config) {
            try {
                $results[] = [
                    'config_id' => (int)$config['id'],
                    'keyword' => $config['keyword'],
                    'result' => $this->runScrapeJob((int)$config['id']),
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'config_id' => (int)$config['id'],
                    'keyword' => $config['keyword'],
                    'error' => $e->getMessage(),
                ];
            }
            // Delay giữa các config
            usleep(2_000_000);
        }
        return $results;
    }

    /**
     * Thống kê scraper.
     */
    public function summary(): array
    {
        $configs = $this->allConfigs();
        $active = 0;
        $totalScraped = 0;
        foreach ($configs as $c) {
            if ((int)$c['is_active'] === 1) $active++;
            $result = json_decode($c['last_run_result'] ?? '{}', true);
            $totalScraped += (int)($result['scraped'] ?? 0);
        }
        return [
            'total_configs' => count($configs),
            'active_configs' => $active,
            'total_scraped' => $totalScraped,
        ];
    }

    // ─── Trending Scrapers (Không cần keyword) ─────────

    /**
     * Cào sản phẩm trending theo danh mục — KHÔNG cần từ khóa.
     */
    private function scrapeTrendingProducts(string $platform, int $maxPages, int $minSold, array &$errors, array $categoryIds = []): array
    {
        $allProducts = [];

        if ($platform === 'shopee') {
            // 1. Daily Discover (sản phẩm gợi ý hot)
            try {
                $discover = $this->scrapeShopeeDiscover($maxPages);
                $allProducts = array_merge($allProducts, $discover);
            } catch (\Throwable $e) {
                $errors[] = 'Discover: ' . $e->getMessage();
            }
            usleep((int)($this->requestDelay * 1_000_000));

            // 2. Top bán chạy theo từng danh mục
            $cats = !empty($categoryIds) ? $categoryIds : array_keys(self::SHOPEE_CATEGORIES);
            foreach ($cats as $catId) {
                $catName = self::SHOPEE_CATEGORIES[$catId] ?? "Cat#{$catId}";
                for ($page = 1; $page <= min($maxPages, 3); $page++) {
                    try {
                        $products = $this->scrapeShopeeCategory((int)$catId, $page);
                        $allProducts = array_merge($allProducts, $products);
                    } catch (\Throwable $e) {
                        $errors[] = "{$catName} trang {$page}: {$e->getMessage()}";
                    }
                    usleep((int)($this->requestDelay * 1_000_000));
                }
            }
        } else {
            // Fallback: dùng các keyword phổ biến để cào trending
            $trendingKeywords = ['bán chạy', 'hot deal', 'giảm giá sốc', 'freeship', 'best seller'];
            foreach ($trendingKeywords as $kw) {
                try {
                    $products = $this->scrapeSearchPage($platform, $kw, 1, 'sold');
                    $allProducts = array_merge($allProducts, $products);
                } catch (\Throwable $e) {
                    $errors[] = "Keyword '{$kw}': {$e->getMessage()}";
                }
                usleep((int)($this->requestDelay * 1_000_000));
            }
        }

        // Deduplicate theo source_product_id
        $unique = [];
        foreach ($allProducts as $p) {
            $key = $p['source_product_id'] ?? '';
            if ($key !== '' && !isset($unique[$key])) {
                $unique[$key] = $p;
            }
        }

        // Sort theo sold_count giảm dần
        $result = array_values($unique);
        usort($result, fn($a, $b) => (int)($b['sold_count'] ?? 0) <=> (int)($a['sold_count'] ?? 0));

        return $result;
    }

    /**
     * Shopee Daily Discover — sản phẩm gợi ý nổi bật.
     */
    private function scrapeShopeeDiscover(int $maxPages = 2): array
    {
        $jobs = [];
        for ($page = 0; $page < $maxPages; $page++) {
            $jobs[] = ['type' => 'discover', 'page' => $page];
        }

        $browserResults = $this->runShopeeBrowserJobs($jobs);
        $products = [];
        $errors = [];
        foreach ($browserResults as $result) {
            if (!empty($result['error'])) {
                $errors[] = (string)$result['error'];
                continue;
            }
            $products = array_merge($products, $result['products'] ?? []);
        }

        if (!empty($products)) {
            return $products;
        }
        if (!empty($errors)) {
            throw new RuntimeException($errors[0]);
        }

        $products = [];
        for ($page = 0; $page < $maxPages; $page++) {
            $offset = $page * 60;
            $url = 'https://shopee.vn/api/v4/recommend/recommend?' . http_build_query([
                'bundle' => 'daily_discover_main',
                'limit' => 60,
                'offset' => $offset,
            ]);

            $body = $this->httpGet($url, [
                'Referer: https://shopee.vn/',
                'Accept: application/json',
                'X-Shopee-Language: vi',
            ]);

            $data = json_decode($body, true);
            $sections = $data['data']['sections'] ?? [];
            foreach ($sections as $section) {
                $items = $section['data']['item'] ?? [];
                foreach ($items as $item) {
                    $parsed = $this->parseShopeeItem($item, 'Shopee Daily Discover');
                    if ($parsed) $products[] = $parsed;
                }
            }

            if ($page < $maxPages - 1) {
                usleep((int)($this->requestDelay * 1_000_000));
            }
        }
        return $products;
    }

    /**
     * Shopee: Top bán chạy theo danh mục (category ID).
     */
    private function scrapeShopeeCategory(int $catId, int $page = 1): array
    {
        $browserResults = $this->runShopeeBrowserJobs([[
            'type' => 'category',
            'categoryId' => $catId,
            'categoryName' => self::SHOPEE_CATEGORIES[$catId] ?? "Cat#{$catId}",
            'page' => $page,
        ]]);
        $browserResult = $browserResults[0] ?? null;
        if (is_array($browserResult) && empty($browserResult['error'])) {
            return $browserResult['products'] ?? [];
        }
        if (is_array($browserResult) && !empty($browserResult['error'])) {
            throw new RuntimeException((string)$browserResult['error']);
        }

        $offset = ($page - 1) * 60;
        $url = 'https://shopee.vn/api/v4/search/search_items?' . http_build_query([
            'by' => 'sold',
            'limit' => 60,
            'match_id' => $catId,
            'newest' => $offset,
            'order' => 'desc',
            'page_type' => 'search',
            'scenario' => 'PAGE_CATEGORY',
            'version' => 2,
        ]);

        $body = $this->httpGet($url, [
            'Referer: https://shopee.vn/',
            'Accept: application/json',
            'X-Shopee-Language: vi',
            'X-API-SOURCE: pc',
        ]);

        $data = json_decode($body, true);
        $items = $data['items'] ?? [];

        $catName = self::SHOPEE_CATEGORIES[$catId] ?? 'Unknown';
        $products = [];
        foreach ($items as $item) {
            $parsed = $this->parseShopeeItem($item['item_basic'] ?? $item, "Shopee [{$catName}]");
            if ($parsed) $products[] = $parsed;
        }
        return $products;
    }

    /**
     * Parse 1 item Shopee thành mảng chuẩn.
     */
    private function parseShopeeItem(array $info, string $source = 'Shopee'): ?array
    {
        $shopId = (int)($info['shopid'] ?? 0);
        $itemId = (int)($info['itemid'] ?? 0);
        if ($itemId === 0) return null;

        return [
            'source_product_id' => "SH-{$shopId}-{$itemId}",
            'product_name' => $info['name'] ?? 'N/A',
            'product_url' => "https://shopee.vn/product/{$shopId}/{$itemId}",
            'price' => (float)($info['price'] ?? 0) / 100000,
            'sold_count' => (int)($info['sold'] ?? $info['historical_sold'] ?? 0),
            'notes' => $source,
        ];
    }

    // ─── Keyword Search Scrapers ──────────────────────

    private function scrapeSearchPage(string $platform, string $keyword, int $page, string $sortBy): array
    {
        return match ($platform) {
            'shopee' => $this->scrapeShopee($keyword, $page, $sortBy),
            'tiktokshop' => $this->scrapeTikTokShop($keyword, $page, $sortBy),
            'lazada' => $this->scrapeLazada($keyword, $page, $sortBy),
            default => throw new InvalidArgumentException("Platform '{$platform}' chưa được hỗ trợ."),
        };
    }

    private function scrapeShopee(string $keyword, int $page, string $sortBy): array
    {
        $browserResults = $this->runShopeeBrowserJobs([[
            'type' => 'search',
            'keyword' => $keyword,
            'page' => $page,
            'sortBy' => $sortBy,
        ]]);
        $browserResult = $browserResults[0] ?? null;
        if (is_array($browserResult) && empty($browserResult['error'])) {
            return $browserResult['products'] ?? [];
        }
        if (is_array($browserResult) && !empty($browserResult['error'])) {
            throw new RuntimeException((string)$browserResult['error']);
        }

        $offset = ($page - 1) * 60;
        $sortMap = [
            'sold' => 'sold', 'price_asc' => 'price', 'price_desc' => 'price',
            'relevance' => 'relevance',
        ];
        $apiSort = $sortMap[$sortBy] ?? 'sold';
        $order = $sortBy === 'price_desc' ? 'desc' : 'asc';

        $url = 'https://shopee.vn/api/v4/search/search_items?' . http_build_query([
            'keyword' => $keyword,
            'limit' => 60,
            'newest' => $offset,
            'order' => $order,
            'page_type' => 'search',
            'scenario' => 'PAGE_GLOBAL_SEARCH',
            'by' => $apiSort,
            'version' => 2,
        ]);

        $body = $this->httpGet($url, [
            'Referer: https://shopee.vn/',
            'Accept: application/json',
            'X-Shopee-Language: vi',
            'X-API-SOURCE: pc',
        ]);

        $data = json_decode($body, true);
        $items = $data['items'] ?? $data['item_basic'] ?? [];

        $products = [];
        foreach ($items as $item) {
            $info = $item['item_basic'] ?? $item;
            $shopId = (int)($info['shopid'] ?? 0);
            $itemId = (int)($info['itemid'] ?? 0);
            if ($itemId === 0) continue;

            $price = (float)($info['price'] ?? 0) / 100000;
            $soldCount = (int)($info['sold'] ?? $info['historical_sold'] ?? 0);

            $products[] = [
                'source_product_id' => "SH-{$shopId}-{$itemId}",
                'product_name' => $info['name'] ?? 'N/A',
                'product_url' => "https://shopee.vn/product/{$shopId}/{$itemId}",
                'price' => $price,
                'sold_count' => $soldCount,
                'notes' => 'Scraped from Shopee search',
            ];
        }

        return $products;
    }

    private function scrapeTikTokShop(string $keyword, int $page, string $sortBy): array
    {
        $offset = ($page - 1) * 30;
        $sortMap = [
            'sold' => 2, 'price_asc' => 3, 'price_desc' => 4, 'relevance' => 0,
        ];
        $sortType = $sortMap[$sortBy] ?? 2;

        $url = 'https://www.tiktok.com/api/v1/commerce/search/product?' . http_build_query([
            'query' => $keyword,
            'offset' => $offset,
            'count' => 30,
            'sort_type' => $sortType,
        ]);

        $body = $this->httpGet($url, [
            'Referer: https://www.tiktok.com/',
            'Accept: application/json',
        ]);

        $data = json_decode($body, true);
        $items = $data['data']['products'] ?? $data['products'] ?? [];

        $products = [];
        foreach ($items as $item) {
            $productId = (string)($item['id'] ?? $item['product_id'] ?? '');
            if ($productId === '') continue;

            $products[] = [
                'source_product_id' => "TT-{$productId}",
                'product_name' => $item['title'] ?? $item['name'] ?? 'N/A',
                'product_url' => $item['url'] ?? "https://www.tiktok.com/view/product/{$productId}",
                'price' => (float)($item['price']['original_price'] ?? $item['price'] ?? 0),
                'sold_count' => (int)($item['sold_count'] ?? $item['sales'] ?? 0),
                'notes' => 'Scraped from TikTok Shop',
            ];
        }

        return $products;
    }

    private function scrapeLazada(string $keyword, int $page, string $sortBy): array
    {
        $sortMap = [
            'sold' => 'pricedesc', 'price_asc' => 'priceasc', 'price_desc' => 'pricedesc',
            'relevance' => 'pop',
        ];
        $apiSort = $sortMap[$sortBy] ?? 'pop';

        $url = 'https://www.lazada.vn/catalog/?' . http_build_query([
            'q' => $keyword,
            'page' => $page,
            'sort' => $apiSort,
            'ajax' => 'true',
        ]);

        $body = $this->httpGet($url, [
            'Referer: https://www.lazada.vn/',
            'Accept: application/json',
            'X-Requested-With: XMLHttpRequest',
        ]);

        $data = json_decode($body, true);
        $items = $data['mods']['listItems'] ?? [];

        $products = [];
        foreach ($items as $item) {
            $nid = (string)($item['nid'] ?? $item['itemId'] ?? '');
            if ($nid === '') continue;

            $products[] = [
                'source_product_id' => "LZ-{$nid}",
                'product_name' => $item['name'] ?? 'N/A',
                'product_url' => $item['productUrl'] ?? "https://www.lazada.vn/-i{$nid}.html",
                'price' => (float)str_replace(['.', ','], ['', '.'], (string)($item['price'] ?? '0')),
                'sold_count' => (int)($item['sold'] ?? $item['itemSoldCntShow'] ?? 0),
                'notes' => 'Scraped from Lazada',
            ];
        }

        return $products;
    }

    // ─── HTTP Client ──────────────────────────────────

    private function httpGet(string $url, array $headers = []): string
    {
        $userAgent = $this->userAgents[array_rand($this->userAgents)];
        $host = (string)parse_url($url, PHP_URL_HOST);
        $allHeaders = array_merge($this->buildDefaultHeaders($url, $userAgent), $headers);

        $lastError = '';
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            if (str_contains($host, 'shopee.vn')) {
                $this->bootstrapShopeeSession($userAgent);
            }

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER => $allHeaders,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_ENCODING => 'gzip, deflate',
                CURLOPT_COOKIEJAR => $this->cookieFile,
                CURLOPT_COOKIEFILE => $this->cookieFile,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            ]);

            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError !== '') {
                $lastError = "cURL error: {$curlError}";
                error_log("[SCRAPER] Attempt {$attempt}: {$lastError}");
                usleep(1_000_000 * $attempt);
                continue;
            }

            if ($httpCode === 429) {
                $lastError = "Rate limited (429). Waiting...";
                error_log("[SCRAPER] {$lastError}");
                sleep(5 * $attempt);
                continue;
            }

            if ($httpCode >= 400) {
                $lastError = "HTTP {$httpCode}";
                if ($httpCode === 403 && str_contains($host, 'shopee.vn')) {
                    $this->shopeeSessionBootstrapped = false;
                    $lastError .= ' (Shopee anti-bot blocked this server-side request)';
                }
                error_log("[SCRAPER] Attempt {$attempt}: {$lastError}");
                usleep(1_000_000 * $attempt);
                continue;
            }

            return (string)$response;
        }

        if ($lastError !== '' && str_contains($host, 'shopee.vn') && str_contains($lastError, 'HTTP 403')) {
            throw new RuntimeException(
                "Scraper request failed after {$this->maxRetries} attempts: {$lastError}. " .
                'Shopee is blocking direct cURL access to api/v4. Try residential proxy/cookie seeding or switch to browser automation.'
            );
        }

        throw new RuntimeException("Scraper request failed after {$this->maxRetries} attempts: {$lastError}");
    }

    private function buildDefaultHeaders(string $url, string $userAgent): array
    {
        $host = (string)parse_url($url, PHP_URL_HOST);
        $origin = str_starts_with($host, 'www.') ? "https://{$host}" : 'https://www.' . $host;
        $referer = $origin . '/';

        if (str_contains($host, 'shopee.vn')) {
            $origin = 'https://shopee.vn';
            $referer = 'https://shopee.vn/';
        } elseif (str_contains($host, 'tiktok.com')) {
            $origin = 'https://www.tiktok.com';
            $referer = 'https://www.tiktok.com/';
        } elseif (str_contains($host, 'lazada.vn')) {
            $origin = 'https://www.lazada.vn';
            $referer = 'https://www.lazada.vn/';
        }

        return [
            'User-Agent: ' . $userAgent,
            'Accept-Language: vi-VN,vi;q=0.9,en;q=0.8',
            'Accept: application/json, text/plain, */*',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Origin: ' . $origin,
            'Referer: ' . $referer,
            'Sec-Fetch-Dest: empty',
            'Sec-Fetch-Mode: cors',
            'Sec-Fetch-Site: same-origin',
        ];
    }

    private function bootstrapShopeeSession(string $userAgent): void
    {
        if ($this->shopeeSessionBootstrapped) {
            return;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://shopee.vn/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'User-Agent: ' . $userAgent,
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language: vi-VN,vi;q=0.9,en;q=0.8',
                'Cache-Control: no-cache',
                'Pragma: no-cache',
                'Upgrade-Insecure-Requests: 1',
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);

        curl_exec($ch);
        curl_close($ch);
        $this->shopeeSessionBootstrapped = true;
    }

    private function runShopeeBrowserJobs(array $jobs): array
    {
        if (!file_exists($this->browserScriptPath)) {
            return [];
        }

        if (!function_exists('proc_open')) {
            return [];
        }

        $nodeBinary = $this->findNodeBinary();
        $payload = json_encode([
            'jobs' => array_values($jobs),
            'userDataDir' => STORAGE_PATH . '/browser/shopee-profile',
        ], JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            return [];
        }

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            [$nodeBinary, $this->browserScriptPath, $payload],
            $descriptorSpec,
            $pipes,
            BASE_PATH,
            null,
            ['bypass_shell' => true]
        );

        if (!is_resource($process)) {
            return [];
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            throw new RuntimeException(trim($stderr) !== '' ? trim($stderr) : 'Browser scraper process failed');
        }

        $decoded = json_decode((string)$stdout, true);
        if (!is_array($decoded) || !isset($decoded['results']) || !is_array($decoded['results'])) {
            throw new RuntimeException('Browser scraper returned invalid JSON payload');
        }

        return $decoded['results'];
    }

    private function findNodeBinary(): string
    {
        $candidates = [
            getenv('NODE_BINARY') ?: null,
            'C:\\Program Files\\nodejs\\node.exe',
            'node',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }
            if ($candidate === 'node' || file_exists($candidate)) {
                return $candidate;
            }
        }

        return 'node';
    }

    // ─── Helpers ──────────────────────────────────────

    private function updateLastRun(int $configId, array $all, array $filtered, array $syncResult, array $errors): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE scraper_configs SET last_run_at = NOW(), last_run_result = :res, updated_at = NOW()
             WHERE id = :id AND site_id = :sid'
        );
        $resultJson = json_encode([
            'scraped' => count($all),
            'filtered' => count($filtered),
            'synced_new' => $syncResult['summary']['inserted_count'],
            'synced_updated' => $syncResult['summary']['updated_count'],
            'errors' => $errors,
        ], JSON_UNESCAPED_UNICODE);
        $stmt->execute([':res' => $resultJson, ':id' => $configId, ':sid' => APP_SITE_ID]);
    }

    private function sanitizePlatform(string $platform): string
    {
        return in_array($platform, ['shopee', 'tiktokshop', 'lazada'], true) ? $platform : 'shopee';
    }

    private function ensureConfigTable(): void
    {
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS scraper_configs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT NOT NULL DEFAULT 1,
    keyword VARCHAR(255) NOT NULL,
    platform VARCHAR(50) NOT NULL DEFAULT 'shopee',
    min_sold_count INT UNSIGNED NOT NULL DEFAULT 100,
    max_pages TINYINT UNSIGNED NOT NULL DEFAULT 3,
    sort_by VARCHAR(20) NOT NULL DEFAULT 'sold',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_run_at DATETIME NULL,
    last_run_result JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_scraper_site_active (site_id, is_active),
    KEY idx_scraper_platform (platform)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
}
