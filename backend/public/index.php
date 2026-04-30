<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$productSyncService = new ProductSyncService();
$taskLogService = new TaskLogService();
$linkService = new AffiliateLinkService();
$contentService = new ContentService();
$postingService = new PostingService();
$automationSettingsService = new AutomationSettingsService();
$integrationConfigService = new IntegrationConfigService();
$scraperService = new ScraperService();

// ── URL Parsing ──
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
$basePath = $basePath === '/' ? '' : $basePath;
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = $basePath !== '' && strpos($requestPath, $basePath) === 0
    ? substr($requestPath, strlen($basePath))
    : $requestPath;
$path = $path === '' ? '/' : $path;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ══════════════════════════════════════════
//  API ENDPOINTS (JSON only, no layout)
// ══════════════════════════════════════════

if ($path === '/health') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Affiliate MVP backend is running', 'data' => ['app' => APP_NAME, 'env' => APP_ENV, 'time' => date('c')]], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/api/products') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $productSyncService->allProducts()], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/api/links') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $linkService->allLinks()], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/api/contents') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $contentService->allContents()], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/api/posts') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $postingService->allPosts()], JSON_UNESCAPED_UNICODE);
    exit;
}

// ══════════════════════════════════════════
//  POST ACTION HANDLERS
//  Always return JSON (consumed by AJAX)
// ══════════════════════════════════════════

