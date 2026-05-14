<?php

declare(strict_types=1);

require_once __DIR__ . '/OpenAIContentProvider.php';

/**
 * ProductScoringService — AI-powered product opportunity scoring.
 *
 * Combines heuristic metrics (sales velocity, price stability, review ratio)
 * with AI analysis to assign a 0-100 score + recommendation to each product.
 *
 * Run daily via cron: workers/score_products_daily.php
 */
final class ProductScoringService
{
    private PDO $pdo;
    private static bool $schemaBootstrapped = false;

    public function __construct()
    {
        $this->pdo = db_pdo();
    }

    public static function bootstrapSchema(): void
    {
        if (self::$schemaBootstrapped) {
            return;
        }

        $service = new self();
        $service->ensureScoresTable();
        self::$schemaBootstrapped = true;
    }

    /**
     * Score all eligible products (those with snapshots).
     * @return array{scored: int, errors: string[]}
     */
    public function scoreAllProducts(int $limit = 50): array
    {
        $siteId = function_exists('currentSiteId') ? currentSiteId() : APP_SITE_ID;

        $stmt = $this->pdo->prepare(
            'SELECT p.*, COUNT(s.id) AS snapshot_count
             FROM affiliate_products p
             LEFT JOIN product_market_snapshots s ON s.product_id = p.id AND s.site_id = p.site_id
             WHERE p.site_id = :site_id AND p.status != \'archived\'
             GROUP BY p.id
             HAVING snapshot_count > 0
             ORDER BY p.sold_count DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':site_id', $siteId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $scored = 0;
        $errors = [];

        foreach ($products as $product) {
            try {
                $this->scoreProduct((int)$product['id'], $product);
                $scored++;
            } catch (\Throwable $e) {
                $errors[] = "Product #{$product['id']}: {$e->getMessage()}";
                error_log("[SCORING] Error scoring product #{$product['id']}: {$e->getMessage()}");
            }
        }

        return ['scored' => $scored, 'errors' => $errors];
    }

    /**
     * Score a single product by ID.
     */
    public function scoreProduct(int $productId, ?array $product = null): array
    {
        $siteId = function_exists('currentSiteId') ? currentSiteId() : APP_SITE_ID;

        if ($product === null) {
            $stmt = $this->pdo->prepare('SELECT * FROM affiliate_products WHERE id = :id AND site_id = :sid');
            $stmt->execute([':id' => $productId, ':sid' => $siteId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) {
                throw new InvalidArgumentException("Product #{$productId} không tồn tại.");
            }
        }

        $metrics = $this->calculateMetrics($productId, $product, $siteId);
        $aiAnalysis = $this->runAIAnalysis($product, $metrics);

        // Combine heuristic + AI scores
        $overallScore = $this->computeOverallScore($metrics, $aiAnalysis);
        $recommendation = $this->getRecommendation($overallScore);
        $trendDirection = $this->getTrendDirection($metrics);

        $scoreData = [
            'site_id' => $siteId,
            'product_id' => $productId,
            'overall_score' => round($overallScore, 2),
            'sales_velocity' => round($metrics['sales_velocity'], 2),
            'price_stability' => round($metrics['price_stability'], 2),
            'review_sentiment' => round($metrics['review_score'], 2),
            'competition_level' => round($metrics['competition_score'], 2),
            'trend_direction' => $trendDirection,
            'ai_analysis' => json_encode($aiAnalysis, JSON_UNESCAPED_UNICODE),
            'recommendation' => $recommendation,
        ];

        $this->upsertScore($scoreData);
        return $scoreData;
    }

    /**
     * Get top recommended products.
     */
    public function getTopRecommendations(int $limit = 20): array
    {
        $siteId = function_exists('currentSiteId') ? currentSiteId() : APP_SITE_ID;

        $stmt = $this->pdo->prepare(
            'SELECT ps.*, ap.product_name, ap.product_url, ap.price, ap.sold_count,
                    ap.source_platform, ap.affiliate_url, ap.status AS product_status
             FROM product_scores ps
             INNER JOIN affiliate_products ap ON ps.product_id = ap.id AND ap.site_id = ps.site_id
             WHERE ps.site_id = :site_id
             ORDER BY ps.overall_score DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':site_id', $siteId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row['ai_analysis'] = json_decode((string)($row['ai_analysis'] ?? '{}'), true) ?: [];
        }
        unset($row);

        return $rows;
    }

    /**
     * Get score for a specific product.
     */
    public function getProductScore(int $productId): ?array
    {
        $siteId = function_exists('currentSiteId') ? currentSiteId() : APP_SITE_ID;

        $stmt = $this->pdo->prepare(
            'SELECT * FROM product_scores WHERE product_id = :pid AND site_id = :sid ORDER BY scored_at DESC LIMIT 1'
        );
        $stmt->execute([':pid' => $productId, ':sid' => $siteId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $row['ai_analysis'] = json_decode((string)($row['ai_analysis'] ?? '{}'), true) ?: [];
        }
        return $row ?: null;
    }

    /**
     * Summary stats for dashboard.
     */
    public function summary(): array
    {
        $siteId = function_exists('currentSiteId') ? currentSiteId() : APP_SITE_ID;

        $stmt = $this->pdo->prepare(
            'SELECT
                COUNT(*) AS total_scored,
                ROUND(AVG(overall_score), 1) AS avg_score,
                SUM(CASE WHEN recommendation = \'strong_buy\' THEN 1 ELSE 0 END) AS strong_buy_count,
                SUM(CASE WHEN recommendation = \'buy\' THEN 1 ELSE 0 END) AS buy_count,
                SUM(CASE WHEN recommendation = \'hold\' THEN 1 ELSE 0 END) AS hold_count,
                SUM(CASE WHEN recommendation = \'avoid\' THEN 1 ELSE 0 END) AS avoid_count,
                SUM(CASE WHEN trend_direction = \'rising\' THEN 1 ELSE 0 END) AS rising_count
             FROM product_scores WHERE site_id = :sid'
        );
        $stmt->execute([':sid' => $siteId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_scored' => 0, 'avg_score' => 0,
            'strong_buy_count' => 0, 'buy_count' => 0,
            'hold_count' => 0, 'avoid_count' => 0,
            'rising_count' => 0,
        ];
    }

    /**
     * Get trend data for charts (snapshots aggregated by day).
     */
    public function getTrendData(int $productId, int $days = 30): array
    {
        $siteId = function_exists('currentSiteId') ? currentSiteId() : APP_SITE_ID;

        $stmt = $this->pdo->prepare(
            'SELECT DATE(captured_at) AS date,
                    AVG(price) AS avg_price,
                    MAX(sold_count) AS max_sold,
                    AVG(rating) AS avg_rating,
                    MAX(review_count) AS max_reviews
             FROM product_market_snapshots
             WHERE site_id = :sid AND product_id = :pid
               AND captured_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY DATE(captured_at)
             ORDER BY date ASC'
        );
        $stmt->bindValue(':sid', $siteId, PDO::PARAM_INT);
        $stmt->bindValue(':pid', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Score distribution for dashboard charts.
     */
    public function getScoreDistribution(): array
    {
        $siteId = function_exists('currentSiteId') ? currentSiteId() : APP_SITE_ID;

        $stmt = $this->pdo->prepare(
            'SELECT
                SUM(CASE WHEN overall_score >= 80 THEN 1 ELSE 0 END) AS excellent,
                SUM(CASE WHEN overall_score >= 60 AND overall_score < 80 THEN 1 ELSE 0 END) AS good,
                SUM(CASE WHEN overall_score >= 40 AND overall_score < 60 THEN 1 ELSE 0 END) AS average,
                SUM(CASE WHEN overall_score < 40 THEN 1 ELSE 0 END) AS poor
             FROM product_scores WHERE site_id = :sid'
        );
        $stmt->execute([':sid' => $siteId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['excellent' => 0, 'good' => 0, 'average' => 0, 'poor' => 0];
    }

    // ─── Private: Metric Calculations ─────────────────

    private function calculateMetrics(int $productId, array $product, int $siteId): array
    {
        $sold = (int)($product['sold_count'] ?? 0);
        $price = (float)($product['price'] ?? 0);

        // Get snapshots for 30 days
        $stmt = $this->pdo->prepare(
            'SELECT * FROM product_market_snapshots
             WHERE site_id = :sid AND product_id = :pid
             ORDER BY captured_at ASC'
        );
        $stmt->execute([':sid' => $siteId, ':pid' => $productId]);
        $snapshots = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Sales velocity (units/day over last 7 days)
        $salesVelocity = $this->calcSalesVelocity($snapshots, $sold);

        // Price stability (0-100, higher = more stable)
        $priceStability = $this->calcPriceStability($snapshots, $price);

        // Review score (based on ratio and count)
        $reviewScore = $this->calcReviewScore($snapshots, $sold);

        // Competition estimate (based on price point and category signals)
        $competitionScore = $this->calcCompetitionScore($product);

        // Growth rate 7d
        $growth7d = $this->calcGrowth($snapshots, $sold, 7);

        return [
            'sales_velocity' => $salesVelocity,
            'price_stability' => $priceStability,
            'review_score' => $reviewScore,
            'competition_score' => $competitionScore,
            'growth_7d' => $growth7d,
            'snapshot_count' => count($snapshots),
            'sold' => $sold,
            'price' => $price,
        ];
    }

    private function calcSalesVelocity(array $snapshots, int $currentSold): float
    {
        if (count($snapshots) < 2) {
            return 0.0;
        }

        $oldest = end($snapshots);
        $sevenDaysAgo = null;
        $threshold = time() - (7 * 86400);

        foreach ($snapshots as $snap) {
            if (strtotime((string)$snap['captured_at']) <= $threshold) {
                $sevenDaysAgo = $snap;
            }
        }

        $reference = $sevenDaysAgo ?: $snapshots[0];
        $refSold = (int)($reference['sold_count'] ?? 0);
        $delta = max(0, $currentSold - $refSold);
        $days = max(1, (time() - strtotime((string)$reference['captured_at'])) / 86400);

        return round($delta / $days, 2);
    }

    private function calcPriceStability(array $snapshots, float $currentPrice): float
    {
        if (count($snapshots) < 2 || $currentPrice <= 0) {
            return 50.0; // neutral
        }

        $prices = array_map(fn($s) => (float)($s['price'] ?? 0), $snapshots);
        $prices = array_filter($prices, fn($p) => $p > 0);
        if (empty($prices)) return 50.0;

        $avg = array_sum($prices) / count($prices);
        $variance = array_sum(array_map(fn($p) => pow($p - $avg, 2), $prices)) / count($prices);
        $cv = $avg > 0 ? sqrt($variance) / $avg : 0; // coefficient of variation

        // CV < 0.05 = very stable (100), CV > 0.3 = very unstable (0)
        return max(0, min(100, round(100 - ($cv * 333), 2)));
    }

    private function calcReviewScore(array $snapshots, int $sold): float
    {
        if (empty($snapshots)) return 30.0;

        $latestReviews = 0;
        foreach (array_reverse($snapshots) as $snap) {
            $latestReviews = (int)($snap['review_count'] ?? 0);
            if ($latestReviews > 0) break;
        }

        if ($latestReviews <= 0) return 30.0;

        $ratio = $sold > 0 ? ($latestReviews / $sold) * 100 : 0;

        // Natural review ratio is 5-20%
        if ($ratio >= 5 && $ratio <= 25) return 80.0;
        if ($ratio > 25) return 60.0; // suspiciously high
        if ($ratio < 3 && $sold >= 1000) return 20.0; // likely fake sales
        return 50.0;
    }

    private function calcCompetitionScore(array $product): float
    {
        $price = (float)($product['price'] ?? 0);
        // Low-price items are more competitive
        if ($price < 50000) return 30.0;   // very competitive
        if ($price < 200000) return 50.0;  // moderately competitive
        if ($price < 500000) return 65.0;
        return 75.0; // less competition at higher prices
    }

    private function calcGrowth(array $snapshots, int $currentSold, int $days): float
    {
        if (count($snapshots) < 2) return 0.0;

        $threshold = time() - ($days * 86400);
        $reference = null;
        foreach ($snapshots as $snap) {
            if (strtotime((string)$snap['captured_at']) <= $threshold) {
                $reference = $snap;
            }
        }

        if (!$reference) {
            $reference = $snapshots[0];
        }

        $refSold = (int)($reference['sold_count'] ?? 0);
        if ($refSold <= 0) return 0.0;

        return round((($currentSold - $refSold) / $refSold) * 100, 2);
    }

    // ─── Private: AI Analysis ─────────────────────────

    private function runAIAnalysis(array $product, array $metrics): array
    {
        // Check if AI is available
        $apiKey = openai_api_key();
        if ($apiKey === '' && openai_base_url() === 'https://api.openai.com/v1') {
            // Fallback: heuristic-only analysis
            return $this->heuristicAnalysis($product, $metrics);
        }

        try {
            $prompt = $this->buildScoringPrompt($product, $metrics);
            $payload = [
                'model' => openai_model(),
                'temperature' => 0.3,
                'max_tokens' => 500,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Ban la chuyen gia phan tich san pham affiliate. Tra ve JSON voi key: ai_score (0-100), reasoning (string ngan), market_insight (string), risk_factors (array string).'
                    ],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ];

            $ch = curl_init(openai_base_url() . '/chat/completions');
            $headers = ['Content-Type: application/json'];
            if ($apiKey !== '') {
                $headers[] = 'Authorization: Bearer ' . $apiKey;
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_TIMEOUT => 30,
            ]);

            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false || $httpCode >= 400) {
                return $this->heuristicAnalysis($product, $metrics);
            }

            $decoded = json_decode($response, true);
            $content = $decoded['choices'][0]['message']['content'] ?? '';
            $result = json_decode($content, true);

            if (is_array($result) && isset($result['ai_score'])) {
                return $result;
            }

            return $this->heuristicAnalysis($product, $metrics);
        } catch (\Throwable $e) {
            error_log("[SCORING] AI analysis failed: {$e->getMessage()}");
            return $this->heuristicAnalysis($product, $metrics);
        }
    }

    private function heuristicAnalysis(array $product, array $metrics): array
    {
        $sold = $metrics['sold'];
        $velocity = $metrics['sales_velocity'];
        $stability = $metrics['price_stability'];

        $reasons = [];
        if ($velocity > 50) $reasons[] = 'Tốc độ bán rất cao';
        elseif ($velocity > 10) $reasons[] = 'Tốc độ bán khá tốt';
        else $reasons[] = 'Tốc độ bán trung bình';

        if ($stability > 80) $reasons[] = 'Giá ổn định';
        elseif ($stability < 40) $reasons[] = 'Giá biến động mạnh';

        $aiScore = min(100, max(0,
            ($velocity > 0 ? min(35, log10(max(1, $velocity)) * 20) : 10)
            + ($stability * 0.25)
            + ($metrics['review_score'] * 0.15)
            + ($sold >= 10000 ? 15 : ($sold >= 1000 ? 10 : 5))
        ));

        return [
            'ai_score' => round($aiScore, 1),
            'reasoning' => implode('. ', $reasons) . '.',
            'market_insight' => $sold >= 10000
                ? 'Sản phẩm đã chứng minh nhu cầu mass-market.'
                : 'Cần theo dõi thêm dữ liệu để đánh giá rõ hơn.',
            'risk_factors' => $stability < 40
                ? ['Giá biến động có thể là flash sale hoặc phá giá']
                : [],
        ];
    }

    private function buildScoringPrompt(array $product, array $metrics): string
    {
        $price = number_format($metrics['price'], 0, ',', '.');
        return implode("\n", [
            'Phan tich san pham affiliate va cho diem tiem nang:',
            '- Ten: ' . ($product['product_name'] ?? ''),
            '- Gia: ' . $price . ' VND',
            '- Luot ban: ' . number_format($metrics['sold']),
            '- Toc do ban/ngay: ' . $metrics['sales_velocity'],
            '- Tang truong 7 ngay: ' . $metrics['growth_7d'] . '%',
            '- On dinh gia: ' . $metrics['price_stability'] . '/100',
            '- Diem review: ' . $metrics['review_score'] . '/100',
            '- So snapshots: ' . $metrics['snapshot_count'],
            '',
            'Cho diem 0-100 va giai thich ngan gon.',
        ]);
    }

    // ─── Private: Score Computation ───────────────────

    private function computeOverallScore(array $metrics, array $aiAnalysis): float
    {
        $aiScore = (float)($aiAnalysis['ai_score'] ?? 50);

        // Weighted combination: 40% AI + 25% velocity + 15% stability + 10% reviews + 10% competition
        $velocityNorm = min(100, $metrics['sales_velocity'] > 0 ? log10(max(1, $metrics['sales_velocity'])) * 50 : 0);

        return min(100, max(0,
            ($aiScore * 0.40)
            + ($velocityNorm * 0.25)
            + ($metrics['price_stability'] * 0.15)
            + ($metrics['review_score'] * 0.10)
            + ($metrics['competition_score'] * 0.10)
        ));
    }

    private function getRecommendation(float $score): string
    {
        if ($score >= 80) return 'strong_buy';
        if ($score >= 60) return 'buy';
        if ($score >= 35) return 'hold';
        return 'avoid';
    }

    private function getTrendDirection(array $metrics): string
    {
        $growth = $metrics['growth_7d'];
        if ($growth > 10) return 'rising';
        if ($growth < -10) return 'declining';
        return 'stable';
    }

    // ─── Private: DB Operations ───────────────────────

    private function upsertScore(array $data): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO product_scores
                (site_id, product_id, overall_score, sales_velocity, price_stability,
                 review_sentiment, competition_level, trend_direction, ai_analysis, recommendation, scored_at)
             VALUES
                (:site_id, :product_id, :overall_score, :sales_velocity, :price_stability,
                 :review_sentiment, :competition_level, :trend_direction, :ai_analysis, :recommendation, NOW())
             ON DUPLICATE KEY UPDATE
                overall_score = VALUES(overall_score),
                sales_velocity = VALUES(sales_velocity),
                price_stability = VALUES(price_stability),
                review_sentiment = VALUES(review_sentiment),
                competition_level = VALUES(competition_level),
                trend_direction = VALUES(trend_direction),
                ai_analysis = VALUES(ai_analysis),
                recommendation = VALUES(recommendation),
                scored_at = NOW(),
                updated_at = NOW()'
        );
        $stmt->execute($data);
    }

    private function ensureScoresTable(): void
    {
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS product_scores (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT NOT NULL DEFAULT 1,
    product_id BIGINT UNSIGNED NOT NULL,
    overall_score DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT '0-100 overall score',
    sales_velocity DECIMAL(10,2) DEFAULT 0 COMMENT 'Units sold per day',
    price_stability DECIMAL(5,2) DEFAULT 0 COMMENT 'Price stability 0-100',
    review_sentiment DECIMAL(5,2) DEFAULT 0 COMMENT 'Review sentiment 0-100',
    competition_level DECIMAL(5,2) DEFAULT 0 COMMENT 'Competition level 0-100',
    trend_direction ENUM('rising','stable','declining') DEFAULT 'stable',
    ai_analysis TEXT DEFAULT NULL COMMENT 'AI reasoning JSON',
    recommendation ENUM('strong_buy','buy','hold','avoid') DEFAULT 'hold',
    scored_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_scores_site_product (site_id, product_id),
    KEY idx_scores_overall (site_id, overall_score DESC),
    KEY idx_scores_recommendation (site_id, recommendation),
    KEY idx_scores_trend (site_id, trend_direction),
    KEY idx_scores_scored_at (scored_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        // Add columns to snapshots if missing
        $this->ensureColumn('product_market_snapshots', 'category', 'VARCHAR(100) DEFAULT NULL AFTER rating');
        $this->ensureColumn('product_market_snapshots', 'shop_name', 'VARCHAR(200) DEFAULT NULL AFTER category');
        $this->ensureColumn('product_market_snapshots', 'shop_rating', 'DECIMAL(3,2) DEFAULT 0 AFTER shop_name');
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
        );
        $stmt->execute([':table_name' => $table, ':column_name' => $column]);

        if ((int)$stmt->fetchColumn() > 0) {
            return;
        }

        $this->pdo->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
    }
}
