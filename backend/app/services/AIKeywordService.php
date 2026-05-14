<?php
declare(strict_types=1);

require_once __DIR__ . '/DatabaseStorage.php';
require_once __DIR__ . '/OpenAIContentProvider.php';

/**
 * AI-powered keyword suggestor.
 * Uses cx/gpt-5.5 via local 9router to analyze top products
 * and suggest NEW keywords for the next crawl cycle.
 */
class AIKeywordService
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = db_pdo();
    }

    /**
     * Analyze top-selling products and suggest new trending keywords.
     * Returns array of suggested keywords with rationale.
     */
    public function suggestKeywords(int $count = 5): array
    {
        $topProducts = $this->getTopSellingProducts();
        if (empty($topProducts)) {
            return $this->getFallbackKeywords();
        }

        $prompt = $this->buildKeywordPrompt($topProducts, $count);
        $response = $this->callAI($prompt);

        return $this->parseAIResponse($response, $count);
    }

    /**
     * Analyze a single product and extract/return the most relevant keyword for crawling.
     */
    public function extractKeywordFromProduct(array $product): string
    {
        $prompt = "Bạn là chuyên gia affiliate marketing Việt Nam.\n\n" .
            "Trả về MỘT từ khóa tiếng Việt tốt nhất để tìm sản phẩm TƯƠNG TỰ trên Shopee.\n" .
            "Chỉ trả về từ khóa, không giải thích.\n\n" .
            "Sản phẩm: " . ($product['product_name'] ?? '') . "\n" .
            "Giá: " . number_format((float)($product['price'] ?? 0)) . " VND\n" .
            "Đã bán: " . number_format((int)($product['sold_count'] ?? 0)) . "\n\n" .
            "Từ khóa gợi ý (tiếng Việt, 2-5 từ): ";

        $response = $this->callAI($prompt);
        $keyword = trim($response);
        $keyword = preg_replace('/["\'\.\!\?\-]/', '', $keyword);
        return $keyword ?: ($product['product_name'] ?? '');
    }

    /**
     * Save a keyword to scraper_configs so it gets picked up by the next crawl run.
     */
    public function saveKeywordForCrawl(string $keyword, string $source = 'ai_suggested'): bool
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return false;
        }

        // Check if keyword already exists recently (within 24h)
        $stmt = $this->pdo->prepare("
            SELECT id FROM scraper_configs
            WHERE site_id = :sid AND keyword = :kw
              AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            LIMIT 1
        ");
        $stmt->execute([':sid' => currentSiteId(), ':kw' => $keyword]);
        if ($stmt->fetch()) {
            return false; // Already exists
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO scraper_configs (site_id, keyword, platform, min_sold_count, max_pages, sort_by, is_active, created_at, updated_at)
            VALUES (:sid, :kw, 'shopee', 10, 3, 'top_sales', 1, NOW(), NOW())
        ");
        return $stmt->execute([':sid' => currentSiteId(), ':kw' => $keyword]);
    }

    private function getTopSellingProducts(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare("
            SELECT product_name, price, sold_count, source_platform
            FROM affiliate_products
            WHERE site_id = :sid AND sold_count > 0
            ORDER BY sold_count DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':sid', currentSiteId(), \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getRecentKeywords(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare("
            SELECT keyword, COUNT(*) as cnt
            FROM scraper_configs
            WHERE site_id = :sid AND keyword != '__trending__'
            GROUP BY keyword
            ORDER BY cnt DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':sid', currentSiteId(), \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function buildKeywordPrompt(array $topProducts, int $count): string
    {
        $productsList = "Top sản phẩm đang bán chạy:\n";
        foreach ($topProducts as $i => $p) {
            $productsList .= ($i + 1) . ". {$p['product_name']} - ";
            $productsList .= number_format((float)$p['price']) . "đ - ";
            $productsList .= number_format((int)$p['sold_count']) . " đã bán\n";
        }

        $recentKw = $this->getRecentKeywords();
        $recentList = "Từ khóa đã dùng gần đây:\n";
        foreach ($recentKw as $kw) {
            $recentList .= "- {$kw['keyword']} (đã dùng {$kw['cnt']} lần)\n";
        }

        return <<<PROMPT
Bạn là chuyên gia phân tích thị trường affiliate Việt Nam.

{$productsList}

{$recentList}

Hãy gợi ý {$count} từ khóa MỚI (chưa có ở trên hoặc khác biệt) để tìm sản phẩm tiềm năng trên Shopee.
Quan tâm: sản phẩm đang có nhu cầu cao, ít cạnh tranh, phù hợp thị trường Việt Nam.

Trả về theo format, mỗi dòng 1 từ khóa:
[KEYWORD] | [lý do ngắn gọn]

 Ví dụ:
tai nghe bluetooth không dây | nhu cầu cao, ít dây trending
bàn phím cơ gaming | thị trường game lớn, ít cạnh tranh gay

Chỉ trả về {$count} dòng, không giải thích thêm.
PROMPT;
    }

    private function callAI(string $prompt): string
    {
        $provider = new OpenAIContentProvider('cx/gpt-5.5', openai_base_url(), openai_api_key());
        $response = $provider->generate(['prompt' => $prompt]);

        if (empty($response['content'])) {
            return '';
        }

        $content = $response['content'];
        // Strip markdown code blocks
        $content = trim(preg_replace('/^(```.*?```)/s', '', $content));
        $content = trim($content);
        return $content;
    }

    private function parseAIResponse(string $response, int $count): array
    {
        $lines = explode("\n", trim($response));
        $keywords = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Parse "keyword | reason" format
            $parts = array_map('trim', explode('|', $line));
            $keyword = trim($parts[0], '- *"\' ');
            $keyword = preg_replace('/^[\-\*\s]+/', '', $keyword);

            if ($keyword !== '' && mb_strlen($keyword) >= 2) {
                $reason = $parts[1] ?? '';
                $keywords[] = [
                    'keyword' => $keyword,
                    'reason' => trim($reason),
                ];
            }

            if (count($keywords) >= $count) {
                break;
            }
        }

        return $keywords;
    }

    private function getFallbackKeywords(): array
    {
        return [
            ['keyword' => 'tai nghe bluetooth', 'reason' => 'Nhu cầu cao, xu hướng không dây'],
            ['keyword' => 'bàn phím cơ gaming', 'reason' => 'Thị trường game lớn'],
            ['keyword' => 'son môi', 'reason' => 'Mỹ phẩm hot constant'],
            ['keyword' => 'máy xay sinh tố', 'reason' => 'Thiết bị nhà bếp tiện lợi'],
            ['keyword' => 'balo laptop', 'reason' => 'Học sinh sinh viên, văn phòng'],
        ];
    }
}