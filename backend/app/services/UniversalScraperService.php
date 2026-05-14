<?php
declare(strict_types=1);

require_once __DIR__ . '/DatabaseStorage.php';
require_once __DIR__ . '/ScraperService.php';
require_once __DIR__ . '/ProductSyncService.php';
require_once __DIR__ . '/OpenAIContentProvider.php';

/**
 * Universal scraper: scrape products from ANY source URL using AI.
 * Boss pastes a URL or raw HTML, AI extracts product data.
 */
class UniversalScraperService
{
    private $pdo;
    private $scraperService;
    private $productSyncService;

    public const PLATFORMS = ['shopee', 'tiki', 'lazada', 'generic'];

    public function __construct()
    {
        $this->pdo = db_pdo();
        $this->scraperService = new ScraperService();
        $this->productSyncService = new ProductSyncService();
    }

    /**
     * Scrape products from any URL.
     * Uses AI to identify products on the page and extract structured data.
     */
    public function scrapeUrl(string $url, string $platform = 'generic', int $maxProducts = 200): array
    {
        $html = $this->fetchUrl($url);
        if (!$html) {
            throw new \RuntimeException('Không thể truy cập URL: ' . $url);
        }

        // If Shopee/Tiki/Lazada, use their own parsers
        if ($platform === 'shopee') {
            return $this->scraperService->runScrapeJob($this->getOrCreateConfig($url, $platform));
        }

        // For all other sources: use AI to extract products
        return $this->extractProductsWithAI($html, $url, $platform, $maxProducts);
    }

    /**
     * Parse raw text/CSV data with AI.
     * Boss pastes product list (text, CSV, HTML table), AI returns structured array.
     */
    public function parseRawData(string $raw, string $platform = 'manual'): array
    {
        $prompt = "Bạn là chuyên gia trích xuất dữ liệu sản phẩm từ text thô.\n\n" .
            "Dưới đây là dữ liệu sản phẩm thô (có thể là CSV, text, HTML, danh sách):\n\n" .
            "```\n" . substr($raw, 0, 4000) . "\n```\n\n" .
            "Hãy trích xuất tất cả sản phẩm, trả về JSON array, mỗi sản phẩm có:\n" .
            "- product_name (tên sản phẩm)\n" .
            "- price (số, VND)\n" .
            "- sold_count (số nguyên, số đã bán)\n" .
            "- source_product_id (ID từ nguồn, nếu có)\n" .
            "- rating (số thập phân 1-5, nếu có)\n" .
            "- review_count (số nguyên, nếu có)\n" .
            "- product_url (URL sản phẩm, nếu có)\n\n" .
            "Trả về JSON array, không giải thích. Ví dụ:\n" .
            "[{\"product_name\":\"Máy xay sinh tố\",\"price\":450000,\"sold_count\":1200,\"source_product_id\":\"\",\"rating\":4.5,\"review_count\":300}]";

        $provider = new OpenAIContentProvider('cx/gpt-5.5', openai_base_url(), openai_api_key());
        $response = $provider->generate(['prompt' => $prompt]);
        $json = trim($response['content'] ?? '');

        // Strip markdown code blocks
        $json = preg_replace('/^(```json?|```)/', '', $json);
        $json = trim($json);

        $products = json_decode($json, true);
        if (!is_array($products)) {
            $products = [];
        }

        // Save to DB
        $added = 0;
        foreach ($products as $p) {
            if ($this->saveProduct($p, $platform)) {
                $added++;
            }
        }

        return [
            'extracted' => count($products),
            'saved' => $added,
            'products' => $products,
        ];
    }

    /**
     * Use AI to extract products from any HTML page.
     */
    public function extractProductsWithAI(string $html, string $url, string $platform, int $maxProducts): array
    {
        // Get page title to give AI context
        $title = '';
        if (preg_match('/<title>(.*?)<\/title>/is', $html, $m)) {
            $title = trim($m[1]);
        }

        // Strip scripts and styles to reduce token usage
        $clean = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $clean = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $clean);
        $clean = preg_replace('/<!--.*?-->/s', '', $clean);
        $clean = preg_replace('/\s+/', ' ', $clean);
        $clean = trim(substr($clean, 0, 15000)); // First 15K chars

