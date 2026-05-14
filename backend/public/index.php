<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

// ── URL Parsing ──
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = parse_url($requestUri, PHP_URL_PATH) ?: '/';

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$phpSelf = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
$knownPrefix = '/affiliate-mvp-laptop/backend/public';

$baseCandidates = array_values(array_unique(array_filter([
    rtrim(str_replace('\\', '/', dirname($scriptName)), '/'),
    rtrim(str_replace('\\', '/', dirname($phpSelf)), '/'),
    strpos($requestPath, $knownPrefix) === 0 ? $knownPrefix : '',
])));

$basePath = '';
foreach ($baseCandidates as $candidate) {
    if ($candidate !== '' && ($requestPath === $candidate || strpos($requestPath, $candidate . '/') === 0)) {
        $basePath = $candidate;
        break;
    }
}

$path = $basePath !== '' && strpos($requestPath, $basePath) === 0
    ? substr($requestPath, strlen($basePath))
    : $requestPath;
$path = '/' . ltrim((string)$path, '/');
$path = $path === '/index.php' ? '/' : $path;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ── CSRF token (generate once per session) ──
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ══════════════════════════════════════════
//  AUTH: Public routes (no login required)
// ══════════════════════════════════════════
$publicRoutes = ['/login', '/health'];
$isPublicRoute = in_array($path, $publicRoutes, true);

// ── Login page (GET/HEAD) ──
if ($path === '/login' && in_array($method, ['GET', 'HEAD'], true)) {
    if (isLoggedIn()) {
        redirect_to('/');
    }
    if ($method === 'HEAD') {
        http_response_code(200);
        exit;
    }
    // Render standalone login page (no layout)
    $error = null;
    require APP_VIEWS_PATH . '/auth/login.php';
    exit;
}

// ── Login handler (POST) ──
if ($path === '/login' && $method === 'POST') {
    verify_csrf();

    $authService = new AuthService();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    // Check lock
    $lockSeconds = $authService->getRemainingLockSeconds($username);
    if ($lockSeconds > 0) {
        $minutes = (int)ceil($lockSeconds / 60);
        $error = "Tài khoản bị khóa tạm thời. Vui lòng thử lại sau {$minutes} phút.";
        require APP_VIEWS_PATH . '/auth/login.php';
        exit;
    }

    $user = $authService->attempt($username, $password);

    if ($user === null) {
        $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
        require APP_VIEWS_PATH . '/auth/login.php';
        exit;
    }

    $authService->createSession($user);

    // Redirect to original page (if saved)
    $redirectTo = $_SESSION['redirect_after_login'] ?? '/';
    unset($_SESSION['redirect_after_login']);
    redirect_to($redirectTo);
}

// ── Logout ──
if ($path === '/logout') {
    $authService = new AuthService();
    $authService->logout();
    // Start new session for flash message
    session_start();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    set_flash('success', 'Đã đăng xuất thành công.');
    redirect_to('/login');
}

// ── Health check (public, no auth) ──
if ($path === '/health') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Affiliate MVP backend is running', 'data' => ['app' => APP_NAME, 'env' => APP_ENV, 'time' => date('c')]], JSON_UNESCAPED_UNICODE);
    exit;
}

// ══════════════════════════════════════════
//  AUTH MIDDLEWARE: All routes below require login
// ══════════════════════════════════════════
requireAuth();

// ── Initialize services (only for authenticated requests) ──
$productSyncService = new ProductSyncService();
$userSelectedProductService = new UserSelectedProductService();
$taskLogService = new TaskLogService();
$siteService = new SiteService();
$linkService = new AffiliateLinkService();
$contentService = new ContentService();
$imageMediaService = new ImageMediaService();
$videoMediaService = new VideoMediaService();
$postingService = new PostingService();
$automationSettingsService = new AutomationSettingsService();
$integrationConfigService = new IntegrationConfigService();
$scraperService = new ScraperService();
$pendingScrapeJobService = new PendingScrapeJobService();
$scoringService = new ProductScoringService();
$userProductService = new UserProductService();
$channelService = new SocialChannelService();

function queueScrapeIntervention(
    PendingScrapeJobService $pendingScrapeJobService,
    ScraperService $scraperService,
    string $type,
    array $payload,
    string $reason
): array {
    $job = $pendingScrapeJobService->create($type, $payload);
    $telegram = new TelegramService();
    if ($telegram->isConfigured()) {
        $cdpUrl = $scraperService->ensureShopeeLiveBrowser();
        file_put_contents(
            STORAGE_PATH . '/data/telegram_scraper_state.json',
            json_encode(['offset' => $telegram->latestUpdateOffset()], JSON_PRETTY_PRINT),
            LOCK_EX
        );
        $srvInfo = (new ServerInfoService())->get();
        $telegram->sendScrapeInterventionRequest(
            $job['id'],
            $srvInfo['rustdesk_id'] ?? '—',
            defined('RUSTDESK_PASSWORD') ? RUSTDESK_PASSWORD : '',
            $reason . ($cdpUrl ? ' Chrome chuyên dụng: ' . $cdpUrl : '')
        );
        @exec(PHP_BINARY . ' ' . escapeshellarg(BASE_PATH . '/scripts/scraper_telegram_worker.php') . ' > ' . escapeshellarg(STORAGE_PATH . '/logs/scraper_telegram_worker.out.log') . ' 2>&1 &');
    }

    return $job;
}

function queueScrapeAuto(PendingScrapeJobService $pendingScrapeJobService, string $type, array $payload): array
{
    $job = $pendingScrapeJobService->create($type, $payload, 'queued');
    @exec(PHP_BINARY . ' ' . escapeshellarg(BASE_PATH . '/scripts/scraper_telegram_worker.php') . ' > ' . escapeshellarg(STORAGE_PATH . '/logs/scraper_telegram_worker.out.log') . ' 2>&1 &');
    return $job;
}

function isShopeeInterventionError(string $message): bool
{
    return preg_match('/captcha|verification|verify\/traffic|verify\/captcha|Shopee verification|Page Unavailable|Please go back|đăng nhập lại|login/i', $message) === 1;
}

// ══════════════════════════════════════════
//  MODULE CHECK: Route → required module
// ══════════════════════════════════════════
$routeModuleMap = [
    '/scraper'  => 'SCRAPER',  '/products' => 'PRODUCTS',
    '/links'    => 'LINKS',    '/contents' => 'CONTENTS',
    '/posts'    => 'POSTS',    '/settings' => 'SETTINGS',
    '/logs'     => 'LOGS',
    '/server-info' => 'SERVER_INFO',
    '/admin/modules' => 'ADMIN', '/admin/permissions' => 'ADMIN',
    '/admin/users'   => 'ADMIN',
    '/admin/sites'   => 'ADMIN',
];