if ($method === 'POST') {
    $handled = true;

    try {
        switch ($path) {
            case '/sync/manual':
                $platform = trim((string)($_POST['platform'] ?? 'affiliate_api'));
                $products = json_decode(trim((string)($_POST['products_json'] ?? '')), true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($products)) throw new InvalidArgumentException('Payload phải là mảng JSON.');
                $result = $productSyncService->syncBatch($platform, $products);
                json_response(true, 'Đã đồng bộ ' . $result['summary']['received_count'] . ' sản phẩm. Thêm mới: ' . $result['summary']['inserted_count'] . ', cập nhật: ' . $result['summary']['updated_count'] . '.');
                break;

            case '/links/generate':
                $productId = (int)($_POST['product_id'] ?? 0);
                $campaignCode = trim((string)($_POST['campaign_code'] ?? 'MVP-LAPTOP'));
                $linkService->generateForProduct($productId, $campaignCode !== '' ? $campaignCode : 'MVP-LAPTOP');
                json_response(true, 'Đã tạo affiliate link cho sản phẩm #' . $productId . '.');
                break;

            case '/links/generate-all':
                $campaignCode = trim((string)($_POST['campaign_code'] ?? 'MVP-LAPTOP'));
                $limit = max(1, min(20, (int)($_POST['limit'] ?? 5)));
                $result = $linkService->generateForEligibleProducts($campaignCode !== '' ? $campaignCode : 'MVP-LAPTOP', $limit);
                json_response(true, 'Đã tạo ' . $result['count'] . ' affiliate link tự động.');
                break;

            case '/contents/generate':
                $productId = (int)($_POST['product_id'] ?? 0);
                $provider = trim((string)($_POST['provider'] ?? 'template_engine'));
                $contentService->generateDraftForProduct($productId, $provider !== '' ? $provider : 'template_engine');
                json_response(true, 'Đã sinh draft content cho sản phẩm #' . $productId . '.');
                break;

            case '/contents/generate-all':
                $provider = trim((string)($_POST['provider'] ?? 'template_engine'));
                $limit = max(1, min(20, (int)($_POST['limit'] ?? 5)));
                $result = $contentService->generateForEligibleProducts($limit, $provider !== '' ? $provider : 'template_engine');
                json_response(true, 'Đã sinh ' . $result['count'] . ' draft content.');
                break;

            case '/contents/approve':
                $contentId = (int)($_POST['content_id'] ?? 0);
                $contentService->approveContent($contentId);
                json_response(true, 'Đã approve content #' . $contentId . '.');
                break;

            case '/contents/reject':
                $contentId = (int)($_POST['content_id'] ?? 0);
                $contentService->rejectContent($contentId);
                json_response(true, 'Đã reject content #' . $contentId . '.');
                break;

            case '/posts/schedule':
                $contentId = (int)($_POST['content_id'] ?? 0);
                $channel = trim((string)($_POST['channel'] ?? 'fanpage_manual'));
                $scheduledAt = trim((string)($_POST['scheduled_at'] ?? ''));
                $postingService->schedulePost($contentId, $channel !== '' ? $channel : 'fanpage_manual', $scheduledAt);
                json_response(true, 'Đã schedule bài đăng cho content #' . $contentId . '.');
                break;

            case '/posts/schedule-all':
                $channel = trim((string)($_POST['channel'] ?? 'fanpage_manual'));
                $limit = max(1, min(20, (int)($_POST['limit'] ?? 5)));
                $result = $postingService->scheduleForApprovedContents($limit, $channel !== '' ? $channel : 'fanpage_manual');
                json_response(true, 'Đã tạo lịch đăng cho ' . $result['count'] . ' bài.');
                break;

            case '/posts/mark-success':
                $postId = (int)($_POST['post_id'] ?? 0);
                $resultNote = trim((string)($_POST['result_note'] ?? ''));
                $postingService->markPosted($postId, $resultNote);
                json_response(true, 'Đã đánh dấu bài #' . $postId . ' là đã đăng.');
                break;

            case '/posts/mark-failed':
                $postId = (int)($_POST['post_id'] ?? 0);
                $resultNote = trim((string)($_POST['result_note'] ?? ''));
                $postingService->markFailed($postId, $resultNote);
                json_response(true, 'Đã đánh dấu bài #' . $postId . ' là thất bại.');
                break;

            case '/settings/automation':
                $settings = $automationSettingsService->save($_POST);
                json_response(true, 'Đã lưu cấu hình tự động hóa.', ['data' => $settings]);
                break;

            case '/settings/integrations':
                $integrations = $integrationConfigService->save($_POST);
                json_response(true, 'Đã lưu cấu hình API/tài khoản. Nếu vừa đổi key, reload trang để cập nhật trạng thái.', ['data' => array_keys($integrations)]);
                break;

            // ── Scraper actions ──
            case '/scraper/save-config':
                $configId = $scraperService->saveConfig($_POST);
                json_response(true, 'Đã lưu cấu hình cào #' . $configId . '.');
                break;

            case '/scraper/delete-config':
                $configId = (int)($_POST['config_id'] ?? 0);
                $scraperService->deleteConfig($configId);
                json_response(true, 'Đã xóa cấu hình #' . $configId . '.');
                break;

            case '/scraper/run':
                $configId = (int)($_POST['config_id'] ?? 0);
                $result = $scraperService->runScrapeJob($configId);
                $msg = "Đã cào {$result['scraped']} SP, lọc {$result['filtered']} SP bán chạy, đồng bộ {$result['synced']} SP.";
                if (!empty($result['errors'])) $msg .= ' (' . count($result['errors']) . ' lỗi)';
                json_response(true, $msg);
                break;

            case '/scraper/run-all':
                $results = $scraperService->runAllActive();
                $totalSynced = 0;
                foreach ($results as $r) $totalSynced += ($r['result']['synced'] ?? 0);
                json_response(true, 'Đã chạy ' . count($results) . ' cấu hình, đồng bộ tổng ' . $totalSynced . ' SP.');
                break;

            case '/scraper/trending':
                $platform = trim((string)($_POST['platform'] ?? 'shopee'));
                $minSold = max(0, (int)($_POST['min_sold_count'] ?? 100));
                $maxPages = max(1, min(3, (int)($_POST['max_pages'] ?? 2)));
                $categoryIds = array_map('intval', (array)($_POST['category_ids'] ?? []));
                $result = $scraperService->scrapeTrending($platform, $categoryIds, $minSold, $maxPages);
                $msg = "Đã cào {$result['scraped']} SP trending, lọc {$result['filtered']} SP bán chạy, đồng bộ {$result['synced']} SP.";
                if (!empty($result['errors'])) $msg .= ' (' . count($result['errors']) . ' lỗi)';
                json_response(true, $msg);
                break;

            default:
                $handled = false;
                break;
        }
    } catch (Throwable $throwable) {
        $taskLogService->create($path, 'failed', $_POST, [], $throwable->getMessage());
        json_response(false, $throwable->getMessage());
    }

    if ($handled) exit;
}

