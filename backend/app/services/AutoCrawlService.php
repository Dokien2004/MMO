<?php
declare(strict_types=1);

require_once __DIR__ . '/DatabaseStorage.php';
require_once __DIR__ . '/AIKeywordService.php';
require_once __DIR__ . '/ScraperService.php';
require_once __DIR__ . '/ProductSyncService.php';
require_once __DIR__ . '/OpenAIContentProvider.php';
require_once __DIR__ . '/UniversalScraperService.php';

/**
 * Auto-crawl service: crawls from multiple platforms (Shopee, Tiki, Lazada)
 * based on AI-suggested or manually provided keywords.
 * Uses AI to decide how many products to collect per keyword (100-200).
 */
class AutoCrawlService
{
    private $pdo;
    private $keywordService;
    private $scraperService;
    private $productSyncService;

    /** @var string[] Supported platforms */
    public const PLATFORMS = ['shopee', 'tiki', 'lazada'];

    public function __construct()
    {
        $this->pdo = db_pdo();
        $this->keywordService = new AIKeywordService();
        $this->scraperService = new ScraperService();
        $this->productSyncService = new ProductSyncService();
    }

    /**
     * Main auto-crawl entry point.
     * 1. AI suggests/refreshes keywords
     * 2. For each keyword, AI decides how many products to collect (100-200)
     * 3. Crawl from each platform
     * 4. Return crawl summary
     */
    public function runAutoCrawl(?int $siteId = null): array
    {
        if ($siteId !== null) {
            $_SESSION['site_id'] = $siteId;
        }

        $sessionId = $this->startCrawlSession();
        $summary = [
            'session_id' => $sessionId,
            'started_at' => date('Y-m-d H:i:s'),
            'keywords_processed' => 0,
            'products_added' => 0,
            'products_updated' => 0,
            'errors' => [],
            'platforms' => [],
        ];

        try {
            // Get keywords from active configs
            $keywords = $this->getActiveKeywords();
            if (empty($keywords)) {
                // Fallback: let AI suggest
                $suggestions = $this->keywordService->suggestKeywords(3);
                $keywords = array_column($suggestions, 'keyword');
            }

            // For each keyword, let AI decide limit then crawl
            foreach ($keywords as $keyword) {
                $limit = $this->decideLimitForKeyword($keyword);
                $kwResult = $this->crawlKeyword($keyword, $limit, $summary);
                $summary['keywords_processed']++;
                $summary['products_added'] += $kwResult['added'];
                $summary['products_updated'] += $kwResult['updated'];
            }
        } catch (\Throwable $e) {
            $summary['errors'][] = $e->getMessage();
        }

        $summary['finished_at'] = date('Y-m-d H:i:s');
        $this->endCrawlSession($sessionId, $summary);

        return $summary;
    }

    /**
     * Let AI decide how many products to crawl for a given keyword (100-200).
     */
    public function decideLimitForKeyword(string $keyword): int
    {
        $prompt = "Bạn là chuyên gia thu thập dữ liệu Shopee Việt Nam.\n\n" .
            "Một từ khóa tìm kiếm. Hãy quyết định số lượng sản phẩm CẦN THU THẬP để có data tốt mà không trùng lặp quá nhiều.\n\n" .
            "Từ khóa: {$keyword}\n\n" .
            "Trả về MỘT số nguyên từ 100 đến 200.\n" .
            "Nếu từ khóa ngách, ít sản phẩm → trả về 100.\n" .
            "Nếu từ khóa phổ biến, nhiều sản phẩm → trả về 150-200.\n\n" .
            "Số (100-200): ";

        try {
            $provider = new OpenAIContentProvider('cx/gpt-5.5', openai_base_url(), openai_api_key());
            $response = $provider->generate(['prompt' => $prompt]);
            $raw = trim($response['content'] ?? '150');
            if (preg_match('/\d+/', $raw, $m)) {
                $limit = (int) $m[0];
                return max(100, min(200, $limit));
            }
        } catch (\Throwable) {
            // fallback
        }
        return 150;
    }

    /**
     * Crawl products for a specific keyword across all active platforms.
     */
    public function crawlKeyword(string $keyword, int $limit = 150, array &$summary = []): array
    {
        $result = ['added' => 0, 'updated' => 0, 'platforms' => []];
        $limitPerPlatform = (int) ceil($limit / count(self::PLATFORMS));

        foreach (self::PLATFORMS as $platform) {
            try {
                $platformResult = $this->crawlPlatform($platform, $keyword, $limitPerPlatform);
                $result['added'] += $platformResult['added'];
                $result['updated'] += $platformResult['updated'];
                $result['platforms'][$platform] = $platformResult;
                $summary['platforms'][$platform] = ($summary['platforms'][$platform] ?? 0) + $platformResult['added'];
            } catch (\Throwable $e) {
                $result['platforms'][$platform] = ['error' => $e->getMessage()];
                $summary['errors'][] = "{$platform}: {$e->getMessage()}";
            }
        }

        return $result;
    }