$requiredModule = $routeModuleMap[$path] ?? null;
if ($requiredModule && !isModuleEnabled($requiredModule)) {
    requireModule($requiredModule); // Will exit with 403
}

$getPermissionMap = [
    '/scraper'  => 'scraper.view',
    '/products' => 'products.view',
    '/links'    => 'links.view',
    '/contents' => 'contents.view',
    '/posts'    => 'posts.view',
    '/settings' => 'settings.view',
    '/logs'     => 'logs.view',
];

if ($method === 'GET' && isset($getPermissionMap[$path])) {
    requirePermission($getPermissionMap[$path]);
}

// ══════════════════════════════════════════
//  API ENDPOINTS (JSON only, no layout)
// ══════════════════════════════════════════

if ($path === '/api/products') {
    requirePermission('products.view');
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $productSyncService->allProducts()], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/api/shopee-product') {
    requirePermission('products.view');
    header('Content-Type: application/json; charset=utf-8');
    $link = trim((string)($_GET['link'] ?? ''));
    if ($link === '' || !preg_match('#^https://([^/]+\.)?shopee\.vn/#i', $link)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Thiếu hoặc sai link Shopee.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $cdpUrl = $scraperService->ensureShopeeLiveBrowser();
        $payload = json_encode([
            'link' => $link,
            'cdpUrl' => $cdpUrl,
            'userDataDir' => STORAGE_PATH . '/browser/shopee-product-profile',
        ], JSON_UNESCAPED_UNICODE);
        $script = BASE_PATH . '/scripts/shopee_product_detail.js';
        $cmd = [PHP_OS_FAMILY === 'Windows' ? 'node.exe' : 'node', $script, (string)$payload];
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($cmd, $descriptorSpec, $pipes, BASE_PATH, null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new RuntimeException('Không chạy được Shopee product scraper.');
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $stdout = '';
        $stderr = '';
        $deadline = time() + 130;
        while (time() < $deadline) {
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (!$status['running']) break;
            usleep(200000);
        }
        $status = proc_get_status($process);
        if ($status['running']) {
            proc_terminate($process);
            throw new RuntimeException('Shopee product scraper timeout.');
        }
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            throw new RuntimeException(trim($stderr) !== '' ? trim($stderr) : 'Shopee product scraper failed.');
        }
        $data = json_decode(trim($stdout), true, 512, JSON_THROW_ON_ERROR);
        echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $throwable) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $throwable->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($path === '/api/products/import' && $method === 'POST') {
    header('Content-Type: application/json');

    $expectedToken = product_import_token();
    $providedToken = $_SERVER['HTTP_X_IMPORT_TOKEN'] ?? ($_POST['import_token'] ?? '');
    if ($expectedToken === '' || !hash_equals($expectedToken, (string)$providedToken)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized import token.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $payload = json_decode((string)file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
            $platform = trim((string)($payload['platform'] ?? 'shopee'));
            $products = $payload['products'] ?? $payload;
        } else {
            $platform = trim((string)($_POST['platform'] ?? 'shopee'));
            $products = json_decode(trim((string)($_POST['products_json'] ?? '')), true, 512, JSON_THROW_ON_ERROR);
        }

        if (!is_array($products)) {
            throw new InvalidArgumentException('Payload phải là mảng JSON hoặc object có key products.');
        }

        $result = $productSyncService->syncBatch($platform !== '' ? $platform : 'shopee', array_values($products));
        echo json_encode(['success' => true, 'message' => 'Imported products.', 'data' => $result['summary']], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $throwable) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $throwable->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($path === '/api/links') {
    requirePermission('links.view');
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $linkService->allLinks()], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/api/contents') {
    requirePermission('contents.view');
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $contentService->allContents()], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/api/posts') {
    requirePermission('posts.view');
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $postingService->allPosts()], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── API: Product Scores ──
if ($path === '/api/product-scores') {
    requirePermission('products.view');
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $scoringService->getTopRecommendations(30)], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/api/score-distribution') {
    requirePermission('products.view');
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $scoringService->getScoreDistribution()], JSON_UNESCAPED_UNICODE);
    exit;
}

