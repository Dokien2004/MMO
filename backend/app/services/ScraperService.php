<?php

declare(strict_types=1);

require_once __DIR__ . '/ProductSyncService.php';
require_once __DIR__ . '/TaskLogService.php';

/**
 * ScraperService — Cào dữ liệu sản phẩm bán chạy từ các sàn TMĐT.
 *
 * Hỗ trợ Shopee qua live Chromium/CDP đã đăng nhập.
 *
 * Chế độ Trending: Cào top bán chạy KHÔNG cần nhập từ khóa.
 * Kết quả được đẩy vào ProductSyncService để lưu DB.
 */
final class ScraperService
{
    private ProductSyncService $productSyncService;
    private TaskLogService $taskLogService;
    private PDO $pdo;

    /** Rate limit: tối thiểu N giây giữa mỗi request (cố tình chậm để giống người thật hơn) */
    private float $requestDelay = 8.0;

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
    private string $cloakBrowserScriptPath;
    private static bool $schemaBootstrapped = false;

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
        $this->cloakBrowserScriptPath = BASE_PATH . '/scripts/shopee_cloak_scraper.mjs';
    }

    public static function bootstrapSchema(): void
    {
        if (self::$schemaBootstrapped) {
            return;
        }

        $service = new self();
        $service->ensureConfigTable();
        $service->ensureMarketSnapshotTable();
        self::$schemaBootstrapped = true;
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
            'SELECT * FROM scraper_configs WHERE site_id = ' . currentSiteId() . ' ORDER BY updated_at DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findConfig(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM scraper_configs WHERE id = :id AND site_id = :sid');
        $stmt->execute([':id' => $id, ':sid' => currentSiteId()]);
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
                ':id' => $id, ':sid' => currentSiteId(),
            ]);
            return $id;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO scraper_configs (site_id, keyword, platform, min_sold_count, max_pages, sort_by, is_active)
             VALUES (:sid, :kw, :pl, :ms, :mp, :sb, :ia)'
        );
        $stmt->execute([
            ':sid' => currentSiteId(), ':kw' => $keyword, ':pl' => $platform,
            ':ms' => $minSold, ':mp' => $maxPages, ':sb' => $sortBy, ':ia' => $isActive,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function deleteConfig(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM scraper_configs WHERE id = :id AND site_id = :sid');
        $stmt->execute([':id' => $id, ':sid' => currentSiteId()]);
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

        $platform = 'shopee';
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
            $this->recordMarketSnapshots($syncResult['products'] ?? []);
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
        ], implode('; ', $errors));

        return [
            'scraped' => count($allProducts),
            'filtered' => count($filtered),
            'synced' => $syncResult['summary']['inserted_count'] + $syncResult['summary']['updated_count'],
            'errors' => $errors,
        ];
    }

    /**
     * Cào trending 1 lần (không cần config) — endpoint nhanh cho UI.
     * @param string   $platform    Luôn ép về shopee để dùng live CDP
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
            $this->recordMarketSnapshots($syncResult['products'] ?? []);
        }

        $this->taskLogService->create('scraper_trending', empty($errors) ? 'success' : 'failed', [
            'platform' => $platform, 'categories' => count($categoryIds) ?: 'all',
        ], [
            'scraped' => count($allProducts),
            'filtered' => count($filtered),
            'synced' => $syncResult['summary']['inserted_count'] + $syncResult['summary']['updated_count'],
        ], implode('; ', $errors));

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

    /**
     * Product Radar: dùng dữ liệu đã cào như tín hiệu thị trường, không chỉ lưu sản phẩm thô.
     * Trả về danh sách cơ hội có chấm điểm, nhu cầu, đối tượng và góc content affiliate.
     */
    public function buildProductRadar(int $limit = 12): array
    {
        $limit = max(3, min(30, $limit));
        $products = $this->productSyncService->topSellingProducts($limit, 0);
        $this->recordMarketSnapshots($products);
        $opportunities = [];

        foreach ($products as $product) {
            $opportunities[] = $this->scoreProductOpportunity($product);
        }

        usort($opportunities, static fn(array $a, array $b): int => (int)$b['score'] <=> (int)$a['score']);

        return [
            'generated_at' => date('c'),
            'source' => 'Shopee slow crawl + DB sản phẩm site hiện tại + heuristic affiliate scoring',
            'count' => count($opportunities),
            'opportunities' => $opportunities,
            'github_repos' => $this->githubResearchRepos(),
            'notes' => [
                'Điểm cao không đảm bảo có hoa hồng; cần kiểm tra chính sách Shopee Affiliate và biên lợi nhuận.',
                'Nên chọn sản phẩm có nhu cầu rõ, dễ làm video/review, ít rủi ro hoàn hàng.',
                'Repo/công cụ bên dưới dùng để học ý tưởng phân tích dữ liệu, review/sentiment và crawler — không copy mù quáng vào production.',
            ],
        ];
    }

    private function scoreProductOpportunity(array $product): array
    {
        $name = (string)($product['product_name'] ?? '');
        $lower = mb_strtolower($name, 'UTF-8');
        $sold = (int)($product['sold_count'] ?? 0);
        $price = (float)($product['price'] ?? 0);
        $metrics = $this->marketMetrics($product);

        $runRateScore = $metrics['run_rate_7d'] > 0
            ? min(35, (int)round(log10(max(1, $metrics['run_rate_7d'])) * 14))
            : min(20, (int)round(log10(max(1, $sold)) * 5));
        $demandScore = min(30, (int)round(log10(max(1, $sold)) * 8));
        $priceScore = $price <= 0 ? 10 : ($price >= 30000 && $price <= 350000 ? 20 : ($price < 30000 ? 12 : 14));
        $contentScore = $this->containsAny($lower, ['áo', 'quần', 'túi', 'đèn', 'máy', 'tai nghe', 'bình', 'kệ', 'mỹ phẩm', 'cotton', 'basic', 'cleanfit']) ? 20 : 12;
        $riskPenalty = $this->containsAny($lower, ['fake', 'rep', '1:1', 'thuốc', 'giảm cân', 'trị', 'chữa', 'sex']) ? 18 : 0;
        $reviewPenalty = $metrics['review_signal'] === 'Nghi vấn buff: bán cao nhưng review quá thấp' ? 12 : 0;
        $pricePenalty = $metrics['price_signal'] === 'Biến động giá mạnh / có thể đang flash sale hoặc phá giá' ? 8 : 0;
        $score = max(0, min(100, $runRateScore + $demandScore + $priceScore + $contentScore + 10 - $riskPenalty - $reviewPenalty - $pricePenalty));

        return [
            'product_id' => (int)($product['id'] ?? 0),
            'name' => $name,
            'url' => (string)($product['product_url'] ?? ''),
            'affiliate_url' => (string)($product['affiliate_url'] ?? ''),
            'sold_count' => $sold,
            'price' => $price,
            'score' => $score,
            'run_rate_7d' => $metrics['run_rate_7d'],
            'run_rate_30d' => $metrics['run_rate_30d'],
            'growth_7d' => $metrics['growth_7d'],
            'review_count' => $metrics['review_count'],
            'review_ratio' => $metrics['review_ratio'],
            'review_signal' => $metrics['review_signal'],
            'price_change_pct' => $metrics['price_change_pct'],
            'price_signal' => $metrics['price_signal'],
            'keyword_signal' => $metrics['keyword_signal'],
            'demand' => $this->demandLabel($sold),
            'target_audience' => $this->targetAudience($lower),
            'why_hot' => $this->whyHot($lower, $sold, $price),
            'content_angle' => $this->contentAngle($lower),
            'risk' => $riskPenalty > 0 ? 'Cần kiểm tra kỹ claim/nhãn hiệu/chính sách sàn trước khi quảng bá.' : 'Rủi ro vừa phải; vẫn cần xem review thật và shop uy tín.',
            'next_action' => $score >= 75 ? 'Ưu tiên lấy link affiliate thật/test content video ngắn.' : ($score >= 55 ? 'Theo dõi thêm review/giá và test 1 bài content.' : 'Chưa ưu tiên, chỉ giữ làm ý tưởng.'),
        ];
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($text, $needle)) {
                return true;
            }
        }
        return false;
    }

    private function demandLabel(int $sold): string
    {
        if ($sold >= 100000) return 'Rất cao / mass market';
        if ($sold >= 10000) return 'Cao / đã chứng minh nhu cầu';
        if ($sold >= 1000) return 'Khá / có traction';
        if ($sold >= 100) return 'Ngách nhỏ / cần test thêm';
        return 'Chưa đủ tín hiệu';
    }

    private function targetAudience(string $name): string
    {
        if ($this->containsAny($name, ['áo', 'quần', 'cleanfit', 'cotton', 'basic'])) return 'Nam/nữ 16–30, học sinh/sinh viên/người đi làm thích đồ basic, giá vừa phải.';
        if ($this->containsAny($name, ['mẹ', 'bé', 'trẻ em'])) return 'Phụ huynh trẻ, mẹ bỉm, gia đình có con nhỏ.';
        if ($this->containsAny($name, ['máy', 'điện', 'tai nghe', 'sạc'])) return 'Người dùng công nghệ, dân văn phòng/sinh viên cần đồ tiện ích.';
        if ($this->containsAny($name, ['kệ', 'bếp', 'đèn', 'nhà'])) return 'Người thuê trọ/gia đình trẻ thích tối ưu không gian sống.';
        return 'Người mua phổ thông; cần đọc review để chia nhỏ chân dung khách hàng.';
    }

    private function whyHot(string $name, int $sold, float $price): string
    {
        $signals = [];
        if ($sold >= 10000) $signals[] = 'lượt bán lớn chứng minh nhu cầu thật';
        if ($price > 0 && $price <= 350000) $signals[] = 'giá dễ ra quyết định';
        if ($this->containsAny($name, ['basic', 'cleanfit', 'cotton', 'trơn'])) $signals[] = 'style basic/dễ phối, tệp khách rộng';
        if (empty($signals)) $signals[] = 'có tín hiệu từ dữ liệu crawl nhưng cần kiểm tra thêm review/độ cạnh tranh';
        return implode('; ', $signals) . '.';
    }

    private function contentAngle(string $name): string
    {
        if ($this->containsAny($name, ['áo', 'quần', 'cleanfit', 'cotton'])) return 'Video thử mặc thật: trước/sau khi phối đồ, chất vải, form dáng, giặt có bai không.';
        if ($this->containsAny($name, ['máy', 'tai nghe', 'sạc'])) return 'Video test thực tế: unbox, đo hiệu quả, so sánh trước/sau, lỗi cần biết.';
        if ($this->containsAny($name, ['kệ', 'bếp', 'đèn', 'nhà'])) return 'Video setup góc phòng/bàn làm việc, giải quyết pain-point chật/bừa/thiếu sáng.';
        return 'Bài review ngắn: vấn đề khách gặp → sản phẩm giải quyết → bằng chứng/review → CTA săn deal.';
    }

    private function githubResearchRepos(): array
    {
        return [
            ['name' => 'dtungpka/shopee-scraper', 'url' => 'https://github.com/dtungpka/shopee-scraper', 'use' => 'Tham khảo cách lấy product/review Shopee để bổ sung tín hiệu review.'],
            ['name' => 'AvazAsgarov/streamlit-e-commerce-dashboard', 'url' => 'https://github.com/AvazAsgarov/streamlit-e-commerce-dashboard', 'use' => 'Tham khảo dashboard Streamlit/Pandas cho sales trend, filters, export.'],
            ['name' => 'GbollyAnaltic/ecommerce-dashboard', 'url' => 'https://github.com/GbollyAnaltic/ecommerce-dashboard', 'use' => 'Tham khảo KPI/dashboard realtime cho phân tích sản phẩm và doanh số.'],
            ['name' => 'GitHub topic: ecommerce-analysis', 'url' => 'https://github.com/topics/ecommerce-analysis', 'use' => 'Học mô hình phân tích sales, cohort, category trend cho dashboard.'],
            ['name' => 'GitHub topic: market-research', 'url' => 'https://github.com/topics/market-research', 'use' => 'Tìm ý tưởng framework nghiên cứu thị trường và report tự động.'],
            ['name' => 'CloakHQ/CloakBrowser', 'url' => 'https://github.com/CloakHQ/CloakBrowser', 'use' => 'Đã áp dụng cho crawl chậm, profile bền, giảm verify khi lấy tín hiệu công khai.'],
            ['name' => 'oxylabs/lazada-scraper', 'url' => 'https://github.com/oxylabs/lazada-scraper', 'use' => 'Tham khảo schema dữ liệu marketplace nếu sau này so sánh đa sàn.'],
        ];
    }

    private function marketMetrics(array $product): array
    {
        $productId = (int)($product['id'] ?? 0);
        $sold = (int)($product['sold_count'] ?? 0);
        $price = (float)($product['price'] ?? 0);
        $reviewCount = (int)($product['review_count'] ?? $product['rating_count'] ?? 0);

        $snapshots = $productId > 0 ? $this->snapshotsForProduct($productId, 30) : [];
        $old7 = $this->oldestSnapshotAtLeastDays($snapshots, 7);
        $old30 = $this->oldestSnapshotAtLeastDays($snapshots, 30) ?: (end($snapshots) ?: null);

        $delta7 = $old7 ? max(0, $sold - (int)$old7['sold_count']) : 0;
        $delta30 = $old30 ? max(0, $sold - (int)$old30['sold_count']) : 0;
        $run7 = $old7 ? round($delta7 / max(1, $this->daysBetween((string)$old7['captured_at'], date('c'))), 1) : 0.0;
        $run30 = $old30 ? round($delta30 / max(1, $this->daysBetween((string)$old30['captured_at'], date('c'))), 1) : 0.0;
        $growth7 = $old7 && (int)$old7['sold_count'] > 0 ? round((($sold - (int)$old7['sold_count']) / (int)$old7['sold_count']) * 100, 1) : null;

        $oldPrice = $old30 ? (float)$old30['price'] : 0.0;
        $priceChangePct = ($oldPrice > 0 && $price > 0) ? round((($price - $oldPrice) / $oldPrice) * 100, 1) : null;
        $reviewRatio = $sold > 0 && $reviewCount > 0 ? round(($reviewCount / $sold) * 100, 1) : null;

        return [
            'run_rate_7d' => $run7,
            'run_rate_30d' => $run30,
            'growth_7d' => $growth7,
            'review_count' => $reviewCount,
            'review_ratio' => $reviewRatio,
            'review_signal' => $this->reviewSignal($sold, $reviewCount, $reviewRatio),
            'price_change_pct' => $priceChangePct,
            'price_signal' => $this->priceSignal($priceChangePct),
            'keyword_signal' => 'Chưa nối Google Trends/Shopee suggest; đang dùng tên sản phẩm + sold làm tín hiệu nền.',
        ];
    }

    private function reviewSignal(int $sold, int $reviewCount, ?float $ratio): string
    {
        if ($reviewCount <= 0) return 'Chưa có dữ liệu review; cần crawl review_count để phát hiện buff đơn.';
        if ($sold >= 1000 && ($ratio ?? 0) < 3) return 'Nghi vấn buff: bán cao nhưng review quá thấp';
        if (($ratio ?? 0) >= 8 && ($ratio ?? 0) <= 25) return 'Tỷ lệ review/sold khá tự nhiên';
        return 'Tỷ lệ review/sold cần kiểm tra thêm theo ngành hàng';
    }

    private function priceSignal(?float $priceChangePct): string
    {
        if ($priceChangePct === null) return 'Chưa đủ lịch sử giá; cần gom snapshot vài ngày.';
        if (abs($priceChangePct) >= 25) return 'Biến động giá mạnh / có thể đang flash sale hoặc phá giá';
        if (abs($priceChangePct) >= 10) return 'Giá có biến động vừa, nên theo dõi thêm';
        return 'Giá tương đối ổn định';
    }

    private function snapshotsForProduct(int $productId, int $days): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM product_market_snapshots WHERE site_id = :site_id AND product_id = :product_id AND captured_at >= DATE_SUB(NOW(), INTERVAL :days DAY) ORDER BY captured_at DESC'
        );
        $stmt->bindValue(':site_id', currentSiteId(), PDO::PARAM_INT);
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function oldestSnapshotAtLeastDays(array $snapshots, int $days): ?array
    {
        $threshold = time() - ($days * 86400);
        $fallback = null;
        foreach ($snapshots as $snapshot) {
            $fallback = $snapshot;
            if (strtotime((string)$snapshot['captured_at']) <= $threshold) {
                return $snapshot;
            }
        }
        return $days <= 7 ? $fallback : null;
    }

    private function daysBetween(string $from, string $to): float
    {
        $seconds = max(1, strtotime($to) - strtotime($from));
        return max(1.0, $seconds / 86400);
    }

    private function recordMarketSnapshots(array $products): void
    {
        if (empty($products)) return;
        $stmt = $this->pdo->prepare(
            'INSERT INTO product_market_snapshots (site_id, product_id, source_platform, source_product_id, price, sold_count, review_count, rating, captured_at)
             VALUES (:site_id, :product_id, :source_platform, :source_product_id, :price, :sold_count, :review_count, :rating, NOW())'
        );
        foreach ($products as $product) {
            $productId = (int)($product['id'] ?? 0);
            if ($productId <= 0) continue;
            $stmt->execute([
                ':site_id' => currentSiteId(),
                ':product_id' => $productId,
                ':source_platform' => (string)($product['source_platform'] ?? 'shopee'),
                ':source_product_id' => (string)($product['source_product_id'] ?? ''),
                ':price' => (float)($product['price'] ?? 0),
                ':sold_count' => (int)($product['sold_count'] ?? 0),
                ':review_count' => (int)($product['review_count'] ?? $product['rating_count'] ?? 0),
                ':rating' => (float)($product['rating'] ?? 0),
            ]);
        }
    }

    // ─── Trending Scrapers (Không cần keyword) ─────────

    /**
     * Cào sản phẩm trending theo danh mục — KHÔNG cần từ khóa.
     */
    private function scrapeTrendingProducts(string $platform, int $maxPages, int $minSold, array &$errors, array $categoryIds = []): array
    {
        $allProducts = [];

        if ($platform === 'shopee') {
            // Cào Top bán chạy theo danh mục bằng Chromium/CloakBrowser chậm, ít request.
            // Bỏ Daily Discover vì endpoint này hay kích hoạt traffic verification.
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
            $errors[] = 'Hệ thống hiện chỉ bật cào Shopee qua live CDP.';
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
        $browserError = null;
        try {
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
                $browserError = (string)$browserResult['error'];
            }
        } catch (\Throwable $e) {
            $browserError = $e->getMessage();
        }

        if ($this->shouldUseCloakBrowser() && $browserError !== null) {
            throw new RuntimeException($browserError);
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

        try {
            $body = $this->httpGet($url, [
                'Referer: https://shopee.vn/',
                'Accept: application/json',
                'X-Shopee-Language: vi',
                'X-API-SOURCE: pc',
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException($browserError ?: $e->getMessage());
        }

        $data = json_decode($body, true);
        $items = $data['items'] ?? [];

        $catName = self::SHOPEE_CATEGORIES[$catId] ?? 'Unknown';
        $products = [];
        foreach ($items as $item) {
            $parsed = $this->parseShopeeItem($item['item_basic'] ?? $item, "Shopee [{$catName}]");
            if ($parsed) $products[] = $parsed;
        }
        if (empty($products) && $browserError !== null) {
            throw new RuntimeException($browserError);
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
            'tiki' => $this->scrapeTiki($keyword, $page, $sortBy),
            default => throw new InvalidArgumentException("Platform '{$platform}' chưa được hỗ trợ."),
        };
    }

    private function scrapeShopee(string $keyword, int $page, string $sortBy): array
    {
        $browserError = null;
        try {
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
                $browserError = (string)$browserResult['error'];
            }
        } catch (\Throwable $e) {
            $browserError = $e->getMessage();
        }

        if ($this->shouldUseCloakBrowser() && $browserError !== null) {
            throw new RuntimeException($browserError);
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

        try {
            $body = $this->httpGet($url, [
                'Referer: https://shopee.vn/',
                'Accept: application/json',
                'X-Shopee-Language: vi',
                'X-API-SOURCE: pc',
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException($browserError ?: $e->getMessage());
        }

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

        if (empty($products) && $browserError !== null) {
            throw new RuntimeException($browserError);
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

    private function scrapeTiki(string $keyword, int $page, string $sortBy): array
    {
        return $this->tikiScraperClient->scrapeSearch($keyword, $page, $sortBy, 40);
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
        } elseif (str_contains($host, 'tiki.vn')) {
            $origin = 'https://tiki.vn';
            $referer = 'https://tiki.vn/';
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
        if (!function_exists('proc_open')) {
            return [];
        }

        $useCloakBrowser = $this->shouldUseCloakBrowser();
        $scriptPath = $useCloakBrowser ? $this->cloakBrowserScriptPath : $this->browserScriptPath;
        if (!file_exists($scriptPath)) {
            return [];
        }

        $nodeBinary = $this->findNodeBinary();
        $liveCdpUrl = $useCloakBrowser ? null : $this->discoverLiveCdpUrl();
        $profileDir = trim((string)(getenv('SHOPEE_USER_DATA_DIR') ?: ''));
        if ($profileDir === '') {
            $defaultChromeProfile = rtrim((string)(getenv('HOME') ?: '/home/dokien'), '/') . '/.config/google-chrome';
            $profileDir = is_dir($defaultChromeProfile)
                ? $defaultChromeProfile
                : STORAGE_PATH . '/browser/shopee-profile';
        }
        $payload = json_encode([
            'jobs' => array_values($jobs),
            // Use the user's real Chrome profile by default so a completed login
            // in RustDesk/Chrome can be reused by the scraper. The Node script
            // copies the profile to a temp dir, so it can work even if Chrome is open.
            'userDataDir' => $profileDir,
            // Optional: if SHOPEE_LIVE_CDP_URL is configured, attach to that live browser.
            // Otherwise the Node scraper launches its own Chromium on a free port.
            'cdpUrl' => $liveCdpUrl,
            // Default to visible Chrome because Shopee often blocks headless.
            // Set SHOPEE_HEADLESS=1 only when you explicitly want headless mode.
            'headless' => $useCloakBrowser ? getenv('SHOPEE_CLOAK_HEADLESS') !== '0' : getenv('SHOPEE_HEADLESS') === '1',
            'limit' => (int)(getenv('SHOPEE_CLOAK_LIMIT') ?: 12),
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
            [$nodeBinary, $scriptPath, $payload],
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

    private function shouldUseCloakBrowser(): bool
    {
        $engine = strtolower(trim((string)(getenv('SHOPEE_BROWSER_ENGINE') ?: 'cloak')));
        if (in_array($engine, ['legacy', 'cdp', 'chrome'], true)) {
            return false;
        }
        return file_exists($this->cloakBrowserScriptPath)
            && is_dir(BASE_PATH . '/node_modules/cloakbrowser')
            && is_dir(BASE_PATH . '/node_modules/playwright-core');
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

    public function ensureShopeeLiveBrowser(): ?string
    {
        $existing = $this->discoverLiveCdpUrl();
        if ($existing !== null) {
            return $existing;
        }

        $port = $this->getFreeTcpPort();
        if ($port <= 0) {
            return null;
        }

        $chrome = null;
        foreach (['/opt/google/chrome/chrome', '/usr/bin/google-chrome', '/usr/bin/chromium-browser', '/usr/bin/chromium'] as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                $chrome = $candidate;
                break;
            }
        }
        if ($chrome === null) {
            return null;
        }

        $profileDir = STORAGE_PATH . '/browser/shopee-live-profile';
        if (!is_dir($profileDir)) {
            mkdir($profileDir, 0755, true);
        }

        $logFile = STORAGE_PATH . '/logs/shopee_live_browser.log';
        $display = getenv('DISPLAY') ?: ':1';
        $runtimeDir = getenv('XDG_RUNTIME_DIR') ?: '/run/user/' . getmyuid();

        $args = [
            escapeshellarg($chrome),
            '--remote-debugging-address=127.0.0.1',
            '--remote-debugging-port=' . $port,
            '--user-data-dir=' . escapeshellarg($profileDir),
            '--no-first-run',
            '--no-default-browser-check',
            '--disable-blink-features=AutomationControlled',
            '--disable-popup-blocking',
            '--window-size=1365,900',
            '--lang=vi-VN',
            '--no-sandbox',
            escapeshellarg('https://shopee.vn/'),
        ];

        $cmd = 'DISPLAY=' . escapeshellarg($display)
            . ' XDG_RUNTIME_DIR=' . escapeshellarg($runtimeDir)
            . ' nohup ' . implode(' ', $args)
            . ' > ' . escapeshellarg($logFile) . ' 2>&1 & echo $!';
        @exec($cmd, $out);

        $cdpUrl = 'http://127.0.0.1:' . $port;
        file_put_contents(STORAGE_PATH . '/data/shopee_live_browser.json', json_encode([
            'cdpUrl' => $cdpUrl,
            'profileDir' => $profileDir,
            'pid' => isset($out[0]) ? (int)$out[0] : null,
            'started_at' => date('c'),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

        for ($i = 0; $i < 20; $i++) {
            usleep(250000);
            if ($this->isCdpAlive($cdpUrl)) {
                return $cdpUrl;
            }
        }

        return $cdpUrl;
    }

    private function getFreeTcpPort(): int
    {
        $socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if (!$socket) {
            return 0;
        }
        $name = stream_socket_get_name($socket, false);
        fclose($socket);
        if (is_string($name) && preg_match('/:(\d+)$/', $name, $m)) {
            return (int)$m[1];
        }
        return 0;
    }

    private function discoverLiveCdpUrl(): ?string
    {
        $configured = trim((string)(getenv('SHOPEE_LIVE_CDP_URL') ?: ''));
        if ($configured !== '' && $this->isCdpAlive($configured)) {
            return rtrim($configured, '/');
        }

        $stateFile = STORAGE_PATH . '/data/shopee_live_browser.json';
        if (is_file($stateFile)) {
            $state = json_decode((string)file_get_contents($stateFile), true);
            $stateCdp = is_array($state) ? (string)($state['cdpUrl'] ?? '') : '';
            if ($stateCdp !== '' && $this->isCdpAlive($stateCdp)) {
                return rtrim($stateCdp, '/');
            }
        }

        $out = [];
        @exec("pgrep -af 'remote-debugging-port' 2>/dev/null", $out);
        foreach ($out as $line) {
            if (preg_match('/--remote-debugging-port(?:=|\s+)(\d+)/', $line, $m)) {
                $candidate = 'http://127.0.0.1:' . $m[1];
                if ($this->isCdpAlive($candidate)) {
                    return $candidate;
                }
            }
        }

        // Backward-compatible common ports, but none is required.
        foreach ([19333, 9222, 9223, 9224] as $port) {
            $candidate = 'http://127.0.0.1:' . $port;
            if ($this->isCdpAlive($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function isCdpAlive(string $cdpUrl): bool
    {
        $ch = curl_init(rtrim($cdpUrl, '/') . '/json/version');
        curl_setopt_array($ch, [
            CURLOPT_TIMEOUT => 2,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $httpCode === 200;
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
        $stmt->execute([':res' => $resultJson, ':id' => $configId, ':sid' => currentSiteId()]);
    }

    private function sanitizePlatform(string $platform): string
    {
        return 'shopee';
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

    private function ensureMarketSnapshotTable(): void
    {
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS product_market_snapshots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT NOT NULL DEFAULT 1,
    product_id BIGINT UNSIGNED NOT NULL,
    source_platform VARCHAR(50) NOT NULL,
    source_product_id VARCHAR(100) NOT NULL,
    price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    sold_count INT UNSIGNED NOT NULL DEFAULT 0,
    review_count INT UNSIGNED NOT NULL DEFAULT 0,
    rating DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    captured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_market_snapshots_product_time (site_id, product_id, captured_at),
    KEY idx_market_snapshots_source_time (site_id, source_platform, source_product_id, captured_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function checkShopeeSession(): array
    {
        $cdpUrl = $this->discoverLiveCdpUrl();

        if ($cdpUrl === null) {
            return [
                'alive'       => false,
                'has_session' => false,
                'captcha_required' => false,
                'optional'    => true,
                'message'     => 'Không có live CDP đang chạy — scraper sẽ tự mở Chrome riêng trên cổng ngẫu nhiên khi cào.',
            ];
        }

        // Detect active Shopee verification/captcha tabs. Do NOT treat missing
        // cookies as captcha: SQLite cookie files are encrypted/locked and are
        // not a reliable login signal while Chromium is running.
        $captchaRequired = false;
        $shopeeTabFound = false;
        $tabSummary = [];
        $ch = curl_init($cdpUrl . '/json/list');
        curl_setopt_array($ch, [
            CURLOPT_TIMEOUT => 5,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $tabsRaw = curl_exec($ch);
        $tabsCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($tabsCode === 200 && is_string($tabsRaw) && $tabsRaw !== '') {
            $tabs = json_decode($tabsRaw, true);
            if (is_array($tabs)) {
                foreach ($tabs as $tab) {
                    if (!is_array($tab)) continue;
                    $url = (string)($tab['url'] ?? '');
                    $title = (string)($tab['title'] ?? '');
                    if (str_contains($url, 'shopee.vn')) {
                        $shopeeTabFound = true;
                        $tabSummary[] = trim($title) !== '' ? $title : $url;
                    }

                    $haystack = strtolower($url . ' ' . $title);
                    if (preg_match('#shopee\.vn/.*/?verify/(traffic|captcha)|shopee\.vn/verify/(traffic|captcha)|captcha|xác minh|verify your account|unusual traffic#i', $haystack)) {
                        $captchaRequired = true;
                    }
                }
            }
        }

        return [
            'alive'       => true,
            'has_session' => true,
            'captcha_required' => $captchaRequired,
            'shopee_tab_found' => $shopeeTabFound,
            'tabs' => $tabSummary,
            'message'     => $captchaRequired
                ? 'Shopee đang yêu cầu xác minh captcha/verify.'
                : ($shopeeTabFound ? 'Browser Shopee đang mở, chưa thấy captcha.' : 'Browser CDP đang chạy, chưa thấy tab Shopee bị captcha.'),
        ];
    }
}