// ══════════════════════════════════════════
//  PAGE ROUTES (GET — rendered with layout)
// ══════════════════════════════════════════

$samplePayload = json_encode([
    ['source_product_id' => 'SP-1001', 'product_name' => 'Máy xay mini cầm tay', 'product_url' => 'https://example.com/products/sp-1001', 'price' => 199000, 'sold_count' => 180, 'status' => 'new'],
    ['source_product_id' => 'SP-1002', 'product_name' => 'Đèn ngủ LED để bàn', 'product_url' => 'https://example.com/products/sp-1002', 'price' => 259000, 'sold_count' => 74, 'status' => 'new'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

$automationSettings = $automationSettingsService->get();

switch ($path) {
    case '/':
        render('dashboard/index', [
            'pageTitle'      => 'Dashboard',
            'currentPage'    => 'dashboard',
            'productSummary' => $productSyncService->dashboardSummary(),
            'linkSummary'    => $linkService->summary(),
            'contentSummary' => $contentService->summary(),
            'postSummary'    => $postingService->summary(),
            'recentLogs'     => $taskLogService->recent(5),
            'automationSettings' => $automationSettings,
            'integrationStatus' => $automationSettingsService->integrationStatus(),
            'topSellingProducts' => $productSyncService->topSellingProducts(5, (int)($automationSettings['min_sold_count'] ?? 0)),
        ]);
        break;

    case '/products':
        render('products/index', [
            'pageTitle'      => 'Sản phẩm',
            'currentPage'    => 'products',
            'productSummary' => $productSyncService->dashboardSummary(),
            'products'       => $productSyncService->allProducts(),
            'samplePayload'  => $samplePayload,
            'automationSettings' => $automationSettings,
        ]);
        break;

    case '/links':
        render('links/index', [
            'pageTitle'   => 'Affiliate Links',
            'currentPage' => 'links',
            'linkSummary' => $linkService->summary(),
            'links'       => $linkService->allLinks(),
        ]);
        break;

    case '/contents':
        render('contents/index', [
            'pageTitle'      => 'Nội dung',
            'currentPage'    => 'contents',
            'contentSummary' => $contentService->summary(),
            'contents'       => $contentService->allContents(),
        ]);
        break;

    case '/posts':
        render('posts/index', [
            'pageTitle'   => 'Đăng bài',
            'currentPage' => 'posts',
            'postSummary' => $postingService->summary(),
            'posts'       => $postingService->allPosts(),
        ]);
        break;

    case '/logs':
        render('logs/index', [
            'pageTitle'   => 'Nhật ký',
            'currentPage' => 'logs',
            'logs'        => $taskLogService->recent(50),
        ]);
        break;

    case '/scraper':
        render('scraper/index', [
            'pageTitle'       => 'Cào dữ liệu',
            'currentPage'     => 'scraper',
            'scraperSummary'  => $scraperService->summary(),
            'productSummary'  => $productSyncService->dashboardSummary(),
            'configs'         => $scraperService->allConfigs(),
            'categories'      => $scraperService->getCategories(),
            'topProducts'     => $productSyncService->topSellingProducts(10, 50),
        ]);
        break;

    case '/settings':
        render('settings/index', [
            'pageTitle' => 'Tự động hóa',
            'currentPage' => 'settings',
            'automationSettings' => $automationSettings,
            'integrationStatus' => $automationSettingsService->integrationStatus(),
            'integrationConfig' => $integrationConfigService->masked(),
            'topSellingProducts' => $productSyncService->topSellingProducts(10, 0),
        ]);
        break;

    default:
        http_response_code(404);
        echo '404 — Không tìm thấy trang: ' . e($path);
        break;
}