if (preg_match('#^/api/product-trend/(\d+)$#', $path, $m)) {
    requirePermission('products.view');
    header('Content-Type: application/json');
    $days = max(7, min(90, (int)($_GET['days'] ?? 30)));
    echo json_encode(['success' => true, 'data' => $scoringService->getTrendData((int)$m[1], $days)], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── My Products API (single product fetch) ──
if (preg_match('#^/api/my-products/(\d+)$#', $path, $m)) {
    requirePermission('products.view');
    header('Content-Type: application/json');
    $item = $userProductService->findById((int)$m[1]);
    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy sản phẩm.'], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => true, 'data' => $item], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ── My Products dynamic POST routes (update/archive with ID param) ──
if ($method === 'POST' && preg_match('#^/my-products/update/(\d+)$#', $path, $m)) {
    verify_csrf();
    requirePermission('products.view');
    try {
        $updated = $userProductService->update((int)$m[1], $_POST);
        json_response(true, $updated ? 'Đã cập nhật sản phẩm.' : 'Không có thay đổi.');
    } catch (Throwable $e) {
        json_response(false, $e->getMessage());
    }
    exit;
}

if ($method === 'POST' && preg_match('#^/my-products/archive/(\d+)$#', $path, $m)) {
    verify_csrf();
    requirePermission('products.view');
    try {
        $userProductService->archive((int)$m[1]);
        json_response(true, 'Đã lưu trữ sản phẩm.');
    } catch (Throwable $e) {
        json_response(false, $e->getMessage());
    }
    exit;
}

// ── Channel API (single channel fetch) ──
if (preg_match('#^/api/channels/(\d+)$#', $path, $m)) {
    requirePermission('settings.view');
    header('Content-Type: application/json');
    $item = $channelService->findById((int)$m[1]);
    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy kênh.'], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => true, 'data' => $item], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ── Channel dynamic POST routes (update/delete with ID param) ──
if ($method === 'POST' && preg_match('#^/channels/update/(\d+)$#', $path, $m)) {
    verify_csrf();
    requirePermission('settings.edit');
    try {
        $channelService->update((int)$m[1], $_POST);
        json_response(true, 'Đã cập nhật kênh.');
    } catch (Throwable $e) {
        json_response(false, $e->getMessage());
    }
    exit;
}

if ($method === 'POST' && preg_match('#^/channels/delete/(\d+)$#', $path, $m)) {
    verify_csrf();
    requirePermission('settings.edit');
    try {
        $channelService->delete((int)$m[1]);
        json_response(true, 'Đã xóa kênh.');
    } catch (Throwable $e) {
        json_response(false, $e->getMessage());
    }
    exit;
}

// ── Publish to channel (multi-channel) ──
if ($method === 'POST' && preg_match('#^/channels/publish/(\d+)$#', $path, $m)) {
    verify_csrf();
    requirePermission('posts.manage');
    try {
        $channelId = (int)$m[1];
        $contentId = (int)($_POST['content_id'] ?? 0);
        $channel = $channelService->findById($channelId);
        if (!$channel) throw new InvalidArgumentException('Không tìm thấy kênh #' . $channelId);
        if (!$channelService->canPostToday($channelId)) throw new RuntimeException('Kênh đã đạt giới hạn bài/ngày.');
        
        $content = $contentService->findById($contentId);
        if (!$content) throw new InvalidArgumentException('Không tìm thấy content #' . $contentId);
        
        $result = [];
        switch ($channel['channel_type']) {
            case 'facebook_page':
                $fbPublisher = new FacebookPagePublisher();
                $result = $fbPublisher->publish($content, []);
                break;
            case 'facebook_group':
                $fbGroupPublisher = new FacebookGroupPublisher();
                $result = $fbGroupPublisher->publish($content, $channel);
                break;
            case 'tiktok':
                $tiktokPublisher = new TikTokPublisher();
                $result = $tiktokPublisher->publish($content, $channel);
                break;
            default:
                throw new InvalidArgumentException('Loại kênh không được hỗ trợ: ' . $channel['channel_type']);
        }
        
        $channelService->incrementPostCount($channelId);
        $taskLogService->create('channel_publish', 'success', [
            'channel_id' => $channelId,
            'content_id' => $contentId,
            'channel_type' => $channel['channel_type'],
        ], $result);
        
        json_response(true, $result['message'] ?? 'Đã đăng bài thành công.', $result);
    } catch (Throwable $e) {
        json_response(false, $e->getMessage());
    }
    exit;
}

// ══════════════════════════════════════════
//  POST ACTION HANDLERS
//  CSRF validated + permission checked
// ══════════════════════════════════════════

// POST → Permission map
$postPermissionMap = [
    '/scraper/run'           => 'scraper.run',
    '/scraper/run-all'       => 'scraper.run',
    '/scraper/trending'      => 'scraper.run',
    '/scraper/radar'         => 'scraper.run',
    '/scraper/save-config'   => 'scraper.config',
    '/scraper/delete-config' => 'scraper.config',
    '/sync/manual'           => 'products.sync',
    '/products/store'        => 'products.sync',
    '/products/import'       => 'products.sync',
    '/links/generate'        => 'links.generate',
    '/links/generate-all'    => 'links.generate',
    '/contents/generate'     => 'contents.generate',
    '/contents/generate-all' => 'contents.generate',
    '/contents/generate-image' => 'contents.generate',
    '/contents/generate-images' => 'contents.generate',
    '/contents/generate-video' => 'contents.generate',
    '/contents/generate-videos' => 'contents.generate',
    '/contents/approve'      => 'contents.approve',
    '/contents/reject'       => 'contents.approve',
    '/posts/schedule'        => 'posts.schedule',
    '/posts/schedule-all'    => 'posts.schedule',
    '/posts/schedule-selected' => 'posts.schedule',
    '/posts/publish'         => 'posts.manage',
    '/posts/publish-due'     => 'posts.manage',
    '/posts/mark-success'    => 'posts.manage',
    '/posts/mark-failed'     => 'posts.manage',
    '/settings/automation'   => 'settings.edit',
    '/settings/integrations' => 'settings.edit',
    '/settings/check-facebook-token' => 'settings.view',
    '/admin/modules/toggle'  => 'admin.modules',
    '/admin/permissions/save' => 'admin.permissions',
    '/admin/roles/sync'       => 'admin.permissions',
    '/admin/users/store'      => 'admin.users',
    '/admin/users/update'     => 'admin.users',
    '/admin/users/toggle'     => 'admin.users',
    '/admin/users/unlock'     => 'admin.users',
    '/admin/sites/store'     => 'admin.sites',
    '/admin/sites/update'    => 'admin.sites',
    '/admin/sites/toggle'    => 'admin.sites',
    '/admin/sites/change-current' => 'admin.sites',
    '/my-products/add'            => 'products.view',
    '/my-products/pick'           => 'products.view',
    '/my-products/generate-content' => 'products.view',
    '/channels/create'             => 'settings.edit',
];

if ($method === 'POST') {
    // CSRF validation for all POST
    verify_csrf();

    // Permission check
    if (isset($postPermissionMap[$path])) {
        requirePermission($postPermissionMap[$path]);
    }

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

            case '/products/store':
                $product = $productSyncService->createManualProduct($_POST);
                json_response(true, 'Đã lưu sản phẩm thủ công #' . (int)($product['id'] ?? 0) . '.', ['data' => $product]);
                break;

            case '/products/import':
                if (empty($_FILES['product_file']) || !is_array($_FILES['product_file'])) {
                    throw new InvalidArgumentException('Chưa chọn file CSV/XLSX để import.');
                }
                $file = $_FILES['product_file'];
                if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    throw new InvalidArgumentException('Upload file lỗi, mã lỗi: ' . (int)($file['error'] ?? -1));
                }
                $platform = trim((string)($_POST['platform'] ?? 'manual'));
                $result = $productSyncService->importProductsFromFile((string)$file['tmp_name'], (string)$file['name'], $platform);
                json_response(true, 'Đã import ' . $result['summary']['received_count'] . ' sản phẩm. Thêm mới: ' . $result['summary']['inserted_count'] . ', cập nhật: ' . $result['summary']['updated_count'] . '.', ['data' => $result['summary']]);
                break;

            case '/products/select':
                // Thêm/cập nhật sản phẩm vào bảng chọn lọc (user_selected_products)
                $productId = (int)($_POST['product_id'] ?? 0);
                $affiliateUrl = trim((string)($_POST['affiliate_url'] ?? ''));
                $pdo = db_pdo();
                $stmt = $pdo->prepare("SELECT * FROM affiliate_products WHERE id = ? AND site_id = ?");
                $stmt->execute([$productId, (int)currentSiteId()]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$product) {
                    json_response(false, 'Không tìm thấy sản phẩm #' . $productId);
                }
                $data = [
                    'source_product_id' => $product['source_product_id'],
                    'product_name' => $product['product_name'],
                    'product_url' => $product['product_url'],
                    'affiliate_url' => $affiliateUrl,
                    'source_platform' => $product['source_platform'],
                    'price' => $product['price'],
                    'status' => !empty($affiliateUrl) ? 'pending' : 'pending',
                ];
                $saved = $userSelectedProductService->upsert($data);
                json_response(true, 'Đã thêm sản phẩm vào danh sách chọn.');
                break;

            case '/links/generate':
                $productId = (int)($_POST['product_id'] ?? 0);
                $campaignCode = trim((string)($_POST['campaign_code'] ?? 'MVP-LAPTOP'));
                $affiliateUrl = trim((string)($_POST['affiliate_url'] ?? ''));
                $linkService->generateForProduct($productId, $campaignCode !== '' ? $campaignCode : 'MVP-LAPTOP', $affiliateUrl);
                json_response(true, 'Đã lưu affiliate link thật cho sản phẩm #' . $productId . '.');
                break;

            case '/links/generate-all':
                $campaignCode = trim((string)($_POST['campaign_code'] ?? 'MVP-LAPTOP'));
                $limit = max(1, min(20, (int)($_POST['limit'] ?? 5)));
                $result = $linkService->generateForEligibleProducts($campaignCode !== '' ? $campaignCode : 'MVP-LAPTOP', $limit);
                json_response(true, 'Đã đồng bộ ' . $result['count'] . ' affiliate link đã dán sẵn. Link Shopee thật phải lấy từ App Shopee rồi dán vào từng sản phẩm.');
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

            case '/contents/generate-image':
                $contentId = (int)($_POST['content_id'] ?? 0);
                $updated = $imageMediaService->generateForContent($contentId);
                json_response(true, 'Đã tạo ảnh AI cho content #' . $contentId . '.', ['data' => ['media_url' => $updated['media_url'] ?? '']]);
                break;

            case '/contents/generate-video':
                $contentId = (int)($_POST['content_id'] ?? 0);
                $updated = $videoMediaService->generateForContent($contentId);
                json_response(true, 'Đã tạo video sản phẩm cho content #' . $contentId . '.', ['data' => ['media_url' => $updated['media_url'] ?? '']]);
                break;

            case '/contents/generate-videos':
                $limit = max(1, min(20, (int)($_POST['limit'] ?? 5)));
                $result = $videoMediaService->generateForPendingContents($limit);
                $message = 'Đã tạo ' . (int)$result['count'] . ' video sản phẩm.';
                if (!empty($result['errors'])) {
                    $message .= ' Có ' . count($result['errors']) . ' lỗi.';
                }
                json_response((int)$result['count'] > 0, $message, ['data' => $result]);
                break;

            case '/contents/generate-images':
                $limit = max(1, min(20, (int)($_POST['limit'] ?? 5)));
                $lockFile = STORAGE_PATH . '/logs/generate_pending_images.lock';
                if (is_file($lockFile)) {
                    $lockInfo = json_decode((string)file_get_contents($lockFile), true);
                    $pid = (int)($lockInfo['pid'] ?? 0);
                    if ($pid > 0 && function_exists('posix_kill') && @posix_kill($pid, 0)) {
                        json_response(true, 'Đang có job tạo ảnh chạy nền. Vui lòng chờ xong rồi refresh trang.', ['data' => $lockInfo]);
                        break;
                    }
                    @unlink($lockFile);
                }

                $script = BASE_PATH . '/scripts/generate_pending_images.php';
                $logFile = STORAGE_PATH . '/logs/generate_pending_images.out.log';
                $command = 'nohup ' . escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($script) . ' ' . (int)$limit . ' >> ' . escapeshellarg($logFile) . ' 2>&1 & echo $!';
                $pid = trim((string)shell_exec($command));

                $taskLogService->create('generate_pending_images_dispatch', 'started', [
                    'limit' => $limit,
                    'pid' => $pid,
                ]);

                json_response(true, 'Đã đưa ' . $limit . ' ảnh vào hàng đợi tạo nền. Khi xong hệ thống sẽ gửi Telegram thông báo.', ['data' => ['pid' => $pid, 'limit' => $limit]]);
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
                $scheduledAt = trim((string)($_POST['scheduled_at'] ?? ''));
                $intervalMinutes = max(1, min(1440, (int)($_POST['interval_minutes'] ?? 15)));
                $result = $postingService->scheduleForApprovedContents($limit, $channel !== '' ? $channel : 'fanpage_manual', $scheduledAt, $intervalMinutes);
                $message = 'Đã tạo lịch đăng cho ' . $result['count'] . ' bài.';
                if (!empty($result['errors'])) {
                    $message .= ' Có ' . count($result['errors']) . ' lỗi.';
                }
                json_response((int)$result['count'] > 0, $message, ['data' => $result]);
                break;

            case '/posts/schedule-selected':
                $channel = trim((string)($_POST['channel'] ?? 'fanpage_manual'));
                $scheduledAt = trim((string)($_POST['scheduled_at'] ?? ''));
                $intervalMinutes = max(1, min(1440, (int)($_POST['interval_minutes'] ?? 15)));
                $contentIds = $_POST['content_ids'] ?? [];
                if (!is_array($contentIds)) {
                    $contentIds = [$contentIds];
                }
                $result = $postingService->scheduleSelectedContents($contentIds, $channel !== '' ? $channel : 'fanpage_manual', $scheduledAt, $intervalMinutes);
                $message = 'Đã lên lịch ' . (int)$result['count'] . ' bài đã chọn.';
                if (!empty($result['errors'])) {
                    $message .= ' Có ' . count($result['errors']) . ' lỗi.';
                }
                json_response((int)$result['count'] > 0, $message, ['data' => $result]);
                break;

            case '/posts/publish':
                $postId = (int)($_POST['post_id'] ?? 0);
                $posted = $postingService->publishPost($postId);
                json_response(true, 'Đã đăng bài #' . $postId . ' lên Facebook.', ['data' => $posted]);
                break;

            case '/posts/publish-due':
                $limit = max(1, min(20, (int)($_POST['limit'] ?? 5)));
                $result = $postingService->publishDueScheduledPosts($limit);
                json_response(true, 'Đã đăng tự động ' . (int)$result['count'] . ' bài đến hạn.', ['data' => $result]);
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

            case '/settings/check-facebook-token':
                $facebookCheck = (new FacebookPagePublisher())->checkToken();
                json_response((bool)$facebookCheck['ok'], (string)$facebookCheck['message'], ['data' => $facebookCheck]);
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

                $scraperService->ensureShopeeLiveBrowser();
                $sessionCheck = $scraperService->checkShopeeSession();
                if (!empty($sessionCheck['captcha_required'])) {
                    $job = queueScrapeIntervention(
                        $pendingScrapeJobService,
                        $scraperService,
                        'config',
                        ['config_id' => $configId],
                        'Chrome Shopee chuyên dụng đang cần đăng nhập/vượt captcha. Vào server xử lý rồi nhắn bot "xong".'
                    );
                    json_response(true, 'Chrome cần can thiệp. Đã tạo job chờ #' . $job['id'] . ' và gửi Telegram.');
                }

                $job = queueScrapeAuto($pendingScrapeJobService, 'config', ['config_id' => $configId]);
                json_response(true, 'Chrome ổn. Đã đưa job #' . $job['id'] . ' vào hàng đợi chạy nền, web không cần chờ.');
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

                $payload = [
                    'platform' => $platform,
                    'min_sold_count' => $minSold,
                    'max_pages' => $maxPages,
                    'category_ids' => $categoryIds,
                ];

                $scraperService->ensureShopeeLiveBrowser();
                $sessionCheck = $scraperService->checkShopeeSession();
                if (!empty($sessionCheck['captcha_required'])) {
                    $job = queueScrapeIntervention(
                        $pendingScrapeJobService,
                        $scraperService,
                        'trending',
                        $payload,
                        'Chrome Shopee chuyên dụng đang cần đăng nhập/vượt captcha. Vào server xử lý rồi nhắn bot "xong".'
                    );
                    json_response(true, 'Chrome cần can thiệp. Đã tạo job chờ #' . $job['id'] . ' và gửi Telegram.');
                }

                $job = queueScrapeAuto($pendingScrapeJobService, 'trending', $payload);
                json_response(true, 'Chrome ổn. Đã đưa job #' . $job['id'] . ' vào hàng đợi chạy nền, web không cần chờ.');
                break;

            case '/scraper/radar':
                $keyword = trim((string)($_POST['keyword'] ?? ''));
                $fresh = !empty($_POST['fresh_crawl']);

                if ($fresh && $keyword !== '') {
                    $configId = $scraperService->saveConfig([
                        'keyword' => $keyword,
                        'platform' => 'shopee',
                        'min_sold_count' => 0,
                        'max_pages' => 1,
                        'sort_by' => 'sold',
                        'is_active' => 0,
                    ]);
                    try {
                        $scraperService->runScrapeJob($configId);
                    } finally {
                        $scraperService->deleteConfig($configId);
                    }
                }

                $radar = $scraperService->buildProductRadar(12);
                $taskLogService->create('product_radar', 'success', [
                    'keyword' => $keyword,
                    'fresh_crawl' => $fresh,
                ], $radar);
                json_response(true, 'Đã phân tích ' . (int)$radar['count'] . ' cơ hội sản phẩm tiềm năng.', ['data' => $radar]);
                break;

            // ── AI Product Scoring ──
            case '/scores/run':
                requirePermission('products.view');
                $limit = max(5, min(100, (int)($_POST['limit'] ?? 30)));
                $result = $scoringService->scoreAllProducts($limit);
                $taskLogService->create('score_products_manual', empty($result['errors']) ? 'success' : 'failed', ['limit' => $limit], $result);
                json_response(true, 'Đã chấm điểm ' . $result['scored'] . ' sản phẩm.', ['data' => $result]);
                break;

            case '/scores/score-one':
                requirePermission('products.view');
                $productId = (int)($_POST['product_id'] ?? 0);
                $scoreData = $scoringService->scoreProduct($productId);
                json_response(true, 'Đã chấm điểm sản phẩm #' . $productId . '.', ['data' => $scoreData]);
                break;

            // ── Scrape any URL (AI extracts products from any source) ──
            case '/scraper/scrape-url':
                requirePermission('scraper.view');
                $url = trim((string)($_POST['url'] ?? ''));
                $platform = trim((string)($_POST['platform'] ?? 'generic'));
                $limit = min(200, max(10, (int)($_POST['limit'] ?? 100)));
                if ($url === '') {
                    json_response(false, 'URL không được để trống.');
                }
                $svc = new UniversalScraperService();
                $result = $svc->scrapeUrl($url, $platform ?: 'generic', $limit);
                $taskLogService->create('universal_scrape', 'success', ['url' => $url, 'platform' => $platform], $result);
                json_response(true, 'Đã thu thập ' . $result['saved'] . ' sản phẩm.', $result);
                break;

            // ── Parse pasted raw data (CSV/text/HTML) with AI ──
            case '/scraper/parse-raw':
                requirePermission('scraper.view');
                $raw = trim((string)($_POST['raw'] ?? ''));
                $platform = trim((string)($_POST['platform'] ?? 'manual'));
                if ($raw === '') {
                    json_response(false, 'Dữ liệu thô không được để trống.');
                }
                $svc = new UniversalScraperService();
                $result = $svc->parseRawData($raw, $platform ?: 'manual');
                $taskLogService->create('raw_parse', 'success', ['platform' => $platform], $result);
                json_response(true, 'Đã trích xuất ' . $result['saved'] . ' sản phẩm.', $result);
                break;


            case '/admin/modules/toggle':
                $moduleId = (int)($_POST['module_id'] ?? 0);
                $enabled = (bool)($_POST['enabled'] ?? false);
                $moduleService = new ModuleService();
                $moduleService->toggle($moduleId, $enabled);
                json_response(true, $enabled ? 'Đã bật module.' : 'Đã tắt module.');
                break;

            // ── Admin: Permission matrix save ──
            case '/admin/permissions/save':
                $permService = new PermissionService();
                $result = $permService->saveMatrix($_POST);
                json_response(true, 'Đã lưu phân quyền cho ' . $result['updated'] . ' roles.');
                break;

            case '/admin/roles/sync':
                $configFile = APP_PATH . '/config/permissions_list.php';
                if (!file_exists($configFile)) {
                    throw new RuntimeException('Không tìm thấy file cấu hình permissions_list.php.');
                }

                $permissionsList = require $configFile;
                if (!is_array($permissionsList)) {
                    throw new RuntimeException('File permissions_list.php phải trả về một mảng quyền hợp lệ.');
                }

                $permService = new PermissionService();
                $result = $permService->syncPermissionsFromConfig($permissionsList);
                set_flash('success', $result['message']);

                $redirectTo = normalize_internal_path(
                    (string)($_POST['redirect_to'] ?? '/admin/roles'),
                    rtrim((string)($basePath ?? ''), '/')
                );
                redirect_to($redirectTo);
                break;

            // ── Admin: User CRUD ──
            case '/admin/users/store':
                $userService = new UserService();
                $userService->create([
                    'username'  => trim((string)($_POST['username'] ?? '')),
                    'email'     => trim((string)($_POST['email'] ?? '')),
                    'password'  => (string)($_POST['password'] ?? ''),
                    'full_name' => trim((string)($_POST['full_name'] ?? '')),
                    'role_id'   => (int)($_POST['role_id'] ?? 2),
                    'site_id'   => (int)($_POST['site_id'] ?? currentSiteId()),
                ]);
                json_response(true, 'Đã tạo người dùng mới.');
                break;

            case '/admin/users/update':
                $userService = new UserService();
                $userId = (int)($_POST['user_id'] ?? 0);
                if ($userId <= 0) throw new InvalidArgumentException('User ID không hợp lệ.');
                $userService->update($userId, [
                    'username'  => trim((string)($_POST['username'] ?? '')),
                    'email'     => trim((string)($_POST['email'] ?? '')),
                    'password'  => (string)($_POST['password'] ?? ''),
                    'full_name' => trim((string)($_POST['full_name'] ?? '')),
                    'role_id'   => (int)($_POST['role_id'] ?? 2),
                    'site_id'   => (int)($_POST['site_id'] ?? currentSiteId()),
                ]);
                json_response(true, 'Đã cập nhật người dùng.');
                break;

            case '/admin/users/toggle':
                $userService = new UserService();
                $userService->toggleActive((int)($_POST['user_id'] ?? 0), (bool)($_POST['active'] ?? false));
                json_response(true, 'Đã cập nhật trạng thái.');
                break;

            case '/admin/users/unlock':
                $userService = new UserService();
                $userService->unlockUser((int)($_POST['user_id'] ?? 0));
                json_response(true, 'Đã gỡ khóa tạm thời.');
                break;

            // ── Admin: Site CRUD ──
            case '/admin/sites/store':
                $siteService->create($_POST);
                json_response(true, 'Đã tạo site mới.');
                break;

            case '/admin/sites/update':
                $siteId = (int)($_POST['site_id'] ?? 0);
                if ($siteId <= 0) throw new InvalidArgumentException('Site ID không hợp lệ.');
                $siteService->update($siteId, $_POST);
                json_response(true, 'Đã cập nhật site.');
                break;

            case '/admin/sites/toggle':
                $siteService->toggleActive((int)($_POST['site_id'] ?? 0), (bool)($_POST['active'] ?? false));
                json_response(true, 'Đã cập nhật trạng thái site.');
                break;

            case '/admin/sites/change-current':
                $site = $siteService->changeCurrentSite((int)($_POST['site_id'] ?? 0));
                json_response(true, 'Đã chuyển sang site ' . $site['code'] . '.');
                break;

            // ── My Products: manual add ──
            case '/my-products/add':
                $result = $userProductService->addManual($_POST);
                json_response(true, 'Đã thêm sản phẩm "' . ($result['product_name'] ?? '') . '".', $result);
                break;

            // ── My Products: pick from AI Radar ──
            case '/my-products/pick':
                $sourceId = (int)($_POST['source_product_id'] ?? 0);
                if ($sourceId <= 0) throw new InvalidArgumentException('Thiếu ID sản phẩm nguồn.');
                $result = $userProductService->pickFromRadar($sourceId);
                json_response(true, 'Đã chọn sản phẩm "' . ($result['product_name'] ?? '') . '".', $result);
                break;

            // ── My Products: trigger content generation ──
            case '/my-products/generate-content':
                $productId = (int)($_POST['product_id'] ?? 0);
                $item = $userProductService->findById($productId);
                if (!$item) throw new InvalidArgumentException('Không tìm thấy sản phẩm #' . $productId);
                if (empty($item['affiliate_url'])) throw new RuntimeException('Sản phẩm chưa có link affiliate.');
                // Generate content via ContentService
                $contentResult = $contentService->generateForProduct([
                    'id' => $item['id'],
                    'product_name' => $item['product_name'],
                    'product_url' => $item['product_url'],
                    'affiliate_url' => $item['affiliate_url'],
                    'price' => $item['price'],
                    'source_platform' => $item['source_platform'],
                ]);
                $userProductService->update($productId, ['status' => 'content_generated', 'content_status' => 'generated']);
                json_response(true, 'Đã sinh nội dung cho sản phẩm.', $contentResult);
                break;

            // ── Channels: Create ──
            case '/channels/create':
                $channelId = $channelService->create($_POST);
                json_response(true, 'Đã thêm kênh mới thành công.', ['id' => $channelId]);
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
        requirePermission('products.view');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;
        $siteId = (int)currentSiteId();
        $pdo = db_pdo();
        $total = (int)$pdo->query("SELECT COUNT(*) FROM affiliate_products WHERE site_id = {$siteId}")->fetchColumn();
        $dbProducts = $pdo->query(
            "SELECT * FROM affiliate_products WHERE site_id = {$siteId} ORDER BY sold_count DESC, id DESC LIMIT {$perPage} OFFSET {$offset}"
        )->fetchAll(PDO::FETCH_ASSOC);
        $totalPages = max(1, (int)ceil($total / $perPage));
        $productSummary = [
            'total' => $total,
            'with_link' => (int)$pdo->query("SELECT COUNT(*) FROM affiliate_products WHERE site_id = {$siteId} AND affiliate_url != ''")->fetchColumn(),
            'hot' => (int)$pdo->query("SELECT COUNT(*) FROM affiliate_products WHERE site_id = {$siteId} AND sold_count >= 50")->fetchColumn(),
        ];
        render('products/index', [
            'pageTitle'      => 'Sản phẩm',
            'currentPage'    => 'products',
            'productSummary' => $productSummary,
            'products'       => $dbProducts,
            'pagination'     => ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'totalPages' => $totalPages],
        ]);
        break;

    case '/contents':
        render('contents/index', [
            'pageTitle'      => 'Nội dung',
            'currentPage'    => 'contents',
            'contentSummary' => $contentService->summary(),
            'contents'       => $contentService->allContents(),
            'products'       => $productSyncService->allProducts(),
            'automationSettings' => $automationSettings,
        ]);
        break;

    case '/posts':
        $postContents = [];
        foreach ($contentService->allContents() as $content) {
            $postContents[(int)$content['id']] = $content;
        }
        render('posts/index', [
            'pageTitle'    => 'Đăng bài',
            'currentPage'  => 'posts',
            'postSummary'  => $postingService->summary(),
            'posts'        => $postingService->allPosts(),
            'postContents' => $postContents,
            'contents'     => $contentService->allContents(),
            'fanpageApiReady' => $postingService->fanpageApiAvailable(),
            'integrationStatus' => $automationSettingsService->integrationStatus(),
            'automationSettings' => $automationSettings,
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
        requirePermission('scraper.view');
        // Keep the dedicated Shopee Chrome window available for intervention/scraping.
        $scraperService->ensureShopeeLiveBrowser();
        $serverInfoSvc = new ServerInfoService();
        render('scraper/index', [
            'pageTitle'       => 'Product Radar',
            'currentPage'     => 'scraper',
            'scraperSummary'  => $scraperService->summary(),
            'shopeeSession'   => $scraperService->checkShopeeSession(),
            'productSummary'  => $productSyncService->dashboardSummary(),
            'configs'         => $scraperService->allConfigs(),
            'categories'      => $scraperService->getCategories(),
            'topProducts'     => $productSyncService->topSellingProducts(10, 50),
            'productRadar'    => $scraperService->buildProductRadar(12),
            'serverInfo'      => $serverInfoSvc->get(),
        ]);
        break;

    case '/analytics':
        requirePermission('products.view');
        render('analytics/index', [
            'pageTitle'          => 'Phân tích & AI Radar',
            'currentPage'        => 'analytics',
            'scoreSummary'       => $scoringService->summary(),
            'scoreDistribution'  => $scoringService->getScoreDistribution(),
            'topRecommendations' => $scoringService->getTopRecommendations(20),
            'productSummary'     => $productSyncService->dashboardSummary(),
        ]);
        break;

    case '/channels':
        requirePermission('settings.view');
        render('channels/index', [
            'currentPage' => 'channels',
            'channels' => $channelService->list(),
            'summary' => $channelService->summary(),
        ]);
        break;

    case '/my-products':
        requirePermission('products.view');
        $filters = [
            'search'   => trim((string)($_GET['search'] ?? '')),
            'status'   => trim((string)($_GET['status'] ?? '')),
            'platform' => trim((string)($_GET['platform'] ?? '')),
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $products = $userProductService->list($filters, $perPage, ($page - 1) * $perPage);
        render('my-products/index', [
            'pageTitle'   => 'SP Đã Chọn — My Products',
            'currentPage' => 'my_products',
            'products'    => $products,
            'summary'     => $userProductService->summary(),
            'filters'     => $filters,
        ]);
        break;

    case '/server-info':
        requirePermission('server-info.view');
        $serverInfo = new ServerInfoService();
        render('server-info/index', [
            'pageTitle'   => 'Thông tin Server',
            'currentPage' => 'server_info',
            'serverInfo'  => $serverInfo->get(),
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

    // ══════════════════════════════════════════
    //  ADMIN — Module List (system_modules/index)
    // ══════════════════════════════════════════
    case '/admin/modules':
        requirePermission('admin.modules');
        $moduleService = new ModuleService();
        render('admin/system_modules/index', [
            'pageTitle'    => 'Quản trị — Modules',
            'currentPage'  => 'admin_modules',
            'modules'      => $moduleService->getAll(),
        ]);
        break;

    // ══════════════════════════════════════════
    //  ADMIN — Permission Matrix
    // ══════════════════════════════════════════
    case '/admin/permissions':
        requirePermission('admin.permissions');
        $permService = new PermissionService();
        $matrixData = $permService->buildMatrix();
        render('admin/roles/permissions', [
            'pageTitle'    => 'Quản trị — Phân quyền',
            'currentPage'  => 'admin_permissions',
            'roles'        => $matrixData['roles'],
            'groups'       => $matrixData['groups'],
            'matrix'       => $matrixData['matrix'],
        ]);
        break;

    // ══════════════════════════════════════════
    //  ADMIN — User Management
    // ══════════════════════════════════════════
    case '/admin/users':
        requirePermission('admin.users');
        $userService = new UserService();
        render('admin/users/index', [
            'pageTitle'    => 'Quản trị — Người dùng',
            'currentPage'  => 'admin_users',
            'users'        => $userService->getAll(),
            'roles'        => $userService->getAllRoles(),
            'sites'        => $siteService->getActive(),
        ]);
        break;

    case '/admin/users/add':
        requirePermission('admin.users');
        $userService = new UserService();
        render('admin/users/add', [
            'pageTitle'     => 'Thêm người dùng',
            'currentPage'   => 'admin_users',
            'csrf_token'    => $_SESSION['csrf_token'] ?? '',
            'sites'         => $siteService->getActive(),
            'roles'         => $userService->getAllRoles(),
            'departments'   => [],
            'full_name'     => '', 'email' => '', 'username' => '',
            'default_site_id' => currentSiteId(),
            'department_id' => '', 'role_id' => 2,
            'access_sites'  => [],
            'full_name_err' => '', 'email_err' => '', 'username_err' => '',
            'password_err'  => '', 'confirm_password_err' => '',
        ]);
        break;

    case '/admin/users/profile':
        // Any logged-in user can view their own profile
        $userService = new UserService();
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $user = $userService->findById($userId);
        if (!$user) { http_response_code(404); echo '404 — User not found.'; exit; }
        render('admin/users/profile', [
            'pageTitle'            => 'Hồ sơ cá nhân',
            'currentPage'         => 'admin_users',
            'csrf_token'          => $_SESSION['csrf_token'] ?? '',
            'user'                => (object)$user,
            'all_users'           => $userService->getAll(),
            'other_session_count' => 0,
            'old_password'        => '', 'new_password' => '', 'confirm_password' => '',
            'old_password_err'    => '', 'new_password_err' => '', 'confirm_password_err' => '',
        ]);
        break;

    // ══════════════════════════════════════════
    //  ADMIN — Site Management
    // ══════════════════════════════════════════
    case '/admin/sites':
        requirePermission('admin.sites');
        render('admin/sites/index', [
            'pageTitle'    => 'Quản trị — Sites',
            'currentPage'  => 'admin_sites',
            'sites'        => $siteService->getAll(),
            'activeSites'  => $siteService->getActive(),
            'currentSite'  => currentSite(),
        ]);
        break;

    case '/admin/sites/add':
        requirePermission('admin.sites');
        render('admin/sites/add', [
            'pageTitle'    => 'Thêm Site mới',
            'currentPage'  => 'admin_sites',
            'csrf_token'   => $_SESSION['csrf_token'] ?? '',
            'all_sites'    => $siteService->getAll(),
            'code' => '', 'name' => '', 'address' => '',
            'parent_site_id' => '', 'is_master' => 0, 'is_active' => 1,
            'code_err' => '', 'name_err' => '',
        ]);
        break;

    // ══════════════════════════════════════════
    //  ADMIN — Role Management
    // ══════════════════════════════════════════
    case '/admin/roles':
        requirePermission('admin.permissions');
        $permService = new PermissionService();
        render('admin/roles/index', [
            'pageTitle'    => 'Quản trị — Vai trò',
            'currentPage'  => 'admin_permissions',
            'roles'        => $permService->getAllRoles(),
            'csrf_token'   => $_SESSION['csrf_token'] ?? '',
        ]);
        break;

    default:
        // ══════════════════════════════════════════
        //  DYNAMIC ADMIN ROUTES (parameterized IDs)
        //  Pattern: /admin/{section}/{action}/{id}
        // ══════════════════════════════════════════
        $handled = false;

        // ── /admin/users/edit/{id} ──
        if (preg_match('#^/admin/users/edit/(\d+)$#', $path, $m)) {
            requirePermission('admin.users');
            $userService = new UserService();
            $editUser = $userService->findById((int)$m[1]);
            if (!$editUser) { http_response_code(404); echo '404 — User not found.'; exit; }
            render('admin/users/edit', [
                'pageTitle'     => 'Sửa người dùng',
                'currentPage'   => 'admin_users',
                'csrf_token'    => $_SESSION['csrf_token'] ?? '',
                'id'            => $editUser['id'],
                'username'      => $editUser['username'],
                'email'         => $editUser['email'],
                'full_name'     => $editUser['full_name'],
                'role_id'       => $editUser['role_id'],
                'default_site_id' => $editUser['site_id'],
                'is_active'     => $editUser['is_active'] ?? 1,
                'department_id' => $editUser['department_id'] ?? '',
                'employee_id'   => $editUser['employee_id'] ?? '',
                'employee_name' => $editUser['employee_name'] ?? '',
                'sites'         => $siteService->getActive(),
                'roles'         => $userService->getAllRoles(),
                'departments'   => [],
                'access_sites'  => [],
                'all_warehouses'=> [],
                'user_warehouses'=> [],
                'full_name_err' => '', 'email_err' => '',
                'password_err'  => '', 'confirm_password_err' => '',
            ]);
            $handled = true;
        }

        // ── /admin/sites/edit/{id} ──
        if (!$handled && preg_match('#^/admin/sites/edit/(\d+)$#', $path, $m)) {
            requirePermission('admin.sites');
            $editSite = $siteService->findById((int)$m[1]);
            if (!$editSite) { http_response_code(404); echo '404 — Site not found.'; exit; }
            render('admin/sites/edit', [
                'pageTitle'    => 'Sửa Site',
                'currentPage'  => 'admin_sites',
                'csrf_token'   => $_SESSION['csrf_token'] ?? '',
                'id'           => $editSite['id'],
                'code'         => $editSite['code'],
                'name'         => $editSite['name'],
                'address'      => $editSite['address'] ?? '',
                'parent_site_id' => $editSite['parent_site_id'] ?? '',
                'is_master'    => $editSite['is_master'] ?? 0,
                'is_active'    => $editSite['is_active'] ?? 1,
                'all_sites'    => $siteService->getAll(),
                'code_err' => '', 'name_err' => '',
            ]);
            $handled = true;
        }

        // ── /admin/sites/change/{id} (GET — switch current site) ──
        if (!$handled && preg_match('#^/admin/sites/change/(\d+)$#', $path, $m)) {
            requirePermission('admin.sites');
            try {
                $siteService->changeCurrentSite((int)$m[1]);
                set_flash('success', 'Đã chuyển đổi Site thành công.');
            } catch (Throwable $ex) {
                set_flash('error', $ex->getMessage());
            }
            redirect_to('/admin/sites');
        }

        // ── /admin/roles/permissions/{id} (POST — save permissions) ──
        if (!$handled && $method === 'POST' && preg_match('#^/admin/roles/permissions/(\d+)$#', $path, $m)) {
            requirePermission('admin.permissions');
            $roleId = (int)$m[1];
            $permIds = $_POST['perm'] ?? [];
            $permService = new PermissionService();
            $permService->saveRolePermissions($roleId, $permIds);
            set_flash('success', 'Đã lưu phân quyền thành công.');
            redirect_to('/admin/roles/permissions/' . $roleId);
            $handled = true;
        }

        // ── /admin/roles/permissions/{id} (GET — permission matrix for specific role) ──
        if (!$handled && preg_match('#^/admin/roles/permissions/(\d+)$#', $path, $m)) {
            requirePermission('admin.permissions');
            $permService = new PermissionService();
            $matrixData = $permService->buildMatrix();
            render('admin/roles/permissions', [
                'pageTitle'    => 'Phân quyền — Role #' . (int)$m[1],
                'currentPage'  => 'admin_permissions',
                'roles'        => $matrixData['roles'],
                'groups'       => $matrixData['groups'],
                'matrix'       => $matrixData['matrix'],
                'focusRoleId'  => (int)$m[1],
            ]);
            $handled = true;
        }

        if (!$handled) {
            http_response_code(404);
            echo '404 — Không tìm thấy trang: ' . e($path) . '<br>';
            echo '<!-- Debug Info: requestPath=' . e($requestPath) . ', basePath=' . e($basePath) . ', scriptName=' . e($_SERVER['SCRIPT_NAME'] ?? '') . ' -->';
        }
        break;
}