    private function crawlPlatform(string $platform, string $keyword, int $limit): array
    {
        if ($platform === 'shopee') {
            return $this->crawlShopee($keyword, $limit);
        }
        if ($platform === 'tiki') {
            return $this->crawlTiki($keyword, $limit);
        }
        if ($platform === 'lazada') {
            return $this->crawlLazada($keyword, $limit);
        }
        return ['added' => 0, 'updated' => 0];
    }

    private function crawlShopee(string $keyword, int $limit): array
    {
        $pages = min((int) ceil($limit / 20), 5);
        $this->scraperService->ensureShopeeLiveBrowser();
        $session = $this->scraperService->checkShopeeSession();

        if (!($session['active'] ?? false)) {
            throw new \RuntimeException('Shopee session chưa active, cần đăng nhập thủ công');
        }

        $configId = $this->getOrCreateConfig($keyword, $pages);
        if (!$configId) {
            throw new \RuntimeException('Không thể tạo config cho từ khóa: ' . $keyword);
        }

        $result = $this->scraperService->runScrapeJob($configId);
        return [
            'added' => $result['synced'],
            'updated' => $result['synced'],
            'scraped' => $result['scraped'],
        ];
    }

    private function getOrCreateConfig(string $keyword, int $pages): ?int
    {
        $stmt = $this->pdo->prepare("
            SELECT id FROM scraper_configs
            WHERE site_id = :sid AND keyword = :kw AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([':sid' => currentSiteId(), ':kw' => $keyword]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            return (int) $row['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO scraper_configs (site_id, keyword, platform, min_sold_count, max_pages, sort_by, is_active, created_at, updated_at)
            VALUES (:sid, :kw, 'shopee', 5, :pages, 'top_sales', 1, NOW(), NOW())
        ");
        $ok = $stmt->execute([':sid' => currentSiteId(), ':kw' => $keyword, ':pages' => $pages]);
        return $ok ? (int) $this->pdo->lastInsertId() : null;
    }

    private function crawlTiki(string $keyword, int $limit): array
    {
        $added = 0;
        $url = "https://tiki.vn/api/v2/products?q=" . urlencode($keyword) . "&limit=" . min($limit, 40);
        $json = $this->httpGet($url, ['Accept: application/json', 'Referer: https://tiki.vn/']);
        if (!$json) {
            return ['added' => 0, 'updated' => 0];
        }

        $data = json_decode($json, true);
        $items = $data['data'] ?? [];
        foreach ($items as $item) {
            $soldRaw = $item['quantity_sold']['value'] ?? 0;
            $p = [
                'product_name' => $item['name'] ?? '',
                'price' => (float) ($item['price'] ?? 0),
                'original_price' => (float) ($item['original_price'] ?? 0),
                'sold_count' => (int) $soldRaw,
                'rating' => (float) ($item['rating_average'] ?? 0),
                'review_count' => (int) ($item['review_count'] ?? 0),
                'source_product_id' => (string) ($item['id'] ?? ''),
                'source_platform' => 'tiki',
                'product_url' => 'https://tiki.vn/' . ($item['url_path'] ?? ''),
                'image_url' => $item['thumbnail_url'] ?? '',
            ];
            if ($this->saveProduct($p, 'tiki')) {
                $added++;
            }
        }

        return ['added' => $added, 'updated' => 0];
    }

    private function crawlLazada(string $keyword, int $limit): array
    {
        $added = 0;
        $url = "https://www.lazada.vn/catalog/search?q=" . urlencode($keyword) . "&start=0&length=" . min($limit, 40);
        $html = $this->httpGet($url);
        if (!$html) {
            return ['added' => 0, 'updated' => 0];
        }

        // Use AI to extract from Lazada HTML
        $svc = new UniversalScraperService();
        $result = $svc->extractProductsWithAI($html, $url, 'lazada', $limit);
        return ['added' => $result['saved'], 'updated' => 0];
    }

    private function parseTikiSearch(string $html): array
    {
        $products = [];
        if (preg_match('/window\.__INITIAL_STATE__\s*=\s*(\{.*?\});/s', $html, $m)) {
            $data = json_decode($m[1], true);
            $items = $data['search']['products'] ?? [];
            foreach ($items as $item) {
                $products[] = [
                    'product_name' => $item['name'] ?? '',
                    'price' => (float) ($item['price'] ?? 0),
                    'original_price' => (float) ($item['original_price'] ?? 0),
                    'sold_count' => (int) ($item['all_time_quantity_sold'] ?? 0),
                    'rating' => (float) ($item['rating_score'] ?? 0),
                    'review_count' => (int) ($item['review_count'] ?? 0),
                    'source_product_id' => (string) ($item['id'] ?? ''),
                    'source_platform' => 'tiki',
                    'product_url' => 'https://tiki.vn' . ($item['url_path'] ?? ''),
                    'image_url' => $item['thumbnail_url'] ?? '',
                ];
            }
        }
        return $products;
    }

    private function parseLazadaSearch(string $html): array
    {
        $products = [];
        if (preg_match('/window\.pageData\s*=\s*(\{.*?})\s*window\;/s', $html, $m)) {
            $data = json_decode($m[1], true);
            $items = $data['mods']['listItems'] ?? [];
            foreach ($items as $item) {
                $products[] = [
                    'product_name' => $item['name'] ?? '',
                    'price' => (float) ($item['price'] ?? 0),
                    'original_price' => (float) ($item['originalPrice'] ?? 0),
                    'sold_count' => (int) ($item['quantitySold'] ?? 0),
                    'rating' => (float) ($item['ratingScore'] ?? 0),
                    'review_count' => (int) ($item['review'] ?? 0),
                    'source_product_id' => (string) ($item['itemId'] ?? ''),
                    'source_platform' => 'lazada',
                    'product_url' => ($item['productUrl'] ?? ''),
                    'image_url' => $item['image'] ?? '',
                ];
            }
        }
        return $products;
    }

    private function saveProduct(array $p, string $platform): bool
    {
        $sourceId = $p['source_product_id'] ?? '';
        if ($sourceId === '') {
            return false;
        }

        $stmt = $this->pdo->prepare('SELECT id FROM affiliate_products WHERE site_id=:sid AND source_platform=:pl AND source_product_id=:spid LIMIT 1');
        $stmt->execute([':sid'=>currentSiteId(),':pl'=>$platform,':spid'=>$sourceId]);
        $exists = $stmt->fetch();

        $now = date('Y-m-d H:i:s');
        $vals = [
            currentSiteId(),
            $p['product_name'] ?? '',
            (float)($p['price'] ?? 0),
            (int)($p['sold_count'] ?? 0),
            $platform,
            $sourceId,
            $p['product_url'] ?? '',
            'new',
            $now,
            $now,
        ];

        if ($exists) {
            $sql = 'UPDATE affiliate_products SET price=?,sold_count=?,updated_at=? WHERE id=?';
            try {
                $stmt = $this->pdo->prepare($sql);
                return $stmt->execute([$vals[2], $vals[3], $now, $exists['id']]);
            } catch (Throwable) {
                return false;
            }
        } else {
            $cols = 'site_id,product_name,price,sold_count,source_platform,source_product_id,product_url,status,created_at,updated_at';
            $placeholders = implode(',', array_fill(0, count($vals), '?'));
            $sql = "INSERT INTO affiliate_products ($cols) VALUES ($placeholders)";
            try {
                $stmt = $this->pdo->prepare($sql);
                return $stmt->execute($vals);
            } catch (Throwable) {
                return false;
            }
        }
    }

    private function httpGet(string $url, array $extraHeaders = []): ?string
    {
        $defaultHeaders = "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\nAccept: text/html,application/xhtml+xml,application/json\r\nAccept-Language: vi-VN,vi;q=0.9,en;q=0.8\r\n";
        $extra = implode("\r\n", $extraHeaders);
        if ($extra) $defaultHeaders .= $extra . "\r\n";
        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'header' => $defaultHeaders,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);
        $html = @file_get_contents($url, false, $context);
        return $html ?: null;
    }

    private function getActiveKeywords(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT keyword FROM scraper_configs
            WHERE site_id = :sid AND is_active = 1 AND keyword != '__trending__'
            ORDER BY updated_at DESC
            LIMIT 10
        ");
        $stmt->execute([':sid' => currentSiteId()]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function isPlatformEnabled(string $platform): bool
    {
        // Default: only shopee is enabled (Tiki/Lazada may need more setup)
        $enabled = @ini_get('mmo.auto_crawl.platforms') ?: 'shopee';
        $platforms = array_map('trim', explode(',', $enabled));
        return in_array($platform, $platforms, true);
    }

    private function startCrawlSession(): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO cron_job_logs (site_id, job_name, status, started_at)
            VALUES (:sid, 'auto_crawl', 'running', NOW())
        ");
        $stmt->execute([':sid' => currentSiteId()]);
        return (int) $this->pdo->lastInsertId();
    }

    private function endCrawlSession(int $sessionId, array $summary): void
    {
        $status = empty($summary['errors']) ? 'success' : 'partial';
        $stmt = $this->pdo->prepare("
            UPDATE cron_job_logs
            SET status = :st, finished_at = NOW(), result = :res
            WHERE id = :id
        ");
        $stmt->execute([
            ':st' => $status,
            ':res' => json_encode($summary),
            ':id' => $sessionId,
        ]);
    }
}