        $prompt = "Bạn là chuyên gia trích xuất dữ liệu sản phẩm từ HTML bất kỳ.\n\n" .
            "Trang: {$url}\n" .
            "Tiêu đề: {$title}\n" .
            "Nền tảng: {$platform}\n\n" .
            "HTML (đã clean):\n```\n{$clean}\n```\n\n" .
            "Hãy trích xuất tất cả sản phẩm tìm thấy, trả về JSON array.\n" .
            "Mỗi sản phẩm có:\n" .
            "- product_name, price, sold_count, source_product_id\n" .
            "- rating, review_count, product_url, image_url\n" .
            "- source_platform = '{$platform}'\n\n" .
            "Tối đa {$maxProducts} sản phẩm. Trả về JSON array thuần, không markdown.";

        $provider = new OpenAIContentProvider('cx/gpt-5.5', openai_base_url(), openai_api_key());
        $response = $provider->generate(['prompt' => $prompt]);
        $raw = trim($response['content'] ?? '');
        $raw = preg_replace('/^(```json?|```)/', '', $raw);
        $raw = trim($raw);

        $products = json_decode($raw, true);
        if (!is_array($products)) {
            return ['extracted' => 0, 'saved' => 0, 'products' => []];
        }

        $added = 0;
        foreach ($products as $p) {
            if ($this->saveProduct($p, $platform)) {
                $added++;
            }
        }

        return [
            'extracted' => count($products),
            'saved' => $added,
            'products' => $products,
        ];
    }

    /**
     * Auto-discover products from a URL using AI to figure out the platform.
     */
    public function autoDiscover(string $url): array
    {
        $platform = $this->detectPlatform($url);
        return $this->scrapeUrl($url, $platform);
    }

    /**
     * Detect platform from URL.
     */
    private function detectPlatform(string $url): string
    {
        $lower = strtolower($url);
        if (strpos($lower, 'shopee') !== false) return 'shopee';
        if (strpos($lower, 'tiki') !== false) return 'tiki';
        if (strpos($lower, 'lazada') !== false) return 'lazada';
        if (strpos($lower, 'amazon') !== false) return 'amazon';
        if (strpos($lower, 'sendo') !== false) return 'sendo';
        if (strpos($lower, '差評') !== false || strpos($lower, 'điện máy') !== false) return 'dienmay';
        return 'generic';
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

    private function saveProduct(array $p, string $platform): bool
    {
        $name = $p['product_name'] ?? '';
        if ($name === '') {
            return false;
        }

        $sourceId = (string) ($p['source_product_id'] ?? '');

        if ($sourceId !== '') {
            $stmt = $this->pdo->prepare("
                SELECT id FROM affiliate_products
                WHERE site_id = :sid AND source_platform = :pl AND source_product_id = :spid
                LIMIT 1
            ");
            $stmt->execute([
                ':sid' => currentSiteId(),
                ':pl' => $platform,
                ':spid' => $sourceId,
            ]);
            $exists = $stmt->fetch();
        } else {
            $exists = false;
        }

        $data = [
            'site_id' => currentSiteId(),
            'product_name' => $name,
            'price' => (float) ($p['price'] ?? 0),
            'original_price' => (float) ($p['original_price'] ?? 0),
            'sold_count' => (int) ($p['sold_count'] ?? 0),
            'rating' => (float) ($p['rating'] ?? 0),
            'review_count' => (int) ($p['review_count'] ?? 0),
            'source_platform' => $platform,
            'source_product_id' => $sourceId,
            'product_url' => ($p['product_url'] ?? ''),
            'image_url' => ($p['image_url'] ?? ''),
            'is_active' => 1,
        ];

        if ($exists) {
            $sets = implode(', ', array_map(fn($k) => "{$k} = :{$k}", array_keys($data)));
            $sets .= ', updated_at = NOW()';
            $sql = "UPDATE affiliate_products SET {$sets} WHERE id = :id";
            $data['id'] = $exists['id'];
        } else {
            $cols = implode(', ', array_keys($data));
            $vals = implode(', ', array_map(fn($k) => ":{$k}", array_keys($data)));
            $sql = "INSERT INTO affiliate_products ({$cols}) VALUES ({$vals})";
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($data);
        } catch (\Throwable) {
            return false;
        }
    }

    private function fetchUrl(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 20,
                'header' => "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\nAccept: text/html,application/xhtml+xml\r\nAccept-Language: vi-VN,vi;q=0.9,en;q=0.8\r\n",
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);
        $html = @file_get_contents($url, false, $context);
        return $html ?: null;
    }
}